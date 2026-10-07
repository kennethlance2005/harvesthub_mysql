// login.js — AJAX login

const alertEl = document.getElementById('login-alert');
const loginForm = document.getElementById('login-form');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const submitButton = loginForm.querySelector('button[type="submit"]');

function updateSubmitButton() {
  submitButton.disabled = !emailInput.value.trim() || !passwordInput.value;
}

emailInput.addEventListener('input', updateSubmitButton);
passwordInput.addEventListener('input', updateSubmitButton);
updateSubmitButton();

loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  updateSubmitButton();
  if (submitButton.disabled) return;

  alertEl.hidden = true;

  const formData = new URLSearchParams({
    action: 'login',
    email: emailInput.value.trim(),
    password: passwordInput.value,
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