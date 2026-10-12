<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use MoteCMS\Auth;
use MoteCMS\Config;
use MoteCMS\Csrf;
use MoteCMS\Logger;
use MoteCMS\PageRepository;
use MoteCMS\UserRepository;
use function MoteCMS\e;
use function MoteCMS\security_headers;

$config = Config::load();

// Initialize logger
$logger = null;
if ($config->getString('logging_enabled') !== 'false') {
    $logDir = $config->getString('log_dir') ?: sys_get_temp_dir() . '/motecms-logs';
    $logger = new Logger(true, $logDir);
}

// Initialize user repository if multi-user mode is enabled
$userRepo = null;
if ($config->getString('multi_user_mode') !== 'false') {
    $usersFile = $config->getString('users_file') ?: dirname(__DIR__, 2) . '/config/users.php';
    $userRepo = new UserRepository($usersFile);
}

// Initialize auth with userRepo and logger
$auth = new Auth($config, $userRepo, $logger);
$auth->start();

// Initialize page repository with logger
$repo = new PageRepository($config->getString('content_dir'), $logger);

security_headers(true);
header('Content-Type: text/html; charset=UTF-8');

$action = (string)($_GET['action'] ?? 'list');
$errors = [];

function admin_header(string $title): void { 
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="content">'; 
}

function admin_footer(): void { 
    echo '</main></body></html>'; 
}

function require_csrf(): void { 
    if (!Csrf::valid((string)($_POST['csrf'] ?? ''))) { 
        http_response_code(400); 
        admin_header('Bad request'); 
        echo '<p class="error">Invalid CSRF token.</p>'; 
        admin_footer(); 
        exit; 
    } 
}

function require_admin(): void {
    global $auth, $logger;
    if (!$auth->isAdmin()) {
        $logger?->warning('Unauthorized access attempt to admin area', ['user' => $auth->getUser(), 'role' => $auth->getRole()]);
        http_response_code(403);
        admin_header('Forbidden');
        echo '<p class="error">You do not have permission to access this area.</p>';
        admin_footer();
        exit;
    }
}

function page_form(array $page, array $errors, string $action, string $button): void {
    if ($errors) echo '<div class="error"><ul><li>' . implode('</li><li>', array_map('MoteCMS\\e', $errors)) . '</li></ul></div>';
    echo '<form class="form" method="post" action="?action=' . e($action) . '">';
    echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
    if ($action === 'update') echo '<input type="hidden" name="current_slug" value="' . e((string)($page['current_slug'] ?? '')) . '">';
    foreach ([['title','Title'],['slug','Slug'],['order','Menu order']] as $f) 
        echo '<p class="field"><label>' . $f[1] . '<input name="' . $f[0] . '" value="' . e((string)($page[$f[0]] ?? '')) . '"></label></p>';
    echo '<p class="field"><label>Status<select name="status"><option value="draft"' . (($page['status'] ?? '')==='draft'?' selected':'') . '>draft</option><option value="published"' . (($page['status'] ?? '')==='published'?' selected':'') . '>published</option></select></label></p>';
    echo '<p class="field"><label>Menu<select name="menu">';
    foreach (['none','header','sidebar'] as $m) 
        echo '<option value="' . $m . '"' . (($page['menu'] ?? '')===$m?' selected':'') . '>' . $m . '</option>';
    echo '</select></label></p>';
    echo '<p class="field"><label>Content<textarea name="content" rows="16">' . e((string)($page['content'] ?? '')) . '</textarea></label></p>';
    echo '<p class="actions"><button>' . e($button) . '</button><a class="button" href="index.php">Cancel</a></p></form>';
}

// Handle logout
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $logger?->info('User logged out', ['user' => $auth->getUser()]);
    $auth->logout();
    header('Location: index.php');
    exit;
}

