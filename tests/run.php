<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use MoteCMS\Auth;
use MoteCMS\Config;
use MoteCMS\Csrf;
use MoteCMS\FrontMatter;
use MoteCMS\Markdown;
use MoteCMS\PageRepository;

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$tests = 0;
function ok(bool $value, string $name): void { global $tests; $tests++; if (!$value) { fwrite(STDERR, "FAIL: $name\n"); exit(1); } echo "."; }

ok(PageRepository::validSlug('home-1'), 'valid slug');
ok(!PageRepository::validSlug('../bad'), 'path traversal slug invalid');
ok(!PageRepository::validSlug('Bad'), 'uppercase slug invalid');
$parsed = FrontMatter::parse("---\ntitle: Test\nslug: test\nstatus: draft\nmenu: none\norder: 5\n---\n\nBody");
ok($parsed['meta']['title'] === 'Test' && trim($parsed['content']) === 'Body', 'front matter parse');
$round = FrontMatter::parse(FrontMatter::serialize(['title'=>'T','slug'=>'t','status'=>'published','menu'=>'header','order'=>1], 'Hello'));
ok($round['meta']['slug'] === 't' && trim($round['content']) === 'Hello', 'front matter round trip');
$dir = sys_get_temp_dir() . '/motecms-test-' . bin2hex(random_bytes(4));
mkdir($dir);
$repo = new PageRepository($dir);
$repo->save(['title'=>'B','slug'=>'b','status'=>'published','menu'=>'sidebar','order'=>20,'content'=>'B']);
$repo->save(['title'=>'A','slug'=>'a','status'=>'published','menu'=>'sidebar','order'=>10,'content'=>'A']);
$repo->save(['title'=>'Draft','slug'=>'draft','status'=>'draft','menu'=>'sidebar','order'=>1,'content'=>'D']);
$nav = $repo->navigation('sidebar');
ok(count($nav) === 2 && $nav[0]['slug'] === 'a' && $nav[1]['slug'] === 'b', 'navigation sorting and draft exclusion');
$md = new Markdown();
$html = $md->render("# Hi\n\n<script>alert(1)</script> [x](javascript:alert(1)) [ok](https://example.com) `code` **b** *e*");
ok(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'script tags escaped');
ok(!str_contains($html, 'javascript:') && str_contains($html, 'href="https://example.com"'), 'unsafe link blocked');
ok($repo->find('../bad') === null, 'find blocks traversal');
$dupe = $repo->validate(['title'=>'Dup','slug'=>'a','status'=>'draft','menu'=>'none','order'=>1,'content'=>'']);
ok($dupe !== [], 'duplicate slug handling');
$repo->save(['title'=>'A2','slug'=>'a','status'=>'published','menu'=>'header','order'=>1,'content'=>'New'], 'a');
ok($repo->find('a')['title'] === 'A2', 'atomic update');
$token = Csrf::token();
ok(Csrf::valid($token) && !Csrf::valid('bad'), 'csrf validation');
array_map('unlink', glob($dir.'/*') ?: []); array_map('unlink', glob($dir.'/pages.lock') ?: []); rmdir($dir);

// Test Config
$config = Config::load();
ok($config->getString('site_name') === 'MoteCMS', 'config default site_name');
ok($config->getString('nonexistent', 'default') === 'default', 'config default value');

// Test Auth rate limiting
$_SESSION = [];
$auth = new Auth($config);
$auth->start();
for ($i = 0; $i < 5; $i++) {
    ok(!$auth->login('wrongpassword'), 'rate limiting attempt ' . ($i + 1));
}
ok(!$auth->login('wrongpassword'), 'rate limiting blocks after 5 attempts');

// Test content length validation
$longContent = str_repeat('a', 100001);
$contentValidation = $repo->validate(['title'=>'Test','slug'=>'test-long','status'=>'draft','menu'=>'none','order'=>1,'content'=>$longContent]);
ok(in_array('Content is too long.', $contentValidation, true), 'content length validation');

// Test PageRepository content length constant
ok(defined('MoteCMS\PageRepository::MAX_CONTENT_LENGTH'), 'MAX_CONTENT_LENGTH constant defined');

echo "\n$tests tests passed\n";
