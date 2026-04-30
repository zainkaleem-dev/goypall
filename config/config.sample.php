<?php
/**
 * Pitch Page System — Configuration File
 * Generated during installation
 */

// Database
define('DB_HOST', '{{DB_HOST}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_PREFIX', 'px_');

// Site
define('SITE_URL', '{{SITE_URL}}');
define('SITE_NAME', '{{SITE_NAME}}');

// Security
define('SECRET_KEY', '{{SECRET_KEY}}');
define('CSRF_SECRET', '{{CSRF_SECRET}}');

// Sessions
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME', 'PX_SESSION');

// Environment
define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_VERSION', '1.0.0');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('ADMIN_PATH', ROOT_PATH . '/admin');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UPLOADS_URL', SITE_URL . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Timezone
date_default_timezone_set('UTC');
