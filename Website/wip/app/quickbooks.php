<?php
/**
 * app/quickbooks.php
 *
 * Lightweight QuickBooks Online client for creating invoices and syncing payments.
 */

declare(strict_types=1);

class QuickBooksClient
{
    private string $clientId;
    private string $clientSecret;
    private string $refreshToken;
    private string $realmId;
    private string $environment;
    private string $baseUrl;
    private string $tokenCache;
    private ?array $tokenData = null;

    public function __construct()
    {
        $this->clientId = trim((string) (QUICKBOOKS_CLIENT_ID ?? ''));
        $this->clientSecret = trim((string) (QUICKBOOKS_CLIENT_SECRET ?? ''));
        $this->refreshToken = trim((string) (QUICKBOOKS_REFRESH_TOKEN ?? ''));
        $this->realmId = trim((string) (QUICKBOOKS_REALM_ID ?? ''));
        $this->environment = strtolower(trim((string) (QUICKBOOKS_ENVIRONMENT ?? 'sandbox')));
        $this->baseUrl = trim((string) (QUICKBOOKS_BASE_URL ?? ''));
        $this->tokenCache = __DIR__ . '/../temp/quickbooks_token.json';
        $this->hydrateTokenFromCache();
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->refreshToken !== ''
            && $this->realmId !== '';
    }

    public function createInvoice(array $booking, float $amount, ?string $dueDate = null): array
    {
        $this->requireConfiguration();

        if ($amount <= 0) {
            throw new InvalidArgumentException('Invoice amount must be greater than zero.');
        }

        $customer = $this->ensureCustomer($booking);
        $itemId = trim((string) (QUICKBOOKS_SERVICE_ITEM_ID ?? ''));
        if ($itemId === '') {
            throw new RuntimeException('QuickBooks service item ID is required (QUICKBOOKS_SERVICE_ITEM_ID).');
        }

        $description = sprintf(
            'Cleaning service for booking %s (%s %s)',
            $booking['booking_id'] ?? '',
            $booking['service_type'] ?? '',
            $booking['frequency'] ?? ''
        );

        $payload = [
            'CustomerRef' => [
                'value' => $customer['Id'],
                'name' => $customer['DisplayName'] ?? ($booking['first_name'] ?? 'Client'),
            ],
            'Line' => [[
                'Amount' => round($amount, 2),
                'Description' => $description,
                'DetailType' => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'ItemRef' => [
                        'value' => $itemId,
                        'name' => QUICKBOOKS_SERVICE_ITEM_NAME ?: 'Cleaning Service',
                    ],
                    'Qty' => 1,
                    'UnitPrice' => round($amount, 2),
                ],
            ]],
            'TxnDate' => date('Y-m-d'),
            'DueDate' => $dueDate ?: date('Y-m-d', strtotime('+7 days')),
            'PrivateNote' => sprintf('Auto-generated invoice for booking %s', $booking['booking_id'] ?? ''),
            'DocNumber' => $booking['booking_id'] ?? null,
            'BillEmail' => [
                'Address' => $booking['email'] ?? null,
            ],
            'BillAddr' => [
                'Line1' => $booking['address'] ?? '',
                'City' => $booking['city'] ?? '',
                'CountrySubDivisionCode' => $booking['state'] ?? '',
                'PostalCode' => $booking['zip'] ?? '',
            ],
        ];

        $response = $this->request('POST', 'invoice', $payload, ['minorversion' => 65]);

        if (!isset($response['Invoice'])) {
            throw new RuntimeException('QuickBooks invoice creation failed: ' . json_encode($response));
        }

