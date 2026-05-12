var MAX_ATTEMPTS = 3;
var COOLDOWN_MS = 60000;
var cooldownTick = null;
var fields = ['f_name', 'l_name', 'std_num', 'college', 'department', 'section', 'sex', 'username', 'email', 'password'];

function previewImage(input) {
    document.getElementById('fileName').innerHTML = input.files[0].name;
    document.getElementById('imagePreview').src = URL.createObjectURL(input.files[0]);
}

// Removed legacy cys listener

// Student number: digits only
document.getElementById('std_num').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 9);
});

// Password strength
document.getElementById('password').addEventListener('input', function () {
    var pw = this.value;
    var score = 0;
    var colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    var bar = document.getElementById('pwBar');
    var label = document.getElementById('pwLabel');
    if (pw.length === 0) {
        bar.style.width = '0%'; bar.style.background = '#ccc';
        label.textContent = ''; label.style.color = '';
    } else {
        bar.style.width = (score * 20) + '%';
        bar.style.background = colors[score - 1];
        label.textContent = labels[score - 1];
        label.style.color = colors[score - 1];
    }
});

function isStrongPassword(pw) {
    return pw.length >= 8 && pw.length <= 16 &&
        /[A-Z]/.test(pw) && /[a-z]/.test(pw) &&
        /[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw);
}

function validateField(id) {
    var el = document.getElementById(id);
    var val = el.value.trim();
    var ok = true;

    if (id === 'std_num') ok = /^\d{9}$/.test(val);
    else if (id === 'email') ok = /^[^\s@]+@dlsud\.edu\.ph$/.test(val);
    else if (id === 'password') ok = isStrongPassword(el.value);
    else ok = val !== '';

    el.classList.toggle('is-valid', ok);
    el.classList.toggle('is-invalid', !ok);

    // If basic validation passed, check if it's already taken (remote check)
    if (ok && (id === 'std_num' || id === 'email' || id === 'username')) {
        checkRemoteAvailability(id, id === 'f_name' || id === 'l_name' ? null : id);
    }

    return ok;
}

function checkRemoteAvailability(id, type) {
    if (!type) return;
    var el = document.getElementById(id);
    var val = el.value.trim();
    if (!val) return;

    fetch('check_availability.php?type=' + type + '&value=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            if (!data.available) {
                el.classList.add('is-invalid');
                el.classList.remove('is-valid');
                var label = (type === 'std_num' ? 'Student number' : (type === 'email' ? 'Email' : 'Username'));
                document.getElementById('err-' + id).textContent = label + ' is already taken.';
            }
        })
        .catch(err => console.error('Availability check error:', err));
}

// Attach blur/change listeners to all fields
for (var i = 0; i < fields.length; i++) {
    (function (id) {
        var el = document.getElementById(id);
        if (!el) return; // Skip if element not found
        var evt = (el.tagName === 'SELECT') ? 'change' : 'input';
        el.addEventListener('blur', function () { validateField(id); });
        el.addEventListener(evt, function () {
            if (el.classList.contains('is-invalid') || el.classList.contains('is-valid')) {
                validateField(id);
            }
        });
    })(fields[i]);
}

// Attempt tracking
function getAttempts() {
    try { return JSON.parse(sessionStorage.getItem('regis_att') || '{"count":0,"until":0}'); }
    catch (e) { return { count: 0, until: 0 }; }
}
function saveAttempts(d) { sessionStorage.setItem('regis_att', JSON.stringify(d)); }

function startCooldown(until) {
    var box = document.getElementById('cooldownBox');
    var ticker = document.getElementById('countdownText');
    document.getElementById('submitBtn').disabled = true;
    box.classList.add('show');
    if (cooldownTick) clearInterval(cooldownTick);
    cooldownTick = setInterval(function () {
        var left = Math.ceil((until - Date.now()) / 1000);
        if (left <= 0) {
            clearInterval(cooldownTick);
            box.classList.remove('show');
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('attemptWarn').classList.remove('show');
            var d = getAttempts(); d.count = 0; d.until = 0; saveAttempts(d);
        } else {
            ticker.textContent = left + 's';
        }
    }, 500);
}

// Resume cooldown on page load
(function () {
    var d = getAttempts();
    if (d.until > Date.now()) startCooldown(d.until);
})();

document.getElementById('regisForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var d = getAttempts();
    if (d.until > Date.now()) { startCooldown(d.until); return; }

    var allOk = true;
    for (var i = 0; i < fields.length; i++) {
        if (!validateField(fields[i])) allOk = false;
    }

    if (!allOk) {
        // Only count attempt if password is the issue
        if (!isStrongPassword(document.getElementById('password').value)) {
            d.count++;
            var warn = document.getElementById('attemptWarn');
            var left = MAX_ATTEMPTS - d.count;
            if (d.count >= MAX_ATTEMPTS) {
                d.until = Date.now() + COOLDOWN_MS;
                saveAttempts(d);
                startCooldown(d.until);
                warn.classList.remove('show');
            } else {
                saveAttempts(d);
                warn.textContent = '<i class="bi bi-exclamation-triangle-fill"></i> ' + left + ' attempt' + (left !== 1 ? 's' : '') + ' remaining before cooldown.';
                warn.classList.add('show');
            }
        }
        return;
    }

    d.count = 0; d.until = 0; saveAttempts(d);

    // AJAX Submission to keep user on "same tab" for errors
    var formData = new FormData(this);
    var submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering...';

    fetch('regis.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => {
            var contentType = response.headers.get('content-type');
            if (contentType && contentType.indexOf('application/json') !== -1) {
                return response.json().then(data => {
                    if (!data.success) {
                        // Clear previous server errors
                        fields.forEach(id => {
                            var el = document.getElementById(id);
                            if (el) el.classList.remove('is-invalid');
                        });

                        data.errors.forEach(err => {
                            var targetId = null;
                            var msg = err;

                            if (err.toLowerCase().includes('student number')) targetId = 'std_num';
                            else if (err.toLowerCase().includes('email')) targetId = 'email';
                            else if (err.toLowerCase().includes('username')) targetId = 'username';
                            else if (err.toLowerCase().includes('password')) targetId = 'password';
                            else if (err.toLowerCase().includes('first name')) targetId = 'f_name';
                            else if (err.toLowerCase().includes('last name')) targetId = 'l_name';
                            else if (err.toLowerCase().includes('sex')) targetId = 'sex';

                            if (targetId) {
                                var el = document.getElementById(targetId);
                                var errDiv = document.getElementById('err-' + targetId);
                                if (el) el.classList.add('is-invalid');
                                if (el) el.classList.remove('is-valid');
                                if (errDiv) errDiv.textContent = msg;
                            } else {
                                alert(msg);
                            }
                        });

                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Register';
                    } else if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                });
            } else {
                // Success or HTML error page - redirect or replace content
                return response.text().then(html => {
                    document.open();
                    document.write(html);
                    document.close();
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register';
        });
});