// Handle login
if (!$auth->check()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::valid((string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            admin_header('Bad request');
            echo '<p class="error">Invalid CSRF token.</p>';
            admin_footer();
            exit;
        }
        
        // Multi-user mode
        if ($userRepo !== null) {
            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            if ($auth->login($username, $password)) {
                $logger?->info('User logged in', ['user' => $username, 'role' => $auth->getRole()]);
                header('Location: index.php');
                exit;
            }
        } else {
            // Legacy single-user mode
            $password = (string)($_POST['password'] ?? '');
            if ($auth->login('admin', $password)) {
                $logger?->info('User logged in (legacy mode)');
                header('Location: index.php');
                exit;
            }
        }
        
        $logger?->warning('Failed login attempt', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $errors[] = 'Login failed.';
    }
    
    admin_header('MoteCMS login');
    if ($errors) echo '<p class="error">' . e($errors[0]) . '</p>';
    echo '<h1>MoteCMS login</h1>';
    echo '<form method="post">';
    echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
    if ($userRepo !== null) {
        echo '<p class="field"><label>Username<input type="text" name="username" required></label></p>';
    }
    echo '<p class="field"><label>Password<input type="password" name="password" required></label></p>';
    echo '<p><button>Log in</button></p>';
    echo '</form>';
    admin_footer();
    exit;
}

// Handle user management (admin only)
if ($userRepo !== null && $action === 'users') {
    require_admin();
    
    // Handle user creation
    if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        try {
            $userRepo->addUser(
                (string)$_POST['username'],
                (string)$_POST['password'],
                (string)$_POST['role'],
                (string)$_POST['name']
            );
            $logger?->info('User created', ['user' => $_POST['username'], 'role' => $_POST['role'], 'by' => $auth->getUser()]);
            header('Location: ?action=users');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    // Handle user deletion
    if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $username = (string)($_POST['username'] ?? '');
        try {
            $userRepo->deleteUser($username);
            $logger?->info('User deleted', ['user' => $username, 'by' => $auth->getUser()]);
            header('Location: ?action=users');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    // Display user management interface
    admin_header('User Management');
    echo '<h1>User Management</h1>';
    echo '<p class="actions"><a class="button" href="?action=new_user">Add User</a><a class="button" href="index.php">Back to Admin</a></p>';
    
    if ($action === 'new_user' || ($action === 'create_user' && $errors)) {
        echo '<h2>Add User</h2>';
        echo '<form method="post" action="?action=create_user">';
        echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
        echo '<p class="field"><label>Username<input name="username" value="' . e($_POST['username'] ?? '') . '" required></label></p>';
        echo '<p class="field"><label>Password<input type="password" name="password" required></label></p>';
        echo '<p class="field"><label>Name<input name="name" value="' . e($_POST['name'] ?? '') . '"></label></p>';
        echo '<p class="field"><label>Role<select name="role">';
        foreach ($userRepo->getRoles() as $role) {
            $selected = (($_POST['role'] ?? '') === $role) ? ' selected' : '';
            echo '<option value="' . e($role) . '"' . $selected . '>' . e(ucfirst($role)) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p class="actions"><button>Create</button><a class="button" href="?action=users">Cancel</a></p>';
        echo '</form>';
        admin_footer();
        exit;
    }
    
    // Display user list
    if ($errors) echo '<p class="error">' . e(implode(' ', $errors)) . '</p>';
    echo '<table><thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Actions</th></tr></thead><tbody>';
    foreach ($userRepo->all() as $username => $user) {
        echo '<tr>';
        echo '<td>' . e($username) . '</td>';
        echo '<td>' . e($user['name']) . '</td>';
        echo '<td>' . e($user['role']) . '</td>';
        echo '<td>';
        if ($username !== $auth->getUser()) { // Cannot delete yourself
            echo '<form method="post" action="?action=delete_user" style="display:inline;">';
            echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
            echo '<input type="hidden" name="username" value="' . e($username) . '">';
            echo '<button type="submit" class="delete-button">Delete</button>';
            echo '</form>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    admin_footer();
    exit;
}

// Handle page management (editor and above)
if (!$auth->check('editor')) {
    http_response_code(403);
    admin_header('Forbidden');
    echo '<p class="error">You do not have permission to access this area.</p>';
    admin_footer();
    exit;
}

// Handle page actions
try {
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $errors = $repo->validate($_POST);
        if (!$errors) {
            $repo->save($_POST, null, $auth->getUser());
            $logger?->info('Page created', ['slug' => $_POST['slug'], 'user' => $auth->getUser()]);
            header('Location: index.php');
            exit;
        }
    }
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $current = (string)$_POST['current_slug'];
        $errors = $repo->validate($_POST, $current);
        if (!$errors) {
            $repo->save($_POST, $current, $auth->getUser());
            $logger?->info('Page updated', ['slug' => $_POST['slug'], 'user' => $auth->getUser()]);
            header('Location: index.php');
            exit;
        }
    }
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        $slug = (string)$_POST['slug'];
        $repo->delete($slug, $auth->getUser());
        $logger?->info('Page deleted', ['slug' => $slug, 'user' => $auth->getUser()]);
        header('Location: index.php');
        exit;
    }
} catch (Throwable $e) {
    $logger?->error('Error in page action', ['error' => $e->getMessage(), 'action' => $action, 'user' => $auth->getUser()]);
    error_log($e->getMessage());
    $errors[] = 'An error occurred.';
}

// Handle view actions
admin_header('MoteCMS admin');
echo '<h1>MoteCMS admin</h1>';
echo '<p>Logged in as: <strong>' . e($auth->getUser() ?? 'Unknown') . '</strong> (Role: ' . e($auth->getRole() ?? 'Unknown') . ')</p>';

// Show admin links if user is admin
echo '<p class="actions">';
echo '<a class="button" href="?action=new">Create page</a>';
if ($auth->isAdmin() && $userRepo !== null) {
    echo '<a class="button" href="?action=users">Manage Users</a>';
    echo '<a class="button" href="?action=logs">View Logs</a>';
}
echo '<form method="post" action="?action=logout" style="display:inline;">';
echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
echo '<button type="submit">Log out</button>';
echo '</form>';
echo '</p>';

// Handle logs viewing (admin only)
if ($action === 'logs') {
    require_admin();
    if ($logger === null || !$logger->isEnabled()) {
        echo '<p class="error">Logging is disabled.</p>';
    } else {
        echo '<h2>System Logs</h2>';
        echo '<p class="actions"><a class="button" href="index.php">Back to Admin</a></p>';
        echo '<div class="log-container"><pre>';
        foreach ($logger->getLogs(100) as $log) {
            echo e($log) . "\n";
        }
        echo '</pre></div>';
    }
    admin_footer();
    exit;
}

// Handle page list/view
if ($action === 'new' || ($action === 'create' && $errors)) {
    echo '<h2>Create page</h2>';
    page_form($_POST + ['status'=>'draft','menu'=>'none','order'=>'0','content'=>''], $errors, 'create', 'Create');
} elseif ($action === 'edit') {
    $page = $repo->find((string)($_GET['slug'] ?? ''));
    if (!$page) {
        echo '<p class="error">Page not found.</p>';
    } else {
        $page['current_slug'] = $page['slug'];
        echo '<h2>Edit ' . e($page['title']) . '</h2>';
        page_form($page, $errors, 'update', 'Save');
    }
} elseif ($action === 'update' && $errors) {
    $page = $_POST;
    $page['current_slug'] = $_POST['current_slug'] ?? '';
    page_form($page, $errors, 'update', 'Save');
} elseif ($action === 'delete') {
    $slug = (string)($_GET['slug'] ?? '');
    $page = $repo->find($slug);
    echo '<h2>Delete page</h2>';
    if (!$page) {
        echo '<p class="error">Page not found.</p>';
    } else {
        echo '<form method="post" action="?action=delete">';
        echo '<input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">';
        echo '<input type="hidden" name="slug" value="' . e($slug) . '">';
        echo '<p>Delete <strong>' . e($page['title']) . '</strong>?</p>';
        echo '<p class="actions"><button>Delete</button><a class="button" href="index.php">Cancel</a></p>';
        echo '</form>';
    }
} else {
    if ($errors) echo '<p class="error">' . e(implode(' ', $errors)) . '</p>';
    echo '<table><thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Menu</th><th>Actions</th></tr></thead><tbody>';
    foreach ($repo->all() as $p) {
        echo '<tr>';
        echo '<td>' . e($p['title']) . '</td>';
        echo '<td>' . e($p['slug']) . '</td>';
        echo '<td>' . e($p['status']) . '</td>';
        echo '<td>' . e($p['menu']) . '</td>';
        echo '<td>';
        echo '<a href="?action=edit&amp;slug=' . e($p['slug']) . '">Edit</a> ';
        echo '<a href="?action=delete&amp;slug=' . e($p['slug']) . '">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

admin_footer();
