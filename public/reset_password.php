<?php
// Force PHP to show errors on the screen for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    die("Invalid password reset link. Please request a new one.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Set New Password</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="login-brand">
      <span class="sprout">🌱</span>
      <h1>HarvestHub</h1>
    </div>
    <h2 class="login-title">Set New Password</h2>
    
    <form id="reset-form" novalidate>
      <input type="hidden" id="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      
      <div class="field field-underline">
        <label for="password">New Password</label>
        <input type="password" id="password" autocomplete="new-password" required minlength="8">
        <ul id="reset-password-reqs" class="password-reqs">
          <li data-requirement="length" class="invalid">At least 8 characters</li>
          <li data-requirement="upper" class="invalid">At least 1 uppercase letter</li>
          <li data-requirement="lower" class="invalid">At least 1 lowercase letter</li>
          <li data-requirement="number" class="invalid">At least 1 number</li>
          <li data-requirement="special" class="invalid">At least 1 special character</li>
        </ul>
      </div>
      <button type="submit" class="btn btn-light btn-block">Update Password</button>
      <p class="form-alert" id="reset-alert" role="alert" hidden></p>
    </form>
  </div>
</div>

<script>
const resetForm = document.getElementById('reset-form');
const passwordInput = document.getElementById('password');
const requirementsList = document.getElementById('reset-password-reqs');
const resetAlert = document.getElementById('reset-alert');
const passwordRequirements = [
  ['length', value => value.length >= 8],
  ['upper', value => /[A-Z]/.test(value)],
  ['lower', value => /[a-z]/.test(value)],
  ['number', value => /\d/.test(value)],
  ['special', value => /[\W_]/.test(value)]
];

function updatePasswordRequirements() {
  const value = passwordInput.value;
  passwordRequirements.forEach(([name, test]) => {
    const requirement = requirementsList.querySelector(`[data-requirement="${name}"]`);
    const valid = test(value);
    requirement.classList.toggle('valid', valid);
    requirement.classList.toggle('invalid', !valid);
  });
}

passwordInput.addEventListener('focus', () => requirementsList.classList.add('active'));
passwordInput.addEventListener('input', updatePasswordRequirements);
passwordInput.addEventListener('blur', () => {
  const isValid = passwordRequirements.every(([, test]) => test(passwordInput.value));
  if (passwordInput.value === '' || isValid) requirementsList.classList.remove('active');
});

resetForm.addEventListener('submit', async (e) => {
    e.preventDefault();
  resetAlert.hidden = true;

  const isPasswordValid = passwordRequirements.every(([, test]) => test(passwordInput.value));
  if (!isPasswordValid) {
    updatePasswordRequirements();
    requirementsList.classList.add('active');
    resetAlert.textContent = 'Please meet all password requirements.';
    resetAlert.hidden = false;
    passwordInput.focus();
    return;
  }

    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'reset_password',
                email: document.getElementById('email').value,
                token: document.getElementById('token').value,
                password: document.getElementById('password').value
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            alert('Password successfully updated. You can now log in.');
            window.location.href = 'login.php';
        } else {
          resetAlert.textContent = data.error;
          resetAlert.hidden = false;
        }
    } catch (err) {
        resetAlert.textContent = 'Network error. Please try again.';
        resetAlert.hidden = false;
    }
});
</script>
</body>
</html>