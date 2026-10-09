<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
if ($user) {
    header('Location: ' . loginRedirectFor($user['role']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="HarvestHub brings community gardeners together.">
  <title>HarvestHub — Growing Together</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=24">
  <style>
    .home-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--cream-100);
    }
    .home-header {
      width: 100%;
      padding: 22px 24px;
      border-bottom: 1px solid var(--line);
      background: var(--white);
    }
    .home-header-inner {
      max-width: var(--max);
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .home-brand {
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: 1.4rem;
      font-weight: 700;
      text-decoration: none;
    }
    .home-brand span { margin-right: 8px; }
    .home-nav { display: flex; align-items: center; gap: 10px; }
    .home-nav .btn { padding: 10px 18px; }
    .home-main {
      width: min(100% - 48px, var(--max));
      flex: 1;
      margin: 0 auto;
      display: grid;
      place-items: center;
      padding-top: 72px;
      padding-bottom: 88px;
    }
    .home-hero {
      width: 100%;
      max-width: 780px;
      text-align: center;
    }
    .home-eyebrow {
      margin: 0 0 14px;
      color: var(--brown-700);
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .home-hero h1 {
      margin: 0;
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: clamp(2.8rem, 8vw, 5.4rem);
      font-weight: 600;
      line-height: 1.05;
    }
    .home-intro {
      max-width: 580px;
      margin: 22px auto 30px;
      color: var(--ink-600);
      font-size: clamp(1rem, 2vw, 1.15rem);
    }
    .home-actions {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    .home-footer {
      padding: 18px 24px;
      border-top: 1px solid var(--line);
      color: var(--ink-600);
      font-size: 0.85rem;
      text-align: center;
    }
    @media (max-width: 480px) {
      .home-header { padding: 16px; }
      .home-nav { gap: 6px; }
      .home-nav .btn { padding: 9px 12px; font-size: 0.85rem; }
      .home-main { width: min(100% - 32px, var(--max)); }
    }
  </style>
</head>
<body class="home-page">
  <header class="home-header">
    <div class="home-header-inner">
      <a class="home-brand" href="index.php"><span aria-hidden="true">🌱</span>HarvestHub</a>
      <nav class="home-nav" aria-label="Account">
        <a class="btn btn-ghost" href="login.php">Login</a>
        <a class="btn btn-accent" href="register.php">Register</a>
      </nav>
    </div>
  </header>

  <main class="home-main">
    <section class="home-hero" aria-labelledby="home-title">
      <p class="home-eyebrow">Welcome to HarvestHub</p>
      <h1 id="home-title">Growing stronger gardens together.</h1>
      <p class="home-intro">A community space for gardeners to grow, share, and take part in a thriving local garden.</p>
      <div class="home-actions">
        <a class="btn btn-accent" href="register.php">Create an account</a>
        <a class="btn btn-ghost" href="login.php">Login to HarvestHub</a>
      </div>
    </section>
  </main>

  <footer class="home-footer">
    &copy; <?= date('Y') ?> HarvestHub
  </footer>
</body>
</html>
