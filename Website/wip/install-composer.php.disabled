<?php
echo "Installing dependencies with fixed environment...<br>";

// Set the required environment variables
putenv('HOME=' . __DIR__);
putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');

// Change to current directory
chdir(__DIR__);

// Run composer install
$command = 'composer install --no-dev --optimize-autoloader 2>&1';
echo "Running: <code>{$command}</code><br><br>";

exec($command, $output, $returnCode);

echo "<pre>";
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";

echo "Exit code: " . $returnCode . "<br>";

if ($returnCode === 0) {
    echo "✅ Composer install successful!<br>";
    echo "✅ Vendor folder should be created.<br>";
} else {
    echo "❌ Composer install failed.<br>";
}