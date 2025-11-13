<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin(['admin', 'manager', 'staff']);

$user = auth_user();
$basePath = dirname($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Interface Debug - Wasatch Cleaners</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/js/litepicker.js" defer></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <style>
        .debug-panel {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #1a202c;
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            z-index: 10000;
            max-width: 600px;
            max-height: 700px;
            overflow-y: auto;
            border: 2px solid #e53e3e;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .test-pass { color: #38a169; }
        .test-fail { color: #e53e3e; }
        .test-warning { color: #d69e2e; }
        .test-info { color: #3182ce; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Admin Interface Debugger</h1>
        <p class="text-slate-600 mb-6">Comprehensive testing for dashboard and admin functionality</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Server-Side Tests -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Server-Side Tests</h2>
                <div id="server-tests" class="space-y-3">
                    <?php
                    // Run server-side tests
                    runServerSideTests();
                    ?>
                </div>
            </div>

            <!-- Client-Side Tests -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Client-Side Tests</h2>
                <div id="client-tests" class="space-y-3">
                    <div class="text-slate-500">Running client tests...</div>
                </div>
            </div>

            <!-- Configuration Info -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Configuration</h2>
                <div class="space-y-2 text-sm">
                    <div><strong>User:</strong> <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['role']) ?>)</div>
                    <div><strong>Base Path:</strong> <?= htmlspecialchars($basePath) ?></div>
                    <div><strong>PHP Version:</strong> <?= phpversion() ?></div>
                    <div><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Quick Actions</h2>
                <div class="space-y-2">
                    <button onclick="runAllTests()" class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Run All Tests</button>
                    <button onclick="testAPIs()" class="w-full bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600">Test APIs</button>
                    <button onclick="checkDependencies()" class="w-full bg-purple-500 text-white py-2 px-4 rounded hover:bg-purple-600">Check Dependencies</button>
                    <button onclick="clearCache()" class="w-full bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600">Clear Cache & Reload</button>
                </div>
            </div>
        </div>

        <!-- Debug Results Panel -->
        <div id="debug-results" class="mt-6 bg-white rounded-xl shadow p-6">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">Debug Results</h2>
            <div id="results-content" class="space-y-3"></div>
        </div>
    </div>

    <script>
        // Client-side debug functionality
        class ClientDebugger {
            constructor() {
                this.results = [];
                this.issues = [];
            }

            async runAllTests() {
                this.clearResults();
                this.log('Starting comprehensive client-side tests...');
                
                await this.testDependencies();
                await this.testConfiguration();
                await this.testDOMElements();
                await this.testJavaScriptFunctions();
                await this.testAPIEndpoints();
                await this.testEventListeners();
                
                this.displayResults();
            }

            async testDependencies() {
                this.log('Testing dependencies...');
                
                // Test AdminUtils
                if (typeof window.AdminUtils === 'undefined') {
                    this.addResult('fail', 'AdminUtils', 'AdminUtils not loaded - check api-client.js');
                    return;
                }

                const requiredUtils = ['ApiClient', 'DomUtils', 'showSuccess', 'showError', 'showNotification'];
                const missingUtils = requiredUtils.filter(util => !window.AdminUtils[util]);
                
                if (missingUtils.length > 0) {
                    this.addResult('fail', 'AdminUtils Methods', `Missing: ${missingUtils.join(', ')}`);
                } else {
                    this.addResult('pass', 'AdminUtils', 'All utilities available');
                }

                // Test external dependencies
                const deps = {
                    'Chart.js': typeof Chart !== 'undefined',
                    'Litepicker': typeof Litepicker !== 'undefined'
                };

                const missingDeps = Object.entries(deps).filter(([_, available]) => !available).map(([name]) => name);
                
                if (missingDeps.length > 0) {
                    this.addResult('fail', 'External Dependencies', `Missing: ${missingDeps.join(', ')}`);
                } else {
                    this.addResult('pass', 'External Dependencies', 'All dependencies loaded');
                }
            }

            async testConfiguration() {
                this.log('Testing configuration...');
                
                if (typeof window.ADMIN_CONFIG === 'undefined') {
                    this.addResult('fail', 'ADMIN_CONFIG', 'ADMIN_CONFIG not defined');
                    return;
                }

                const requiredConfig = ['user', 'basePath', 'endpoints'];
                const missingConfig = requiredConfig.filter(key => !window.ADMIN_CONFIG[key]);
                
                if (missingConfig.length > 0) {
                    this.addResult('fail', 'ADMIN_CONFIG Keys', `Missing: ${missingConfig.join(', ')}`);
                } else {
                    this.addResult('pass', 'ADMIN_CONFIG', 'Configuration loaded');
                }

                // Test endpoints
                const endpoints = window.ADMIN_CONFIG.endpoints || {};
                const requiredEndpoints = ['overview', 'calendar', 'reports'];
                const missingEndpoints = requiredEndpoints.filter(endpoint => !endpoints[endpoint]);
                
                if (missingEndpoints.length > 0) {
                    this.addResult('fail', 'API Endpoints', `Missing: ${missingEndpoints.join(', ')}`);
                } else {
                    this.addResult('pass', 'API Endpoints', 'All endpoints configured');
                }
            }

            async testDOMElements() {
                this.log('Testing DOM elements...');
                
                const requiredElements = [
                    'statScheduled', 'statInProgress', 'statCompleted', 'statCancelled',
                    'statusChart', 'upcomingJobsList', 'calendarContainer',
                    'calendarViewDay', 'calendarViewWeek', 'calendarViewMonth',
                    'calendarDatePicker'
                ];

                const missingElements = requiredElements.filter(id => !document.getElementById(id));
                
                if (missingElements.length > 0) {
                    this.addResult('fail', 'DOM Elements', `Missing: ${missingElements.join(', ')}`);
                } else {
                    this.addResult('pass', 'DOM Elements', 'All required elements found');
                }
            }

            async testJavaScriptFunctions() {
                this.log('Testing JavaScript functions...');
                
                // Test DashboardState
                if (typeof DashboardState === 'undefined') {
                    this.addResult('fail', 'DashboardState', 'DashboardState not defined');
                    return;
                }

                // Test critical functions
                const requiredFunctions = [
                    'loadOverviewStats', 'loadStatusChart', 'loadUpcomingJobs',
                    'loadCalendar', 'showBookingDetail', 'closeBookingDetail'
                ];

                const missingFunctions = requiredFunctions.filter(fn => typeof window[fn] !== 'function');
                
                if (missingFunctions.length > 0) {
                    this.addResult('fail', 'Dashboard Functions', `Missing: ${missingFunctions.join(', ')}`);
                } else {
                    this.addResult('pass', 'Dashboard Functions', 'All functions available');
                }
            }

            async testAPIEndpoints() {
                this.log('Testing API endpoints...');
                
                const endpoints = window.ADMIN_CONFIG?.endpoints;
                if (!endpoints) {
                    this.addResult('fail', 'API Connectivity', 'No endpoints configured');
                    return;
                }

                // Test overview endpoint
                try {
                    const response = await fetch(endpoints.overview);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'API error');
                    
                    this.addResult('pass', 'API Overview', 'Endpoint working');
                } catch (error) {
                    this.addResult('fail', 'API Overview', `Failed: ${error.message}`);
                }

                // Test calendar endpoint
                try {
                    const today = new Date().toISOString().split('T')[0];
                    const response = await fetch(`${endpoints.calendar}?view=day&date=${today}`);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    
                    const data = await response.json();
                    this.addResult('pass', 'API Calendar', 'Endpoint working');
                } catch (error) {
                    this.addResult('fail', 'API Calendar', `Failed: ${error.message}`);
                }
            }

            async testEventListeners() {
                this.log('Testing event listeners...');
                
                // Test if dashboard.js functions are callable
                try {
                    if (typeof initializeDashboard === 'function') {
                        this.addResult('pass', 'Initialization', 'initializeDashboard available');
                    } else {
                        this.addResult('fail', 'Initialization', 'initializeDashboard not found');
                    }

                    // Test state management
                    if (typeof DashboardState !== 'undefined') {
                        DashboardState.setCalendarView('week');
                        this.addResult('pass', 'State Management', 'DashboardState working');
                    }
                } catch (error) {
                    this.addResult('fail', 'Event Tests', `Error: ${error.message}`);
                }
            }

            addResult(status, test, message) {
                this.results.push({ status, test, message });
                this.updateClientTests();
            }

            updateClientTests() {
                const container = document.getElementById('client-tests');
                let html = '';
                
                this.results.forEach(result => {
                    const colorClass = result.status === 'pass' ? 'test-pass' : 'test-fail';
                    const icon = result.status === 'pass' ? '✅' : '❌';
                    
                    html += `
                        <div class="flex items-start space-x-2">
                            <span class="${colorClass}">${icon}</span>
                            <div>
                                <div class="font-semibold ${colorClass}">${result.test}</div>
                                <div class="text-xs text-slate-600">${result.message}</div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }

            displayResults() {
                const container = document.getElementById('results-content');
                let html = '<h3 class="font-semibold text-slate-900 mb-3">Client-Side Test Results</h3>';
                
                const passed = this.results.filter(r => r.status === 'pass').length;
                const failed = this.results.filter(r => r.status === 'fail').length;
                
                html += `<div class="mb-4 p-3 rounded ${failed > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">`;
                html += `<strong>Summary:</strong> ${passed} passed, ${failed} failed`;
                html += `</div>`;
                
                this.results.forEach(result => {
                    const colorClass = result.status === 'pass' ? 'text-green-700' : 'text-red-700';
                    const icon = result.status === 'pass' ? '✅' : '❌';
                    
                    html += `
                        <div class="flex items-start space-x-2 p-2 border-b border-slate-200">
                            <span class="${colorClass}">${icon}</span>
                            <div class="flex-1">
                                <div class="font-semibold ${colorClass}">${result.test}</div>
                                <div class="text-sm text-slate-600">${result.message}</div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }

            clearResults() {
                this.results = [];
                this.issues = [];
            }

            log(message) {
                console.log(`[Debug] ${message}`);
            }
        }

        // Global functions
        const debugger = new ClientDebugger();

        function runAllTests() {
            debugger.runAllTests();
        }

        async function testAPIs() {
            debugger.clearResults();
            await debugger.testAPIEndpoints();
            debugger.displayResults();
        }

        async function checkDependencies() {
            debugger.clearResults();
            await debugger.testDependencies();
            debugger.displayResults();
        }

        function clearCache() {
            if (confirm('Clear cache and reload?')) {
                localStorage.clear();
                sessionStorage.clear();
                window.location.reload();
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Load dashboard configuration for testing
            window.ADMIN_CONFIG = <?= json_encode([
                'user' => $user,
                'basePath' => $basePath,
                'endpoints' => [
                    'overview' => $basePath . '/api/overview.php',
                    'calendar' => $basePath . '/api/bookings.php',
                    'customers' => $basePath . '/api/customers.php',
                    'staff' => $basePath . '/api/staff.php',
                    'invoices' => $basePath . '/api/invoices.php',
                    'settings' => $basePath . '/api/settings.php',
                    'notifications' => $basePath . '/api/notifications.php',
                    'reports' => $basePath . '/api/reports.php',
                ],
            ], JSON_UNESCAPED_SLASHES) ?>;

            // Load dashboard scripts for testing
            loadScript('js/api-client.js', function() {
                loadScript('js/dashboard.js', function() {
                    console.log('Dashboard scripts loaded for testing');
                });
            });
        });

        function loadScript(src, callback) {
            const script = document.createElement('script');
            script.src = src;
            script.onload = callback;
            script.onerror = function() {
                console.error('Failed to load script: ' + src);
                callback();
            };
            document.head.appendChild(script);
        }
    </script>
</body>
</html>

<?php
function runServerSideTests() {
    testPHPConfiguration();
    testDatabaseConnection();
    testFilePermissions();
    testAPIAccess();
    testSessionManagement();
}

function testPHPConfiguration() {
    $tests = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'MySQLi Extension' => extension_loaded('mysqli'),
        'JSON Extension' => extension_loaded('json'),
        'Session Support' => extension_loaded('session'),
        'cURL Extension' => extension_loaded('curl'),
        'File Uploads' => ini_get('file_uploads'),
        'Memory Limit' => ini_get('memory_limit') >= '128M',
    ];

    foreach ($tests as $test => $result) {
        $status = $result ? 'pass' : 'fail';
        $icon = $result ? '✅' : '❌';
        $color = $result ? 'test-pass' : 'test-fail';
        echo "<div class='flex items-start space-x-2'>
                <span class='$color'>$icon</span>
                <div>
                    <div class='font-semibold $color'>$test</div>
                </div>
              </div>";
    }
}

function testDatabaseConnection() {
    try {
        $conn = db();
        if ($conn && $conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
        
        // Test query
        $result = $conn->query("SELECT 1 as test");
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        echo "<div class='flex items-start space-x-2'>
                <span class='test-pass'>✅</span>
                <div>
                    <div class='font-semibold test-pass'>Database Connection</div>
                    <div class='text-xs text-slate-600'>Connected successfully</div>
                </div>
              </div>";
    } catch (Exception $e) {
        echo "<div class='flex items-start space-x-2'>
                <span class='test-fail'>❌</span>
                <div>
                    <div class='font-semibold test-fail'>Database Connection</div>
                    <div class='text-xs text-slate-600'>Failed: " . htmlspecialchars($e->getMessage()) . "</div>
                </div>
              </div>";
    }
}

function testFilePermissions() {
    $paths = [
        '../app/bootstrap.php' => 'Readable',
        '../logs/' => 'Writable',
        '../uploads/' => 'Writable',
    ];
    
    foreach ($paths as $path => $requirement) {
        $fullPath = __DIR__ . '/' . $path;
        $exists = file_exists($fullPath);
        $readable = is_readable($fullPath);
        $writable = is_writable($fullPath);
        
        $status = $exists && ($requirement === 'Readable' ? $readable : $writable);
        $icon = $status ? '✅' : '❌';
        $color = $status ? 'test-pass' : 'test-fail';
        
        echo "<div class='flex items-start space-x-2'>
                <span class='$color'>$icon</span>
                <div>
                    <div class='font-semibold $color'>File: $path</div>
                    <div class='text-xs text-slate-600'>$requirement - " . ($status ? 'OK' : 'FAILED') . "</div>
                </div>
              </div>";
    }
}

function testAPIAccess() {
    $endpoints = [
        '/api/overview.php',
        '/api/bookings.php',
        '/api/reports.php'
    ];
    
    foreach ($endpoints as $endpoint) {
        $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . $endpoint;
        $accessible = checkURLAccessible($url);
        
        $icon = $accessible ? '✅' : '❌';
        $color = $accessible ? 'test-pass' : 'test-fail';
        
        echo "<div class='flex items-start space-x-2'>
                <span class='$color'>$icon</span>
                <div>
                    <div class='font-semibold $color'>API: $endpoint</div>
                    <div class='text-xs text-slate-600'>" . ($accessible ? 'Accessible' : 'Not accessible') . "</div>
                </div>
              </div>";
    }
}

function testSessionManagement() {
    $user = auth_user();
    $tests = [
        'User Authenticated' => !empty($user['id']),
        'User Role Set' => !empty($user['role']),
    ];
    
    foreach ($tests as $test => $result) {
        $icon = $result ? '✅' : '❌';
        $color = $result ? 'test-pass' : 'test-fail';
        echo "<div class='flex items-start space-x-2'>
                <span class='$color'>$icon</span>
                <div>
                    <div class='font-semibold $color'>$test</div>
                </div>
              </div>";
    }
}

function checkURLAccessible($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    return $content !== false;
}
?>