<?php
// 数据库配置文件
define('DB_HOST', 'localhost');
define('DB_NAME', 'resource_station');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 管理员配置
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // 建议修改为更安全的密码

// 会话配置
define('SESSION_NAME', 'resource_admin');
define('SESSION_LIFETIME', 3600); // 1小时

// 网站配置
define('SITE_TITLE', '老陈资源站');
define('SITE_DESCRIPTION', 'Apple风格资源管理器');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die('数据库连接失败: ' . $e->getMessage());
}
?>