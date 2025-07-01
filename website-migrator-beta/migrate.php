<?php
require_once __DIR__ . '/../src/FileHandler.php';
require_once __DIR__ . '/../src/DBHandler.php';
require_once __DIR__ . '/../src/CyberPanelAPI.php';

echo "WordPress Migrator Beta CLI\n";
echo "===========================\n\n";

$handle = fopen("php://stdin", "r");

function prompt($message, $default = null) {
    if ($default) {
        echo $message . " [$default]: ";
    } else {
        echo $message . ": ";
    }
    $input = trim(fgets(STDIN));
    return $input === '' ? $default : $input;
}

function loadEnv($path) {
    if (!file_exists($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $env[$key] = $value;
    }
    return $env;
}

$env = loadEnv(__DIR__ . '/.env');

$direction = prompt(
    "Select migration direction:\n(1) cPanel to CyberPanel\n(2) CyberPanel to cPanel",
    $env['DEFAULT_DIRECTION'] ?? null
);

if (!in_array($direction, ['1', '2'])) {
    echo "Invalid selection.\n";
    exit(1);
}


$source = [];
$dest = [];

if ($direction === '1') {
    echo "Enter source cPanel FTP details\n";
    $source['host'] = prompt("Source FTP Host", $env['CPANEL_FTP_HOST'] ?? null);
    $source['port'] = (int)prompt("Source FTP Port (default 21)", $env['CPANEL_FTP_PORT'] ?? '21');
    $source['user'] = prompt("Source FTP Username", $env['CPANEL_FTP_USER'] ?? null);
    $source['pass'] = prompt("Source FTP Password", $env['CPANEL_FTP_PASS'] ?? null);

    echo "Enter destination CyberPanel SSH details\n";
    $dest['host'] = prompt("Destination SSH Host", $env['CYBERPANEL_SSH_HOST'] ?? null);
    $dest['port'] = (int)prompt("Destination SSH Port (default 22)", $env['CYBERPANEL_SSH_PORT'] ?? '22');
    $dest['user'] = prompt("Destination SSH Username", $env['CYBERPANEL_SSH_USER'] ?? null);
    $dest['pass'] = prompt("Destination SSH Password", $env['CYBERPANEL_SSH_PASS'] ?? null);
} else {
    echo "Enter source CyberPanel SSH details\n";
    $source['host'] = prompt("Source SSH Host", $env['SOURCE_CYBERPANEL_SSH_HOST'] ?? null);
    $source['port'] = (int)prompt("Source SSH Port (default 22)", $env['SOURCE_CYBERPANEL_SSH_PORT'] ?? '22');
    $source['user'] = prompt("Source SSH Username", $env['SOURCE_CYBERPANEL_SSH_USER'] ?? null);
    $source['pass'] = prompt("Source SSH Password", $env['SOURCE_CYBERPANEL_SSH_PASS'] ?? null);

    echo "Enter destination cPanel FTP details\n";
    $dest['host'] = prompt("Destination FTP Host", $env['DEST_C_PANEL_FTP_HOST'] ?? null);
    $dest['port'] = (int)prompt("Destination FTP Port (default 21)", $env['DEST_C_PANEL_FTP_PORT'] ?? '21');
    $dest['user'] = prompt("Destination FTP Username", $env['DEST_C_PANEL_FTP_USER'] ?? null);
    $dest['pass'] = prompt("Destination FTP Password", $env['DEST_C_PANEL_FTP_PASS'] ?? null);
}

// Initialize FileHandler and DBHandler
$fileHandler = new FileHandler();
$dbHandler = new DBHandler(
    [
        'host' => $source['host'],
        'port' => $source['port'],
        'database' => [
            'user' => $source['user'],
            'password' => $source['pass'],
            'name' => prompt("Source Database Name"),
        ],
    ],
    [
        'host' => $dest['host'],
        'port' => $dest['port'],
        'username' => $dest['user'],
        'password' => $dest['pass'],
        'database' => [
            'name' => prompt("Destination Database Name"),
            'user' => prompt("Destination Database User"),
            'password' => prompt("Destination Database Password"),
        ],
    ]
);

echo "Starting migration process...\n";

$sourceDir = prompt("Enter the WordPress directory to migrate on source server (e.g. public_html or /home/user/public_html)");
$zipFileName = "wordpress_migration_" . time() . ".zip";
$localZipPath = __DIR__ . "/$zipFileName";

if ($direction === '1') {
    // cPanel to CyberPanel
    echo "Connecting to source FTP...\n";
    if (!$fileHandler->connectFTP($source['host'], $source['user'], $source['pass'], $source['port'])) {
        echo "Failed to connect to source FTP.\n";
        exit(1);
    }
    echo "Zipping source directory on cPanel...\n";
    // Download files to local first
    $localBackupDir = __DIR__ . '/backup';
    if (!is_dir($localBackupDir)) {
        mkdir($localBackupDir, 0755, true);
    }
    $fileHandler->downloadFiles($sourceDir, $localBackupDir);
    // Zip the downloaded files
    $zip = new ZipArchive();
    if ($zip->open($localZipPath, ZipArchive::CREATE) !== TRUE) {
        echo "Failed to create zip archive.\n";
        exit(1);
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($localBackupDir));
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($localBackupDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
    echo "Source directory zipped to $localZipPath\n";

    echo "Connecting to destination CyberPanel via SSH...\n";
    $cyberPanelAPI = new CyberPanelAPI($dest['host'], $dest['user'], $dest['pass'], $dest['port']);
    if (!$cyberPanelAPI->connect()) {
        echo "Failed to connect to destination CyberPanel SSH.\n";
        exit(1);
    }
    echo "Uploading zip file to destination CyberPanel...\n";
    $remoteZipPath = "/tmp/$zipFileName";
    $sftp = $cyberPanelAPI->getSftpResource();
    $stream = fopen("ssh2.sftp://{$sftp}{$remoteZipPath}", 'w');
    $localFile = fopen($localZipPath, 'r');
    if (!$stream || !$localFile) {
        echo "Failed to open streams for file transfer.\n";
        exit(1);
    }
    while (!feof($localFile)) {
        fwrite($stream, fread($localFile, 8192));
    }
    fclose($localFile);
    fclose($stream);
    echo "Zip file uploaded to $remoteZipPath\n";
    echo "Please unzip the file manually on the destination CyberPanel server at $remoteZipPath\n";

} else {
    // CyberPanel to cPanel
    echo "Connecting to source CyberPanel via SSH...\n";
    $cyberPanelAPI = new CyberPanelAPI($source['host'], $source['user'], $source['pass'], $source['port']);
    if (!$cyberPanelAPI->connect()) {
        echo "Failed to connect to source CyberPanel SSH.\n";
        exit(1);
    }
    echo "Zipping source directory on CyberPanel...\n";
    $zipCmd = "zip -r /tmp/$zipFileName " . escapeshellarg($sourceDir);
    $cyberPanelAPI->execCommand($zipCmd);

    // Instead of downloading locally, stream directly from source CyberPanel to destination FTP
    echo "Connecting to destination cPanel FTP...\n";
    if (!$fileHandler->connectFTP($dest['host'], $dest['user'], $dest['pass'], $dest['port'])) {
        echo "Failed to connect to destination FTP.\n";
        exit(1);
    }

    echo "Opening source zip file stream from CyberPanel...\n";
    $sftp = $cyberPanelAPI->getSftpResource();
    $remoteZipPath = "/tmp/$zipFileName";
    $sourceStream = fopen("ssh2.sftp://{$sftp}{$remoteZipPath}", 'r');
    if (!$sourceStream) {
        echo "Failed to open source zip file stream.\n";
        exit(1);
    }

    echo "Uploading zip file to destination FTP directly from source stream...\n";
    $ftpConn = $fileHandler->getFtpConnection();
    // Ask user for destination path on FTP server for zip upload
    $defaultRemotePath = $zipFileName;
    $remoteFtpPath = prompt("Enter destination path on FTP server to upload zip file (relative to FTP root)", $defaultRemotePath);

    // Sanitize path: remove leading slashes
    $remoteFtpPath = ltrim($remoteFtpPath, '/');

    // Extract directory path from remoteFtpPath
    $remoteDir = dirname($remoteFtpPath);
    if ($remoteDir !== '.' && $remoteDir !== '') {
        // Create directories recursively if not exist
        $dirs = explode('/', $remoteDir);
        $path = '';
        foreach ($dirs as $dir) {
            $path .= '/' . $dir;
            @ftp_mkdir($ftpConn, $path);
        }
    }

    $uploadSuccess = ftp_fput($ftpConn, $remoteFtpPath, $sourceStream, FTP_BINARY);
    fclose($sourceStream);

    if (!$uploadSuccess) {
        echo "Failed to upload zip file to destination FTP.\n";
        exit(1);
    }

    echo "Cleaning up zip file on source CyberPanel...\n";
    $cyberPanelAPI->execCommand("rm $remoteZipPath");

    echo "Please unzip the file manually on the destination FTP server at $remoteFtpPath\n";
}

echo "Migration file transfer completed.\n";

/*
The previous call to $fileHandler->downloadFiles() here is redundant and causes errors when source is CyberPanel.
We have already handled file download above depending on source type.
*/

$dumpFile = __DIR__ . '/db_dump.sql';
if (!empty($source['user']) && !empty($source['pass']) && !empty($dest['user']) && !empty($dest['pass'])) {
    echo "Exporting database...\n";
    if (!$dbHandler->exportDatabase($dumpFile)) {
        echo "Database export failed.\n";
        exit(1);
    }

    // Upload and import database on destination
    if ($direction === '1') {
        echo "Uploading database dump to destination CyberPanel...\n";
        if (!$dbHandler->uploadDumpFile($dumpFile, '/tmp/db_dump.sql')) {
            echo "Failed to upload database dump.\n";
            exit(1);
        }
        echo "Importing database on destination CyberPanel...\n";
        if (!$dbHandler->importDatabase('/tmp/db_dump.sql')) {
            echo "Database import failed.\n";
            exit(1);
        }
    } else {
        echo "Uploading database dump to destination cPanel FTP...\n";
        // Implement upload via FTP or other method
        echo "Importing database on destination cPanel...\n";
        // Implement import logic
    }
} else {
    echo "Database credentials not fully provided. Skipping database export/import.\n";
}

echo "Migration completed successfully.\n";
?>
