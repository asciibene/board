<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$error = '';

// Posting requires an authenticated account.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: auth.php');
        exit;
    }

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $author = $_SESSION['username'];

        if ($title === '' || $body === '') {
            $error = 'Thread title and message are required.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO threads (title, author, created_at) VALUES (?, ?, NOW())');
                $stmt->execute([$title, $author]);
                $threadId = $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO posts (thread_id, author, body, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$threadId, $author, $body]);

                $pdo->commit();
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Thread submission failed: ' . $e->getMessage());
                $error = 'An error occurred while saving your post.';
            }
        }
    }
}

// Fetch all threads with reply counts.
$stmt = $pdo->query('
    SELECT t.*, COUNT(p.id) AS total_posts
    FROM threads t
    LEFT JOIN posts p ON t.id = p.thread_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
');
$threads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retro Bulletin Board</title>
    <style>
        body { font-family: monospace; background: #ececec; color: #111; margin: 20px; }
        h1, h3 { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #aaa; margin-bottom: 20px; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; text-align: left; font-size: 13px; }
        th { background: #dcdcdc; }
        form.post-form { background: #fff; padding: 15px; border: 1px solid #aaa; max-width: 600px; }
        label { display: block; margin-top: 8px; font-weight: bold; font-size: 12px; }
        input[type="text"], textarea { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; font-family: monospace; border: 1px solid #888; }
        button { margin-top: 10px; padding: 6px 14px; font-family: monospace; background: #ddd; border: 1px solid #666; cursor: pointer; }
        button:hover { background: #ccc; }
        .error { color: #c00; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>Bulletin Board</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <p>Signed in as <?= h($_SESSION['username']) ?>.
            <form action="auth.php" method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Log Out</button>
            </form>
        </p>
    <?php else: ?>
        <p><a href="auth.php">Log in or create an account</a> to start a thread.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr><th>Topic</th><th>Author</th><th>Replies</th><th>Created</th></tr>
        </thead>
        <tbody>
            <?php if (empty($threads)): ?>
                <tr><td colspan="4">No threads found. Start the first conversation below!</td></tr>
            <?php else: ?>
                <?php foreach ($threads as $thread): ?>
                    <tr>
                        <td><a href="thread.php?id=<?= (int) $thread['id'] ?>"><?= h($thread['title']) ?></a></td>
                        <td><?= h($thread['author']) ?></td>
                        <td><?= max(0, (int) $thread['total_posts'] - 1) ?></td>
                        <td><?= h($thread['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (isset($_SESSION['user_id'])): ?>
        <form class="post-form" action="index.php" method="POST">
            <h3>Start a New Thread</h3>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label for="title">Thread Title</label>
            <input id="title" type="text" name="title" maxlength="255" required>
            <label for="body">Message Body</label>
            <textarea id="body" name="body" rows="5" required></textarea>
            <button type="submit">Post Thread</button>
        </form>
    <?php endif; ?>
</body>
</html>
