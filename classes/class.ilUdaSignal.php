<?php

declare(strict_types=1);

/**
 * "Signal" of a presentation: a tiny static JSON file in the web data
 * directory of the ILIAS client (data/<client>/xuda_signal_<obj_id>.json)
 * with the id and revision of the current session. It changes with every change of
 * the session (other question, voting opened/closed, results, end).
 *
 * The phones of the participants read this file every 1.5 seconds – the web
 * server delivers it without PHP – and ask join.php only when it changed
 * (and in any case every few seconds). Without the file (not writable,
 * other set-up) they simply ask join.php every time.
 *
 * Content: {"s": session id (0 = none), "r": revision}. Nothing else.
 *
 * Directly in the client directory, not in a sub-directory: ILIAS sends
 * every path data/x/y/z through its web access checker (.htaccess →
 * wac.php), which would start ILIAS and refuse visitors without session.
 */
class ilUdaSignal
{
    private const PREFIX = 'xuda_signal_';

    private static function dir(): ?string
    {
        return defined('CLIENT_WEB_DIR') ? CLIENT_WEB_DIR : null;
    }

    private static function file(int $obj_id): string
    {
        return self::PREFIX . $obj_id . '.json';
    }

    /** web address of the signal file, relative to the ILIAS address */
    public static function relativeUrl(int $obj_id): ?string
    {
        if (!defined('ILIAS_WEB_DIR') || !defined('CLIENT_ID')) {
            return null;
        }
        return ILIAS_WEB_DIR . '/' . CLIENT_ID . '/' . self::file($obj_id);
    }

    public static function write(int $obj_id, int $session_id, int $rev): void
    {
        $dir = self::dir();
        if ($dir === null || !is_dir($dir)) {
            return;
        }
        $tmp = @tempnam($dir, self::PREFIX);
        if ($tmp === false) {
            return;
        }
        // write and rename: readers never see a half written file
        if (@file_put_contents($tmp, (string) json_encode(['s' => $session_id, 'r' => $rev])) === false
            || !@chmod($tmp, 0644)
            || !@rename($tmp, $dir . '/' . self::file($obj_id))) {
            @unlink($tmp);
        }
    }

    public static function delete(int $obj_id): void
    {
        $dir = self::dir();
        if ($dir !== null && is_file($dir . '/' . self::file($obj_id))) {
            @unlink($dir . '/' . self::file($obj_id));
        }
    }

    public static function deleteAll(): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return;
        }
        foreach ((array) glob($dir . '/' . self::PREFIX . '*') as $file) {
            @unlink((string) $file);
        }
    }
}
