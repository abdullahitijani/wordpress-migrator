<?php

class FileHandler {
    private $ftpConn;
    private $logFile = __DIR__ . '/../logs/migration.log';

    public function connectFTP($host, $user, $pass, $port = 21) {
        $this->ftpConn = ftp_connect($host, $port);
        if (!$this->ftpConn) {
            $this->log("❌ FTP connection to $host:$port failed.");
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
                if (!is_dir($localPath)) {
                    mkdir($localPath);
                }
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

    /**
     * Incremental download: only download files that are new or updated compared to local copies.
     * @param string $remoteDir
     * @param string $localDir
     */
    public function incrementalDownload($remoteDir = "public_html", $localDir = __DIR__ . '/../backup/') {
        if (!is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }
        $this->recursiveIncrementalDownload($remoteDir, $localDir);
        $this->log("✅ Incremental download complete from $remoteDir");
    }

    private function recursiveIncrementalDownload($remoteDir, $localDir) {
        $files = ftp_nlist($this->ftpConn, $remoteDir);
        foreach ($files as $file) {
            $basename = basename($file);
            $localPath = $localDir . '/' . $basename;
            $remotePath = "$remoteDir/$basename";

            if ($basename === '.' || $basename === '..') continue;

            if (@ftp_chdir($this->ftpConn, $remotePath)) {
                // Directory
                ftp_chdir($this->ftpConn, "..");
                if (!is_dir($localPath)) {
                    mkdir($localPath);
                }
                $this->recursiveIncrementalDownload($remotePath, $localPath);
            } else {
                // File
                $remoteModTime = ftp_mdtm($this->ftpConn, $remotePath);
                $localModTime = file_exists($localPath) ? filemtime($localPath) : 0;

                if ($remoteModTime === -1) {
                    // Could not get remote mod time, download anyway
                    $download = true;
                } else {
                    $download = $remoteModTime > $localModTime;
                }

                if ($download) {
                    if (ftp_get($this->ftpConn, $localPath, $remotePath, FTP_BINARY)) {
                        touch($localPath, $remoteModTime);
                        $this->log("Incrementally downloaded: $remotePath");
                    } else {
                        $this->log("❌ Failed to download: $remotePath");
                    }
                } else {
                    $this->log("Skipped (up-to-date): $remotePath");
                }
            }
        }
    }

    private function log($message) {
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
