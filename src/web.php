<?php
require_once __DIR__ . '/FileHandler.php';
require_once __DIR__ . '/DBHandler.php';
require_once __DIR__ . '/CyberPanelAPI.php';

$message = '';
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $direction = $_POST['direction'] ?? '';
    $sourceDir = $_POST['sourceDir'] ?? '';
    $destDir = $_POST['destDir'] ?? '';

    $cpanelHost = $_POST['cpanelHost'] ?? '';
    $cpanelUser = $_POST['cpanelUser'] ?? '';
    $cpanelPass = $_POST['cpanelPass'] ?? '';
    $cpanelPort = $_POST['cpanelPort'] ?? 22;
    $cpanelDbName = $_POST['cpanelDbName'] ?? '';
    $cpanelDbUser = $_POST['cpanelDbUser'] ?? '';
    $cpanelDbPass = $_POST['cpanelDbPass'] ?? '';

    $cyberHost = $_POST['cyberHost'] ?? '';
    $cyberUser = $_POST['cyberUser'] ?? '';
    $cyberPass = $_POST['cyberPass'] ?? '';
    $cyberPort = $_POST['cyberPort'] ?? 22;
    $cyberDbName = $_POST['cyberDbName'] ?? '';
    $cyberDbUser = $_POST['cyberDbUser'] ?? '';
    $cyberDbPass = $_POST['cyberDbPass'] ?? '';

    $sourceConfig = [];
    $destConfig = [];

    if ($direction === 'cpanel_to_cyberpanel') {
        $sourceConfig = [
            'host' => $cpanelHost,
            'username' => $cpanelUser,
            'password' => $cpanelPass,
            'port' => (int)$cpanelPort,
            'database' => [
                'name' => $cpanelDbName,
                'user' => $cpanelDbUser,
                'password' => $cpanelDbPass,
            ],
        ];
        $destConfig = [
            'host' => $cyberHost,
            'username' => $cyberUser,
            'password' => $cyberPass,
            'port' => (int)$cyberPort,
            'database' => [
                'name' => $cyberDbName,
                'user' => $cyberDbUser,
                'password' => $cyberDbPass,
            ],
        ];
    } elseif ($direction === 'cyberpanel_to_cpanel') {
        $sourceConfig = [
            'host' => $cyberHost,
            'username' => $cyberUser,
            'password' => $cyberPass,
            'port' => (int)$cyberPort,
            'database' => [
                'name' => $cyberDbName,
                'user' => $cyberDbUser,
                'password' => $cyberDbPass,
            ],
        ];
        $destConfig = [
            'host' => $cpanelHost,
            'username' => $cpanelUser,
            'password' => $cpanelPass,
            'port' => (int)$cpanelPort,
            'database' => [
                'name' => $cpanelDbName,
                'user' => $cpanelDbUser,
                'password' => $cpanelDbPass,
            ],
        ];
    } else {
        $message = "Invalid migration direction selected.";
    }

    if (!$message) {
        $cyberPanel = new CyberPanelAPI($destConfig['host'], $destConfig['username'], $destConfig['password'], $destConfig['port']);
        if (!$cyberPanel->connect()) {
            $message = "Failed to connect to destination server via SSH.";
        } else {
            if ($direction === 'cpanel_to_cyberpanel') {
                $success = $cyberPanel->transferFromSource(
                    $sourceConfig['username'],
                    $sourceConfig['host'],
                    $sourceConfig['port'],
                    $sourceDir,
                    $destDir
                );
            } else {
                $success = $cyberPanel->transferToDestination(
                    $destConfig['username'],
                    $destConfig['host'],
                    $destConfig['port'],
                    $sourceDir,
                    $destDir
                );
            }

            if (!$success) {
                $message = "File transfer failed.";
            } else {
                $dbHandler = new DBHandler($sourceConfig, $destConfig);
                $dumpFile = "/tmp/wordpress_db_dump.sql";
                if (!$dbHandler->exportDatabase($dumpFile)) {
                    $message = "Database export failed.";
                } elseif (!$dbHandler->transferDumpFile($dumpFile, $dumpFile)) {
                    $message = "Database dump file transfer failed.";
                } elseif (!$dbHandler->importDatabase($dumpFile)) {
                    $message = "Database import failed.";
                } else {
                    $message = "WordPress migration completed successfully.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WordPress Migrator</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f0f4f8;
        margin: 0;
        padding: 0;
        color: #333;
    }
    header {
        background-color: #007bff;
        color: white;
        padding: 1rem 2rem;
        text-align: center;
    }
    main {
        max-width: 700px;
        margin: 2rem auto;
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h1 {
        margin-top: 0;
        color:rgb(12, 0, 179);
    }
    label {
        display: block;
        margin-top: 1rem;
        font-weight: bold;
    }
    input[type="text"],
    input[type="password"],
    input[type="number"],
    select {
        width: 100%;
        padding: 0.5rem;
        margin-top: 0.25rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    button {
        margin-top: 1.5rem;
        background-color: #007bff;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        border-radius: 4px;
        cursor: pointer;
    }
    button:hover {
        background-color: #0056b3;
    }
    .message {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 4px;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
    }
    .error {
        background-color: #f8d7da;
        color: #721c24;
    }
</style>
</head>
<body>
<header>
    <h1>WordPress Migrator</h1>
</header>
<main>
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'failed') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <label for="direction">Migration Direction</label>
        <select id="direction" name="direction" required>
            <option value="">Select direction</option>
            <option value="cpanel_to_cyberpanel" <?php if (($direction ?? '') === 'cpanel_to_cyberpanel') echo 'selected'; ?>>cPanel to CyberPanel</option>
            <option value="cyberpanel_to_cpanel" <?php if (($direction ?? '') === 'cyberpanel_to_cpanel') echo 'selected'; ?>>CyberPanel to cPanel</option>
        </select>

        <label for="sourceDir">Source Directory (full path)</label>
        <input type="text" id="sourceDir" name="sourceDir" value="<?php echo htmlspecialchars($sourceDir ?? ''); ?>" required />

        <label for="destDir">Destination Directory (full path)</label>
        <input type="text" id="destDir" name="destDir" value="<?php echo htmlspecialchars($destDir ?? ''); ?>" required />

        <h2>cPanel Server Credentials</h2>
        <label for="cpanelHost">Host</label>
        <input type="text" id="cpanelHost" name="cpanelHost" value="<?php echo htmlspecialchars($cpanelHost ?? ''); ?>" required />

        <label for="cpanelUser">Username</label>
        <input type="text" id="cpanelUser" name="cpanelUser" value="<?php echo htmlspecialchars($cpanelUser ?? ''); ?>" required />

        <label for="cpanelPass">Password</label>
        <input type="password" id="cpanelPass" name="cpanelPass" value="<?php echo htmlspecialchars($cpanelPass ?? ''); ?>" required />

        <label for="cpanelPort">SSH Port</label>
        <input type="number" id="cpanelPort" name="cpanelPort" value="<?php echo htmlspecialchars($cpanelPort ?? 22); ?>" min="1" max="65535" required />

        <label for="cpanelDbName">Database Name</label>
        <input type="text" id="cpanelDbName" name="cpanelDbName" value="<?php echo htmlspecialchars($cpanelDbName ?? ''); ?>" required />

        <label for="cpanelDbUser">Database User</label>
        <input type="text" id="cpanelDbUser" name="cpanelDbUser" value="<?php echo htmlspecialchars($cpanelDbUser ?? ''); ?>" required />

        <label for="cpanelDbPass">Database Password</label>
        <input type="password" id="cpanelDbPass" name="cpanelDbPass" value="<?php echo htmlspecialchars($cpanelDbPass ?? ''); ?>" required />

        <h2>CyberPanel Server Credentials</h2>
        <label for="cyberHost">Host</label>
        <input type="text" id="cyberHost" name="cyberHost" value="<?php echo htmlspecialchars($cyberHost ?? ''); ?>" required />

        <label for="cyberUser">Username</label>
        <input type="text" id="cyberUser" name="cyberUser" value="<?php echo htmlspecialchars($cyberUser ?? ''); ?>" required />

        <label for="cyberPass">Password</label>
        <input type="password" id="cyberPass" name="cyberPass" value="<?php echo htmlspecialchars($cyberPass ?? ''); ?>" required />

        <label for="cyberPort">SSH Port</label>
        <input type="number" id="cyberPort" name="cyberPort" value="<?php echo htmlspecialchars($cyberPort ?? 22); ?>" min="1" max="65535" required />

        <label for="cyberDbName">Database Name</label>
        <input type="text" id="cyberDbName" name="cyberDbName" value="<?php echo htmlspecialchars($cyberDbName ?? ''); ?>" required />

        <label for="cyberDbUser">Database User</label>
        <input type="text" id="cyberDbUser" name="cyberDbUser" value="<?php echo htmlspecialchars($cyberDbUser ?? ''); ?>" required />

        <label for="cyberDbPass">Database Password</label>
        <input type="password" id="cyberDbPass" name="cyberDbPass" value="<?php echo htmlspecialchars($cyberDbPass ?? ''); ?>" required />

        <button type="submit">Start Migration</button>
    </form>
</main>
</body>
</html>