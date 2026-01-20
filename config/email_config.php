<?php
// Email Configuration for Empire Fitness
// Note: Update these settings with your actual email credentials

define('SMTP_HOST', 'smtp.gmail.com');              // Your SMTP host
define('SMTP_PORT', 587);                            // Usually 587 for TLS or 465 for SSL
define('SMTP_USERNAME', 'empirefitnessgymcamarin@gmail.com');    // Your email
define('SMTP_PASSWORD', 'mkpq oscu ecos mmtw');       // Gmail App Password (NOT your regular password!)
define('SMTP_FROM_EMAIL', 'empirefitnessgymcamarin@gmail.com');  // From email address
define('SMTP_FROM_NAME', 'Empire Fitness');         // From name
define('SMTP_ENCRYPTION', 'tls');                   // 'tls' or 'ssl'
define('SMTP_DEBUG', false);                         // Set to true for debugging

// Gmail Setup Instructions:
// 1. Enable 2-Step Verification on your Google account
// 2. Go to: https://myaccount.google.com/apppasswords
// 3. Select "Mail" and your device
// 4. Copy the 16-character password
// 5. Use this password in SMTP_PASSWORD above (NOT your regular Gmail password!)
?>
