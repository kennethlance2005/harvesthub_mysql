<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Forgot Password</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="login-brand">
      <span class="sprout">🌱</span>
      <h1>HarvestHub</h1>
    </div>
    <h2 class="login-title">Reset Password</h2>
    
    <div id="success-message" hidden>
        <p style="color: #2b7a4b; font-weight: 500; text-align: center;">If an account exists for that email, a reset link has been sent.</p>
        <p class="signup-hint" style="margin-top: 15px;"><a href="login.php" class="signup-link">Back to Login</a></p>
    </div>

    <form id="forgot-form" novalidate>
      <div class="field field-underline">
        <label for="email">Enter your email address</label>
        <input type="email" id="email" name="email" required>
      </div>
      <button type="submit" class="btn btn-light btn-block">Send Reset Link</button>
      <p class="form-alert" id="forgot-alert" role="alert" hidden></p>
      
      <p class="signup-hint" style="margin-top: 20px;">
        <a href="login.php" class="signup-link">Back to Login</a>
      </p>
    </form>
  </div>
</div>

<script>
document.getElementById('forgot-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertEl = document.getElementById('forgot-alert');
    const btn = e.target.querySelector('button');
    
    alertEl.hidden = true;
    btn.textContent = 'Sending...';
    btn.disabled = true;

    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'forgot_password',
                email: document.getElementById('email').value.trim()
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            document.getElementById('forgot-form').hidden = true;
            document.getElementById('success-message').hidden = false;
        } else {
            alertEl.textContent = data.error;
            alertEl.hidden = false;
            btn.textContent = 'Send Reset Link';
            btn.disabled = false;
        }
    } catch (err) {
        alertEl.textContent = 'Network error. Please try again.';
        alertEl.hidden = false;
        btn.textContent = 'Send Reset Link';
        btn.disabled = false;
    }
});
</script>
</body>
</html>