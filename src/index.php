<?php

if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

require_once __DIR__ . '/FileHandler.php';
require_once __DIR__ . '/DBHandler.php';
require_once __DIR__ . '/CyberPanelAPI.php';

// Read config
// $config = json_decode(file_get_contents(__DIR__ . '/config/credentials.json'), true);
$config = json_decode(file_get_contents(__DIR__ . '/../config/credentials.json'), true);


echo "🚀 Starting WordPress Migration\n";

echo "Select migration direction:\n1) cPanel to CyberPanel\n2) CyberPanel to cPanel\nEnter choice (1 or 2): ";
$direction = trim(fgets(STDIN));

echo "Select migration type:\n1) Website files only\n2) Database only\nEnter choice (1 or 2): ";
$migrationType = trim(fgets(STDIN));

if ($direction !== '1' && $direction !== '2') {
    echo "❌ Invalid direction choice. Exiting.\n";
    exit(1);
}

if ($migrationType !== '1' && $migrationType !== '2') {
    echo "❌ Invalid migration type choice. Exiting.\n";
    exit(1);
}

echo "Enter source directory (full path): ";
$sourceDir = trim(fgets(STDIN));

echo "Enter destination directory (full path): ";
$destDir = trim(fgets(STDIN));

// Prompt for cPanel credentials
echo "Enter cPanel FTP host: ";
$cpanelHost = trim(fgets(STDIN));

echo "Enter cPanel FTP port (default 21): ";
$cpanelPort = trim(fgets(STDIN));
if (empty($cpanelPort)) $cpanelPort = 21;

echo "Enter cPanel FTP username: ";
$cpanelUser = trim(fgets(STDIN));

echo "Enter cPanel FTP password: ";
$cpanelPass = trim(fgets(STDIN));

echo "Enter cPanel MySQL host (usually localhost): ";
$cpanelDbHost = trim(fgets(STDIN));
if (empty($cpanelDbHost)) $cpanelDbHost = 'localhost';

echo "Enter cPanel MySQL port (default 3306): ";
$cpanelDbPort = trim(fgets(STDIN));
if (empty($cpanelDbPort)) $cpanelDbPort = 3306;

echo "Enter cPanel MySQL database name: ";
$cpanelDbName = trim(fgets(STDIN));

echo "Enter cPanel MySQL username: ";
$cpanelDbUser = trim(fgets(STDIN));

echo "Enter cPanel MySQL password: ";
$cpanelDbPass = trim(fgets(STDIN));

// Prompt for CyberPanel credentials
echo "Enter CyberPanel SSH host: ";
$cyberHost = trim(fgets(STDIN));

echo "Enter CyberPanel SSH port (default 22): ";
$cyberPort = trim(fgets(STDIN));
if (empty($cyberPort)) $cyberPort = 22;

echo "Enter CyberPanel SSH username: ";
$cyberUser = trim(fgets(STDIN));

echo "Enter CyberPanel SSH password: ";
$cyberPass = trim(fgets(STDIN));

echo "Enter CyberPanel MySQL database name: ";
$cyberDbName = trim(fgets(STDIN));

echo "Enter CyberPanel MySQL username: ";
$cyberDbUser = trim(fgets(STDIN));

echo "Enter CyberPanel MySQL password: ";
$cyberDbPass = trim(fgets(STDIN));

$sourceConfig = [
    'host' => $cpanelHost,
    'port' => (int)$cpanelPort,
    'username' => $cpanelUser,
    'password' => $cpanelPass,
    'database' => [
        'host' => $cpanelDbHost,
        'port' => (int)$cpanelDbPort,
        'name' => $cpanelDbName,
        'user' => $cpanelDbUser,
        'password' => $cpanelDbPass,
    ],
];

$destConfig = [
    'host' => $cyberHost,
    'port' => (int)$cyberPort,
    'username' => $cyberUser,
    'password' => $cyberPass,
    'database' => [
        'name' => $cyberDbName,
        'user' => $cyberDbUser,
        'password' => $cyberDbPass,
    ],
];

$ftpPort = $sourceConfig['port'] ?? 21; // default FTP port

// Initialize FileHandler and connect FTP for source server if direction is cPanel to CyberPanel and migration type is files
if ($direction === '1' && $migrationType === '1') {
    $fileHandler = new FileHandler();
    if (!$fileHandler->connectFTP($sourceConfig['host'], $sourceConfig['username'], $sourceConfig['password'], $ftpPort)) {
        echo "❌ Failed to connect to source FTP server.\n";
        exit(1);
    }
    $fileHandler->downloadFiles($sourceDir, __DIR__ . '/../backup');
}

// Initialize CyberPanelAPI for destination server if migration type is files
if ($migrationType === '1') {
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
}

// Database migration if migration type is database
if ($migrationType === '2') {
    $dbHandler = new DBHandler($sourceConfig, $destConfig);

    // Export database locally
    $dumpFile = __DIR__ . '/../backup/wordpress_db_dump.sql';
    if (!$dbHandler->exportDatabase($dumpFile)) {
        echo "❌ Database export failed.\n";
        exit(1);
    }

    // Upload dump file to destination server
    $remoteDumpFile = "/tmp/wordpress_db_dump.sql";
    if (!$dbHandler->uploadDumpFile($dumpFile, $remoteDumpFile)) {
        echo "❌ Database dump file upload failed.\n";
        exit(1);
    }

    // Import database on destination server
    if (!$dbHandler->importDatabase($remoteDumpFile)) {
        echo "❌ Database import failed.\n";
        exit(1);
    }
    echo "✅ Database migration completed.\n";
}

// TODO: Update wp-config.php on destination server with new DB credentials

echo "🚀 WordPress migration completed successfully.\n";
