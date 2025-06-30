<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $errors = [];

    // Validate inputs
    $cpanelHost = trim($_POST['cpanel_host'] ?? '');
    $cpanelPort = intval($_POST['cpanel_port'] ?? 21);
    $cpanelUser = trim($_POST['cpanel_user'] ?? '');
    $cpanelPass = trim($_POST['cpanel_pass'] ?? '');
    $cpanelDbHost = trim($_POST['cpanel_db_host'] ?? 'localhost');
    $cpanelDbPort = intval($_POST['cpanel_db_port'] ?? 3306);
    $cpanelDbName = trim($_POST['cpanel_db_name'] ?? '');
    $cpanelDbUser = trim($_POST['cpanel_db_user'] ?? '');
    $cpanelDbPass = trim($_POST['cpanel_db_pass'] ?? '');

    $cyberHost = trim($_POST['cyber_host'] ?? '');
    $cyberPort = intval($_POST['cyber_port'] ?? 22);
    $cyberUser = trim($_POST['cyber_user'] ?? '');
    $cyberPass = trim($_POST['cyber_pass'] ?? '');
    $cyberDbName = trim($_POST['cyber_db_name'] ?? '');
    $cyberDbUser = trim($_POST['cyber_db_user'] ?? '');
    $cyberDbPass = trim($_POST['cyber_db_pass'] ?? '');

    $sourceDir = trim($_POST['source_dir'] ?? '');
    $destDir = trim($_POST['dest_dir'] ?? '');
    $direction = $_POST['direction'] ?? '1';

    // Basic validation
    if (empty($cpanelHost)) $errors[] = "cPanel FTP host is required.";
    if (empty($cpanelUser)) $errors[] = "cPanel FTP username is required.";
    if (empty($cpanelPass)) $errors[] = "cPanel FTP password is required.";
    if (empty($cpanelDbName)) $errors[] = "cPanel MySQL database name is required.";
    if (empty($cpanelDbUser)) $errors[] = "cPanel MySQL username is required.";

    if (empty($cyberHost)) $errors[] = "CyberPanel SSH host is required.";
    if (empty($cyberUser)) $errors[] = "CyberPanel SSH username is required.";
    if (empty($cyberPass)) $errors[] = "CyberPanel SSH password is required.";
    if (empty($cyberDbName)) $errors[] = "CyberPanel MySQL database name is required.";
    if (empty($cyberDbUser)) $errors[] = "CyberPanel MySQL username is required.";

    if (empty($sourceDir)) $errors[] = "Source directory is required.";
    if (empty($destDir)) $errors[] = "Destination directory is required.";

    if (count($errors) === 0) {
        // Save inputs to session or pass to migration logic
        // For now, just display success message
        $success = "Migration started. Check logs for progress.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <title>WordPress Migrator - Web UI</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
        <link href="css/footer.css" rel="stylesheet" />
        <style>
            body { font-family: 'Poppins', sans-serif; background: #e6f0ff; color: #003366; margin: 0; padding: 0; }
            .container { max-width: 900px; margin: 20px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px #ccc; display: flex; flex-direction: column; gap: 20px; }
            h1 { color: #0059b3; margin-bottom: 0; }
            label { display: block; margin-top: 10px; font-weight: 600; }
            input[type=text], input[type=password], select {
                width: 100%;
                padding: 10px;
                margin-top: 6px;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 1rem;
                box-sizing: border-box;
            }
            button {
                margin-top: 20px;
                padding: 12px 24px;
                background: #0059b3;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
                font-size: 1rem;
                transition: background-color 0.3s ease;
            }
            button:hover {
                background: #004080;
            }
            .error {
                color: #d93025;
                margin-top: 10px;
                font-weight: 600;
            }
            .success {
                color: #188038;
                margin-top: 10px;
                font-weight: 600;
            }
            .log-viewer {
                margin-top: 20px;
                background: #f0f5ff;
                padding: 10px;
                height: 300px;
                overflow-y: scroll;
                border: 1px solid #ccc;
                font-family: monospace;
                white-space: pre-wrap;
                border-radius: 6px;
            }
        </style>
    </head>
<body>
<div class="container">
    <div style="text-align: right; margin-bottom: 10px;">
        <a href="logout.php" style="color: white; font-weight: 600; text-decoration: none; background-color: #0059b3; padding: 6px 12px; border-radius: 6px; transition: background-color 0.3s ease;">Logout</a>
    </div>
    <h1>WordPress Migrator - Web UI</h1>
    <form method="post" action="">
        <button type="submit" name="action" value="incremental_file_sync" style="margin-right: 10px; padding: 10px 20px; background-color: #0059b3; color: white; border: none; border-radius: 6px; cursor: pointer;">Incremental File Sync</button>
        <button type="submit" name="action" value="incremental_db_sync" style="padding: 10px 20px; background-color: #0059b3; color: white; border: none; border-radius: 6px; cursor: pointer;">Incremental DB Sync</button>
    </form>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

<form method="post" action="">
        <label for="direction">Migration Direction:</label>
        <select name="direction" id="direction">
            <option value="1" <?= ($direction ?? '') === '1' ? 'selected' : '' ?>>cPanel to CyberPanel</option>
            <option value="2" <?= ($direction ?? '') === '2' ? 'selected' : '' ?>>CyberPanel to cPanel</option>
        </select>

        <label for="migration_type">Migration Type:</label>
        <select name="migration_type" id="migration_type">
            <option value="1" <?= ($migrationType ?? '') === '1' ? 'selected' : '' ?>>Website files only</option>
            <option value="2" <?= ($migrationType ?? '') === '2' ? 'selected' : '' ?>>Database only</option>
        </select>

        <label for="source_dir">Source Directory (full path):</label>
        <input type="text" name="source_dir" id="source_dir" value="<?= htmlspecialchars($sourceDir ?? '') ?>" required />

        <label for="dest_dir">Destination Directory (full path):</label>
        <input type="text" name="dest_dir" id="dest_dir" value="<?= htmlspecialchars($destDir ?? '') ?>" required />

        <h2>cPanel FTP Credentials</h2>
        <label for="cpanel_host">FTP Host:</label>
        <input type="text" name="cpanel_host" id="cpanel_host" value="<?= htmlspecialchars($cpanelHost ?? '') ?>" required />

        <label for="cpanel_port">FTP Port:</label>
        <input type="text" name="cpanel_port" id="cpanel_port" value="<?= htmlspecialchars($cpanelPort ?? '21') ?>" />

        <label for="cpanel_user">FTP Username:</label>
        <input type="text" name="cpanel_user" id="cpanel_user" value="<?= htmlspecialchars($cpanelUser ?? '') ?>" required />

        <label for="cpanel_pass">FTP Password:</label>
        <input type="password" name="cpanel_pass" id="cpanel_pass" value="<?= htmlspecialchars($cpanelPass ?? '') ?>" required />

        <label for="cpanel_db_host">MySQL Host:</label>
        <input type="text" name="cpanel_db_host" id="cpanel_db_host" value="<?= htmlspecialchars($cpanelDbHost ?? 'localhost') ?>" />

        <label for="cpanel_db_port">MySQL Port:</label>
        <input type="text" name="cpanel_db_port" id="cpanel_db_port" value="<?= htmlspecialchars($cpanelDbPort ?? '3306') ?>" />

        <label for="cpanel_db_name">Database Name:</label>
        <input type="text" name="cpanel_db_name" id="cpanel_db_name" value="<?= htmlspecialchars($cpanelDbName ?? '') ?>" required />

        <label for="cpanel_db_user">Database Username:</label>
        <input type="text" name="cpanel_db_user" id="cpanel_db_user" value="<?= htmlspecialchars($cpanelDbUser ?? '') ?>" required />

        <label for="cpanel_db_pass">Database Password:</label>
        <input type="password" name="cpanel_db_pass" id="cpanel_db_pass" value="<?= htmlspecialchars($cpanelDbPass ?? '') ?>" />

        <h2>CyberPanel SSH Credentials</h2>
        <label for="cyber_host">SSH Host:</label>
        <input type="text" name="cyber_host" id="cyber_host" value="<?= htmlspecialchars($cyberHost ?? '') ?>" required />

        <label for="cyber_port">SSH Port:</label>
        <input type="text" name="cyber_port" id="cyber_port" value="<?= htmlspecialchars($cyberPort ?? '22') ?>" />

        <label for="cyber_user">SSH Username:</label>
        <input type="text" name="cyber_user" id="cyber_user" value="<?= htmlspecialchars($cyberUser ?? '') ?>" required />

        <label for="cyber_pass">SSH Password:</label>
        <input type="password" name="cyber_pass" id="cyber_pass" value="<?= htmlspecialchars($cyberPass ?? '') ?>" required />

        <label for="cyber_db_name">Database Name:</label>
        <input type="text" name="cyber_db_name" id="cyber_db_name" value="<?= htmlspecialchars($cyberDbName ?? '') ?>" required />

        <label for="cyber_db_user">Database Username:</label>
        <input type="text" name="cyber_db_user" id="cyber_db_user" value="<?= htmlspecialchars($cyberDbUser ?? '') ?>" required />

        <label for="cyber_db_pass">Database Password:</label>
        <input type="password" name="cyber_db_pass" id="cyber_db_pass" value="<?= htmlspecialchars($cyberDbPass ?? '') ?>" />

        <button type="submit">Start Migration</button>
    </form>

    <h2>Migration Logs</h2>
    <div class="log-viewer" id="logViewer">Loading logs...</div>

    <script>
        // Simple AJAX to fetch logs every 3 seconds
        function fetchLogs() {
            fetch('logs.php')
                .then(response => {
                    console.log('Fetch logs response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    console.log('Fetch logs data length:', data.length);
                    if (data.trim() === '') {
                        document.getElementById('logViewer').textContent = 'No logs available yet.';
                    } else {
                        document.getElementById('logViewer').textContent = data;
                        document.getElementById('logViewer').scrollTop = document.getElementById('logViewer').scrollHeight;
                    }
                })
                .catch((error) => {
                    console.error('Fetch logs error:', error);
                    document.getElementById('logViewer').textContent = 'Failed to load logs.';
                });
        }
        setInterval(fetchLogs, 3000);
        fetchLogs();
    </script>
</div>
<footer style="text-align: center; margin: 20px 0; color: #666; font-size: 0.9rem;">
    &copy; <?= date('Y') ?> Abdullahi Tijani - <a href="https://github.com/abdullahitijani/" target="_blank" rel="noopener noreferrer" style="color: #0059b3;">GitHub</a>
</footer>
</body>
</html>
