// 全局变量
let currentFolderId = null;
let isAdmin = false;
let selectedItem = null;
let currentItems = [];
let breadcrumbPath = [];

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    checkAdminStatus();
    loadItems(null);
    setupEventListeners();
});

// 设置事件监听器
function setupEventListeners() {
    // 右键菜单
    document.addEventListener('contextmenu', function(e) {
        if (!isAdmin) return;
        
        e.preventDefault();
        const contextMenu = document.getElementById('contextMenu');
        
        // 检查是否点击在文件项上
        const fileItem = e.target.closest('.file-item');
        if (fileItem) {
            selectedItem = {
                id: fileItem.dataset.id,
                name: fileItem.dataset.name,
                type: fileItem.dataset.type
            };
            document.getElementById('renameItem').style.display = 'block';
            document.getElementById('deleteItem').style.display = 'block';
        } else {
            selectedItem = null;
            document.getElementById('renameItem').style.display = 'none';
            document.getElementById('deleteItem').style.display = 'none';
        }
        
        contextMenu.style.display = 'block';
        contextMenu.style.left = e.pageX + 'px';
        contextMenu.style.top = e.pageY + 'px';
    });
    
    // 点击其他地方隐藏右键菜单
    document.addEventListener('click', function() {
        document.getElementById('contextMenu').style.display = 'none';
    });
    
    // 搜索框回车事件
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchItems();
        }
    });
}

// 检查管理员状态
function checkAdminStatus() {
    fetch('api.php?action=check_admin')
        .then(response => response.json())
        .then(data => {
            isAdmin = data.is_admin;
            updateAdminUI();
        })
        .catch(error => {
            console.error('检查管理员状态失败:', error);
        });
}

// 更新管理员UI
function updateAdminUI() {
    const loginBtn = document.getElementById('loginBtn');
    const logoutBtn = document.getElementById('logoutBtn');
    const adminStatus = document.getElementById('adminStatus');
    const adminTools = document.getElementById('adminTools');
    
    if (isAdmin) {
        loginBtn.style.display = 'none';
        logoutBtn.style.display = 'inline-block';
        adminStatus.style.display = 'block';
        adminTools.style.display = 'block';
    } else {
        loginBtn.style.display = 'inline-block';
        logoutBtn.style.display = 'none';
        adminStatus.style.display = 'none';
        adminTools.style.display = 'none';
    }
}

// 显示登录模态框
function showLoginModal() {
    const modal = new bootstrap.Modal(document.getElementById('loginModal'));
    modal.show();
}

// 登录
function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        alert('请输入用户名和密码');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('username', username);
    formData.append('password', password);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            isAdmin = true;
            updateAdminUI();
            bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
            document.getElementById('loginForm').reset();
            showToast('登录成功', 'success');
        } else {
            showToast(data.message || '登录失败', 'error');
        }
    })
    .catch(error => {
        console.error('登录失败:', error);
        showToast('登录失败', 'error');
    });
}

// 退出登录
function logout() {
    fetch('api.php?action=logout')
        .then(response => response.json())
        .then(data => {
            isAdmin = false;
            updateAdminUI();
            showToast('已退出登录', 'info');
        })
        .catch(error => {
            console.error('退出登录失败:', error);
        });
}

// 加载文件列表
function loadItems(folderId) {
    currentFolderId = folderId;
    const fileList = document.getElementById('fileList');
    fileList.innerHTML = '<div class="loading"><i class="bi bi-arrow-clockwise"></i><div>正在加载...</div></div>';
    
    const url = folderId ? `api.php?action=get_items&folder_id=${folderId}` : 'api.php?action=get_items';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentItems = data.items;
                breadcrumbPath = data.breadcrumb || [];
                renderItems(data.items);
                updateBreadcrumb();
            } else {
                fileList.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><div>加载失败</div></div>';
            }
        })
        .catch(error => {
            console.error('加载文件列表失败:', error);
            fileList.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-circle"></i><div>加载失败</div></div>';
        });
}

