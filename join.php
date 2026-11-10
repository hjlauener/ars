<?php

declare(strict_types=1);

/**
 * Udon ARS – public page for participants (smartphone): join with the
 * six-digit code or the QR code, answer, see results.
 *
 * Works with and without ILIAS login: ILIAS is started without redirect to
 * the login page. Logged-in people are recognised by their ILIAS session;
 * others take part anonymously if the plugin configuration allows it.
 *
 *   GET  join.php?c=123456   page
 *   POST join.php?a=api      JSON interface of the page (see ilUdaJoinEndpoint)
 */

$root = __DIR__;
while (!is_file($root . '/ilias.ini.php')) {
    $parent = dirname($root);
    if ($parent === $root) {
        http_response_code(500);
        exit('ILIAS not found');
    }
    $root = $parent;
}

chdir($root . '/public');
require_once $root . '/vendor/composer/vendor/autoload.php';
require_once $root . '/artifacts/bootstrap_default.php';

// plugin classes, also if the autoloader of ILIAS does not know them yet
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/classes/class.' . $class . '.php';
    if (preg_match('/^il(Uda|UdonArs|ObjUdonArs)\w*$/', $class) && is_file($file)) {
        require_once $file;
    }
});

// With a session cookie (e.g. logged in to ILIAS): web access checker context,
// the ILIAS session tells who it is. Without: REST context, no session at all,
// so that the many requests of anonymous phones do not create or write sessions.
ilContext::init(isset($_COOKIE[session_name()]) ? ilContext::CONTEXT_WAC : ilContext::CONTEXT_REST);
entry_point('ILIAS Legacy Initialisation Adapter');

(new ilUdaJoinEndpoint(__DIR__))->run();
