<?php

namespace App\Support;

/**
 * Finds (and, on request, wraps in __()) the visible English text in a Blade
 * view: plain text between tags, common attributes (placeholder, aria-label,
 * title, alt, label, ...) and label-like values in PHP arrays.
 *
 * It is deliberately conservative — anything it isn't sure about (text mixed
 * with {{ }}, directives, scripts, comments) is left alone and reported by
 * neither mode, so those sentences are handled by hand.
 */
class BladeText
{
    /** Names that stay as they are in every language. */
    private const BRAND = ['valueAFRIK', 'valueAfrik', 'Bridge Score', 'Google', 'Facebook', 'LiveKit'];

    private const ATTRS = 'placeholder|aria-label|title|alt|label|description|heading|subheading|tooltip';

    private const ARRAY_KEYS = 'label|title|desc|sub|hint|heading|subheading|eyebrow|short|placeholder|description|cta|body|text|publish|viewers';

    private const PAREN_DIRECTIVES = 'if|elseif|foreach|forelse|for|while|unless|isset|empty|auth|guest|can|cannot|canany|include|includeIf|extends|section|props|class|json|js|error|lang|disabled|checked|selected|persist|livewire|continue|break|use|env|production|switch|case|inject|aware|yield|push|pushOnce|prepend|prependOnce|stack|slot|component|endcomponent|hasSection|sectionMissing|dd|dump|method|vite';

    private const BARE_DIRECTIVES = 'auth|guest|enderror|endenderror|else|endif|endforeach|endforelse|endfor|endwhile|endunless|endisset|endempty|endauth|endguest|endcan|endcannot|endsection|endpersist|empty|endclass|stack|push|endpush|csrf|method|fluxAppearance|fluxScripts|vite|fonts|default|endswitch|once|endonce|verbatim|endverbatim|endprepend|endslot|endpushOnce|endprependOnce|endphp|break|continue|livewireStyles|livewireScripts|endenv|endproduction|endcomponent';

    /** @return list<string> the English strings that are not yet wrapped */
    public static function scan(string $source): array
    {
        return self::process($source)[1];
    }

