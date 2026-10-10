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
  <link rel="stylesheet" href="assets/style.css?v=44">
  <style>
    .home-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background:
        radial-gradient(ellipse at 4% 12%, rgba(124, 143, 110, 0.2), transparent 31rem),
        radial-gradient(ellipse at 96% 88%, rgba(168, 86, 46, 0.08), transparent 28rem),
        linear-gradient(135deg, var(--cream-100), var(--cream-200));
    }
    .home-header { width: 100%; }
    .home-header-inner {
      height: 72px;
    }
    .home-brand {
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .home-nav { display: flex; align-items: center; gap: clamp(20px, 4vw, 44px); }
    .home-nav a {
      color: var(--cream-200);
      font-size: 0.95rem;
      font-weight: 500;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    .home-nav a:hover { color: var(--white); }
    .home-main {
      flex: 1;
      width: min(100% - 56px, 1280px);
      margin: 0 auto;
      padding: 26px 0 52px;
    }
    .home-hero {
      width: 100%;
      min-height: min(690px, calc(100vh - 170px));
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
      align-items: center;
      overflow: hidden;
      position: relative;
      isolation: isolate;
      padding: clamp(40px, 7vw, 88px);
      border: 1px solid rgba(255, 255, 255, 0.8);
      border-radius: 34px;
      background:
        radial-gradient(ellipse at 90% 8%, rgba(124, 143, 110, 0.18), transparent 31%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.92), rgba(246, 243, 236, 0.92));
      box-shadow: 0 28px 80px rgba(22, 40, 28, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    }
    .home-copy { position: relative; z-index: 2; text-align: center; }
    .home-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      margin: 0 0 22px;
      color: var(--green-700);
      font-size: 0.74rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
    }
    .home-eyebrow::before {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--brown-600);
      content: "";
    }
    .home-hero h1 {
      margin: 0;
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: clamp(3rem, 6.4vw, 5.5rem);
      font-weight: 500;
      letter-spacing: -0.065em;
      line-height: 0.99;
    }
    .home-hero h1 span { color: var(--sage-500); }
    .home-intro {
      max-width: 440px;
      margin: 23px auto 30px;
      color: var(--ink-600);
      font-size: clamp(0.96rem, 1.4vw, 1.08rem);
      line-height: 1.75;
    }
    .home-actions { display: flex; justify-content: center; flex-wrap: wrap; gap: 11px; }
    .home-actions .btn {
      min-height: 52px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 25px;
      border-radius: 999px;
      font-size: 0.9rem;
      transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }
    .home-actions .btn:hover { transform: translateY(-2px); }
    .home-actions .btn-accent {
      background: linear-gradient(135deg, var(--green-800), var(--green-900));
      box-shadow: 0 9px 22px rgba(22, 40, 28, 0.18);
    }
    .home-actions .btn-accent:hover {
      background: var(--green-700);
      box-shadow: 0 12px 26px rgba(22, 40, 28, 0.22);
    }
    .home-actions .btn-ghost {
      border-color: rgba(22, 40, 28, 0.16);
      background: rgba(255, 255, 255, 0.58);
      color: var(--green-900);
    }
    .home-actions .btn-ghost:hover { border-color: var(--sage-500); }
    .garden-preview {
      width: min(100%, 560px);
      position: relative;
      margin: 0 auto;
      transform: rotate(1deg);
    }
    .garden-photo {
      width: 100%;
      display: block;
      aspect-ratio: 1.62;
      object-fit: cover;
      object-position: center;
      border: 6px solid rgba(255, 255, 255, 0.82);
      border-radius: 34px 110px 34px 110px;
      box-shadow: 0 26px 54px rgba(22, 40, 28, 0.2);
    }
    .garden-preview::after {
      position: absolute;
      inset: 6px;
      border-radius: 29px 104px 29px 104px;
      background: linear-gradient(0deg, rgba(22, 40, 28, 0.26), transparent 45%);
      content: "";
      pointer-events: none;
    }
    .preview-note {
      position: absolute;
      z-index: 1;
      right: -3%;
      bottom: 9%;
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 12px 16px;
      border: 1px solid rgba(255, 255, 255, 0.76);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.82);
      box-shadow: 0 12px 28px rgba(22, 40, 28, 0.1);
      color: var(--green-800);
      font-size: 0.68rem;
      font-weight: 600;
      backdrop-filter: blur(12px);
    }
    .preview-note span {
      width: 25px;
      height: 25px;
      display: grid;
      place-items: center;
      border-radius: 50%;
      background: var(--green-800);
      color: var(--cream-100);
      font-size: 1rem;
    }
    .home-about {
      max-width: 660px;
      margin: 0 auto;
      padding: 42px 20px 16px;
      color: var(--ink-600);
      text-align: center;
      scroll-margin-top: 28px;
    }
    .home-about h2 {
      margin: 0 0 8px;
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: 1.8rem;
      font-weight: 500;
      letter-spacing: -0.04em;
    }
    .home-about p { margin: 0; font-size: 0.94rem; line-height: 1.8; }
    .home-section {
      position: relative;
      margin-top: clamp(44px, 7vw, 82px);
      scroll-margin-top: 30px;
    }
    .section-heading {
      max-width: 650px;
      margin: 0 auto 30px;
      text-align: center;
    }
    .section-kicker {
      margin: 0 0 10px;
      color: var(--brown-700);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.15em;
      text-transform: uppercase;
    }
    .section-heading h2,
    .connect-copy h2 {
      margin: 0;
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: clamp(2rem, 4vw, 3.1rem);
      font-weight: 500;
      letter-spacing: -0.055em;
      line-height: 1.08;
    }
    .section-heading > p:last-child {
      max-width: 520px;
      margin: 12px auto 0;
      color: var(--ink-600);
      font-size: 0.95rem;
      line-height: 1.75;
    }
    .event-grid,
    .resource-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 18px;
    }
    .event-card,
    .resource-card {
      min-width: 0;
      position: relative;
      overflow: hidden;
      padding: 25px;
      border: 1px solid rgba(255, 255, 255, 0.82);
      border-radius: 24px;
      background: linear-gradient(145deg, rgba(255, 255, 255, 0.82), rgba(246, 243, 236, 0.7));
      box-shadow: 0 14px 38px rgba(22, 40, 28, 0.055);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .event-card:hover,
    .resource-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 44px rgba(22, 40, 28, 0.1);
    }
    .event-card::after {
      width: 132px;
      height: 132px;
      position: absolute;
      top: -60px;
      right: -44px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(124, 143, 110, 0.17), rgba(124, 143, 110, 0));
      content: "";
      pointer-events: none;
    }
    .event-icon {
      width: 46px;
      height: 46px;
      display: grid;
      place-items: center;
      margin-bottom: 20px;
      border: 1px solid rgba(124, 143, 110, 0.2);
      border-radius: 16px 16px 16px 5px;
      background: linear-gradient(145deg, rgba(124, 143, 110, 0.2), rgba(239, 234, 223, 0.72));
      color: var(--green-800);
      font-size: 1.25rem;
    }
    .event-date {
      margin: 0 0 8px;
      color: var(--brown-700);
      font-size: 0.69rem;
      font-weight: 700;
      letter-spacing: 0.09em;
      text-transform: uppercase;
    }
    .event-card h3,
    .resource-card h3 {
      margin: 0;
      color: var(--green-900);
      font-family: var(--font-display);
      font-size: 1.42rem;
      font-weight: 500;
      letter-spacing: -0.035em;
      line-height: 1.2;
    }
    .event-card > p:not(.event-date) {
      min-height: 78px;
      margin: 10px 0 20px;
      color: var(--ink-600);
      font-size: 0.88rem;
      line-height: 1.7;
    }
    .event-link,
    .resource-link {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      color: var(--green-800);
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: none;
    }
    .event-link::after,
    .resource-link::after { content: "↗"; transition: transform 0.2s ease; }
    .event-link:hover::after,
    .resource-link:hover::after { transform: translate(2px, -2px); }
    .connect-panel {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: center;
      gap: 32px;
      overflow: hidden;
      position: relative;
      padding: clamp(30px, 5vw, 56px);
      border: 1px solid rgba(255, 255, 255, 0.22);
      border-radius: 30px;
      background:
        radial-gradient(ellipse at 96% 12%, rgba(124, 143, 110, 0.48), transparent 34%),
        radial-gradient(ellipse at 10% 120%, rgba(168, 86, 46, 0.24), transparent 40%),
        linear-gradient(135deg, var(--green-800), var(--green-900));
      box-shadow: 0 24px 60px rgba(22, 40, 28, 0.15);
    }
    .connect-copy { max-width: 510px; position: relative; z-index: 1; }
    .connect-copy .section-kicker { color: #d8bd9d; }
    .connect-copy h2 { color: var(--cream-100); }
    .connect-copy > p:last-child {
      max-width: 470px;
      margin: 13px 0 0;
      color: rgba(246, 243, 236, 0.8);
      font-size: 0.92rem;
      line-height: 1.75;
    }
    .social-links {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-end;
      gap: 10px;
      position: relative;
      z-index: 1;
    }
    .social-links a {
      min-height: 46px;
      display: inline-flex;
      align-items: center;
      gap: 9px;
      padding: 0 16px;
      border: 1px solid rgba(255, 255, 255, 0.24);
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.09);
      color: var(--cream-100);
      font-size: 0.83rem;
      font-weight: 600;
      text-decoration: none;
      transition: background-color 0.2s ease, transform 0.2s ease;
      backdrop-filter: blur(12px);
    }
    .social-links a:hover { transform: translateY(-2px); background: rgba(255, 255, 255, 0.17); }
    .social-links a span { color: #d8bd9d; font-size: 1rem; }
    .resource-card { min-height: 225px; display: flex; flex-direction: column; align-items: flex-start; }
    .resource-type {
      margin: 0 0 12px;
      color: var(--brown-700);
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }
    .resource-card > p:not(.resource-type) {
      margin: 10px 0 20px;
      color: var(--ink-600);
      font-size: 0.86rem;
      line-height: 1.7;
    }
    .resource-link { margin-top: auto; }
    .home-footer { padding: 24px 0; }
    .home-footer .footer-row { align-items: center; }
    .home-footer .footer-meta { margin-left: auto; }
    .home-footer .footer-meta p { margin: 0; }
    @media (max-width: 820px) {
      .home-main { width: min(100% - 36px, 620px); padding-top: 12px; }
      .home-hero {
        min-height: 0;
        grid-template-columns: 1fr;
        gap: 12px;
        padding: 52px clamp(22px, 7vw, 54px) 18px;
      }
      .home-hero h1 { font-size: clamp(3rem, 10vw, 4.7rem); }
      .garden-preview { width: min(100%, 520px); }
      .preview-note { right: 0; }
      .event-grid,
      .resource-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .connect-panel { grid-template-columns: 1fr; gap: 24px; }
      .social-links { justify-content: flex-start; }
    }
    @media (max-width: 480px) {
      .home-header-inner { height: 68px; }
      .home-nav { gap: 17px; }
      .home-nav a { font-size: 0.82rem; }
      .home-main { width: min(100% - 24px, 620px); }
      .home-hero { border-radius: 25px; padding: 43px 19px 10px; }
      .home-hero h1 { font-size: clamp(2.7rem, 12vw, 3.6rem); }
      .home-intro { margin: 18px auto 24px; }
      .home-actions { flex-direction: column; align-items: stretch; }
      .garden-photo { border-radius: 25px 72px 25px 72px; }
      .garden-preview::after { border-radius: 20px 66px 20px 66px; }
      .preview-note { right: -2%; bottom: 8%; }
      .home-about { padding-top: 32px; }
      .event-grid,
      .resource-grid { grid-template-columns: 1fr; gap: 13px; }
      .event-card,
      .resource-card { padding: 22px; }
      .event-card > p:not(.event-date) { min-height: 0; }
      .connect-panel { border-radius: 24px; padding: 28px 22px; }
      .social-links { gap: 8px; }
      .social-links a { min-height: 42px; padding: 0 13px; }
      .home-section { margin-top: 50px; }
      .home-footer { padding: 22px 0; }
    }
  </style>
</head>
<body class="home-page">
  <header class="site-header home-header">
    <div class="wrap header-row home-header-inner">
      <a class="wordmark home-brand" href="index.php"><span aria-hidden="true">🌱</span>HarvestHub</a>
      <nav class="home-nav" aria-label="Main navigation">
        <a href="index.php">Home</a>
        <a href="#about-us">About Us</a>
      </nav>
    </div>
  </header>

  <main class="home-main">
    <section class="home-hero" aria-labelledby="home-title">
      <div class="home-copy">
        <p class="home-eyebrow">A little more room to grow</p>
        <h1 id="home-title">Growing stronger gardens <span>together.</span></h1>
        <p class="home-intro">A community space for gardeners to grow, share, and take part in a thriving local garden.</p>
        <div class="home-actions">
          <a class="btn btn-accent" href="register.php">Create an account</a>
          <a class="btn btn-ghost" href="login.php">Login</a>
        </div>
      </div>
      <div class="garden-preview">
        <img class="garden-photo" src="assets/community-garden.jpg" alt="Gardeners tending rows of vegetables in a community garden">
        <div class="preview-note"><span>+</span> Growing together</div>
      </div>
    </section>
    <section class="home-about" id="about-us" aria-labelledby="about-title">
      <h2 id="about-title">Rooted in community.</h2>
      <p>HarvestHub brings local gardeners together to share knowledge, nurture green spaces, and help every garden flourish.</p>
    </section>

    <section class="home-section" id="upcoming-events" aria-labelledby="events-title">
      <div class="section-heading">
        <p class="section-kicker">Make a little time to grow</p>
        <h2 id="events-title">Upcoming events</h2>
        <p>Good things happen when we get our hands in the soil together. Come grow with us.</p>
      </div>
      <div class="event-grid">
        <article class="event-card">
          <div class="event-icon" aria-hidden="true">✿</div>
          <p class="event-date">Date &amp; location to be announced</p>
          <h3>Community garden meetup</h3>
          <p>Meet fellow gardeners, swap ideas, and help shape what our growing community does next.</p>
          <a class="event-link" href="register.php">Be part of the community</a>
        </article>
        <article class="event-card">
          <div class="event-icon" aria-hidden="true">✳</div>
          <p class="event-date">Date &amp; location to be announced</p>
          <h3>Planting day</h3>
          <p>Share a morning of planting, learning, and making a welcoming space for the next harvest.</p>
          <a class="event-link" href="register.php">Be part of the community</a>
        </article>
        <article class="event-card">
          <div class="event-icon" aria-hidden="true">❋</div>
          <p class="event-date">Date &amp; location to be announced</p>
          <h3>Seeds &amp; stories swap</h3>
          <p>Bring a favorite growing tip, discover something new, and share the little things that help gardens thrive.</p>
          <a class="event-link" href="register.php">Be part of the community</a>
        </article>
      </div>
    </section>

    <section class="home-section" id="connect" aria-labelledby="connect-title">
      <div class="connect-panel">
        <div class="connect-copy">
          <p class="section-kicker">Let's grow together</p>
          <h2 id="connect-title">Connect with us</h2>
          <p>Our official HarvestHub group links are coming soon. In the meantime, explore and share with the wider gardening community.</p>
        </div>
        <nav class="social-links" aria-label="Social media platforms">
          <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">f</span>Facebook</a>
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">◎</span>Instagram</a>
          <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">▶</span>YouTube</a>
        </nav>
      </div>
    </section>

    <section class="home-section" id="resources" aria-labelledby="resources-title">
      <div class="section-heading">
        <p class="section-kicker">A few good places to start</p>
        <h2 id="resources-title">Resources &amp; inspiration</h2>
        <p>Fresh ideas, practical guidance, and research into the ways community gardens help people flourish.</p>
      </div>
      <div class="resource-grid">
        <article class="resource-card">
          <p class="resource-type">Practical growing</p>
          <h3>Community garden resources</h3>
          <p>Explore a curated library of references and tools recommended by experienced community gardeners.</p>
          <a class="resource-link" href="https://www.communitygarden.org/resources" target="_blank" rel="noopener noreferrer">Visit the resource library</a>
        </article>
        <article class="resource-card">
          <p class="resource-type">Research &amp; impact</p>
          <h3>Gardens as a catalyst for community change</h3>
          <p>Read research on how community gardens can support connection, health, and access to fresh food.</p>
          <a class="resource-link" href="https://pmc.ncbi.nlm.nih.gov/articles/PMC12951643/" target="_blank" rel="noopener noreferrer">Read the research article</a>
        </article>
        <article class="resource-card">
          <p class="resource-type">Meet the network</p>
          <h3>American Community Gardening Association</h3>
          <p>Connect with a wider network dedicated to building community through community gardening.</p>
          <a class="resource-link" href="https://www.communitygarden.org/" target="_blank" rel="noopener noreferrer">Explore the association</a>
        </article>
      </div>
    </section>
  </main>

  <footer class="site-footer home-footer">
    <div class="wrap footer-row">
      <div class="account-footer-brand">
        <a class="wordmark-light" href="index.php">
          <span aria-hidden="true">🌱</span> HarvestHub
        </a>
        <p class="footer-tagline">Growing stronger gardens together.</p>
      </div>
      <div class="footer-meta">
        <p>&copy; <?= date('Y') ?> HarvestHub</p>
      </div>
    </div>
  </footer>
</body>
</html>