        return $response['Invoice'];
    }

    public function getInvoice(string $invoiceId): array
    {
        $this->requireConfiguration();
        $response = $this->request('GET', 'invoice/' . urlencode($invoiceId), null, ['minorversion' => 65]);

        if (!isset($response['Invoice'])) {
            throw new RuntimeException('Invoice not found in QuickBooks.');
        }

        return $response['Invoice'];
    }

    public function getPayment(string $paymentId): array
    {
        $this->requireConfiguration();
        $response = $this->request('GET', 'payment/' . urlencode($paymentId), null, ['minorversion' => 65]);

        if (!isset($response['Payment'])) {
            throw new RuntimeException('Payment not found in QuickBooks.');
        }

        return $response['Payment'];
    }

    public function ensureCustomer(array $booking): array
    {
        $email = $booking['email'] ?? null;
        if ($email) {
            $existing = $this->findCustomerByEmail($email);
            if ($existing) {
                return $existing;
            }
        }

        $payload = [
            'DisplayName' => trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? '')),
            'PrimaryEmailAddr' => $email ? ['Address' => $email] : null,
            'PrimaryPhone' => isset($booking['phone']) ? ['FreeFormNumber' => $booking['phone']] : null,
            'BillAddr' => [
                'Line1' => $booking['address'] ?? '',
                'City' => $booking['city'] ?? '',
                'CountrySubDivisionCode' => $booking['state'] ?? '',
                'PostalCode' => $booking['zip'] ?? '',
            ],
        ];

        $response = $this->request('POST', 'customer', $payload, ['minorversion' => 65]);

        if (!isset($response['Customer'])) {
            throw new RuntimeException('Unable to create QuickBooks customer.');
        }

        return $response['Customer'];
    }

    public function recordPayment(string $invoiceId, float $amount, string $paymentDate): array
    {
        $invoice = $this->getInvoice($invoiceId);
        $customerRef = $invoice['CustomerRef']['value'] ?? null;

        if (!$customerRef) {
            throw new RuntimeException('QuickBooks invoice is missing a customer reference.');
        }

        $payload = [
            'CustomerRef' => ['value' => $customerRef],
            'TotalAmt' => round($amount, 2),
            'TxnDate' => $paymentDate,
            'Line' => [[
                'Amount' => round($amount, 2),
                'LinkedTxn' => [[
                    'TxnId' => $invoiceId,
                    'TxnType' => 'Invoice',
                ]],
            ]],
        ];

        $response = $this->request('POST', 'payment', $payload, ['minorversion' => 65]);

        if (!isset($response['Payment'])) {
            throw new RuntimeException('Unable to record QuickBooks payment.');
        }

        return $response['Payment'];
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $token = trim((string) (QUICKBOOKS_WEBHOOK_VERIFIER_TOKEN ?? ''));
        if ($token === '' || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $token, true));
        return hash_equals($expected, $signature);
    }

    private function findCustomerByEmail(string $email): ?array
    {
        $this->requireConfiguration();

        // Validate email format strictly before using in query
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format for QuickBooks customer search');
        }

        // Properly escape for QuickBooks Query Language (single quotes must be doubled)
        $escapedEmail = str_replace("'", "''", $email);

        $query = sprintf(
            "SELECT * FROM Customer WHERE PrimaryEmailAddr.Address = '%s' MAXRESULTS 1",
            $escapedEmail
        );

        $response = $this->request('GET', 'query', null, [
            'minorversion' => 65,
            'query' => $query,
        ]);

        if (!empty($response['QueryResponse']['Customer'][0])) {
            return $response['QueryResponse']['Customer'][0];
        }

        return null;
    }

    private function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $this->requireConfiguration();

        $accessToken = $this->getAccessToken();
        $url = $this->getApiBase() . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('QuickBooks request failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($status >= 400) {
            $message = $decoded['Fault']['Error'][0]['Message'] ?? $response;
            throw new RuntimeException('QuickBooks API error: ' . $message);
        }

        return $decoded ?? [];
    }

    private function getAccessToken(): string
    {
        if ($this->tokenData && isset($this->tokenData['access_token'], $this->tokenData['expires_at'])) {
            if ($this->tokenData['expires_at'] > time() + 60) {
                return $this->tokenData['access_token'];
            }
        }

        return $this->refreshAccessToken();
    }

    private function refreshAccessToken(): string
    {
        $this->requireConfiguration();

        $tokenUrl = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);

        $payload = http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('QuickBooks token refresh failed: ' . $error);
        }

        $data = json_decode($response, true);
        if ($status >= 400 || !$data || empty($data['access_token'])) {
            throw new RuntimeException('QuickBooks token refresh error: ' . $response);
        }

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $this->tokenData = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $this->refreshToken,
            'expires_at' => time() + $expiresIn - 60,
        ];

        $this->refreshToken = $this->tokenData['refresh_token'];
        $this->persistTokenCache();

        return $this->tokenData['access_token'];
    }

    private function hydrateTokenFromCache(): void
    {
        if (!file_exists($this->tokenCache)) {
            return;
        }

        $encrypted = file_get_contents($this->tokenCache);
        if (!$encrypted) {
            return;
        }

        // Try to decrypt - if it fails, might be old unencrypted format
        $decrypted = $this->decryptTokenData($encrypted);
        if (!$decrypted) {
            // Fallback: try reading as plain JSON (for backward compatibility)
            $decrypted = $encrypted;
        }

        $decoded = json_decode($decrypted, true);
        if (!$decoded) {
            return;
        }

        $this->tokenData = $decoded;
        if (!empty($decoded['refresh_token'])) {
            $this->refreshToken = $decoded['refresh_token'];
        }
    }

    private function persistTokenCache(): void
    {
        if (!$this->tokenData) {
            return;
        }

        $json = json_encode($this->tokenData, JSON_PRETTY_PRINT);
        $encrypted = $this->encryptTokenData($json);

        // Set restrictive file permissions (owner read/write only)
        file_put_contents($this->tokenCache, $encrypted);
        @chmod($this->tokenCache, 0600);
    }

    /**
     * Encrypt sensitive token data using AES-256-GCM
     */
    private function encryptTokenData(string $data): string
    {
        $key = $this->getEncryptionKey();
        $nonce = random_bytes(12); // GCM standard nonce size
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Token encryption failed');
        }

        // Combine nonce + tag + ciphertext
        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt token data
     */
    private function decryptTokenData(string $encrypted): ?string
    {
        $key = $this->getEncryptionKey();
        $decoded = base64_decode($encrypted, true);

        if ($decoded === false || strlen($decoded) < 28) { // 12 (nonce) + 16 (tag)
            return null;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Get or generate encryption key for token storage
     */
    private function getEncryptionKey(): string
    {
        // Use a key from configuration or generate one
        // In production, store this in environment variable
        $key = defined('QUICKBOOKS_ENCRYPTION_KEY') ? QUICKBOOKS_ENCRYPTION_KEY : null;

        if (!$key) {
            // Fallback: derive key from client secret (not ideal but better than nothing)
            $key = hash('sha256', $this->clientSecret . 'token_encryption', true);
        } elseif (strlen($key) !== 32) {
            // Ensure key is 32 bytes for AES-256
            $key = hash('sha256', $key, true);
        }

        return $key;
    }

    private function requireConfiguration(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('QuickBooks credentials are not fully configured.');
        }
    }

    private function getApiBase(): string
    {
        if ($this->baseUrl !== '') {
            return rtrim($this->baseUrl, '/') . '/' . $this->realmId;
        }

        $host = $this->environment === 'production'
            ? 'https://quickbooks.api.intuit.com/v3/company'
            : 'https://sandbox-quickbooks.api.intuit.com/v3/company';

        return $host . '/' . $this->realmId;
    }
}

function quickbooks_client(): QuickBooksClient
{
    static $client;
    if (!$client) {
        $client = new QuickBooksClient();
    }
    return $client;
}
