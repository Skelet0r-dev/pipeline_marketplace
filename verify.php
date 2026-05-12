<?php
session_start();
$email = $_GET['email'] ?? $_SESSION['verify_email'] ?? '';
$type  = $_GET['type'] ?? $_SESSION['verify_type'] ?? '';
$error = $_GET['error'] ?? '';
$debug = $_GET['debug'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account | Pipeline</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-600: #087832;
            --green-700: #0f5b3b;
            --green-50: #f0faf4;
            --text-dark: #111827;
            --text-soft: #6b7280;
            --white: #ffffff;
            --border: #e5e7eb;
            --radius-lg: 24px;
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f9fafb;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .verify-card {
            background: var(--white);
            padding: 48px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 440px;
            width: 100%;
            text-align: center;
        }

        .verify-icon {
            font-size: 48px;
            margin-bottom: 24px;
            display: inline-block;
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        p {
            color: var(--text-soft);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .otp-input-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 32px;
        }

        .otp-input {
            width: 50px;
            height: 60px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            color: var(--green-600);
            transition: all 0.2s ease;
        }

        .otp-input:focus {
            border-color: var(--green-600);
            outline: none;
            box-shadow: 0 0 0 4px var(--green-50);
        }

        .btn-verify {
            background: var(--green-600);
            color: var(--white);
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.2s ease;
            margin-bottom: 24px;
        }

        .btn-verify:hover {
            background: var(--green-700);
            transform: translateY(-1px);
        }

        .resend-link {
            color: var(--green-600);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #dc3545;
            font-size: 14px;
            margin-top: -16px;
            margin-bottom: 16px;
            display: none;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <div class="verify-card">
        <span class="verify-icon"><i class="bi bi-envelope"></i></span>
        <h1>Verify It's You</h1>
        <p>We've sent a 6-digit verification code to <br><strong><?php echo htmlspecialchars($email ?: 'your email'); ?></strong></p>

        <form id="verifyForm" action="verify_otp.php" method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
            
            <div class="otp-input-group">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
            </div>

            <div id="errorMessage" class="error-message">Invalid or expired code. Please try again.</div>

            <input type="hidden" name="code" id="codeHidden">
            <button type="submit" class="btn-verify">Verify Now</button>
        </form>

        <div class="mt-4">
            <span class="text-muted small">Didn't receive a code?</span><br>
            <a href="#" class="resend-link" id="resendBtn">Resend Verification Code</a>
        </div>
    </div>

    <script>
        // Data from PHP
        const email = <?php echo json_encode($email); ?>;
        const type = <?php echo json_encode($type); ?>;

        // Handle OTP Input Auto-focus
        const inputs = document.querySelectorAll('.otp-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
        });

        // Handle Form Submission
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            let code = '';
            inputs.forEach(input => code += input.value);
            document.getElementById('codeHidden').value = code;

            if (!email) {
                e.preventDefault();
                alert('Verification session lost. Please try logging in again.');
                window.location.href = 'login.html';
            }
        });

        // Resend Logic
        document.getElementById('resendBtn').addEventListener('click', function(e) {
            e.preventDefault();
            const btn = this;
            if (btn.classList.contains('disabled')) return;

            btn.classList.add('disabled');
            btn.textContent = 'Sending...';

            const formData = new FormData();
            formData.append('email', email);
            formData.append('type', type);

            fetch('resend_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('A new code has been sent to your email.');
                } else {
                    alert('Error: ' + (data.error || 'Failed to resend code.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            })
            .finally(() => {
                btn.classList.remove('disabled');
                btn.textContent = 'Resend Verification Code';
            });
        });
    </script>
</body>
</html>
