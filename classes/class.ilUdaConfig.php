<?php

declare(strict_types=1);

/**
 * Global configuration of the plugin (Administration › Plugins › UdonArs ›
 * Konfigurieren). Deliberately small: every value has a default, so the
 * plugin works without ever opening the configuration.
 *
 * - anonymous:   participation without login (code / QR code) allowed
 * - types:       question types that can be added
 * - palette:     standard colour palette of new presentations
 * - retention:   delete results of ended sessions after N days (0 = keep)
 * - short_url:   optional short address shown for participation
 */
class ilUdaConfig
{
    public const TYPES = ['choice', 'cloud', 'rank', 'quiz'];

    private const MODULE = 'xuda';
    private const KEY_ANONYMOUS = 'anonymous';
    private const KEY_TYPES = 'types';
    private const KEY_PALETTE = 'palette';
    private const KEY_RETENTION = 'retention';
    private const KEY_SHORT_URL = 'short_url';
    private const KEY_SALT = 'salt';

    private static ?ilSetting $settings = null;

    private static function settings(): ilSetting
    {
        return self::$settings ??= new ilSetting(self::MODULE);
    }

    public static function anonymousAllowed(): bool
    {
        return self::settings()->get(self::KEY_ANONYMOUS, '1') === '1';
    }

    /** @return string[] enabled question types (in the standard order) */
    public static function enabledTypes(): array
    {
        $value = self::settings()->get(self::KEY_TYPES, null);
        if ($value === null) {
            return self::TYPES;
        }
        return array_values(array_intersect(self::TYPES, explode(',', $value)));
    }

    public static function defaultPalette(): string
    {
        $id = (string) self::settings()->get(self::KEY_PALETTE, ilUdaPalettes::DEFAULT);
        return ilUdaPalettes::isValid($id) ? $id : ilUdaPalettes::DEFAULT;
    }

    public static function retentionDays(): int
    {
        return max(0, (int) self::settings()->get(self::KEY_RETENTION, '0'));
    }

    public static function shortUrl(): string
    {
        return trim((string) self::settings()->get(self::KEY_SHORT_URL, ''));
    }

    /** @param string[] $types */
    public static function save(bool $anonymous, array $types, string $palette, int $retention, string $short_url): void
    {
        $s = self::settings();
        $s->set(self::KEY_ANONYMOUS, $anonymous ? '1' : '0');
        $s->set(self::KEY_TYPES, implode(',', array_values(array_intersect(self::TYPES, $types))));
        $s->set(self::KEY_PALETTE, ilUdaPalettes::isValid($palette) ? $palette : ilUdaPalettes::DEFAULT);
        $s->set(self::KEY_RETENTION, (string) max(0, $retention));
        $s->set(self::KEY_SHORT_URL, trim($short_url));
    }

    /**
     * Secret of this installation; participant tokens are stored only as
     * hash with it, so that stored values cannot be traced back.
     */
    public static function salt(): string
    {
        $salt = (string) self::settings()->get(self::KEY_SALT, '');
        if ($salt === '') {
            $salt = bin2hex(random_bytes(16));
            self::settings()->set(self::KEY_SALT, $salt);
        }
        return $salt;
    }

    public static function deleteAll(): void
    {
        self::settings()->deleteAll();
    }
}
