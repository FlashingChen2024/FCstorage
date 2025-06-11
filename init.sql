-- 老陈资源站数据库初始化脚本

CREATE DATABASE IF NOT EXISTS resource_station CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE resource_station;

-- 文件和文件夹表
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='文件和文件夹表';

-- 插入一些示例数据
INSERT INTO items (name, type, parent_id, download_link, tutorial_link, sort_order) VALUES
('软件工具', 'folder', NULL, NULL, NULL, 1),
('学习资料', 'folder', NULL, NULL, NULL, 2),
('游戏资源', 'folder', NULL, NULL, NULL, 3);

SET @software_id = (SELECT id FROM items WHERE name = '软件工具' AND type = 'folder');
SET @study_id = (SELECT id FROM items WHERE name = '学习资料' AND type = 'folder');
SET @game_id = (SELECT id FROM items WHERE name = '游戏资源' AND type = 'folder');

INSERT INTO items (name, type, parent_id, download_link, tutorial_link, sort_order) VALUES
('开发工具', 'folder', @software_id, NULL, NULL, 1),
('系统工具', 'folder', @software_id, NULL, NULL, 2),
('编程教程', 'folder', @study_id, NULL, NULL, 1),
('设计素材', 'folder', @study_id, NULL, NULL, 2),
('单机游戏', 'folder', @game_id, NULL, NULL, 1),
('游戏MOD', 'folder', @game_id, NULL, NULL, 2);

-- 添加一些示例文件
SET @dev_tools_id = (SELECT id FROM items WHERE name = '开发工具' AND type = 'folder');
SET @sys_tools_id = (SELECT id FROM items WHERE name = '系统工具' AND type = 'folder');

INSERT INTO items (name, type, parent_id, download_link, tutorial_link, sort_order) VALUES
('Visual Studio Code', 'file', @dev_tools_id, 'https://code.visualstudio.com/download', 'https://code.visualstudio.com/docs', 1),
('Git for Windows', 'file', @dev_tools_id, 'https://git-scm.com/download/win', 'https://git-scm.com/docs', 2),
('7-Zip', 'file', @sys_tools_id, 'https://www.7-zip.org/download.html', 'https://www.7-zip.org/faq.html', 1),
('Everything', 'file', @sys_tools_id, 'https://www.voidtools.com/downloads/', 'https://www.voidtools.com/support/', 2);