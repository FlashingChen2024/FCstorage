<?php
require_once 'config.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 启动会话
session_name(SESSION_NAME);
session_start();

// 获取请求参数
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 路由处理
try {
    switch ($action) {
        case 'check_admin':
            checkAdmin();
            break;
        case 'login':
            login();
            break;
        case 'logout':
            logout();
            break;
        case 'get_items':
            getItems();
            break;
        case 'create_item':
            createItem();
            break;
        case 'update_item':
            updateItem();
            break;
        case 'rename_item':
            renameItem();
            break;
        case 'delete_item':
            deleteItem();
            break;
        default:
            throw new Exception('无效的操作');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// 检查管理员状态
function checkAdmin() {
    echo json_encode([
        'success' => true,
        'is_admin' => isAdmin()
    ]);
}

// 登录
function login() {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        throw new Exception('用户名和密码不能为空');
    }
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        echo json_encode([
            'success' => true,
            'message' => '登录成功'
        ]);
    } else {
        throw new Exception('用户名或密码错误');
    }
}

// 退出登录
function logout() {
    session_destroy();
    echo json_encode([
        'success' => true,
        'message' => '已退出登录'
    ]);
}

// 获取文件列表
function getItems() {
    global $pdo;
    
    $folderId = $_GET['folder_id'] ?? null;
    $folderId = $folderId === '' ? null : $folderId;
    
    // 获取当前文件夹的文件列表
    $sql = "SELECT * FROM items WHERE parent_id " . ($folderId ? "= ?" : "IS NULL") . " ORDER BY type DESC, name ASC";
    $params = $folderId ? [$folderId] : [];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    
    // 获取面包屑路径
    $breadcrumb = [];
    if ($folderId) {
        $breadcrumb = getBreadcrumbPath($folderId);
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'breadcrumb' => $breadcrumb
    ]);
}

// 创建项目
function createItem() {
    requireAdmin();
    global $pdo;
    
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $parentId = $_POST['parent_id'] ?? null;
    $parentId = $parentId === '' ? null : $parentId;
    
    if (empty($name)) {
        throw new Exception('名称不能为空');
    }
    
    if (!in_array($type, ['file', 'folder'])) {
        throw new Exception('无效的类型');
    }
    
    // 检查同级目录下是否已存在同名项目
    $sql = "SELECT COUNT(*) FROM items WHERE name = ? AND parent_id " . ($parentId ? "= ?" : "IS NULL");
    $params = $parentId ? [$name, $parentId] : [$name];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('同名文件或文件夹已存在');
    }
    
    // 插入新项目
    $sql = "INSERT INTO items (name, type, parent_id, download_link, tutorial_link) VALUES (?, ?, ?, ?, ?)";
    $downloadLink = $type === 'file' ? ($_POST['download_link'] ?? '') : null;
    $tutorialLink = $type === 'file' ? ($_POST['tutorial_link'] ?? '') : null;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $type, $parentId, $downloadLink, $tutorialLink]);
    
    echo json_encode([
        'success' => true,
        'message' => '创建成功',
        'id' => $pdo->lastInsertId()
    ]);
}

// 更新项目
function updateItem() {
    requireAdmin();
    global $pdo;
    
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $downloadLink = $_POST['download_link'] ?? '';
    $tutorialLink = $_POST['tutorial_link'] ?? '';
    
    if (empty($id) || empty($name)) {
        throw new Exception('ID和名称不能为空');
    }
    
    // 检查项目是否存在
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('项目不存在');
    }
    
    // 检查同级目录下是否已存在同名项目（排除自己）
    $sql = "SELECT COUNT(*) FROM items WHERE name = ? AND parent_id " . ($item['parent_id'] ? "= ?" : "IS NULL") . " AND id != ?";
    $params = $item['parent_id'] ? [$name, $item['parent_id'], $id] : [$name, $id];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('同名文件或文件夹已存在');
    }
    
    // 更新项目
    if ($item['type'] === 'file') {
        $sql = "UPDATE items SET name = ?, download_link = ?, tutorial_link = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $downloadLink, $tutorialLink, $id]);
    } else {
        $sql = "UPDATE items SET name = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);
}

// 重命名项目
function renameItem() {
    requireAdmin();
    global $pdo;
    
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    
    if (empty($id) || empty($name)) {
        throw new Exception('ID和名称不能为空');
    }
    
    // 检查项目是否存在
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('项目不存在');
    }
    
    // 检查同级目录下是否已存在同名项目（排除自己）
    $sql = "SELECT COUNT(*) FROM items WHERE name = ? AND parent_id " . ($item['parent_id'] ? "= ?" : "IS NULL") . " AND id != ?";
    $params = $item['parent_id'] ? [$name, $item['parent_id'], $id] : [$name, $id];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('同名文件或文件夹已存在');
    }
    
    // 重命名项目
    $stmt = $pdo->prepare("UPDATE items SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => '重命名成功'
    ]);
}

// 删除项目
function deleteItem() {
    requireAdmin();
    global $pdo;
    
    $id = $_POST['id'] ?? '';
    
    if (empty($id)) {
        throw new Exception('ID不能为空');
    }
    
    // 检查项目是否存在
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('项目不存在');
    }
    
    // 如果是文件夹，检查是否为空
    if ($item['type'] === 'folder') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE parent_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('文件夹不为空，无法删除');
        }
    }
    
    // 删除项目
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => '删除成功'
    ]);
}

// 获取面包屑路径
function getBreadcrumbPath($folderId) {
    global $pdo;
    
    $path = [];
    $currentId = $folderId;
    
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM items WHERE id = ? AND type = 'folder'");
        $stmt->execute([$currentId]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            break;
        }
        
        array_unshift($path, [
            'id' => $folder['id'],
            'name' => $folder['name']
        ]);
        
        $currentId = $folder['parent_id'];
    }
    
    return $path;
}

// 检查是否为管理员
function isAdmin() {
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        return false;
    }
    
    // 检查会话是否过期
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
        session_destroy();
        return false;
    }
    
    return true;
}

// 要求管理员权限
function requireAdmin() {
    if (!isAdmin()) {
        throw new Exception('需要管理员权限');
    }
}
?>