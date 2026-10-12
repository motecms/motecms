<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use MoteCMS\Auth;
use MoteCMS\Config;
use MoteCMS\Csrf;
use MoteCMS\PageRepository;
use function MoteCMS\e;
use function MoteCMS\security_headers;

$config = Config::load();
$auth = new Auth($config);
$auth->start();
$repo = new PageRepository($config->getString('content_dir'));
security_headers(true);
header('Content-Type: text/html; charset=UTF-8');
$action = (string)($_GET['action'] ?? 'list');
$errors = [];

function admin_header(string $title): void { echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="content">'; }
function admin_footer(): void { echo '</main></body></html>'; }
function require_csrf(): void { if (!Csrf::valid((string)($_POST['csrf'] ?? ''))) { http_response_code(400); admin_header('Bad request'); echo '<p class="error">Invalid CSRF token.</p>'; admin_footer(); exit; } }
function page_form(array $page, array $errors, string $action, string $button): void { if ($errors) echo '<div class="error"><ul><li>' . implode('</li><li>', array_map('MoteCMS\\e', $errors)) . '</li></ul></div>'; echo '<form class="form" method="post" action="?action=' . e($action) . '"><input type="hidden" name="csrf" value="' . e(Csrf::token()) . '">'; if ($action === 'update') echo '<input type="hidden" name="current_slug" value="' . e((string)$page['current_slug']) . '">'; foreach ([['title','Title'],['slug','Slug'],['order','Menu order']] as $f) echo '<p class="field"><label>' . $f[1] . '<input name="' . $f[0] . '" value="' . e((string)($page[$f[0]] ?? '')) . '"></label></p>'; echo '<p class="field"><label>Status<select name="status"><option value="draft"' . (($page['status'] ?? '')==='draft'?' selected':'') . '>draft</option><option value="published"' . (($page['status'] ?? '')==='published'?' selected':'') . '>published</option></select></label></p><p class="field"><label>Menu<select name="menu">'; foreach (['none','header','sidebar'] as $m) echo '<option value="' . $m . '"' . (($page['menu'] ?? '')===$m?' selected':'') . '>' . $m . '</option>'; echo '</select></label></p><p class="field"><label>Content<textarea name="content" rows="16">' . e((string)($page['content'] ?? '')) . '</textarea></label></p><p class="actions"><button>' . e($button) . '</button><a class="button" href="index.php">Cancel</a></p></form>'; }

if (!$auth->check()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::valid((string)($_POST['csrf'] ?? ''))) { http_response_code(400); admin_header('Bad request'); echo '<p class="error">Invalid CSRF token.</p>'; admin_footer(); exit; }
        if ($auth->login((string)($_POST['password'] ?? ''))) { header('Location: index.php'); exit; }
        $errors[] = 'Login failed.';
    }
    admin_header('MoteCMS login');
    if ($errors) echo '<p class="error">' . e($errors[0]) . '</p>';
    echo '<h1>MoteCMS login</h1><form method="post"><input type="hidden" name="csrf" value="' . e(Csrf::token()) . '"><p class="field"><label>Password<input type="password" name="password" required></label></p><p><button>Log in</button></p></form>';
    admin_footer(); exit;
}

try {
    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $auth->logout(); header('Location: index.php'); exit; }
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $errors = $repo->validate($_POST); if (!$errors) { $repo->save($_POST); header('Location: index.php'); exit; } }
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $current = (string)$_POST['current_slug']; $errors = $repo->validate($_POST, $current); if (!$errors) { $repo->save($_POST, $current); header('Location: index.php'); exit; } }
    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') { require_csrf(); $repo->delete((string)$_POST['slug']); header('Location: index.php'); exit; }
} catch (Throwable $e) { error_log($e->getMessage()); $errors[] = 'An error occurred.'; }

admin_header('MoteCMS admin');
echo '<h1>MoteCMS admin</h1><p class="actions"><a class="button" href="?action=new">Create page</a><form method="post" action="?action=logout"><input type="hidden" name="csrf" value="' . e(Csrf::token()) . '"><button>Log out</button></form></p>';
if ($action === 'new' || ($action === 'create' && $errors)) { echo '<h2>Create page</h2>'; page_form($_POST + ['status'=>'draft','menu'=>'none','order'=>'0','content'=>''], $errors, 'create', 'Create'); }
elseif ($action === 'edit') { $page = $repo->find((string)($_GET['slug'] ?? '')); if (!$page) { echo '<p class="error">Page not found.</p>'; } else { $page['current_slug']=$page['slug']; echo '<h2>Edit ' . e($page['title']) . '</h2>'; page_form($page, $errors, 'update', 'Save'); } }
elseif ($action === 'update' && $errors) { $page = $_POST; $page['current_slug'] = $_POST['current_slug'] ?? ''; page_form($page, $errors, 'update', 'Save'); }
elseif ($action === 'delete') { $slug=(string)($_GET['slug'] ?? ''); $page=$repo->find($slug); echo '<h2>Delete page</h2>'; if (!$page) echo '<p class="error">Page not found.</p>'; else echo '<form method="post" action="?action=delete"><input type="hidden" name="csrf" value="' . e(Csrf::token()) . '"><input type="hidden" name="slug" value="' . e($slug) . '"><p>Delete <strong>' . e($page['title']) . '</strong>?</p><p class="actions"><button>Delete</button><a class="button" href="index.php">Cancel</a></p></form>'; }
else { if ($errors) echo '<p class="error">' . e(implode(' ', $errors)) . '</p>'; echo '<table><thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Menu</th><th></th></tr></thead><tbody>'; foreach ($repo->all() as $p) echo '<tr><td>' . e($p['title']) . '</td><td>' . e($p['slug']) . '</td><td>' . e($p['status']) . '</td><td>' . e($p['menu']) . '</td><td><a href="?action=edit&amp;slug=' . e($p['slug']) . '">Edit</a> <a href="?action=delete&amp;slug=' . e($p['slug']) . '">Delete</a></td></tr>'; echo '</tbody></table>'; }
admin_footer();
