<?php

class CyberPanelAPI {
    private $connection;
    private $sftp;
    private $logFile = __DIR__ . '/../logs/migration.log';

    private $host;
    private $port;
    private $username;
    private $password;

    public function __construct($host, $username, $password, $port = 22) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function connect() {
        $this->connection = ssh2_connect($this->host, $this->port);
        if (!$this->connection) {
            $this->log("❌ SSH connection to {$this->host}:{$this->port} failed.");
            return false;
        }
        if (!ssh2_auth_password($this->connection, $this->username, $this->password)) {
            $this->log("❌ SSH authentication failed for user {$this->username}.");
            return false;
        }
        $this->sftp = ssh2_sftp($this->connection);
        $this->log("✅ Connected to CyberPanel server at {$this->host}:{$this->port} as {$this->username}");
        return true;
    }

    public function getSftpResource() {
        return $this->sftp;
    }

    public function execCommand($command) {
        $stream = ssh2_exec($this->connection, $command);
        if (!$stream) {
            $this->log("❌ Failed to execute command: $command");
            return false;
        }
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        $this->log("✅ Executed command: $command\nOutput: $output");
        return $output;
    }

    public function ensureDirectoryExists($remoteDir) {
        $command = "mkdir -p " . escapeshellarg($remoteDir);
        return $this->execCommand($command) !== false;
    }

    /**
     * Transfer files from source server to CyberPanel server using rsync over SSH.
     * Direction: from cPanel to CyberPanel
     * @param string $sourceUser Username on source server
     * @param string $sourceHost Source server hostname or IP
     * @param int $sourcePort SSH port on source server
     * @param string $sourceDir Directory on source server to transfer
     * @param string $destDir Destination directory on CyberPanel server
     * @return bool
     */
    public function transferFromSource($sourceUser, $sourceHost, $sourcePort, $sourceDir, $destDir) {
        $this->log("🚀 Starting transfer from source server $sourceHost:$sourceDir to CyberPanel:$destDir");
        $this->ensureDirectoryExists($destDir);

        // rsync command executed on CyberPanel server pulling from source server
        $rsyncCmd = "rsync -avz -e 'ssh -p $sourcePort' " .
            escapeshellarg("$sourceUser@$sourceHost:$sourceDir/") . " " .
            escapeshellarg($destDir);

        $output = $this->execCommand($rsyncCmd);
        return $output !== false;
    }

    /**
     * Transfer files from CyberPanel server to destination server using rsync over SSH.
     * Direction: from CyberPanel to cPanel
     * @param string $destUser Username on destination server
     * @param string $destHost Destination server hostname or IP
     * @param int $destPort SSH port on destination server
     * @param string $sourceDir Directory on CyberPanel server to transfer
     * @param string $destDir Destination directory on destination server
     * @return bool
     */
    public function transferToDestination($destUser, $destHost, $destPort, $sourceDir, $destDir) {
        $this->log("🚀 Starting transfer from CyberPanel:$sourceDir to destination server $destHost:$destDir");
        // Ensure destination directory exists on destination server
        $mkdirCmd = "ssh -p $destPort $destUser@$destHost 'mkdir -p " . escapeshellarg($destDir) . "'";
        $localExec = shell_exec($mkdirCmd);
        $this->log("Executed remote mkdir command on destination server: $mkdirCmd");

        // rsync command executed on CyberPanel server pushing to destination server
        $rsyncCmd = "rsync -avz -e 'ssh -p $destPort' " .
            escapeshellarg("$sourceDir/") . " " .
            escapeshellarg("$destUser@$destHost:$destDir");

        $output = $this->execCommand($rsyncCmd);
        return $output !== false;
    }

    private function log($message) {
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}
