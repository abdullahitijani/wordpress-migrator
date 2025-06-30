<?php
session_start();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // For demo, hardcoded credentials; replace with config or DB as needed
    $validUser = 'admin';
    $validPass = 'password1234';

    if ($username === $validUser && $password === $validPass) {
        $_SESSION['logged_in'] = true;
        header('Location: web.php');
        exit;
    } else {
        $errors[] = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Login - WordPress Migrator</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet" />
    <link href="css/footer.css" rel="stylesheet" />
    <style>
        body { font-family: 'Poppins', sans-serif; background: #e6f0ff; color: #003366; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .login-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px #ccc; width: 320px; margin: auto; }
        h1 { margin-top: 0; color: #0059b3; text-align: center; }
        label { display: block; margin-top: 15px; font-weight: 600; }
        input[type=text], input[type=password] { width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
        button { margin-top: 20px; width: 100%; padding: 12px; background: #0059b3; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 1rem; transition: background-color 0.3s ease; }
        button:hover { background: #004080; }
        .error { color: #d93025; margin-top: 10px; font-weight: 600; text-align: center; }
        footer { margin-top: auto; width: 100%; text-align: center; margin: 20px 0; color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (!empty($errors)): ?>
            <div class="error"><?= htmlspecialchars(implode('<br>', $errors)) ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" required autofocus />
            <label for="password">Password</label>
            <input type="password" name="password" id="password" required />
            <button type="submit">Log In</button>
        </form>
    </div>
    <footer>
        &copy; <?= date('Y') ?> Abdullahi Tijani - <a href="https://github.com/abdullahitijani/" target="_blank" rel="noopener noreferrer" style="color: #0059b3;">GitHub</a>
    </footer>
</body>
</html>
