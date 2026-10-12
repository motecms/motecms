<?php
declare(strict_types=1);

namespace MoteCMS;

final class Markdown
{
    private static ?array $compiledPatterns = null;

    public function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $html = '';
        $paragraph = [];
        $list = false;
        $code = false;
        $codeLines = [];

        $flushParagraph = function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html .= '<p>' . $this->inline(implode(' ', $paragraph)) . "</p>\n";
                $paragraph = [];
            }
        };
        $closeList = function () use (&$html, &$list): void {
            if ($list) {
                $html .= "</ul>\n";
                $list = false;
            }
        };
        
        // Initialize compiled patterns
        if (self::$compiledPatterns === null) {
            self::$compiledPatterns = [
                'heading' => '/^(#{1,3})\s+(.+)$/',
                'list' => '/^-\s+(.+)$/',
                'link' => '/\[([^\]]+)\]\(([^)\s]+)\)/',
                'inline_code' => '/`([^`]+)`/',
                'bold' => '/\*\*([^*]+)\*\*/',
                'emphasis' => '/(?<!\*)\*([^*]+)\*(?!\*)/'
            ];
        }

        foreach ($lines as $line) {
            if (trim($line) === '```') {
                if ($code) {
                    $html .= '<pre><code>' . $this->esc(implode("\n", $codeLines)) . "</code></pre>\n";
                    $code = false;
                    $codeLines = [];
                } else {
                    $flushParagraph();
                    $closeList();
                    $code = true;
                }
                continue;
            }
            if ($code) {
                $codeLines[] = $line;
                continue;
            }
            $trim = trim($line);
            if ($trim === '') {
                $flushParagraph();
                $closeList();
                continue;
            }
            if (preg_match(self::$compiledPatterns['heading'], $trim, $m)) {
                $flushParagraph();
                $closeList();
                $level = strlen($m[1]);
                $html .= '<h' . $level . '>' . $this->inline($m[2]) . '</h' . $level . ">\n";
                continue;
            }
            if (preg_match(self::$compiledPatterns['list'], $trim, $m)) {
                $flushParagraph();
                if (!$list) {
                    $html .= "<ul>\n";
                    $list = true;
                }
                $html .= '<li>' . $this->inline($m[1]) . "</li>\n";
                continue;
            }
            $paragraph[] = $trim;
        }
        if ($code) {
            $html .= '<pre><code>' . $this->esc(implode("\n", $codeLines)) . "</code></pre>\n";
        }
        $flushParagraph();
        $closeList();
        return $html;
    }

    private function inline(string $text): string
    {
        $safe = $this->esc($text);
        
        // Process links
        $safe = preg_replace_callback(
            self::$compiledPatterns['link'],
            function (array $m): string {
                $label = $m[1];
                $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
                if (!in_array($scheme, ['http', 'https', 'mailto'], true)) {
                    return $label;
                }
                return '<a href="' . $this->esc($url) . '">' . $label . '</a>';
            },
            $safe
        ) ?? $safe;
        
        // Process inline code
        $safe = preg_replace(
            self::$compiledPatterns['inline_code'],
            '<code>$1</code>',
            $safe
        ) ?? $safe;
        
        // Process bold
        $safe = preg_replace(
            self::$compiledPatterns['bold'],
            '<strong>$1</strong>',
            $safe
        ) ?? $safe;
        
        // Process emphasis
        return preg_replace(
            self::$compiledPatterns['emphasis'],
            '<em>$1</em>',
            $safe
        ) ?? $safe;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
