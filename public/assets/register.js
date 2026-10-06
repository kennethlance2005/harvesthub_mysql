// register.js — "Create an Account" form: submits a pending request

const form = document.getElementById('register-form');
const alertEl = document.getElementById('register-alert');
const successEl = document.getElementById('register-success');
const roleSelect = document.getElementById('role');
const shiftField = document.getElementById('shift-field');
const shiftSelect = document.getElementById('shift');

const firstNameInput = document.getElementById('first-name');
const lastNameInput = document.getElementById('last-name');
const emailInput = document.getElementById('email');
const emailReqs = document.getElementById('email-reqs');
const emailReq = document.getElementById('email-req');
const ageInput = document.getElementById('age');
const ageReqs = document.getElementById('age-reqs');
const ageReq = document.getElementById('age-req');

const updateAgeRequirement = () => {
  const digits = ageInput.value.replace(/\D/g, '');
  ageInput.value = digits.slice(0, 2);
  const isValidAge = Number(ageInput.value) >= 18;
  ageReq.classList.toggle('valid', isValidAge);
  ageReq.classList.toggle('invalid', !isValidAge);
};

ageInput.addEventListener('input', updateAgeRequirement);
ageInput.addEventListener('focus', () => ageReqs.classList.add('active'));
ageInput.addEventListener('blur', () => ageReqs.classList.remove('active'));

const updateEmailRequirement = () => {
  const isValidEmail = emailInput.value.trim() !== '' && emailInput.validity.valid;
  emailReq.classList.toggle('valid', isValidEmail);
  emailReq.classList.toggle('invalid', !isValidEmail);
};

emailInput.addEventListener('input', updateEmailRequirement);
emailInput.addEventListener('focus', () => emailReqs.classList.add('active'));
emailInput.addEventListener('blur', () => emailReqs.classList.remove('active'));

// Prevent typing numbers or special symbols into name fields
[firstNameInput, lastNameInput].forEach((input) => {
  input.addEventListener('input', () => {
    input.value = input.value.replace(/[^A-Za-z\s\-']/g, '');
  });
});

roleSelect.addEventListener('change', () => {
  const isCoordinator = roleSelect.value === 'staff';
  
  // Toggle the entire shift row visibility
  if (shiftField) shiftField.hidden = !isCoordinator;
  
  // Toggle Required Status so the form can still submit when hidden
  if (shiftSelect) shiftSelect.required = isCoordinator;
});

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.hidden = true;
  successEl.hidden = true;

  const firstName = firstNameInput.value.trim();
  const lastName = lastNameInput.value.trim();
  const ageVal = ageInput.value.trim();
  const age = parseInt(ageVal, 10);
  const location = document.getElementById('location').value;
  const email = emailInput.value.trim();
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirm-password').value;
  const role = roleSelect.value;
  const shift = shiftSelect ? shiftSelect.value : 'Morning';

  const namePattern = /^[A-Za-z\s\-']+$/;
  if (!firstName || !namePattern.test(firstName)) {
    alertEl.textContent = 'First name must contain letters only.';
    alertEl.hidden = false;
    firstNameInput.focus();
    return;
  }

  if (!lastName || !namePattern.test(lastName)) {
    alertEl.textContent = 'Last name must contain letters only.';
    alertEl.hidden = false;
    lastNameInput.focus();
    return;
  }

  if (!ageVal || isNaN(age) || age < 18 || age > 120) {
    alertEl.textContent = 'You must be at least 18 years old to create an account.';
    alertEl.hidden = false;
    ageInput.focus();
    return;
  }

  if (!location) {
    alertEl.textContent = 'Please select your city.';
    alertEl.hidden = false;
    document.getElementById('location').focus();
    return;
  }

  if (!email || !emailInput.validity.valid) {
    alertEl.textContent = 'Please enter a valid email address.';
    alertEl.hidden = false;
    emailInput.focus();
    return;
  }

  if (!['customer', 'staff'].includes(role)) {
    alertEl.textContent = 'Please select a role.';
    alertEl.hidden = false;
    roleSelect.focus();
    return;
  }

  if (password.length < 6) {
    alertEl.textContent = 'Password must be at least 6 characters.';
    alertEl.hidden = false;
    return;
  }

  if (password !== confirmPassword) {
    alertEl.textContent = 'Passwords do not match.';
    alertEl.hidden = false;
    return;
  }

  const formData = new URLSearchParams({
    action: 'signup_request',
    first_name: firstName,
    last_name: lastName,
    age: age,
    location: location,
    email: email,
    password: password,
    confirm_password: confirmPassword,
    role: role,
    shift: role === 'staff' ? shift : 'Morning',
  });

  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData,
    });
    const data = await res.json();

    if (data.ok) {
      form.reset();
      form.hidden = true;
      successEl.textContent = "Request sent! An administrator will review your account request, and you'll be able to log in once it's approved.";
      successEl.hidden = false;
    } else {
      alertEl.textContent = (data.errors || [data.error]).filter(Boolean).join(' ') || 'Could not submit request.';
      alertEl.hidden = false;
      submitBtn.disabled = false;
    }
  } catch (err) {
    alertEl.textContent = 'Network error. Please try again.';
    alertEl.hidden = false;
    submitBtn.disabled = false;
  }
});

document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const reqList = document.getElementById('password-reqs');
    
    if (passwordInput && reqList) {
        // 1. Show the checklist when the user clicks the password field
        passwordInput.addEventListener('focus', () => {
            reqList.classList.add('active');
        });

        // 2. Hide the checklist when they click away (only if empty or fully valid)
        passwordInput.addEventListener('blur', () => {
            const isValid = document.querySelectorAll('.password-reqs li.valid').length === 5;
            if (passwordInput.value === '' || isValid) {
                reqList.classList.remove('active');
            }
        });

        // 3. The live validation checker
        passwordInput.addEventListener('input', function() {
            const val = this.value;
            
            const toggleValid = (id, isValid) => {
                const el = document.getElementById(id);
                if (isValid) {
                    el.classList.add('valid');
                    el.classList.remove('invalid');
                } else {
                    el.classList.add('invalid');
                    el.classList.remove('valid');
                }
            };

            toggleValid('req-length', val.length >= 8);
            toggleValid('req-upper', /[A-Z]/.test(val));
            toggleValid('req-lower', /[a-z]/.test(val));
            toggleValid('req-num', /\d/.test(val));
            toggleValid('req-special', /[\W_]/.test(val));
        });
    }
});

// Prevent copy, cut, and paste in password fields
document.addEventListener('DOMContentLoaded', () => {
    const passwordFields = ['password', 'confirm-password'];
    
    passwordFields.forEach(id => {
        const inputElement = document.getElementById(id);
        if (inputElement) {
            inputElement.addEventListener('copy', (e) => e.preventDefault());
            inputElement.addEventListener('cut', (e) => e.preventDefault());
            inputElement.addEventListener('paste', (e) => e.preventDefault());
        }
    });
});