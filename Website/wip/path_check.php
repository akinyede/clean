<?php
/**
 * find_actual_files.php
 */
echo "🔍 Finding Actual File Locations\n";
echo "================================\n";

// Start from current directory and search
$startDir = __DIR__;
echo "Current script location: $startDir\n\n";

// Search for key files
$filesToFind = ['config.php', 'bootstrap.php', 'app/', 'vendor/autoload.php'];

foreach ($filesToFind as $file) {
    echo "Searching for: $file\n";
    
    // Search in common locations
    $searchPaths = [
        $startDir . '/' . $file,
        $startDir . '/../' . $file,
        $startDir . '/../../' . $file,
        dirname($startDir) . '/' . $file,
    ];
    
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            echo "  ✅ FOUND: $path\n";
            break;
        } else {
            echo "  ❌ NOT: $path\n";
        }
    }
    echo "---\n";
}

// List what's actually in the wip directory
echo "\n📁 Contents of /wip/ directory:\n";
$wipContents = scandir('/home/enoudohc/ekotgroup.enoudoh.com/wip');
foreach ($wipContents as $item) {
    if ($item !== '.' && $item !== '..') {
        echo "  - $item\n";
    }
}