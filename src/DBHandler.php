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
        $this->sourcePort = $sourceConfig['port'] ?? 3306; // MySQL default port
        $this->sourceUser = $sourceConfig['database']['user'];
        $this->sourcePassword = $sourceConfig['database']['password'];
        $this->sourceDbName = $sourceConfig['database']['name'];

        $this->destHost = $destConfig['host'];
        $this->destPort = $destConfig['port'] ?? 22; // SSH port for destination
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

    /**
     * Export the MySQL database remotely by connecting via mysqli and dumping all tables.
     * Saves the dump locally.
     * @param string $dumpFile Local path to save the dump
     * @return bool
     */
    public function exportDatabase($dumpFile) {
        $mysqli = new mysqli($this->sourceHost, $this->sourceUser, $this->sourcePassword, $this->sourceDbName, $this->sourcePort);
        if ($mysqli->connect_error) {
            $this->log("❌ MySQL connection failed: " . $mysqli->connect_error);
            return false;
        }

        $tables = [];
        $result = $mysqli->query("SHOW TABLES");
        if (!$result) {
            $this->log("❌ Failed to list tables: " . $mysqli->error);
            return false;
        }
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }

        $sqlDump = "";
        foreach ($tables as $table) {
            $result = $mysqli->query("SHOW CREATE TABLE `$table`");
            if (!$result) {
                $this->log("❌ Failed to get create statement for $table: " . $mysqli->error);
                return false;
            }
            $row = $result->fetch_assoc();
            $sqlDump .= $row['Create Table'] . ";\n\n";

            $result = $mysqli->query("SELECT * FROM `$table`");
            if (!$result) {
                $this->log("❌ Failed to select data from $table: " . $mysqli->error);
                return false;
            }

            while ($dataRow = $result->fetch_assoc()) {
                $columns = array_map(function($col) use ($mysqli) {
                    return "`" . $mysqli->real_escape_string($col) . "`";
                }, array_keys($dataRow));
                $values = array_map(function($val) use ($mysqli) {
                    if ($val === null) return "NULL";
                    return "'" . $mysqli->real_escape_string($val) . "'";
                }, array_values($dataRow));

                $sqlDump .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            $sqlDump .= "\n";
        }

        $mysqli->close();

        if (file_put_contents($dumpFile, $sqlDump) === false) {
            $this->log("❌ Failed to write dump file to $dumpFile");
            return false;
        }

        $this->log("✅ Database exported locally to $dumpFile");
        return true;
    }

    /**
     * Import the MySQL database dump file into the destination server via SSH.
     * @param string $dumpFile Path on destination server where dump file is located
     * @return bool
     */
    public function importDatabase($dumpFile) {
        $connection = ssh2_connect($this->destHost, $this->destPort);
        if (!$connection) {
            $this->log("❌ SSH connection to {$this->destHost}:{$this->destPort} failed.");
            return false;
        }
        if (!ssh2_auth_password($connection, $this->destUser, $this->destPassword)) {
            $this->log("❌ SSH authentication failed for user {$this->destUser}.");
            return false;
        }

        $cmd = "mysql -u " . escapeshellarg($this->destDbUser) .
            " -p" . escapeshellarg($this->destDbPass) .
            " " . escapeshellarg($this->destDbName) .
            " < " . escapeshellarg($dumpFile);

        $stream = ssh2_exec($connection, $cmd);
        if (!$stream) {
            $this->log("❌ Failed to execute import command.");
            return false;
        }
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);

        $this->log("✅ Database imported from $dumpFile on destination server. Output: $output");
        return true;
    }

    /**
     * Upload the local dump file to the destination server via SFTP.
     * @param string $localDumpFile Local path of dump file
     * @param string $remoteDumpFile Remote path on destination server
     * @return bool
     */
    public function uploadDumpFile($localDumpFile, $remoteDumpFile) {
        $connection = ssh2_connect($this->destHost, $this->destPort);
        if (!$connection) {
            $this->log("❌ SSH connection to {$this->destHost}:{$this->destPort} failed.");
            return false;
        }
        if (!ssh2_auth_password($connection, $this->destUser, $this->destPassword)) {
            $this->log("❌ SSH authentication failed for user {$this->destUser}.");
            return false;
        }

        $sftp = ssh2_sftp($connection);
        if (!$sftp) {
            $this->log("❌ Failed to initialize SFTP.");
            return false;
        }

        $streamIn = fopen($localDumpFile, 'r');
        if (!$streamIn) {
            $this->log("❌ Failed to open local dump file $localDumpFile.");
            return false;
        }

        $streamOut = fopen("ssh2.sftp://$sftp$remoteDumpFile", 'w');
        if (!$streamOut) {
            $this->log("❌ Failed to open remote dump file $remoteDumpFile.");
            fclose($streamIn);
            return false;
        }

        $writtenBytes = stream_copy_to_stream($streamIn, $streamOut);
        fclose($streamIn);
        fclose($streamOut);

        if ($writtenBytes === false) {
            $this->log("❌ Failed to upload dump file.");
            return false;
        }

        $this->log("✅ Uploaded dump file to $remoteDumpFile.");
        return true;
    }
}
