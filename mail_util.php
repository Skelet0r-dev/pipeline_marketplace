<?php
require_once __DIR__ . '/mail_config.php';

/**
 * Sends an email using Mailjet API v3.1
 * 
 * @param string $toEmail   Recipient's email address
 * @param string $toName    Recipient's name
 * @param string $subject   Email subject
 * @param string $textPart  Plain text version of the message
 * @param string $htmlPart  HTML version of the message
 * @return array            Status of the operation
 */
function sendEmail($toEmail, $toName, $subject, $textPart, $htmlPart) {
    $url = 'https://api.mailjet.com/v3.1/send';

    $data = [
        'Messages' => [
            [
                'From' => [
                    'Email' => MAILJET_SENDER_EMAIL,
                    'Name'  => MAILJET_SENDER_NAME
                ],
                'To' => [
                    [
                        'Email' => $toEmail,
                        'Name'  => $toName
                    ]
                ],
                'Subject'  => $subject,
                'TextPart' => $textPart,
                'HTMLPart' => $htmlPart
            ]
        ]
    ];

    $payload = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, MAILJET_API_KEY . ":" . MAILJET_API_SECRET);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error, 'code' => 0];
    }
    
    curl_close($ch);

    return [
        'success'  => ($httpCode >= 200 && $httpCode < 300),
        'response' => json_decode($response, true),
        'code'     => $httpCode
    ];
}

/**
 * Generates and stores a verification code in the database
 */
function generateVerificationCode($conn, $email, $type) {
    $code = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // Remove old codes for this email and type
    $sql_del = "DELETE FROM USER_VERIFICATION WHERE EMAIL = ? AND TYPE = ?";
    db_query($conn, $sql_del, [$email, $type]);
    
    // Insert new code
    $sql_ins = "INSERT INTO USER_VERIFICATION (EMAIL, CODE, TYPE, EXPIRES_AT) VALUES (?, ?, ?, ?)";
    db_query($conn, $sql_ins, [$email, $code, $type, $expires]);
    
    return $code;
}

/**
 * Verifies a code against the database
 */
function verifyCode($conn, $email, $code, $type) {
    $sql = "SELECT * FROM USER_VERIFICATION 
            WHERE LOWER(TRIM(EMAIL)) = LOWER(TRIM(?)) 
              AND TRIM(CODE) = TRIM(?) 
              AND TYPE = ? 
              AND EXPIRES_AT > ? 
            LIMIT 1";
    $result = db_query($conn, $sql, [$email, $code, $type, date('Y-m-d H:i:s')]);
    $row = db_fetch_assoc($result);
    
    if ($row) {
        // Code is valid, delete it so it can't be used again
        $sql_del = "DELETE FROM USER_VERIFICATION WHERE VERIFY_ID = ?";
        db_query($conn, $sql_del, [$row['VERIFY_ID']]);
        return true;
    }
    
    return false;
}
?>
