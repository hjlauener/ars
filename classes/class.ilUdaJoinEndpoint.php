<?php

declare(strict_types=1);

/**
 * Page and JSON interface for participants (called by join.php).
 *
 * POST join.php?a=api, body JSON:
 *   {action: "state",  code, token}
 *   {action: "answer", code, token, questionId, answer}
 *   {action: "name",   code, token, name}
 * Response: the state of the participant (see state()), or {error: text}.
 *
 * token: random value of the device (localStorage). Logged-in people are
 * identified by their ILIAS account instead, so they answer once per
 * question even with several devices. Only a hash is stored.
 */
class ilUdaJoinEndpoint
{
    private const PREFIX = 'rep_robj_xuda_';
    private const MAX_BODY = 16384;
    private const MAX_NAME = 30;

    private ilUdaService $service;
    private ilLanguage $lng;
    private ilObjUser $user;
    private ilAccessHandler $access;

    public function __construct(private readonly string $plugin_dir)
    {
        global $DIC;
        $this->service = ilUdaService::create();
        $this->user = $DIC->user();
        $this->access = $DIC->access();
        $this->lng = new ilLanguage($this->chooseLanguage());
        $this->lng->loadLanguageModule('rep_robj_xuda');
    }

    public function run(): void
    {
        if (($_GET['a'] ?? '') === 'api') {
            $this->api();
        }
        $this->page();
    }

    private function txt(string $key): string
    {
        return $this->lng->txt(self::PREFIX . $key);
    }

    private function isAnonymous(): bool
    {
        $id = $this->user->getId();
        return $id === 0 || $id === ANONYMOUS_USER_ID;
    }

    /** language: of the ILIAS account, else of the browser (de, en, fr), else English */
    private function chooseLanguage(): string
    {
        $supported = ['de', 'en', 'fr'];
        $wanted = (string) ($_GET['lang'] ?? '');
        if (in_array($wanted, $supported, true)) {
            return $wanted;
        }
        if (!$this->isAnonymous() && in_array($this->user->getLanguage(), $supported, true)) {
            return $this->user->getLanguage();
        }
        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        foreach (explode(',', $header) as $part) {
            $code = substr(trim($part), 0, 2);
            if (in_array($code, $supported, true)) {
                return $code;
            }
        }
        return 'en';
    }

    /** address of the ILIAS installation (without the path of the plugin) */
    private function iliasUrl(): string
    {
        $path = (string) ILIAS_HTTP_PATH;
        $pos = strpos($path, '/Customizing/');
        return rtrim($pos === false ? $path : substr($path, 0, $pos), '/');
    }

    // ------------------------------------------------------------------
    // Page
    // ------------------------------------------------------------------

