<?php

class FileHandler {
    private $ftpConn;
    private $logFile = __DIR__ . '/../logs/migration.log';

    public function connectFTP($host, $user, $pass) {
        $this->ftpConn = ftp_connect($host);
        if (!$this->ftpConn) {
            $this->log("❌ FTP connection to $host failed.");
            return false;
        }

        $login = ftp_login($this->ftpConn, $user, $pass);
        if (!$login) {
            $this->log("❌ FTP login failed for user $user.");
            return false;
        }

        ftp_pasv($this->ftpConn, true); // passive mode
        $this->log("✅ Connected to FTP server at $host");
        return true;
    }

    public function downloadFiles($remoteDir = "public_html", $localDir = __DIR__ . '/../backup/') {
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $this->recursiveDownload($remoteDir, $localDir);
        $this->log("✅ Download complete from $remoteDir");
    }

    private function recursiveDownload($remoteDir, $localDir) {
        $files = ftp_nlist($this->ftpConn, $remoteDir);
        foreach ($files as $file) {
            $basename = basename($file);
            $localPath = $localDir . '/' . $basename;
            $remotePath = "$remoteDir/$basename";

            if ($basename === '.' || $basename === '..') continue;

            if (@ftp_chdir($this->ftpConn, $remotePath)) {
                // It's a directory
                ftp_chdir($this->ftpConn, "..");
                mkdir($localPath);
                $this->recursiveDownload($remotePath, $localPath);
            } else {
                // It's a file
                if (ftp_get($this->ftpConn, $localPath, $remotePath, FTP_BINARY)) {
                    $this->log("Downloaded: $remotePath");
                } else {
                    $this->log("❌ Failed to download: $remotePath");
                }
            }
        }
    }

    private function log($message) {
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
