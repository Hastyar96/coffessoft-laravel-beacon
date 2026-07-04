<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Reader;

/**
 * Parses Blade templates for HTML elements, forms, JavaScript references,
 * Livewire bindings, Alpine directives, and DOM structure.
 *
 * Uses regex-based extraction (Blade is not valid XML due to directives).
 * Every extracted element includes file:line evidence.
 */
class BladeHtmlParser
{
    /**
     * Extract all HTML forms from a Blade template.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractForms(string $contents): array
    {
        $forms = [];

        // <form ...> ... </form>
        $pattern = '/<form\s+([^>]*)>/si';
        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs = $this->parseAttributes($match[1]);

                // Resolve action route
                $action = $attrs['action'] ?? null;
                $resolvedRoute = $this->resolveRouteHelper($match[0]);

                $forms[] = [
                    'action' => $action,
                    'resolved_route' => $resolvedRoute,
                    'method' => $this->resolveFormMethod($attrs, $contents, $match[0]),
                    'id' => $attrs['id'] ?? null,
                    'name' => $attrs['name'] ?? null,
                    'class' => $attrs['class'] ?? null,
                    'multipart' => isset($attrs['enctype']) && str_contains($attrs['enctype'], 'multipart'),
                    'has_csrf' => str_contains($contents, '@csrf') || str_contains($contents, 'csrf_token'),
                    'has_validation' => str_contains($match[0], 'novalidate') === false,
                    'wire_submit' => $attrs['wire:submit'] ?? null,
                    'line' => $this->findLine($contents, $match[0]),
                    'element_id' => $this->elementId($match[0]),
                ];
            }
        }

        return $forms;
    }

    /**
     * Extract all HTML elements with their attributes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractElements(string $contents): array
    {
        $elements = [];
        $elementTypes = [
            'button' => '/<button\s+([^>]*)>/si',
            'a' => '/<a\s+([^>]*)>(.*?)<\/a>/si',
            'input' => '/<input\s+([^>]*)>/si',
            'select' => '/<select\s+([^>]*)>/si',
            'textarea' => '/<textarea\s+([^>]*)>/si',
            'table' => '/<table\s+([^>]*)>/si',
            'modal' => '/<div[^>]*class="[^"]*modal[^"]*"[^>]*>/si',
            'div' => '/<div\s+([^>]*)>/si',
            'img' => '/<img\s+([^>]*)>/si',
            'link' => '/<link\s+([^>]*)>/si',
        ];

        foreach ($elementTypes as $type => $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $attrs = $this->parseAttributes($match[1]);

                    $elements[] = [
                        'type' => $type,
                        'id' => $attrs['id'] ?? null,
                        'class' => $attrs['class'] ?? null,
                        'name' => $attrs['name'] ?? null,
                        'wire' => $this->extractWireDirectives($match[1]),
                        'alpine' => $this->extractAlpineDirectives($match[1]),
                        'x_data' => $attrs['x-data'] ?? null,
                        'x_show' => $attrs['x-show'] ?? null,
                        'x_bind' => $attrs['x-bind'] ?? null,
                        'x_model' => $attrs['x-model'] ?? null,
                        'x_on' => $this->extractXOn($match[1]),
                        'href' => $attrs['href'] ?? null,
                        'onclick' => $attrs['onclick'] ?? null,
                        'onchange' => $attrs['onchange'] ?? null,
                        'onsubmit' => $attrs['onsubmit'] ?? null,
                        'data' => $this->extractDataAttributes($match[1]),
                        'target' => $attrs['target'] ?? null,
                        'wire_key' => $attrs['wire:key'] ?? null,
                        'line' => $this->findLine($contents, $match[0]),
                        'element_id' => $this->elementId($match[0]),
                    ];
                }
            }
        }

        return $elements;
    }

    /**
     * Extract JavaScript blocks (inline scripts, external references).
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractJavaScript(string $contents): array
    {
        $scripts = [];

        // Inline <script> blocks
        if (preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/si', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs = $this->parseAttributes($match[1]);

                $scripts[] = [
                    'type' => 'inline',
                    'src' => $attrs['src'] ?? null,
                    'attributes' => $attrs,
                    'content' => trim($match[2]),
                    'content_length' => strlen(trim($match[2])),
                    'uses_jquery' => str_contains($match[2], '$') || str_contains($match[2], 'jQuery'),
                    'uses_alpine' => str_contains($match[2], 'Alpine'),
                    'uses_livewire' => str_contains($match[2], 'Livewire') || str_contains($match[2], 'livewire'),
                    'has_ajax' => $this->hasAjax($match[2]),
                    'has_datatables' => str_contains($match[2], 'DataTable') || str_contains($match[2], 'dataTable'),
                    'line' => $this->findLine($contents, $match[0]),
                    'element_id' => $this->elementId($match[0]),
                ];
            }
        }

        // @vite or mix directives for scripts
        if (preg_match_all('/@vite\s*\(\s*\[([^\]]*)\]\s*\)/', $contents, $viteMatches)) {
            foreach ($viteMatches[1] as $resources) {
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $resources, $files);
                foreach ($files[1] as $file) {
                    $scripts[] = [
                        'type' => 'vite_entry',
                        'src' => $file,
                        'line' => $this->findLine($contents, $viteMatches[0][0] ?? ''),
                    ];
                }
            }
        }

        if (preg_match_all('/@stack\s*\(\s*[\'"]scripts[\'"]\s*\)/', $contents, $stackMatches)) {
            foreach ($stackMatches[0] as $match) {
                $scripts[] = [
                    'type' => 'stack',
                    'name' => 'scripts',
                    'line' => $this->findLine($contents, $match),
                ];
            }
        }

        return $scripts;
    }

    /**
     * Extract CSS references (links, stacks, vite).
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractStyles(string $contents): array
    {
        $styles = [];

        // <link rel="stylesheet" ...>
        if (preg_match_all('/<link\s+([^>]*rel=["\']stylesheet["\'][^>]*)>/si', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs = $this->parseAttributes($match[1]);
                $styles[] = [
                    'href' => $attrs['href'] ?? null,
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }

        // @vite for CSS
        if (preg_match_all('/@vite\s*\(\s*\[([^\]]*)\]\s*\)/', $contents, $viteMatches)) {
            foreach ($viteMatches[1] as $resources) {
                preg_match_all('/[\'"]([^\'"]+\.css)[\'"]/', $resources, $files);
                foreach ($files[1] as $file) {
                    $styles[] = [
                        'type' => 'vite_css',
                        'src' => $file,
                        'line' => $this->findLine($contents, $viteMatches[0][0] ?? ''),
                    ];
                }
            }
        }

        // @stack('styles')
        if (str_contains($contents, '@stack')) {
            preg_match_all('/@stack\s*\(\s*[\'"]styles[\'"]\s*\)/', $contents, $matches);
            foreach ($matches[0] as $match) {
                $styles[] = [
                    'type' => 'stack',
                    'name' => 'styles',
                    'line' => $this->findLine($contents, $match),
                ];
            }
        }

        return $styles;
    }

    /**
     * Extract all {{ $variable }} usages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractVariables(string $contents): array
    {
        $variables = [];

        if (preg_match_all('/\{\{\s*\$(\w+(?:->\w+)*(?:\[\'?\w+\'?\])*)\s*\}\}/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $variables[] = [
                    'expression' => $match[1],
                    'variable' => strtok($match[1], '->'),
                    'chained' => str_contains($match[1], '->'),
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }

        // @{{ }} unescaped
        if (preg_match_all('/\{!!\s*\$(\w+(?:->\w+)*)\s*!!\}/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $variables[] = [
                    'expression' => $match[1],
                    'variable' => strtok($match[1], '->'),
                    'chained' => str_contains($match[1], '->'),
                    'unescaped' => true,
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }

        return $variables;
    }

    /**
     * Extract Livewire-specific directives and bindings.
     */
    private function extractWireDirectives(string $attributeString): array
    {
        $wire = [];
        $directives = ['model', 'click', 'submit', 'change', 'keydown', 'keyup', 'loading', 'target', 'ignore', 'dirty'];

        foreach ($directives as $dir) {
            if (preg_match('/wire:' . preg_quote($dir, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/', $attributeString, $m)) {
                $wire[$dir] = $m[1];
            }
        }

        return $wire;
    }

    /**
     * Extract Alpine.js directives (x-data, x-show, x-bind, etc.).
     */
    private function extractAlpineDirectives(string $attributeString): array
    {
        $alpine = [];
        $directives = ['data', 'show', 'bind', 'model', 'on', 'text', 'html', 'ref', 'cloak', 'teleport', 'if', 'for'];

        foreach ($directives as $dir) {
            if (preg_match('/x-' . preg_quote($dir, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/', $attributeString, $m)) {
                $alpine[$dir] = $m[1];
            }
        }

        return $alpine;
    }

    /**
     * Extract x-on:event handlers.
     */
    private function extractXOn(string $attributeString): array
    {
        $handlers = [];
        $events = ['click', 'submit', 'change', 'keydown', 'keyup', 'keypress', 'input', 'blur', 'focus', 'mouseenter', 'mouseleave'];

        foreach ($events as $event) {
            if (preg_match('/x-on:' . preg_quote($event, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/', $attributeString, $m)) {
                $handlers[] = [
                    'event' => $event,
                    'handler' => $m[1],
                ];
            }
        }

        // Short syntax: @click, @submit, etc.
        foreach ($events as $event) {
            if (preg_match('/@' . preg_quote($event, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]/', $attributeString, $m)) {
                $handlers[] = [
                    'event' => $event,
                    'handler' => $m[1],
                    'short_syntax' => true,
                ];
            }
        }

        return $handlers;
    }

    /**
     * Extract data-* attributes.
     */
    private function extractDataAttributes(string $attributeString): array
    {
        $data = [];
        if (preg_match_all('/data-([\w-]+)\s*=\s*[\'"]([^\'"]*)[\'"]/', $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $data[$match[1]] = $match[2];
            }
        }
        return $data;
    }

    /**
     * Parse HTML attribute string into key-value pairs.
     */
    private function parseAttributes(string $attrString): array
    {
        $attrs = [];
        if (preg_match_all('/([\w:-]+)\s*=\s*"([^"]*)"/', $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
        }
        if (preg_match_all("/([\w:-]+)\s*=\s*'([^']*)'/", $attrString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs[$match[1]] = $match[2];
            }
        }
        return $attrs;
    }

    /**
     * Resolve route() helper to actual route name.
     */
    private function resolveRouteHelper(string $content): ?string
    {
        if (preg_match('/\{\{\s*route\(\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Resolve form method from attributes and @method directive.
     */
    private function resolveFormMethod(array $attrs, string $contents, string $formTag): string
    {
        $method = strtoupper($attrs['method'] ?? 'GET');

        // Check for @method('PUT') / @method('PATCH') / @method('DELETE') after the form tag
        $formEndPos = strpos($contents, $formTag) + strlen($formTag);
        $afterForm = substr($contents, $formEndPos, 500);
        if (preg_match('/@method\s*\(\s*[\'"](\w+)[\'"]\s*\)/', $afterForm, $m)) {
            return strtoupper($m[1]);
        }

        return $method;
    }

    /**
     * Check if JavaScript content contains AJAX calls.
     */
    private function hasAjax(string $js): bool
    {
        $ajaxPatterns = [
            '/\$\s*\.\s*(?:get|post|ajax|load)\s*\(/',
            '/fetch\s*\(/',
            '/axios\s*\.\s*(?:get|post|put|delete|patch)\s*\(/',
            '/XMLHttpRequest/',
            '/Livewire\./',
        ];

        foreach ($ajaxPatterns as $pattern) {
            if (preg_match($pattern, $js)) {
                return true;
            }
        }

        return false;
    }

    private function findLine(string $contents, string $substring): int
    {
        $pos = strpos($contents, $substring);
        if ($pos === false) return 0;
        return substr_count(substr($contents, 0, $pos), "\n") + 1;
    }

    /**
     * Generate a unique internal element identifier based on position.
     */
    private function elementId(string $match): string
    {
        return 'el_' . md5($match);
    }
}