    private function page(): void
    {
        $code = preg_replace('/\D/', '', (string) ($_GET['c'] ?? ''));
        $obj_id = $code !== '' ? $this->service->repo()->lookupObjIdByCode($code) : 0;
        $data = $obj_id > 0 ? $this->service->repo()->getData($obj_id) : null;
        $palette = ilUdaPalettes::colours($data['palette'] ?? '');
        $dist = 'js/dist/';
        $version = is_file($this->plugin_dir . '/js/dist/join.js') ? (string) filemtime($this->plugin_dir . '/js/dist/join.js') : '0';

        $config = [
            'apiUrl' => 'join.php?a=api',
            'code' => $code,
            'lang' => $this->lng->getLangKey(),
            'palette' => $palette,
            'loggedIn' => !$this->isAnonymous(),
            'quizPoints' => ilUdaService::QUIZ_POINTS,
            'texts' => ilUdaTexts::forFrontend(fn(string $key): string => $this->txt($key)),
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $style = '';
        foreach ($palette as $i => $colour) {
            $style .= '--uda-p' . $i . ':' . $colour . ';';
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        echo '<!doctype html><html lang="' . htmlspecialchars($this->lng->getLangKey()) . '" style="' . $style . '">'
            . '<head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<meta name="robots" content="noindex">'
            . '<meta name="theme-color" content="' . $palette[3] . '">'
            . '<title>' . htmlspecialchars($this->txt('ui_join_page_title')) . '</title>'
            . '<link rel="icon" href="templates/images/icon_xuda.svg" type="image/svg+xml">'
            . '<link rel="stylesheet" href="' . $dist . 'ars.css?v=' . $version . '">'
            . '</head><body class="uda-join-body">'
            . '<div id="uda-join"></div>'
            . '<noscript>' . htmlspecialchars($this->txt('ui_noscript')) . '</noscript>'
            . '<script type="application/json" id="uda-config">' . $json . '</script>'
            . '<script type="module" src="' . $dist . 'join.js?v=' . $version . '"></script>'
            . '</body></html>';
        exit;
    }

    // ------------------------------------------------------------------
    // JSON interface
    // ------------------------------------------------------------------

    private function api(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->sendJson(['error' => 'POST only'], 405);
        }
        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);
        if (strlen($raw) > self::MAX_BODY) {
            $this->sendJson(['error' => $this->txt('ui_err_invalid_answer')], 413);
        }
        $request = json_decode($raw, true);
        if (!is_array($request)) {
            $this->sendJson(['error' => 'invalid request'], 400);
        }

        $code = preg_replace('/\D/', '', (string) ($request['code'] ?? ''));
        $obj_id = $code !== '' ? $this->service->repo()->lookupObjIdByCode($code) : 0;
        $data = $obj_id > 0 ? $this->service->repo()->getData($obj_id) : null;
        if ($data === null || !ilObject::_hasUntrashedReference($obj_id)) {
            $this->sendJson(['status' => 'unknown']);
        }

        $access = $this->checkAccess($obj_id);
        if ($access !== null) {
            $this->sendJson($access);
        }

        $token = $this->participantToken($request['token'] ?? null);
        if ($token === null) {
            $this->sendJson(['error' => 'invalid token'], 400);
        }

        $mode = $data['result_mode'];
        $error = null;
        switch ((string) ($request['action'] ?? 'state')) {
            case 'answer':
                $error = $this->service->submitAnswer(
                    $obj_id,
                    $mode,
                    $token,
                    (int) ($request['questionId'] ?? 0),
                    $request['answer'] ?? null
                );
                break;
            case 'name':
                $session = $this->service->runningSession($obj_id, $mode);
                if ($session !== null) {
                    $this->service->repo()->touchParticipant($session['id'], $token, time());
                    $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($request['name'] ?? ''))), 0, self::MAX_NAME);
                    $this->service->repo()->setNickname($session['id'], $token, $name);
                }
                break;
        }

        $state = $this->state($obj_id, $mode, $token);
        if ($error !== null) {
            $state['error'] = $this->txt($error);
        }
        $this->sendJson($state);
    }

    /** @return array<string, mixed>|null null if the person may take part */
    private function checkAccess(int $obj_id): ?array
    {
        $refs = ilObject::_getAllReferences($obj_id);
        if ($this->isAnonymous()) {
            if (ilUdaConfig::anonymousAllowed()) {
                return null;
            }
            $ref_id = (int) reset($refs);
            return [
                'status' => 'login',
                'loginUrl' => $this->iliasUrl() . '/goto.php?target=xuda_' . $ref_id,
            ];
        }
        foreach ($refs as $ref_id) {
            if ($this->access->checkAccess('read', '', (int) $ref_id)) {
                return null;
            }
        }
        return ilUdaConfig::anonymousAllowed() ? null : ['status' => 'denied'];
    }

    private function participantToken(mixed $device_token): ?string
    {
        if (!$this->isAnonymous()) {
            return ilUdaService::hashToken('user:' . $this->user->getId());
        }
        if (!is_string($device_token) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/', $device_token)) {
            return null;
        }
        return ilUdaService::hashToken('device:' . $device_token);
    }

    /**
     * State for the device of one participant.
     * @return array<string, mixed>
     */
    private function state(int $obj_id, string $mode, string $token): array
    {
        $repo = $this->service->repo();
        $title = ilObject::_lookupTitle($obj_id);
        $session = $this->service->runningSession($obj_id, $mode);
        if ($session === null) {
            return ['status' => 'waiting', 'title' => $title];
        }
        $repo->touchParticipant($session['id'], $token, time());

        $questions = $repo->getQuestions($obj_id);
        $question = null;
        $index = 0;
        foreach ($questions as $i => $q) {
            if ($q['id'] === $session['quest_id']) {
                $question = $q;
                $index = $i;
            }
        }
        $has_quiz = in_array('quiz', array_column($questions, 'type'), true);
        $state = [
            'status' => 'running',
            'title' => $title,
            'rev' => $session['rev'],
            'hasQuiz' => $has_quiz,
            'name' => $has_quiz ? $repo->getNickname($session['id'], $token) : '',
        ];
        if ($question === null) {
            return $state;
        }

        $response = $repo->getResponse($session['id'], $question['id'], $token);
        $state += [
            'question' => $this->service->publicQuestion($question),
            'position' => ['index' => $index + 1, 'count' => count($questions)],
            'voting' => $session['voting'] === 1,
            'remainingMs' => $this->service->remainingMs($session, $question),
            'myAnswer' => $response['answer'] ?? null,
            'showResults' => $session['show_results'] === 1,
        ];

        if ($question['type'] === 'quiz' && $session['voting'] !== 1) {
            $totals = $repo->getPointTotals($session['id']);
            $mine = $totals[$token] ?? null;
            // equal points, equal place
            $rank = $mine === null ? false : count(array_filter($totals, static fn(int $p): bool => $p > $mine));
            $state['quiz'] = [
                'correct' => array_values(array_column(array_filter($question['answers'], static fn(array $a): bool => $a['correct']), 'id')),
                'points' => $response['points'] ?? 0,
                'total' => $totals[$token] ?? 0,
                'rank' => $rank === false ? null : $rank + 1,
                'of' => count($totals),
            ];
        }

        if ($session['show_results'] === 1) {
            $results = $this->service->results($session['id'], $question);
            if (isset($results['leaderboard'])) {
                $results['leaderboard'] = array_map(static fn(array $e): array => [
                    'name' => $e['name'],
                    'points' => $e['points'],
                    'me' => $e['token'] === $token,
                ], $results['leaderboard']);
            }
            $state['results'] = $results;
        }
        return $state;
    }

    /** @param array<string, mixed> $data */
    private function sendJson(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
