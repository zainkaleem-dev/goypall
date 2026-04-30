<?php
/**
 * Pitch Page System — Configuration File
 * Generated during installation
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'pitch2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PREFIX', 'px_');

// Site
define('SITE_URL', 'http://localhost/goypall');
define('SITE_NAME', 'CryptoExchange Pro');

// Security
define('SECRET_KEY', 'a2da8b02de5709439419dc2bbe3651d013889315f17deafb86a58cc585fc3abc');
define('CSRF_SECRET', 'dbdbc74eb8d182c842c563e9256d124167c07560b3a5fb0131ebc043826aad44');

// Sessions
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME', 'PX_SESSION');

// Environment
define('APP_ENV', 'development');
define('APP_DEBUG', true);
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