    /**
     * English the wrapper can't safely rewrite but that still needs translating:
     * tooltip content and confirm dialogs, and capitalised string literals in
     * ternary / concatenation branches inside {{ }} (e.g. {{ $ok ? 'Saved' : 'Save' }}).
     *
     * @return list<string>
     */
    public static function suspects(string $source): array
    {
        $found = [];
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        if (preg_match_all('/(?<![:\w\-@.])(?:wire:confirm|content)="([^"<>{}]*[A-Za-z][^"<>{}]*)"/', $source, $m)) {
            foreach ($m[1] as $v) {
                if (! preg_match('/^(width|initial|text\/|utf|no|yes)/i', $v) && str_contains($source, 'flux:tooltip') || str_starts_with($v, 'Delete') || str_starts_with($v, 'Block')) {
                    $found[] = $v;
                }
            }
        }

        if (preg_match_all('/\{\{(.*?)\}\}/s', $source, $blocks)) {
            foreach ($blocks[1] as $expr) {
                $expr = preg_replace('/__\(\s*([\'"]).*?\1[^)]*\)/s', '', $expr);
                if (preg_match_all('/(?:\?\?|\?|:|\.)\s*\'([A-Z][a-z][^\']*)\'/', $expr, $lits)) {
                    foreach ($lits[1] as $lit) {
                        if (! in_array($lit, self::BRAND, true) && ! preg_match('/[a-z][A-Z]/', $lit) && ! preg_match('/^(WallPost|BridgePost|CommunityPost)$/', $lit)) {
                            $found[] = $lit;
                        }
                    }
                }
            }
        }

        // Capitalised label values in PHP arrays: ['all' => 'All', 'books' => 'Books']
        $php = preg_replace(['/\{\{--.*?--\}\}/s', '/__\(\s*([\'"]).*?\1[^)]*\)/s'], '', $source);
        if (preg_match_all('/=>\s*\'([A-Z][a-z][^\'\\\\$]*)\'/', $php, $values)) {
            foreach ($values[1] as $value) {
                if (! in_array(trim($value), self::BRAND, true) && ! preg_match('/(::|\.php|^Y-m|^M d|^D MMM|^valueAFRIK )/', $value)) {
                    $found[] = $value;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /** @return array{0: string, 1: list<string>} [rewritten source, strings wrapped] */
    public static function process(string $source): array
    {
        $found = [];
        [$work, $store] = self::protect($source);

        $work = self::wrapTextNodes($work, $found);
        $work = self::wrapAttributes($work, $found);
        $out = self::restore($work, $store);
        $out = self::wrapArrayValues($out, $found);

        return [$out, $found];
    }

    // ── regions that must never be touched ──────────────────────────────

    private static function protect(string $src): array
    {
        $store = [];
        $put = function (string $text) use (&$store): string {
            $store[] = $text;

            return "\x00".(count($store) - 1)."\x00";
        };

        foreach ([
            '/<\?php.*?\?>/s', '/@php\b.*?@endphp/s', '/\{\{--.*?--\}\}/s', '/<!--.*?-->/s',
            '/<script\b.*?<\/script>/s', '/<style\b.*?<\/style>/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s',
        ] as $pattern) {
            $src = preg_replace_callback($pattern, fn ($m) => $put($m[0]), $src);
        }

        // Directives with (...) arguments, matched with balanced parentheses.
        $out = '';
        $i = 0;
        preg_match_all('/@(?:'.self::PAREN_DIRECTIVES.')\b[ \t]*\(/', $src, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$text, $offset]) {
            if ($offset < $i) {
                continue;
            }
            $end = self::matchParen($src, $offset + strlen($text) - 1);
            if ($end === -1) {
                continue;
            }
            $out .= substr($src, $i, $offset - $i).$put(substr($src, $offset, $end - $offset + 1));
            $i = $end + 1;
        }
        $src = $out.substr($src, $i);

        $src = preg_replace_callback('/@(?:'.self::BARE_DIRECTIVES.')\b/', fn ($m) => $put($m[0]), $src);

        return [$src, $store];
    }

    private static function matchParen(string $s, int $open): int
    {
        $depth = 0;
        $quote = null;
        for ($j = $open, $n = strlen($s); $j < $n; $j++) {
            $c = $s[$j];
            if ($quote) {
                if ($c === '\\') {
                    $j++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return -1;
    }

    private static function restore(string $src, array $store): string
    {
        while (str_contains($src, "\x00")) {
            $src = preg_replace_callback('/\x00(\d+)\x00/', fn ($m) => $store[(int) $m[1]], $src);
        }

        return $src;
    }

    // ── the three kinds of text ─────────────────────────────────────────

    private static function wrapTextNodes(string $src, array &$found): string
    {
        // Text between a closing ">" and the next tag. Whitespace is split off in
        // code, not in the pattern, so long text runs can't blow PCRE's backtrack limit.
        $result = preg_replace_callback('/>([^<>]+)<(?=[\/A-Za-z!])/u', function ($m) use (&$found) {
            $raw = $m[1];

            if (str_contains($raw, "\x00") || ! preg_match('/\p{L}/u', html_entity_decode($raw))) {
                return $m[0];
            }

            preg_match('/^(\s*)(.*?)(\s*)$/su', $raw, $parts);
            [, $lead, $text, $trail] = $parts;

            $key = self::clean($text);
            if (mb_strlen($key) < 2 || str_starts_with($key, 'http') || in_array(mb_strtolower($key), array_map('mb_strtolower', self::BRAND), true)) {
                return $m[0];
            }

            $found[] = $key;

            return '>'.$lead.'{{ __(\''.self::esc($key).'\') }}'.$trail.'<';
        }, $src);

        return $result ?? throw new \RuntimeException('Blade text scan failed: '.preg_last_error_msg());
    }

    private static function wrapAttributes(string $src, array &$found): string
    {
        return preg_replace_callback('/(?<![:\w\-@.])('.self::ATTRS.')="([^"<>]*)"/', function ($m) use (&$found) {
            if (str_contains($m[2], "\x00") || ! preg_match('/[A-Za-z]/', $m[2]) || preg_match('/^([\w.+-]+@[\w.-]+|[xX•-]+)$/', $m[2])) {
                return $m[0];
            }

            $key = self::clean($m[2]);
            $found[] = $key;

            return $m[1].'="{{ __(\''.self::esc($key).'\') }}"';
        }, $src);
    }

    private static function wrapArrayValues(string $src, array &$found): string
    {
        return preg_replace_callback('/\'('.self::ARRAY_KEYS.')\'\s*=>\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', function ($m) use (&$found) {
            [, $key, $value] = $m;

            if (str_starts_with($value, '__(') || str_starts_with($value, '<') || preg_match('/^[a-z_]+(:[^|]*)?(\|[a-z_]+(:[^|]*)?)+$/', $value) || ! preg_match('/[A-Za-z]{2}/', $value) || preg_match('/^[a-z0-9_.\-]+$/', $value) && str_contains($value, '.')) {
                return $m[0];
            }

            $found[] = str_replace("\\'", "'", $value);

            return "'{$key}' => __('{$value}')";
        }, $src);
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text)));
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
    }
}
