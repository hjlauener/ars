<?php

declare(strict_types=1);

/**
 * Texts for the TypeScript frontend: every language variable of the plugin
 * whose key starts with "ui_" (keys taken from lang/ilias_en.lang), in the
 * language of the page.
 */
class ilUdaTexts
{
    /** @var string[]|null */
    private static ?array $keys = null;

    /**
     * @param callable(string): string $txt translates a key without plugin prefix
     * @return array<string, string> key without "ui_" => text
     */
    public static function forFrontend(callable $txt): array
    {
        $texts = [];
        foreach (self::keys() as $key) {
            $texts[substr($key, 3)] = $txt($key);
        }
        return $texts;
    }

    /** @return string[] */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }
        self::$keys = [];
        $file = dirname(__DIR__) . '/lang/ilias_en.lang';
        foreach (is_file($file) ? (array) file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
            $key = strstr((string) $line, '#:#', true);
            if ($key !== false && str_starts_with($key, 'ui_')) {
                self::$keys[] = $key;
            }
        }
        return self::$keys;
    }
}
