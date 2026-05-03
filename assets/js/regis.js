var MAX_ATTEMPTS = 3;
var COOLDOWN_MS  = 60000;
var cooldownTick = null;
var fields = ['f_name','l_name','stdnum','cys','sex','username','email','password'];

function previewImage(input) {
    document.getElementById('fileName').innerHTML = input.files[0].name;
    document.getElementById('imagePreview').src = URL.createObjectURL(input.files[0]);
}

// CYS auto-uppercase
document.getElementById('cys').addEventListener('input', function() {
    var pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});

// Student number: digits only
document.getElementById('stdnum').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 9);
});

// Password strength
document.getElementById('password').addEventListener('input', function() {
    var pw = this.value;
    var score = 0;
    var colors = ['#dc3545','#fd7e14','#ffc107','#20c997','#28a745'];
    var labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
    if(pw.length >= 8)          score++;
    if(/[A-Z]/.test(pw))        score++;
    if(/[a-z]/.test(pw))        score++;
    if(/[0-9]/.test(pw))        score++;
    if(/[^A-Za-z0-9]/.test(pw)) score++;

    var bar   = document.getElementById('pwBar');
    var label = document.getElementById('pwLabel');
    if(pw.length === 0) {
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
    var el  = document.getElementById(id);
    var val = el.value.trim();
    var ok  = true;

    if(id === 'stdnum')        ok = /^\d{9}$/.test(val);
    else if(id === 'email')    ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    else if(id === 'password') ok = isStrongPassword(el.value);
    else                       ok = val !== '';

    el.classList.toggle('is-ok',  ok);
    el.classList.toggle('is-bad', !ok);
    document.getElementById('err-' + id).classList.toggle('show', !ok);
    return ok;
}

// Attach blur/change listeners to all fields
for(var i = 0; i < fields.length; i++) {
    (function(id) {
        var el = document.getElementById(id);
        var evt = (el.tagName === 'SELECT') ? 'change' : 'input';
        el.addEventListener('blur', function() { validateField(id); });
        el.addEventListener(evt, function() {
            if(el.classList.contains('is-bad') || el.classList.contains('is-ok')) {
                validateField(id);
            }
        });
    })(fields[i]);
}

// Attempt tracking
function getAttempts() {
    try { return JSON.parse(sessionStorage.getItem('regis_att') || '{"count":0,"until":0}'); }
    catch(e) { return {count:0, until:0}; }
}
function saveAttempts(d) { sessionStorage.setItem('regis_att', JSON.stringify(d)); }

function startCooldown(until) {
    var box = document.getElementById('cooldownBox');
    var ticker = document.getElementById('countdownText');
    document.getElementById('submitBtn').disabled = true;
    box.classList.add('show');
    if(cooldownTick) clearInterval(cooldownTick);
    cooldownTick = setInterval(function() {
        var left = Math.ceil((until - Date.now()) / 1000);
        if(left <= 0) {
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
(function() {
    var d = getAttempts();
    if(d.until > Date.now()) startCooldown(d.until);
})();

document.getElementById('regisForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var d = getAttempts();
    if(d.until > Date.now()) { startCooldown(d.until); return; }

    var allOk = true;
    for(var i = 0; i < fields.length; i++) {
        if(!validateField(fields[i])) allOk = false;
    }

    if(!allOk) {
        // Only count attempt if password is the issue
        if(!isStrongPassword(document.getElementById('password').value)) {
            d.count++;
            var warn = document.getElementById('attemptWarn');
            var left = MAX_ATTEMPTS - d.count;
            if(d.count >= MAX_ATTEMPTS) {
                d.until = Date.now() + COOLDOWN_MS;
                saveAttempts(d);
                startCooldown(d.until);
                warn.classList.remove('show');
            } else {
                saveAttempts(d);
                warn.textContent = '⚠️ ' + left + ' attempt' + (left !== 1 ? 's' : '') + ' remaining before cooldown.';
                warn.classList.add('show');
            }
        }
        return;
    }

    d.count = 0; d.until = 0; saveAttempts(d);
    this.submit();
});