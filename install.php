<?php
/**
 * 老陈资源站安装脚本
 * 用于快速部署和配置系统
 */

// 检查是否已经安装
if (file_exists('config.php')) {
    $config_content = file_get_contents('config.php');
    if (strpos($config_content, 'localhost') === false || 
        (isset($_GET['force']) && $_GET['force'] === '1')) {
        // 允许重新安装
    } else {
        die('系统已经安装，如需重新安装请访问 install.php?force=1');
    }
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // 数据库配置
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? 'resource_station';
            $db_user = $_POST['db_user'] ?? 'root';
            $db_pass = $_POST['db_pass'] ?? '';
            $admin_user = $_POST['admin_user'] ?? 'admin';
            $admin_pass = $_POST['admin_pass'] ?? '';
            $site_title = $_POST['site_title'] ?? '老陈资源站';
            
            if (empty($admin_pass)) {
                $error = '管理员密码不能为空';
            } else {
                // 测试数据库连接
                try {
                    $pdo = new PDO(
                        "mysql:host={$db_host};charset=utf8mb4",
                        $db_user,
                        $db_pass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    
                    // 创建数据库（如果不存在）
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `{$db_name}`");
                    
                    // 创建表
                    $sql = "
                        CREATE TABLE IF NOT EXISTS items (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(255) NOT NULL COMMENT '文件/文件夹名称',
                            type ENUM('file', 'folder') NOT NULL COMMENT '类型：文件或文件夹',
                            parent_id INT DEFAULT NULL COMMENT '父文件夹ID，NULL表示根目录',
                            download_link TEXT COMMENT '下载链接（仅文件有效）',
                            tutorial_link TEXT COMMENT '教程链接（仅文件有效）',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
                            sort_order INT DEFAULT 0 COMMENT '排序顺序',
                            
                            INDEX idx_parent_id (parent_id),
                            INDEX idx_type (type),
                            INDEX idx_name (name),
                            
                            FOREIGN KEY (parent_id) REFERENCES items(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件和文件夹表'
                    ";
                    $pdo->exec($sql);
                    
                    // 插入示例数据
                    $pdo->exec("
                        INSERT IGNORE INTO items (id, name, type, parent_id, download_link, tutorial_link, sort_order) VALUES
                        (1, '软件工具', 'folder', NULL, NULL, NULL, 1),
                        (2, '学习资料', 'folder', NULL, NULL, NULL, 2),
                        (3, '游戏资源', 'folder', NULL, NULL, NULL, 3),
                        (4, '开发工具', 'folder', 1, NULL, NULL, 1),
                        (5, '系统工具', 'folder', 1, NULL, NULL, 2),
                        (6, '编程教程', 'folder', 2, NULL, NULL, 1),
                        (7, '设计素材', 'folder', 2, NULL, NULL, 2),
                        (8, '单机游戏', 'folder', 3, NULL, NULL, 1),
                        (9, '游戏MOD', 'folder', 3, NULL, NULL, 2),
                        (10, 'Visual Studio Code', 'file', 4, 'https://code.visualstudio.com/download', 'https://code.visualstudio.com/docs', 1),
                        (11, 'Git for Windows', 'file', 4, 'https://git-scm.com/download/win', 'https://git-scm.com/docs', 2),
                        (12, '7-Zip', 'file', 5, 'https://www.7-zip.org/download.html', 'https://www.7-zip.org/faq.html', 1),
                        (13, 'Everything', 'file', 5, 'https://www.voidtools.com/downloads/', 'https://www.voidtools.com/support/', 2)
                    ");
                    
                    // 生成配置文件
                    $config_content = "<?php\n";
                    $config_content .= "// 数据库配置文件\n";
                    $config_content .= "define('DB_HOST', '{$db_host}');\n";
                    $config_content .= "define('DB_NAME', '{$db_name}');\n";
                    $config_content .= "define('DB_USER', '{$db_user}');\n";
                    $config_content .= "define('DB_PASS', '{$db_pass}');\n";
                    $config_content .= "define('DB_CHARSET', 'utf8mb4');\n\n";
                    
                    $config_content .= "// 管理员配置\n";
                    $config_content .= "define('ADMIN_USERNAME', '{$admin_user}');\n";
                    $config_content .= "define('ADMIN_PASSWORD', '{$admin_pass}');\n\n";
                    
                    $config_content .= "// 会话配置\n";
                    $config_content .= "define('SESSION_NAME', 'resource_admin');\n";
                    $config_content .= "define('SESSION_LIFETIME', 3600); // 1小时\n\n";
                    
                    $config_content .= "// 网站配置\n";
                    $config_content .= "define('SITE_TITLE', '{$site_title}');\n";
                    $config_content .= "define('SITE_DESCRIPTION', 'Apple风格资源管理器');\n\n";
                    
                    $config_content .= "try {\n";
                    $config_content .= "    \$pdo = new PDO(\n";
                    $config_content .= "        \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET,\n";
                    $config_content .= "        DB_USER,\n";
                    $config_content .= "        DB_PASS,\n";
                    $config_content .= "        [\n";
                    $config_content .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
                    $config_content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
                    $config_content .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
                    $config_content .= "        ]\n";
                    $config_content .= "    );\n";
                    $config_content .= "} catch (PDOException \$e) {\n";
                    $config_content .= "    die('数据库连接失败: ' . \$e->getMessage());\n";
                    $config_content .= "}\n";
                    $config_content .= "?>";
                    
                    file_put_contents('config.php', $config_content);
                    
                    $step = 3;
                    $success = '安装成功！';
                    
                } catch (Exception $e) {
                    $error = '数据库连接失败: ' . $e->getMessage();
                }
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>老陈资源站 - 安装向导</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(45deg, #007AFF, #5856D6);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .install-body {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        .step.active {
            background: #007AFF;
            color: white;
        }
        .step.completed {
            background: #28a745;
            color: white;
        }
        .btn-primary {
            background: #007AFF;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="install-container">
                    <div class="install-header">
                        <h2><i class="bi bi-folder2-open me-2"></i>老陈资源站</h2>
                        <p class="mb-0">Apple风格资源管理器 - 安装向导</p>
                    </div>
                    
                    <div class="install-body">
                        <!-- 步骤指示器 -->
                        <div class="step-indicator">
                            <div class="step <?= $step >= 1 ? 'active' : '' ?>">
                                <?= $step > 1 ? '<i class="bi bi-check"></i>' : '1' ?>
                            </div>
                            <div class="step <?= $step >= 2 ? 'active' : '' ?>">
                                <?= $step > 2 ? '<i class="bi bi-check"></i>' : '2' ?>
                            </div>
                            <div class="step <?= $step >= 3 ? 'active' : '' ?>">
                                <?= $step > 3 ? '<i class="bi bi-check"></i>' : '3' ?>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($step == 1): ?>
                            <!-- 步骤1: 欢迎 -->
                            <div class="text-center">
                                <h4>欢迎使用老陈资源站</h4>
                                <p class="text-muted mb-4">这个安装向导将帮助您快速部署和配置系统</p>
                                
                                <div class="row text-start">
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-check-circle text-success me-2"></i>功能特性</h6>
                                        <ul class="list-unstyled text-muted">
                                            <li>• Apple风格界面设计</li>
                                            <li>• 文件夹层级管理</li>
                                            <li>• 下载链接管理</li>
                                            <li>• 管理员权限控制</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="bi bi-gear text-primary me-2"></i>系统要求</h6>
                                        <ul class="list-unstyled text-muted">
                                            <li>• PHP 7.4+</li>
                                            <li>• MySQL 5.7+</li>
                                            <li>• Web服务器</li>
                                            <li>• PDO扩展</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <a href="?step=2" class="btn btn-primary btn-lg mt-4">
                                    开始安装 <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            </div>
                            
                        <?php elseif ($step == 2): ?>
                            <!-- 步骤2: 配置 -->
                            <h4 class="mb-4">系统配置</h4>
                            
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-primary">数据库配置</h6>
                                        <div class="mb-3">
                                            <label class="form-label">数据库主机</label>
                                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">数据库名称</label>
                                            <input type="text" class="form-control" name="db_name" value="resource_station" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">数据库用户名</label>
                                            <input type="text" class="form-control" name="db_user" value="root" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">数据库密码</label>
                                            <input type="password" class="form-control" name="db_pass">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-primary">管理员配置</h6>
                                        <div class="mb-3">
                                            <label class="form-label">管理员用户名</label>
                                            <input type="text" class="form-control" name="admin_user" value="admin" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">管理员密码</label>
                                            <input type="password" class="form-control" name="admin_pass" required>
                                            <div class="form-text">请设置一个安全的密码</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">网站标题</label>
                                            <input type="text" class="form-control" name="site_title" value="老陈资源站" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="?step=1" class="btn btn-outline-secondary me-3">
                                        <i class="bi bi-arrow-left me-2"></i>上一步
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        开始安装 <i class="bi bi-download ms-2"></i>
                                    </button>
                                </div>
                            </form>
                            
                        <?php elseif ($step == 3): ?>
                            <!-- 步骤3: 完成 -->
                            <div class="text-center">
                                <div class="mb-4">
                                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                                </div>
                                <h4 class="text-success mb-3">安装完成！</h4>
                                <p class="text-muted mb-4">老陈资源站已成功安装并配置完成</p>
                                
                                <div class="alert alert-info text-start">
                                    <h6><i class="bi bi-info-circle me-2"></i>重要提示</h6>
                                    <ul class="mb-0">
                                        <li>请删除或重命名 <code>install.php</code> 文件以确保安全</li>
                                        <li>建议定期备份数据库数据</li>
                                        <li>生产环境请使用HTTPS协议</li>
                                    </ul>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="index.html" class="btn btn-primary btn-lg">
                                        <i class="bi bi-house me-2"></i>访问网站
                                    </a>
                                    <a href="README.md" class="btn btn-outline-primary btn-lg ms-3" target="_blank">
                                        <i class="bi bi-book me-2"></i>查看文档
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>