<?php

declare(strict_types=1);

/**
 * The four colour palettes of Udon ARS. Each palette has four shades, from
 * light to dark: [0] surfaces, [1] borders and soft fills, [2] bars and
 * markers, [3] text accents and buttons (white text on it is readable).
 * Every question type works with every palette.
 */
class ilUdaPalettes
{
    public const IDS = ['natural', 'clear', 'warm', 'fresh'];
    public const DEFAULT = 'clear';

    private const COLOURS = [
        'natural' => ['#E0EAE3', '#AFCAB8', '#6D9B86', '#3B5B4C'],
        'clear' => ['#EAF3FE', '#9DC4F8', '#4E80EE', '#253985'],
        'warm' => ['#FDF2DE', '#F7CB94', '#E4916D', '#9D5145'],
        'fresh' => ['#F1E8FD', '#C1B6F8', '#A28CF3', '#652CD1'],
    ];

    public static function isValid(string $id): bool
    {
        return in_array($id, self::IDS, true);
    }

    /** palette id or, if unknown/empty, the standard palette of the plugin configuration */
    public static function resolve(string $id): string
    {
        return self::isValid($id) ? $id : ilUdaConfig::defaultPalette();
    }

    /** @return string[] the four shades */
    public static function colours(string $id): array
    {
        return self::COLOURS[self::resolve($id)];
    }

    /** small colour swatches as HTML (for the info texts of the forms) */
    public static function swatches(string $id): string
    {
        $html = ' <span style="display:inline-flex;gap:3px;vertical-align:middle">';
        foreach (self::COLOURS[$id] ?? [] as $colour) {
            $html .= '<span style="display:inline-block;width:16px;height:16px;border-radius:4px;'
                . 'border:1px solid rgba(0,0,0,.15);background:' . $colour . '"></span>';
        }
        return $html . '</span>';
    }
}
