<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: login.php');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!is_string($password)) {
            $password = '';
        }

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $error = 'Username must be 3–50 characters and use only letters, numbers, and underscores.';
        } elseif ($password === '' || strlen($password) > 72) {
            $error = 'Password must be between 8 and 72 bytes.';
        } elseif ($action === 'register' && strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($action === 'register') {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'That username is already taken.';
                } else {
                    error_log('Registration failed: ' . $e->getMessage());
                    $error = 'Registration could not be completed. Please try again later.';
                }
            }
        } elseif ($action === 'login') {
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } else {
            $error = 'Invalid request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account — Retro Bulletin Board</title>
    <style>
        body { font-family: monospace; background: #ececec; color: #111; margin: 20px; }
        form { background: #fff; padding: 15px; border: 1px solid #aaa; max-width: 420px; margin-bottom: 20px; }
        label { display: block; margin-top: 8px; font-weight: bold; font-size: 12px; }
        input { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; font-family: monospace; border: 1px solid #888; }
        button { margin-top: 10px; padding: 6px 14px; font-family: monospace; background: #ddd; border: 1px solid #666; cursor: pointer; }
        .error { color: #c00; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Bulletin Board Account</h1>
    <?php if ($error !== ''): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
        <p>Signed in as <?= h($_SESSION['username']) ?>.</p>
        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit">Log Out</button>
        </form>
        <p><a href="index.php">Return to the board</a></p>
    <?php else: ?>
        <form action="login.php" method="POST">
            <h2>Log In</h2>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="login">
            <label for="login-username">Username</label>
            <input id="login-username" type="text" name="username" maxlength="50" autocomplete="username" required>
            <label for="login-password">Password</label>
            <input id="login-password" type="password" name="password" autocomplete="current-password" required>
            <button type="submit">Log In</button>
        </form>

        <form action="login.php" method="POST">
            <h2>Create an Account</h2>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="register">
            <label for="register-username">Username</label>
            <input id="register-username" type="text" name="username" maxlength="50" autocomplete="username" required>
            <label for="register-password">Password (at least 8 characters)</label>
            <input id="register-password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <button type="submit">Register</button>
        </form>
    <?php endif; ?>
</body>
</html>
