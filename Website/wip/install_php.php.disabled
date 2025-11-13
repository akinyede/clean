<?php
// install_proper.php
echo "<h3>Installing PHPSpreadsheet with Composer</h3>";

$projectDir = __DIR__;

// Set COMPOSER_HOME environment variable
putenv('COMPOSER_HOME=' . $projectDir . '/.composer');

// Create composer home directory
if (!is_dir($projectDir . '/.composer')) {
    mkdir($projectDir . '/.composer', 0755, true);
    echo "Created COMPOSER_HOME directory.<br>";
}

// Create or update composer.json
$composerJsonPath = $projectDir . '/composer.json';
if (!file_exists($composerJsonPath)) {
    $composerJson = [
        "require" => [
            "phpoffice/phpspreadsheet" => "^2.0"
        ],
        "config" => [
            "allow-plugins" => [
                "dealerdirect/phpcodesniffer-composer-installer" => true
            ]
        ]
    ];
    
    file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Created composer.json.<br>";
} else {
    echo "composer.json already exists.<br>";
}

// Run composer require
echo "Installing PHPSpreadsheet...<br>";
$command = 'cd ' . escapeshellarg($projectDir) . ' && composer require phpoffice/phpspreadsheet 2>&1';
$output = shell_exec($command);

echo "<pre>Installation output: " . htmlspecialchars($output) . "</pre>";

// Verify installation
verifyInstallation($projectDir);

function verifyInstallation($projectDir) {
    echo "<h3>Verifying Installation...</h3>";
    
    $autoloadPath = $projectDir . '/vendor/autoload.php';
    
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            echo "✅ PHPSpreadsheet installed successfully!<br>";
            testFunctionality();
        } else {
            echo "❌ PHPSpreadsheet classes not found.<br>";
            debugInstallation($projectDir);
        }
    } else {
        echo "❌ vendor/autoload.php not found.<br>";
    }
}

function testFunctionality() {
    try {
        $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'PHPSpreadsheet Test');
        $sheet->setCellValue('B1', 'Success!');
        
        echo "✅ Basic functionality test passed!<br>";
        echo "🎉 PHPSpreadsheet is ready to use!<br>";
        
    } catch (Exception $e) {
        echo "⚠️ Test failed: " . $e->getMessage() . "<br>";
    }
}

function debugInstallation($projectDir) {
    echo "<h4>Debug Information:</h4>";
    
    // Check vendor structure
    $vendorDir = $projectDir . '/vendor';
    if (is_dir($vendorDir)) {
        echo "Vendor directory exists.<br>";
        
        // Check phpoffice directory
        $phpofficeDir = $vendorDir . '/phpoffice';
        if (is_dir($phpofficeDir)) {
            echo "phpoffice directory exists.<br>";
            $items = scandir($phpofficeDir);
            echo "Contents: " . implode(', ', array_diff($items, ['.', '..'])) . "<br>";
            
            // Check phpspreadsheet
            $phpspreadsheetDir = $phpofficeDir . '/phpspreadsheet';
            if (is_dir($phpspreadsheetDir)) {
                echo "phpspreadsheet directory exists.<br>";
                if (is_dir($phpspreadsheetDir . '/src')) {
                    echo "src directory exists.<br>";
                    
                    // Check for key files
                    $keyFiles = [
                        '/src/Spreadsheet.php',
                        '/src/Worksheet/Worksheet.php'
                    ];
                    
                    foreach ($keyFiles as $file) {
                        if (file_exists($phpspreadsheetDir . $file)) {
                            echo "✅ Found: $file<br>";
                        } else {
                            echo "❌ Missing: $file<br>";
                        }
                    }
                }
            }
        }
    }
}
?>