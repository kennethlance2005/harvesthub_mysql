// login.js — AJAX login

const alertEl = document.getElementById('login-alert');

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.hidden = true;

  const formData = new URLSearchParams({
    action: 'login',
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value,
    remember: document.getElementById('remember').checked ? '1' : '0'
  });

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData,
    });
    const data = await res.json();

    if (data.ok) {
      window.location.href = data.redirect;
    } else {
      alertEl.textContent = data.error || 'Login failed.';
      alertEl.hidden = false;
    }
  } catch (err) {
    alertEl.textContent = 'Network error. Please try again.';
    alertEl.hidden = false;
  }
});