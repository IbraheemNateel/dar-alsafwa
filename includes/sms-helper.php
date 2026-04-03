<?php
/**
 * دار صفوة - مساعد إرسال الرسائل النصية
 * Dar Safwa - SMS Helper
 *
 * يدعم: Unifonic, Twilio
 * التكوين في: config/sms.php
 */

/**
 * تحويل رقم الهاتف إلى الصيغة الدولية (فلسطين/الأردن: 970)
 * يدعم: 059xxx, 0097059xxx, 97059xxx, +97059xxx
 */
function normalizePhoneNumber(string $phone): string {
    $phone = preg_replace('/\s+/', '', $phone);
    $phone = ltrim($phone, '+0');
    if (preg_match('/^59\d{8}$/', $phone)) {
        return '970' . $phone;
    }
    if (preg_match('/^97059\d{8}$/', $phone)) {
        return $phone;
    }
    if (preg_match('/^9627[789]\d{7}$/', $phone)) {
        return $phone;
    }
    if (preg_match('/^0?5[0-9]\d{8}$/', $phone)) {
        return '970' . ltrim($phone, '0');
    }
    return $phone;
}

/**
 * إرسال رسالة SMS
 * @return bool true عند النجاح، false عند الفشل
 */
function sendSMS(string $phone, string $message): bool {
    $configPath = __DIR__ . '/../config/sms.php';
    if (!file_exists($configPath)) {
        error_log('SMS: ملف التكوين غير موجود');
        return false;
    }
    require_once $configPath;

    if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
        error_log("SMS (معطّل): {$phone}");
        return false;
    }

    $phone = normalizePhoneNumber($phone);
    if (strlen($phone) < 10) {
        error_log("SMS: رقم غير صالح: {$phone}");
        return false;
    }

    $provider = defined('SMS_PROVIDER') ? SMS_PROVIDER : 'unifonic';

    switch ($provider) {
        case 'unifonic':
            return sendSMSUnifonic($phone, $message);
        case 'twilio':
            return sendSMSTwilio($phone, $message);
        default:
            error_log("SMS: بوابة غير معروفة: {$provider}");
            return false;
    }
}

/**
 * إرسال عبر Unifonic
 */
function sendSMSUnifonic(string $phone, string $message): bool {
    $appSid = defined('UNIFONIC_APP_SID') ? UNIFONIC_APP_SID : '';
    $baseUrl = defined('UNIFONIC_BASE_URL') ? UNIFONIC_BASE_URL : 'https://rest.unifonic.com/rest/SMS/messages';

    if (empty($appSid)) {
        error_log('SMS Unifonic: AppSid غير مكوّن');
        return false;
    }

    $data = [
        'AppSid' => $appSid,
        'Recipient' => $phone,
        'Body' => $message
    ];

    $ch = curl_init($baseUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("SMS Unifonic خطأ: {$err}");
        return false;
    }

    $decoded = json_decode($response, true);
    $success = ($httpCode >= 200 && $httpCode < 300);
    if (!$success) {
        error_log("SMS Unifonic فشل (HTTP {$httpCode}): {$response}");
    }
    return $success;
}

/**
 * إرسال عبر Twilio
 */
function sendSMSTwilio(string $phone, string $message): bool {
    $accountSid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $authToken = defined('TWILIO_AUTH_TOKEN') ? TWILIO_AUTH_TOKEN : '';
    $from = defined('TWILIO_PHONE_NUMBER') ? TWILIO_PHONE_NUMBER : '';

    if (empty($accountSid) || empty($authToken) || empty($from)) {
        error_log('SMS Twilio: بيانات الدخول غير مكوّنة');
        return false;
    }

    $to = '+' . ltrim($phone, '+');
    $from = '+' . ltrim($from, '+');

    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
    $data = [
        'To' => $to,
        'From' => $from,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("SMS Twilio خطأ: {$err}");
        return false;
    }

    $success = ($httpCode >= 200 && $httpCode < 300);
    if (!$success) {
        error_log("SMS Twilio فشل (HTTP {$httpCode}): {$response}");
    }
    return $success;
}

/**
 * إرسال SMS متابعة يومية لولي الأمر
 */
function sendFollowupSMS(array $student, string $memFrom, string $memTo, int $memRating, ?string $revFrom, ?string $revTo, int $revRating, int $behaviorRating): bool {
    $reviewPart = ($revFrom && $revTo)
        ? "وراجع من {$revFrom} إلى {$revTo} بتقييم {$revRating} من 5"
        : "ولم يراجع اليوم (0 من 5)";

    $message = "الطالب {$student['full_name']} قد سمع من {$memFrom} إلى {$memTo} حفظ بتقييم {$memRating} من 5، {$reviewPart}، وتقييم السلوك {$behaviorRating} من 10. - دار صفوة";

    return sendSMS($student['guardian_phone'], $message);
}
