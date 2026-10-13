<?php
session_start();

// Already logged in? Go straight to admin.
if (!empty($_SESSION['logged_in'])) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username === getenv("MAIL_USERNAME") && $password === getenv("MAIL_PASSWORD")) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $username;
        header('Location: dashboard.php');
        exit;
    }

    // Wrong credentials: stay on this page and show a message
    $error = 'Incorrect username or password. Try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in</title>
<style>
  :root {
    --bg: #eef1f5;
    --panel: #ffffff;
    --ink: #1c2430;
    --muted: #5d6b7d;
    --line: #cfd6df;
    --accent: #1f5fbf;
    --error-bg: #fdecec;
    --error-ink: #9b1c1c;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center;
    background: var(--bg); color: var(--ink);
    font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
  }
  .card {
    width: min(92vw, 380px); background: var(--panel);
    padding: 32px 28px; border-radius: 10px;
    border: 1px solid var(--line);
  }
  h1 { margin: 0 0 4px; font-size: 1.5rem; }
  p.sub { margin: 0 0 22px; color: var(--muted); font-size: .95rem; }
  label { display: block; margin: 14px 0 6px; font-size: .9rem; font-weight: 600; }
  input {
    width: 100%; padding: 11px 12px; font-size: 1rem;
    border: 1px solid var(--line); border-radius: 6px; background: #fff;
  }
  input:focus-visible, button:focus-visible { outline: 3px solid #9ec1f5; outline-offset: 1px; }
  button {
    width: 100%; margin-top: 22px; padding: 12px; font-size: 1rem; font-weight: 600;
    color: #fff; background: var(--accent); border: 0; border-radius: 6px; cursor: pointer;
  }
  button:hover { background: #184c9b; }
  .error {
    margin-bottom: 8px; padding: 10px 12px; border-radius: 6px;
    background: var(--error-bg); color: var(--error-ink); font-size: .92rem;
  }
</style>
</head>
<body>
  <main class="card">
    <h1>Admin sign in</h1>
    <p class="sub">Enter your username and password to continue.</p>

    <?php if ($error): ?>
      <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="off">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autofocus
             value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <button type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>