<?php

require_once __DIR__ . '/src/FileHandler.php';

// Read config
$config = json_decode(file_get_contents(__DIR__ . '/config/credentials.json'), true);

$ftpHost = $config['cpanel']['host'];
$ftpUser = $config['cpanel']['username'];
$ftpPass = $config['cpanel']['password'];

// Start Migration
echo "🚀 Starting WordPress Migration\n";

$fileHandler = new FileHandler();

if ($fileHandler->connectFTP($ftpHost, $ftpUser, $ftpPass)) {
    $fileHandler->downloadFiles();
} else {
    echo "❌ Failed to connect to FTP. Check credentials.\n";
}

echo "✅ Migration step 1 completed. Check logs/migration.log\n";
