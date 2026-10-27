<?php
require_once 'db.php';

$error = '';

// Handle new thread submission (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title  = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $body   = trim($_POST['body'] ?? '');

    if ($title === '' || $author === '' || $body === '') {
        $error = 'All fields are required.';
    } else {
        try {
            // Use a transaction to ensure both thread and initial post succeed together
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO threads (title, author, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$title, $author]);
            $threadId = $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO posts (thread_id, author, body, created_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$threadId, $author, $body]);

            $pdo->commit();

            // PRG Pattern: Redirect to prevent duplicate posts on page refresh
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while saving your post.';
        }
    }
}

// Fetch all threads with reply counts
$stmt = $pdo->query('
    SELECT t.*, COUNT(p.id) as total_posts 
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
    <title>Retro Bulletin Board</title>
    <style>
        body { font-family: monospace; background: #ececec; color: #111; margin: 20px; }
        h1, h3 { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #aaa; margin-bottom: 20px; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; text-align: left; font-size: 13px; }
        th { background: #dcdcdc; }
        form { background: #fff; padding: 15px; border: 1px solid #aaa; max-width: 600px; }
        label { display: block; margin-top: 8px; font-weight: bold; font-size: 12px; }
        input[type="text"], textarea { width: 100%; padding: 6px; margin-top: 4px; box-sizing: border-box; font-family: monospace; border: 1px solid #888; }
        button { margin-top: 10px; padding: 6px 14px; font-family: monospace; background: #ddd; border: 1px solid #666; cursor: pointer; }
        button:hover { background: #ccc; }
        .error { color: #c00; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>

    <h1>Bulletin Board</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Topic</th>
                <th>Author</th>
                <th>Replies</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($threads)): ?>
                <tr><td colspan="4">No threads found. Start the first conversation below!</td></tr>
            <?php else: ?>
                <?php foreach ($threads as $thread): ?>
                    <tr>
                        <td><a href="thread.php?id=<?= $thread['id'] ?>"><?= htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><?= htmlspecialchars($thread['author'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= max(0, $thread['total_posts'] - 1) ?></td>
                        <td><?= htmlspecialchars($thread['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <form action="index.php" method="POST">
        <h3>Start a New Thread</h3>
        
        <label>Your Handle:</label>
        <input type="text" name="author" maxlength="100" required>
        
        <label>Thread Title:</label>
        <input type="text" name="title" maxlength="255" required>
        
        <label>Message Body:</label>
        <textarea name="body" rows="5" required></textarea>
        
        <button type="submit">Post Thread</button>
    </form>

</body>
</html>
