<?php

declare(strict_types=1);

/**
 * GUI class of the Udon ARS repository object.
 *
 * The views "Fragen" (editor), "Präsentieren" and "Ergebnisse" are a Vue
 * application (frontend/, built into js/dist/ars.js). It talks to this class
 * via the command "api" (POST, JSON). Participants use join.php.
 *
 * Permissions (ILIAS RBAC):
 * - visible: the object appears in the course/category
 * - read:    tab "Teilnehmen" (code, QR code, link to the participation page)
 * - write:   edit questions, present, results, settings
 *
 * @ilCtrl_isCalledBy ilObjUdonArsGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls      ilObjUdonArsGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjUdonArsGUI extends ilObjectPluginGUI
{
    private const TAB_QUESTIONS = 'questions';
    private const TAB_PRESENT = 'present';
    private const TAB_RESULTS = 'results';
    private const TAB_JOIN = 'join';
    private const TAB_PROPERTIES = 'properties';

    /** commands that need the permission "write" */
    private const WRITE_COMMANDS = [
        'showEditor', 'showPresenter', 'showResults', 'api', 'exportCsv',
        'editProperties', 'updateProperties',
    ];
    /** commands that need the permission "read" */
    private const READ_COMMANDS = ['showContent', 'showJoin'];

    private const MAX_REQUEST_BYTES = 262144;

    private ?ilUdaService $service = null;

    final public function getType(): string
    {
        return ilUdonArsPlugin::ID;
    }

    public function getAfterCreationCmd(): string
    {
        return 'showEditor';
    }

    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    private function service(): ilUdaService
    {
        return $this->service ??= ilUdaService::create();
    }

    private function ars(): ilObjUdonArs
    {
        /** @var ilObjUdonArs $object */
        $object = $this->object;
        return $object;
    }

    private function canWrite(): bool
    {
        return $this->access->checkAccess('write', '', $this->object->getRefId());
    }

    public function performCommand(string $cmd): void
    {
        if (in_array($cmd, self::WRITE_COMMANDS, true)) {
            $this->checkPermission('write');
            $this->$cmd();
            return;
        }

        $this->checkPermission('read');
        if (in_array($cmd, self::READ_COMMANDS, true)) {
            $this->$cmd();
            return;
        }
        $this->showContent();
    }

    protected function setTabs(): void
    {
        $ref_id = $this->object->getRefId();
        $write = $this->access->checkAccess('write', '', $ref_id);

        if ($write) {
            $this->tabs->addTab(self::TAB_QUESTIONS, $this->txt('tab_questions'), $this->ctrl->getLinkTarget($this, 'showEditor'));
            $this->tabs->addTab(self::TAB_PRESENT, $this->txt('tab_present'), $this->ctrl->getLinkTarget($this, 'showPresenter'));
            $this->tabs->addTab(self::TAB_RESULTS, $this->txt('tab_results'), $this->ctrl->getLinkTarget($this, 'showResults'));
        } elseif ($this->access->checkAccess('read', '', $ref_id)) {
            $this->tabs->addTab(self::TAB_JOIN, $this->txt('tab_join'), $this->ctrl->getLinkTarget($this, 'showJoin'));
        }

        $this->addInfoTab();

        if ($write) {
            $this->tabs->addTab(self::TAB_PROPERTIES, $this->txt('properties'), $this->ctrl->getLinkTarget($this, 'editProperties'));
        }

        $this->addPermissionTab();
    }

    protected function showContent(): void
    {
        if ($this->canWrite()) {
            $this->ctrl->redirect($this, 'showEditor');
        }
        $this->showJoin();
    }

    public function addInfoItems(ilInfoScreenGUI $info): void
    {
        $info->addSection($this->txt('info_participation'));
        $info->addProperty($this->txt('info_code'), $this->formatCode($this->ars()->getJoinCode()));
        $info->addProperty($this->txt('info_address'), '<a href="' . htmlspecialchars($this->joinUrl()) . '" target="_blank" rel="noopener">'
            . htmlspecialchars($this->displayUrl()) . '</a>');
    }

    // ------------------------------------------------------------------
    // Vue views
    // ------------------------------------------------------------------

    protected function showEditor(): void
    {
        $this->tabs->activateTab(self::TAB_QUESTIONS);
        $this->renderApp('editor');
    }

    protected function showPresenter(): void
    {
        $this->tabs->activateTab(self::TAB_PRESENT);
        $this->renderApp('presenter');
    }

    protected function showResults(): void
    {
        $this->tabs->activateTab(self::TAB_RESULTS);
        $this->renderApp('results');
    }

    protected function showJoin(): void
    {
        $this->tabs->activateTab(self::TAB_JOIN);
        $this->renderApp('join');
    }

    private function renderApp(string $view): void
    {
        $dist = $this->getPlugin()->getRelativeDirectory() . '/js/dist/';
        $version = $this->getAssetVersion();
        $this->tpl->addCss($dist . 'ars.css?v=' . $version);

        $palette = $this->ars()->getPalette();
        $config = [
            'view' => $view,
            'title' => $this->object->getTitle(),
            'code' => $this->ars()->getJoinCode(),
            'joinUrl' => $this->joinUrl(),
            'displayUrl' => $this->displayUrl(),
            'palette' => ilUdaPalettes::colours($palette),
            'resultMode' => $this->ars()->getResultMode(),
            'lang' => $this->lng->getLangKey(),
            'texts' => ilUdaTexts::forFrontend(fn(string $key): string => $this->txt($key)),
        ];
        if ($view !== 'join') {
            $config += [
                'apiUrl' => $this->ctrl->getFormAction($this, 'api', '', true),
                'exportUrl' => $this->ctrl->getLinkTarget($this, 'exportCsv', '', false, false),
                'presentUrl' => $this->ctrl->getLinkTarget($this, 'showPresenter'),
                'editorUrl' => $this->ctrl->getLinkTarget($this, 'showEditor'),
                'types' => ilUdaConfig::enabledTypes(),
                'quizLimits' => ilUdaService::quizLimits(),
                'quizPoints' => ilUdaService::QUIZ_POINTS,
                'quizBonus' => ilUdaService::QUIZ_BONUS,
            ];
        }

        $html = '<div class="uda-app" data-config="'
            . htmlspecialchars((string) json_encode($config, JSON_UNESCAPED_UNICODE), ENT_QUOTES)
            . '"></div>'
            . '<script type="module" src="'
            . htmlspecialchars($dist . 'ars.js?v=' . $version, ENT_QUOTES)
            . '"></script>';
        $this->tpl->setContent($html);
    }

    private function getAssetVersion(): string
    {
        $file = $this->getPlugin()->getDirectory() . '/js/dist/ars.js';
        return is_file($file) ? (string) filemtime($file) : '0';
    }

    /** full address of the participation page with the code */
    private function joinUrl(): string
    {
        return rtrim(ILIAS_HTTP_PATH, '/') . '/' . $this->getPlugin()->getRelativeDirectory()
            . '/join.php?c=' . $this->ars()->getJoinCode();
    }

    /** address shown to the audience: the short address of the configuration, else the page itself */
    private function displayUrl(): string
    {
        $short = ilUdaConfig::shortUrl();
        $url = $short !== '' ? $short : rtrim(ILIAS_HTTP_PATH, '/') . '/' . $this->getPlugin()->getRelativeDirectory() . '/join.php';
        return (string) preg_replace('#^https?://#i', '', $url);
    }

    private function formatCode(string $code): string
    {
        return strlen($code) === 6 ? substr($code, 0, 3) . ' ' . substr($code, 3) : $code;
    }

    // ------------------------------------------------------------------
    // JSON interface of the Vue application
    // ------------------------------------------------------------------

    protected function api(): void
    {
        global $DIC;
        $raw = (string) $DIC->http()->request()->getBody();
        if (strlen($raw) > self::MAX_REQUEST_BYTES) {
            $this->sendJson(['error' => 'request too large']);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $obj_id = $this->object->getId();
        $mode = $this->ars()->getResultMode();
        $repo = $this->service()->repo();
        $action = (string) ($data['action'] ?? '');

        switch ($action) {
            case 'questions':
                $this->sendJson(['questions' => $repo->getQuestions($obj_id)]);

            case 'add':
                $type = (string) ($data['type'] ?? '');
                if (!in_array($type, ilUdaConfig::enabledTypes(), true)) {
                    $this->sendJson(['error' => $this->txt('ui_err_type_disabled')]);
                }
                $id = $repo->addQuestion($obj_id, $this->service()->newQuestion($type));
                $this->sendJson(['id' => $id, 'questions' => $repo->getQuestions($obj_id)]);

            case 'duplicate':
                $question = $repo->getQuestion($obj_id, (int) ($data['id'] ?? 0));
                if ($question === null) {
                    $this->sendJson(['error' => 'not found']);
                }
                $id = $repo->addQuestion($obj_id, $question);
                $ids = [];
                foreach ($repo->getQuestions($obj_id) as $q) {
                    if ($q['id'] !== $id) {
                        $ids[] = $q['id'];
                    }
                    if ($q['id'] === $question['id']) {
                        $ids[] = $id;
                    }
                }
                $repo->reorderQuestions($obj_id, $ids);
                $this->sendJson(['id' => $id, 'questions' => $repo->getQuestions($obj_id)]);

            case 'save':
                $input = is_array($data['question'] ?? null) ? $data['question'] : [];
                $existing = $repo->getQuestion($obj_id, (int) ($input['id'] ?? 0));
                if ($existing === null) {
                    $this->sendJson(['error' => 'not found']);
                }
                $question = $this->service()->normalizeQuestion($input, $existing['type']);
                $repo->updateQuestion($obj_id, $question);
                $this->sendJson(['question' => $repo->getQuestion($obj_id, $question['id'])]);

            case 'delete':
                $repo->deleteQuestion($obj_id, (int) ($data['id'] ?? 0));
                $this->sendJson(['questions' => $repo->getQuestions($obj_id)]);

            case 'reorder':
                $known = array_column($repo->getQuestions($obj_id), 'id');
                $ids = array_values(array_filter(
                    array_map('intval', is_array($data['ids'] ?? null) ? $data['ids'] : []),
                    static fn(int $id): bool => in_array($id, $known, true)
                ));
                $repo->reorderQuestions($obj_id, array_values(array_unique(array_merge($ids, $known))));
                $this->sendJson(['questions' => $repo->getQuestions($obj_id)]);

            case 'present':
                $this->sendJson($this->presenterState());

            case 'start':
                if ($this->service()->startSession($obj_id, $mode) === null) {
                    $this->sendJson(['error' => $this->txt('ui_err_no_questions')] + $this->presenterState());
                }
                $this->sendJson($this->presenterState());

            case 'goto':
            case 'voting':
            case 'results':
            case 'reset':
            case 'end':
                $session = $this->service()->runningSession($obj_id, $mode);
                if ($session !== null && (int) ($data['sessionId'] ?? 0) === $session['id']) {
                    match ($action) {
                        'goto' => $this->service()->gotoQuestion($obj_id, $session['id'], (int) ($data['questionId'] ?? 0), $mode),
                        'voting' => $this->service()->setVoting($obj_id, $session['id'], ($data['open'] ?? false) === true, $mode),
                        'results' => $this->service()->setShowResults($session['id'], ($data['show'] ?? false) === true),
                        'reset' => $this->service()->resetQuestion($obj_id, $session['id'], $mode),
                        'end' => $this->service()->endSession($session['id']),
                    };
                }
                $this->sendJson($this->presenterState());

            case 'sessions':
                $this->service()->purgeOldResults();
                $this->sendJson(['sessions' => $repo->getSessions($obj_id), 'retentionDays' => ilUdaConfig::retentionDays()]);

            case 'sessionResults':
                $session = $repo->getSession($obj_id, (int) ($data['sessionId'] ?? 0));
                if ($session === null) {
                    $this->sendJson(['error' => 'not found']);
                }
                $list = [];
                foreach ($repo->getQuestions($obj_id) as $question) {
                    $results = $this->service()->results($session['id'], $question);
                    $list[] = ['question' => $question, 'results' => $this->presenterResults($results)];
                }
                $this->sendJson(['session' => $session, 'items' => $list]);

            case 'deleteSession':
                $session = $repo->getSession($obj_id, (int) ($data['sessionId'] ?? 0));
                if ($session !== null) {
                    if ($session['ended'] === 0) {
                        $this->service()->endSession($session['id']);
                    }
                    $repo->deleteSession($session['id']);
                }
                $this->sendJson(['sessions' => $repo->getSessions($obj_id)]);
        }
        $this->sendJson(['error' => 'unknown action']);
    }

    /** @return array<string, mixed> */
    private function presenterState(): array
    {
        $obj_id = $this->object->getId();
        $repo = $this->service()->repo();
        $questions = $repo->getQuestions($obj_id);
        $session = $this->service()->runningSession($obj_id, $this->ars()->getResultMode());
        $state = ['questions' => $questions, 'session' => null];
        if ($session === null) {
            return $state;
        }
        $question = null;
        foreach ($questions as $q) {
            if ($q['id'] === $session['quest_id']) {
                $question = $q;
            }
        }
        $state['session'] = [
            'id' => $session['id'],
            'started' => $session['started'],
            'questionId' => $session['quest_id'],
            'voting' => $session['voting'] === 1,
            'showResults' => $session['show_results'] === 1,
            'rev' => $session['rev'],
        ];
        $state['participants'] = $this->service()->participants($session['id']);
        if ($question !== null) {
            $state['remainingMs'] = $this->service()->remainingMs($session, $question);
            $state['results'] = $this->presenterResults($this->service()->results($session['id'], $question));
        }
        return $state;
    }

    /**
     * @param array<string, mixed> $results
     * @return array<string, mixed> results without participant tokens
     */
    private function presenterResults(array $results): array
    {
        if (isset($results['leaderboard'])) {
            $results['leaderboard'] = array_map(
                static fn(array $e): array => ['name' => $e['name'], 'points' => $e['points']],
                $results['leaderboard']
            );
        }
        return $results;
    }

    /** @param array<string, mixed> $data */
    private function sendJson(array $data): never
    {
        $this->sendResponse(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE),
            'application/json; charset=utf-8'
        );
    }

    private function sendResponse(string $body, string $content_type, ?string $filename = null): never
    {
        global $DIC;
        $http = $DIC->http();
        $response = $http->response()
            ->withHeader('Content-Type', $content_type)
            ->withHeader('Cache-Control', 'no-store')
            ->withBody(\ILIAS\Filesystem\Stream\Streams::ofString($body));
        if ($filename !== null) {
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }
        $http->saveResponse($response);
        $http->sendResponse();
        $http->close();
        exit;
    }

    // ------------------------------------------------------------------
    // CSV export of a session
    // ------------------------------------------------------------------

    protected function exportCsv(): void
    {
        global $DIC;
        $query = $DIC->http()->wrapper()->query();
        $session_id = $query->has('session_id')
            ? $query->retrieve('session_id', $DIC->refinery()->kindlyTo()->int())
            : 0;
        $repo = $this->service()->repo();
        $obj_id = $this->object->getId();
        $session = $repo->getSession($obj_id, $session_id);
        if ($session === null) {
            $this->ctrl->redirect($this, 'showResults');
        }

        $questions = [];
        $numbers = [];
        foreach ($repo->getQuestions($obj_id) as $i => $q) {
            $questions[$q['id']] = $q;
            $numbers[$q['id']] = $i + 1;
        }
        $pseudonyms = [];
        foreach ($repo->getParticipantTokens($session['id']) as $i => $token) {
            $pseudonyms[$token] = $this->txt('csv_participant') . ' ' . ($i + 1);
        }
        $names = $repo->getNicknames($session['id']);

        $rows = [[
            $this->txt('csv_question_nr'), $this->txt('csv_type'), $this->txt('csv_question'),
            $this->txt('csv_participant'), $this->txt('csv_name'), $this->txt('csv_answer'),
            $this->txt('csv_points'), $this->txt('csv_seconds'), $this->txt('csv_time'),
        ]];
        foreach ($repo->getAllResponses($session['id']) as $r) {
            $q = $questions[$r['quest_id']] ?? null;
            if ($q === null) {
                continue;
            }
            $texts = array_column($q['answers'], 'text', 'id');
            $answer = array_map(
                static fn($v): string => $q['type'] === 'cloud' ? (string) $v : (string) ($texts[$v] ?? $v),
                (array) $r['answer']
            );
            $rows[] = [
                (string) $numbers[$q['id']],
                $this->txt('ui_type_' . $q['type']),
                $q['title'],
                $pseudonyms[$r['token']] ?? '',
                $names[$r['token']] ?? '',
                implode($q['type'] === 'rank' ? ' > ' : ' | ', $answer),
                $q['type'] === 'quiz' ? (string) $r['points'] : '',
                number_format($r['answer_ms'] / 1000, 1, '.', ''),
                date('Y-m-d H:i:s', $r['created']),
            ];
        }

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(
                static fn(string $v): string => '"' . str_replace('"', '""', preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v) . '"',
                $row
            )) . "\r\n";
        }
        $filename = 'udon-ars-' . $obj_id . '-' . date('Y-m-d-Hi', $session['started']) . '.csv';
        $this->sendResponse($csv, 'text/csv; charset=utf-8', $filename);
    }

    // ------------------------------------------------------------------
    // Settings of the repository object
    // ------------------------------------------------------------------

    protected function editProperties(?ilPropertyFormGUI $form = null): void
    {
        $this->tabs->activateTab(self::TAB_PROPERTIES);

        if ($form === null) {
            $form = $this->initPropertiesForm();
            $form->setValuesByArray([
                'title' => $this->object->getTitle(),
                'desc' => $this->object->getLongDescription(),
                'palette' => $this->ars()->getPalette(),
                'result_mode' => $this->ars()->getResultMode(),
            ]);
        }

        $this->tpl->setContent($form->getHTML());
    }

    protected function updateProperties(): void
    {
        $form = $this->initPropertiesForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editProperties($form);
            return;
        }

        $this->object->setTitle((string) $form->getInput('title'));
        $this->object->setDescription((string) $form->getInput('desc'));
        $this->ars()->setPalette((string) $form->getInput('palette'));
        $this->ars()->setResultMode((string) $form->getInput('result_mode'));
        if ($form->getInput('renew_code')) {
            $this->ars()->renewJoinCode();
        }
        $this->object->update();

        $this->tpl->setOnScreenMessage('success', $this->lng->txt('msg_obj_modified'), true);
        $this->ctrl->redirect($this, 'editProperties');
    }

    private function initPropertiesForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->txt('properties'));
        $form->setFormAction($this->ctrl->getFormAction($this, 'updateProperties'));

        $title = new ilTextInputGUI($this->lng->txt('title'), 'title');
        $title->setRequired(true);
        $title->setMaxLength(ilObject::TITLE_LENGTH);
        $form->addItem($title);

        $desc = new ilTextAreaInputGUI($this->lng->txt('description'), 'desc');
        $desc->setRows(2);
        $form->addItem($desc);

        $palette = new ilRadioGroupInputGUI($this->txt('prop_palette'), 'palette');
        foreach (ilUdaPalettes::IDS as $id) {
            $option = new ilRadioOption($this->txt('palette_' . $id), $id);
            $option->setInfo($this->txt('palette_' . $id . '_info') . ilUdaPalettes::swatches($id));
            $palette->addOption($option);
        }
        $form->addItem($palette);

        $mode = new ilRadioGroupInputGUI($this->txt('prop_result_mode'), 'result_mode');
        $auto = new ilRadioOption($this->txt('prop_result_auto'), ilUdaService::RESULTS_AUTO);
        $auto->setInfo($this->txt('prop_result_auto_info'));
        $mode->addOption($auto);
        $release = new ilRadioOption($this->txt('prop_result_release'), ilUdaService::RESULTS_RELEASE);
        $release->setInfo($this->txt('prop_result_release_info'));
        $mode->addOption($release);
        $form->addItem($mode);

        $code = new ilNonEditableValueGUI($this->txt('info_code'), 'code_display');
        $code->setValue($this->formatCode($this->ars()->getJoinCode()));
        $code->setInfo($this->txt('prop_code_info'));
        $renew = new ilCheckboxInputGUI($this->txt('prop_renew_code'), 'renew_code');
        $renew->setInfo($this->txt('prop_renew_code_info'));
        $code->addSubItem($renew);
        $form->addItem($code);

        $form->addCommandButton('updateProperties', $this->lng->txt('save'));

        return $form;
    }
}
