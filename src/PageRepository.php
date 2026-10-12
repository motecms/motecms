<?php
declare(strict_types=1);

namespace MoteCMS;

use RuntimeException;

final class PageRepository
{
    public function __construct(private string $dir)
    {
        if (!is_dir($dir)) {
            throw new RuntimeException('Content directory does not exist: ' . $dir);
        }
    }

    public static function validSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug) === 1;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $pages = [];
        foreach (glob($this->dir . '/*.md') ?: [] as $file) {
            $page = $this->readFile($file);
            if ($page !== null) {
                $pages[] = $page;
            }
        }
        usort($pages, fn(array $a, array $b): int => [$a['order'], strtolower($a['title'])] <=> [$b['order'], strtolower($b['title'])]);
        return $pages;
    }

    /** @return array<int,array<string,mixed>> */
    public function navigation(string $menu): array
    {
        return array_values(array_filter($this->all(), fn(array $p): bool => $p['status'] === 'published' && $p['menu'] === $menu));
    }

    /** @return array<string,mixed>|null */
    public function findPublished(string $slug): ?array
    {
        $page = $this->find($slug);
        return $page !== null && $page['status'] === 'published' ? $page : null;
    }

    /** @return array<string,mixed>|null */
    public function find(string $slug): ?array
    {
        if (!self::validSlug($slug)) {
            return null;
        }
        $file = $this->pathForSlug($slug);
        return is_file($file) ? $this->readFile($file) : null;
    }

    private const MAX_CONTENT_LENGTH = 100000; // 100KB

    /** @param array<string,mixed> $data @return array<int,string> */
    public function validate(array $data, ?string $currentSlug = null): array
    {
        $errors = [];
        $slug = (string)($data['slug'] ?? '');
        if (trim((string)($data['title'] ?? '')) === '') $errors[] = 'Title is required.';
        if (!self::validSlug($slug)) $errors[] = 'Slug must match [a-z0-9][a-z0-9-]{0,63}.';
        if (!in_array((string)($data['status'] ?? ''), ['draft', 'published'], true)) $errors[] = 'Status must be draft or published.';
        if (!in_array((string)($data['menu'] ?? ''), ['header', 'sidebar', 'none'], true)) $errors[] = 'Menu must be header, sidebar or none.';
        if (filter_var($data['order'] ?? null, FILTER_VALIDATE_INT) === false) $errors[] = 'Order must be an integer.';
        if (self::validSlug($slug) && $slug !== $currentSlug && is_file($this->pathForSlug($slug))) $errors[] = 'A page with this slug already exists.';
        if (strlen((string)($data['content'] ?? '')) > self::MAX_CONTENT_LENGTH) $errors[] = 'Content is too long.';
        return $errors;
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?string $currentSlug = null): void
    {
        $errors = $this->validate($data, $currentSlug);
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }
        $slug = (string)$data['slug'];
        $meta = ['title' => trim((string)$data['title']), 'slug' => $slug, 'status' => (string)$data['status'], 'menu' => (string)$data['menu'], 'order' => (int)$data['order']];
        $text = FrontMatter::serialize($meta, (string)($data['content'] ?? ''));
        $target = $this->pathForSlug($slug);
        $lockFile = $this->dir . '/pages.lock';
        $lock = fopen($lockFile, 'c');
        if ($lock === false) throw new RuntimeException('Unable to lock content directory.');
        try {
            flock($lock, LOCK_EX);
            $tmp = tempnam($this->dir, '.tmp-');
            if ($tmp === false) throw new RuntimeException('Unable to create temporary file.');
            if (file_put_contents($tmp, $text, LOCK_EX) === false) throw new RuntimeException('Unable to write page.');
            if (!rename($tmp, $target)) throw new RuntimeException('Unable to save page.');
            if ($currentSlug !== null && $currentSlug !== $slug && self::validSlug($currentSlug)) {
                $old = $this->pathForSlug($currentSlug);
                if (is_file($old)) unlink($old);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function delete(string $slug): void
    {
        if (!self::validSlug($slug)) throw new RuntimeException('Invalid slug.');
        $file = $this->pathForSlug($slug);
        if (is_file($file) && !unlink($file)) throw new RuntimeException('Unable to delete page.');
    }

    public function latestMtime(): int
    {
        $max = 0;
        foreach (glob($this->dir . '/*.md') ?: [] as $file) $max = max($max, (int)filemtime($file));
        return $max ?: time();
    }

    private function pathForSlug(string $slug): string
    {
        if (!self::validSlug($slug)) throw new RuntimeException('Invalid slug.');
        return $this->dir . '/' . $slug . '.md';
    }

    /** @return array<string,mixed>|null */
    private function readFile(string $file): ?array
    {
        $parsed = FrontMatter::parse((string)file_get_contents($file));
        $m = $parsed['meta'];
        $slug = (string)($m['slug'] ?? basename($file, '.md'));
        if (!self::validSlug($slug)) return null;
        return ['title' => (string)($m['title'] ?? $slug), 'slug' => $slug, 'status' => in_array($m['status'] ?? '', ['draft','published'], true) ? $m['status'] : 'draft', 'menu' => in_array($m['menu'] ?? '', ['header','sidebar','none'], true) ? $m['menu'] : 'none', 'order' => (int)($m['order'] ?? 0), 'content' => $parsed['content'], 'mtime' => (int)filemtime($file)];
    }
}
