<?php

require_once __DIR__ . '/src/FileHandler.php';
require_once __DIR__ . '/src/DBHandler.php';
require_once __DIR__ . '/src/CyberPanelAPI.php';

// Read config
$config = json_decode(file_get_contents(__DIR__ . '/config/credentials.json'), true);

echo "🚀 Starting WordPress Migration\n";

// Prompt for migration direction
echo "Select migration direction:\n1) cPanel to CyberPanel\n2) CyberPanel to cPanel\nEnter choice (1 or 2): ";
$direction = trim(fgets(STDIN));

if ($direction !== '1' && $direction !== '2') {
    echo "❌ Invalid choice. Exiting.\n";
    exit(1);
}

// Prompt for source and destination directories
echo "Enter source directory (full path): ";
$sourceDir = trim(fgets(STDIN));

echo "Enter destination directory (full path): ";
$destDir = trim(fgets(STDIN));

if ($direction === '1') {
    // cPanel to CyberPanel
    $sourceConfig = $config['cpanel'];
    $destConfig = $config['cyberpanel'];
} else {
    // CyberPanel to cPanel
    $sourceConfig = $config['cyberpanel'];
    $destConfig = $config['cpanel'];
}

// Initialize CyberPanelAPI for destination server
$cyberPanel = new CyberPanelAPI($destConfig['host'], $destConfig['username'], $destConfig['password'], $destConfig['port'] ?? 22);
if (!$cyberPanel->connect()) {
    echo "❌ Failed to connect to destination server via SSH.\n";
    exit(1);
}

// Transfer files
if ($direction === '1') {
    $success = $cyberPanel->transferFromSource(
        $sourceConfig['username'],
        $sourceConfig['host'],
        $sourceConfig['port'] ?? 22,
        $sourceDir,
        $destDir
    );
} else {
    $success = $cyberPanel->transferToDestination(
        $destConfig['username'],
        $destConfig['host'],
        $destConfig['port'] ?? 22,
        $sourceDir,
        $destDir
    );
}

if (!$success) {
    echo "❌ File transfer failed.\n";
    exit(1);
}
echo "✅ File transfer completed.\n";

// Initialize DBHandler
$dbHandler = new DBHandler($sourceConfig, $destConfig);

// Export database on source server
$dumpFile = "/tmp/wordpress_db_dump.sql";
if (!$dbHandler->exportDatabase($dumpFile)) {
    echo "❌ Database export failed.\n";
    exit(1);
}

// Transfer database dump file
$destDumpFile = "/tmp/wordpress_db_dump.sql";
if (!$dbHandler->transferDumpFile($dumpFile, $destDumpFile)) {
    echo "❌ Database dump file transfer failed.\n";
    exit(1);
}

// Import database on destination server
if (!$dbHandler->importDatabase($destDumpFile)) {
    echo "❌ Database import failed.\n";
    exit(1);
}
echo "✅ Database migration completed.\n";

// TODO: Update wp-config.php on destination server with new DB credentials

echo "🚀 WordPress migration completed successfully.\n";
