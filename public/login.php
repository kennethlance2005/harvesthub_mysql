<?php
require_once __DIR__ . '/auth.php';
if ($user = currentUser()) {
    header('Location: ' . loginRedirectFor($user['role']));
    exit;
}

// Retrieve the saved email if it exists
$savedEmail = $_COOKIE['remembered_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Log In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">

    <div class="login-brand">
      <span class="sprout">🌱</span>
      <h1>HarvestHub</h1>
    </div>

    <h2 class="login-title">Login</h2>

    <form id="login-form" novalidate>
      <div class="field field-underline">
        <label for="email">Email</label>
        <!-- Inject the saved email here -->
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($savedEmail, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>

      <div class="field field-underline">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <div class="login-row">
        <label class="remember-me">
          <!-- Automatically check the box if we have a saved email -->
          <input type="checkbox" id="remember" <?= $savedEmail !== '' ? 'checked' : '' ?>>
          Remember Me
        </label>
        <a href="forgot_password.php" class="forgot-link">Forgot Password</a>
      </div>

      <button type="submit" class="btn btn-light btn-block">Log in</button>
      <p class="form-alert" id="login-alert" role="alert" hidden></p>
    </form>

    <p class="demo-hint" id="demo-hint">
      Demo account: <code>maria@harvesthub.test</code> / <code>demo1234</code>
    </p>

    <p class="signup-hint">
      New to HarvestHub? <a href="register.php" class="signup-link">Create an Account</a>
    </p>
  </div>
</div>

<script src="assets/login.js?v=4"></script>
</body>
</html>