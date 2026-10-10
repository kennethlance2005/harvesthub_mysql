<?php
require_once __DIR__ . '/auth.php';
if ($user = currentUser()) {
    header('Location: ' . loginRedirectFor($user['role']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Create an Account</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=44">
</head>
<body>
<div class="login-shell">
  <div class="login-card register-card">

    <div class="login-brand">
      <span class="sprout">🌱</span>
      <h1>HarvestHub</h1>
    </div>

    <a class="login-home-link" href="index.php"><span aria-hidden="true">←</span> Back to home</a>

    <h2 class="login-title">Create an Account</h2>

    <form id="register-form" novalidate>

      <!-- Row 1: Email -->
      <div class="field-row field-row-single">
        <div class="field">
          <label for="email">Email Address <span class="required">*</span></label>
          <input type="email" id="email" name="email" autocomplete="email" aria-describedby="email-reqs" required>
          <ul id="email-reqs" class="password-reqs" aria-live="polite">
            <li id="email-req" class="invalid">Enter a valid email address</li>
          </ul>
        </div>
      </div>

      <!-- Row 2: Identity -->
      <div class="field-row">
        <div class="field">
          <label for="first-name">First Name <span class="required">*</span></label>
          <input type="text" id="first-name" name="first_name" autocomplete="given-name" pattern="[A-Za-z\s\-']+" title="Letters only" required maxlength="25">
        </div>
        <div class="field">
          <label for="last-name">Last Name <span class="required">*</span></label>
          <input type="text" id="last-name" name="last_name" autocomplete="family-name" pattern="[A-Za-z\s\-']+" title="Letters only" required maxlength="25">
        </div>
      </div>

      <!-- Row 3: Age & Location -->
      <div class="field-row">
        <div class="field">
          <label for="age">Age <span class="required">*</span></label>
          <input type="text" id="age" name="age" inputmode="numeric" pattern="[0-9]{1,2}" maxlength="2" autocomplete="off" aria-describedby="age-reqs" required>
          <ul id="age-reqs" class="password-reqs" aria-live="polite">
            <li id="age-req" class="invalid">At least 18 years old</li>
          </ul>
        </div>
        <div class="field">
          <label for="location">Location <span class="required">*</span></label>
          <select id="location" name="location" autocomplete="address-level2" required>
            <option value="" disabled selected style="background: #1e3a2b; color: #fff;">Select your city&hellip;</option>
            <option value="Caloocan" style="background: #1e3a2b; color: #fff;">Caloocan</option>
            <option value="Las Piñas" style="background: #1e3a2b; color: #fff;">Las Piñas</option>
            <option value="Makati" style="background: #1e3a2b; color: #fff;">Makati</option>
            <option value="Malabon" style="background: #1e3a2b; color: #fff;">Malabon</option>
            <option value="Mandaluyong" style="background: #1e3a2b; color: #fff;">Mandaluyong</option>
            <option value="Manila" style="background: #1e3a2b; color: #fff;">Manila</option>
            <option value="Marikina" style="background: #1e3a2b; color: #fff;">Marikina</option>
            <option value="Muntinlupa" style="background: #1e3a2b; color: #fff;">Muntinlupa</option>
            <option value="Navotas" style="background: #1e3a2b; color: #fff;">Navotas</option>
            <option value="Parañaque" style="background: #1e3a2b; color: #fff;">Parañaque</option>
            <option value="Pasay" style="background: #1e3a2b; color: #fff;">Pasay</option>
            <option value="Pasig" style="background: #1e3a2b; color: #fff;">Pasig</option>
            <option value="Pateros" style="background: #1e3a2b; color: #fff;">Pateros</option>
            <option value="Quezon City" style="background: #1e3a2b; color: #fff;">Quezon City</option>
            <option value="San Juan" style="background: #1e3a2b; color: #fff;">San Juan</option>
            <option value="Taguig" style="background: #1e3a2b; color: #fff;">Taguig</option>
            <option value="Valenzuela" style="background: #1e3a2b; color: #fff;">Valenzuela</option>
          </select>
        </div>
      </div>

      <!-- Row 4: Security -->
      <div class="field-row">
        <div class="field">
          <label for="password">Password <span class="required">*</span></label>
          <input type="password" id="password" name="password" autocomplete="new-password" required>
          
          <!-- Live Password Checklist -->
          <ul id="password-reqs" class="password-reqs">
            <li id="req-length" class="invalid">At least 8 characters</li>
            <li id="req-upper" class="invalid">At least 1 uppercase letter</li>
            <li id="req-lower" class="invalid">At least 1 lowercase letter</li>
            <li id="req-num" class="invalid">At least 1 number</li>
            <li id="req-special" class="invalid">At least 1 special character</li>
          </ul>
        </div>
        <div class="field">
          <label for="confirm-password">Confirm Password <span class="required">*</span></label>
          <input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" minlength="8" required>
        </div>
      </div>

      <!-- Terms of Service: required before the request can be sent -->
      <label class="terms-check" for="accept-terms">
        <input type="checkbox" id="accept-terms" name="accept_terms" value="1" required aria-required="true">
        <span>I have read and agree to the <a href="terms.php" target="_blank" rel="noopener" class="terms-link">Terms of Service</a> <span class="required-mark" aria-hidden="true">*</span></span>
      </label>

      <button type="submit" id="register-submit" class="btn btn-light btn-block" style="margin-top: 4px;" disabled>Request Account</button>
      <p class="register-hint" id="register-hint">Fill in all fields and agree to the Terms of Service to request an account.</p>
    </form>

    <form id="verify-email-form" hidden novalidate>
      <p class="register-hint">Enter the six-digit code sent to <strong id="verification-email"></strong>. The code expires in 10 minutes.</p>
      <div class="field field-underline">
        <label for="verification-code">Email verification code <span class="required">*</span></label>
        <input type="text" id="verification-code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
      </div>
      <button type="submit" class="btn btn-light btn-block">Verify Email and Request Account</button>
      <button type="button" id="resend-code" class="btn btn-light btn-block" style="margin-top: 8px;">Resend Code</button>
      <button type="button" id="restart-registration" class="btn btn-light btn-block" style="margin-top: 8px;">Change Details</button>
    </form>

    <p class="form-alert" id="register-alert" role="alert" hidden></p>
    <p class="form-success" id="register-success" role="status" hidden></p>

    <p class="signup-hint">
      Already have an account? <a href="login.php" class="signup-link">Back to Log In</a>
    </p>
  </div>
</div>

<script src="assets/password-toggle.js?v=1"></script>
<script src="assets/register.js?v=8"></script>
</body>
</html>