// 渲染文件列表
function renderItems(items) {
    const fileList = document.getElementById('fileList');
    
    if (items.length === 0) {
        fileList.innerHTML = '<div class="empty-state"><i class="bi bi-folder2-open"></i><div>此文件夹为空</div></div>';
        return;
    }
    
    // 按类型和名称排序
    items.sort((a, b) => {
        if (a.type !== b.type) {
            return a.type === 'folder' ? -1 : 1;
        }
        return a.name.localeCompare(b.name);
    });
    
    let html = '';
    items.forEach(item => {
        const icon = item.type === 'folder' ? 'bi-folder-fill folder-icon' : 'bi-file-earmark file-icon-default';
        const meta = item.type === 'folder' ? '文件夹' : '文件';
        
        html += `
            <div class="file-item" 
                 data-id="${item.id}" 
                 data-name="${item.name}" 
                 data-type="${item.type}"
                 onclick="handleItemClick(${item.id}, '${item.type}')"
                 ondblclick="handleItemDoubleClick(${item.id}, '${item.type}')">
                <div class="file-icon">
                    <i class="bi ${icon}"></i>
                </div>
                <div class="file-name">${escapeHtml(item.name)}</div>
                <div class="file-meta">${meta}</div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

// 更新面包屑导航
function updateBreadcrumb() {
    const breadcrumb = document.getElementById('breadcrumb');
    let html = '<li class="breadcrumb-item"><a href="#" onclick="navigateToFolder(null)">根目录</a></li>';
    
    breadcrumbPath.forEach((item, index) => {
        if (index === breadcrumbPath.length - 1) {
            html += `<li class="breadcrumb-item active">${escapeHtml(item.name)}</li>`;
        } else {
            html += `<li class="breadcrumb-item"><a href="#" onclick="navigateToFolder(${item.id})">${escapeHtml(item.name)}</a></li>`;
        }
    });
    
    breadcrumb.innerHTML = html;
}

// 处理文件项点击
function handleItemClick(id, type) {
    // 清除之前的选中状态
    document.querySelectorAll('.file-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // 选中当前项
    event.currentTarget.classList.add('selected');
    selectedItem = { id, type };
}

// 处理文件项双击
function handleItemDoubleClick(id, type) {
    if (type === 'folder') {
        navigateToFolder(id);
    } else {
        if (isAdmin) {
            editFile(id);
        } else {
            showFileDetail(id);
        }
    }
}

// 导航到文件夹
function navigateToFolder(folderId) {
    loadItems(folderId);
}

// 显示创建模态框
function showCreateModal(type) {
    const modal = new bootstrap.Modal(document.getElementById('createModal'));
    const title = document.getElementById('createModalTitle');
    const fileFields = document.getElementById('fileFields');
    
    title.textContent = type === 'folder' ? '新建文件夹' : '新建文件';
    fileFields.style.display = type === 'file' ? 'block' : 'none';
    
    document.getElementById('createForm').reset();
    document.getElementById('createForm').dataset.type = type;
    document.getElementById('createForm').dataset.mode = 'create';
    
    modal.show();
}

// 编辑文件
function editFile(id) {
    const item = currentItems.find(item => item.id == id);
    if (!item) return;
    
    const modal = new bootstrap.Modal(document.getElementById('createModal'));
    const title = document.getElementById('createModalTitle');
    const fileFields = document.getElementById('fileFields');
    
    title.textContent = '编辑文件';
    fileFields.style.display = 'block';
    
    document.getElementById('itemName').value = item.name;
    document.getElementById('downloadLink').value = item.download_link || '';
    document.getElementById('tutorialLink').value = item.tutorial_link || '';
    
    document.getElementById('createForm').dataset.type = 'file';
    document.getElementById('createForm').dataset.mode = 'edit';
    document.getElementById('createForm').dataset.id = id;
    
    modal.show();
}

// 保存项目
function saveItem() {
    const form = document.getElementById('createForm');
    const mode = form.dataset.mode;
    const type = form.dataset.type;
    const id = form.dataset.id;
    
    const name = document.getElementById('itemName').value.trim();
    if (!name) {
        alert('请输入名称');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', mode === 'create' ? 'create_item' : 'update_item');
    formData.append('name', name);
    formData.append('type', type);
    formData.append('parent_id', currentFolderId || '');
    
    if (mode === 'edit') {
        formData.append('id', id);
    }
    
    if (type === 'file') {
        formData.append('download_link', document.getElementById('downloadLink').value.trim());
        formData.append('tutorial_link', document.getElementById('tutorialLink').value.trim());
    }
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
            loadItems(currentFolderId);
            showToast(mode === 'create' ? '创建成功' : '更新成功', 'success');
        } else {
            showToast(data.message || '操作失败', 'error');
        }
    })
    .catch(error => {
        console.error('保存失败:', error);
        showToast('保存失败', 'error');
    });
}

// 显示重命名模态框
function showRenameModal() {
    if (!selectedItem) return;
    
    const modal = new bootstrap.Modal(document.getElementById('renameModal'));
    document.getElementById('newName').value = selectedItem.name;
    modal.show();
}

// 重命名项目
function renameItem() {
    if (!selectedItem) return;
    
    const newName = document.getElementById('newName').value.trim();
    if (!newName) {
        alert('请输入新名称');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'rename_item');
    formData.append('id', selectedItem.id);
    formData.append('name', newName);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('renameModal')).hide();
            loadItems(currentFolderId);
            showToast('重命名成功', 'success');
        } else {
            showToast(data.message || '重命名失败', 'error');
        }
    })
    .catch(error => {
        console.error('重命名失败:', error);
        showToast('重命名失败', 'error');
    });
}

// 删除项目
function deleteItem() {
    if (!selectedItem) return;
    
    if (!confirm(`确定要删除 "${selectedItem.name}" 吗？`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_item');
    formData.append('id', selectedItem.id);
    
    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadItems(currentFolderId);
            showToast('删除成功', 'success');
        } else {
            showToast(data.message || '删除失败', 'error');
        }
    })
    .catch(error => {
        console.error('删除失败:', error);
        showToast('删除失败', 'error');
    });
}

// 显示文件详情
function showFileDetail(id) {
    const item = currentItems.find(item => item.id == id);
    if (!item || item.type !== 'file') return;
    
    const modal = new bootstrap.Modal(document.getElementById('fileDetailModal'));
    const title = document.getElementById('fileDetailTitle');
    const content = document.getElementById('fileDetailContent');
    
    title.textContent = item.name;
    
    let html = `<div class="mb-3"><strong>文件名：</strong>${escapeHtml(item.name)}</div>`;
    
    if (item.download_link) {
        html += `<div class="mb-3">
            <strong>下载链接：</strong><br>
            <a href="${escapeHtml(item.download_link)}" target="_blank" class="btn btn-apple btn-sm">
                <i class="bi bi-download me-1"></i>立即下载
            </a>
        </div>`;
    }
    
    if (item.tutorial_link) {
        html += `<div class="mb-3">
            <strong>教程链接：</strong><br>
            <a href="${escapeHtml(item.tutorial_link)}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-book me-1"></i>查看教程
            </a>
        </div>`;
    }
    
    if (!item.download_link && !item.tutorial_link) {
        html += '<div class="text-muted">暂无下载链接和教程链接</div>';
    }
    
    content.innerHTML = html;
    modal.show();
}

// 搜索功能
function searchItems() {
    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    
    if (!query) {
        renderItems(currentItems);
        return;
    }
    
    const filteredItems = currentItems.filter(item => 
        item.name.toLowerCase().includes(query)
    );
    
    renderItems(filteredItems);
}

// 显示提示消息
function showToast(message, type = 'info') {
    // 创建简单的提示消息
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}