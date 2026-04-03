<?php
/**
 * إعدادات بوابة الرسائل النصية - دار صفوة
 * SMS Gateway Configuration - Dar Safwa
 *
 * لتفعيل SMS: ضع القيم الصحيحة ثم عيّن SMS_ENABLED = true
 * للتعطيل: SMS_ENABLED = false
 */

// تفعيل/تعطيل إرسال SMS
define('SMS_ENABLED', false); // غيّر إلى true عند التكوين

// اختيار البوابة: 'unifonic' | 'twilio' | 'custom'
define('SMS_PROVIDER', 'unifonic');

// رقم المرسل (يُستخدم مع بعض البوابات مثل Twilio
define('SMS_SENDER_ID', '');

// --- Unifonic ---
// احصل على AppSid من لوحة تحكم Unifonic
define('UNIFONIC_APP_SID', '');
// إذا لم يعمل، جرّب: https://api.unifonic.com/rest/SMS/messages
define('UNIFONIC_BASE_URL', 'https://rest.unifonic.com/rest/SMS/messages');

// --- Twilio ---
// من لوحة تحكم Twilio: Account SID و Auth Token
define('TWILIO_ACCOUNT_SID', '');
define('TWILIO_AUTH_TOKEN', '');
define('TWILIO_PHONE_NUMBER', '+972567183456'); // رقم Twilio الذي يُرسل منه (مثال: +970591234567)
