<?php

class DBHandler {
    private $logFile = __DIR__ . '/../logs/migration.log';

    private $sourceHost;
    private $sourcePort;
    private $sourceUser;
    private $sourcePassword;
    private $sourceDbName;
    private $sourceDbUser;
    private $sourceDbPass;

    private $destHost;
    private $destPort;
    private $destUser;
    private $destPassword;
    private $destDbName;
    private $destDbUser;
    private $destDbPass;

    public function __construct($sourceConfig, $destConfig) {
        $this->sourceHost = $sourceConfig['host'];
        $this->sourcePort = $sourceConfig['port'] ?? 22;
        $this->sourceUser = $sourceConfig['username'];
        $this->sourcePassword = $sourceConfig['password'];
        $this->sourceDbName = $sourceConfig['database']['name'];
        $this->sourceDbUser = $sourceConfig['database']['user'];
        $this->sourceDbPass = $sourceConfig['database']['password'];

        $this->destHost = $destConfig['host'];
        $this->destPort = $destConfig['port'] ?? 22;
        $this->destUser = $destConfig['username'];
        $this->destPassword = $destConfig['password'];
        $this->destDbName = $destConfig['database']['name'];
        $this->destDbUser = $destConfig['database']['user'];
        $this->destDbPass = $destConfig['database']['password'];
    }

    private function log($message) {
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }

    private function sshConnect($host, $port, $user, $password) {
        $connection = ssh2_connect($host, $port);
        if (!$connection) {
            $this->log("❌ SSH connection to $host:$port failed.");
            return false;
        }
        if (!ssh2_auth_password($connection, $user, $password)) {
            $this->log("❌ SSH authentication failed for user $user on $host.");
            return false;
        }
        return $connection;
    }

    private function execCommand($connection, $command) {
        $stream = ssh2_exec($connection, $command);
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

    /**
     * Export the MySQL database on the source server to a SQL dump file.
     * @param string $dumpFile Path on source server to save the dump
     * @return bool|string Returns dump file path on success, false on failure
     */
    public function exportDatabase($dumpFile) {
        $conn = $this->sshConnect($this->sourceHost, $this->sourcePort, $this->sourceUser, $this->sourcePassword);
        if (!$conn) return false;

        $cmd = "mysqldump -u " . escapeshellarg($this->sourceDbUser) .
            " -p" . escapeshellarg($this->sourceDbPass) .
            " " . escapeshellarg($this->sourceDbName) .
            " > " . escapeshellarg($dumpFile);

        $result = $this->execCommand($conn, $cmd);
        if ($result === false) {
            $this->log("❌ Database export failed.");
            return false;
        }
        $this->log("✅ Database exported to $dumpFile on source server.");
        return $dumpFile;
    }

    /**
     * Import the MySQL database dump file into the destination server.
     * @param string $dumpFile Path on destination server where dump file is located
     * @return bool
     */
    public function importDatabase($dumpFile) {
        $conn = $this->sshConnect($this->destHost, $this->destPort, $this->destUser, $this->destPassword);
        if (!$conn) return false;

        $cmd = "mysql -u " . escapeshellarg($this->destDbUser) .
            " -p" . escapeshellarg($this->destDbPass) .
            " " . escapeshellarg($this->destDbName) .
            " < " . escapeshellarg($dumpFile);

        $result = $this->execCommand($conn, $cmd);
        if ($result === false) {
            $this->log("❌ Database import failed.");
            return false;
        }
        $this->log("✅ Database imported from $dumpFile on destination server.");
        return true;
    }

    /**
     * Transfer the database dump file from source server to destination server.
     * @param string $dumpFile Path of dump file on source server
     * @param string $destPath Path to save dump file on destination server
     * @return bool
     */
    public function transferDumpFile($dumpFile, $destPath) {
        $conn = $this->sshConnect($this->sourceHost, $this->sourcePort, $this->sourceUser, $this->sourcePassword);
        if (!$conn) return false;

        $sftp = ssh2_sftp($conn);
        if (!$sftp) {
            $this->log("❌ Failed to initialize SFTP on source server.");
            return false;
        }

        $stream = fopen("ssh2.sftp://$sftp$dumpFile", 'r');
        if (!$stream) {
            $this->log("❌ Failed to open dump file $dumpFile on source server.");
            return false;
        }

        $connDest = $this->sshConnect($this->destHost, $this->destPort, $this->destUser, $this->destPassword);
        if (!$connDest) {
            fclose($stream);
            return false;
        }

        $sftpDest = ssh2_sftp($connDest);
        if (!$sftpDest) {
            $this->log("❌ Failed to initialize SFTP on destination server.");
            fclose($stream);
            return false;
        }

        $streamDest = fopen("ssh2.sftp://$sftpDest$destPath", 'w');
        if (!$streamDest) {
            $this->log("❌ Failed to open destination file $destPath on destination server.");
            fclose($stream);
            return false;
        }

        $writtenBytes = stream_copy_to_stream($stream, $streamDest);
        fclose($stream);
        fclose($streamDest);

        if ($writtenBytes === false) {
            $this->log("❌ Failed to transfer dump file from source to destination.");
            return false;
        }

        $this->log("✅ Transferred dump file from $dumpFile to $destPath.");
        return true;
    }
}
