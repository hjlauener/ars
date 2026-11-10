<?php

declare(strict_types=1);

/**
 * Logic of Udon ARS, shared by the presenter (ilObjUdonArsGUI) and the
 * participants (join.php, ilUdaJoinEndpoint):
 * - question types and their validation
 * - sessions (start, navigate, open/close voting, show results, end)
 * - answers, quiz points and the aggregated results
 *
 * Only aggregated results leave the server; single answers only in the
 * CSV export of the presenter.
 */
class ilUdaService
{
    /** answers per question type: minimum shown in a new question, maximum */
    private const MAX_ANSWERS = ['choice' => 10, 'cloud' => 0, 'rank' => 10, 'quiz' => 6];
    private const NEW_ANSWERS = ['choice' => 3, 'cloud' => 0, 'rank' => 3, 'quiz' => 4];
    private const MAX_TITLE = 300;
    private const MAX_ANSWER_TEXT = 150;
    private const MAX_WORD = 40;
    private const MAX_WORDS_LIMIT = 10;
    private const QUIZ_LIMITS = [0, 10, 15, 20, 30, 45, 60, 90, 120];
    private const QUIZ_DEFAULT_LIMIT = 20;
    /** quiz points: correct answer, plus up to this bonus for speed (only with time limit) */
    public const QUIZ_POINTS = 100;
    public const QUIZ_BONUS = 100;
    /** a participant counts as "present" if the device asked within this time */
    private const ACTIVE_SECONDS = 15;
    /** small tolerance for answers that arrive just after the time limit */
    private const GRACE_MS = 1500;
    private const CLOUD_TOP = 60;
    /** a session still "running" after this time was forgotten: it ends by itself */
    private const MAX_SESSION_SECONDS = 12 * 3600;
    private const LEADERBOARD_TOP = 10;

    public const RESULTS_AUTO = 'auto';
    public const RESULTS_RELEASE = 'release';

    public function __construct(private readonly ilUdaRepository $repo)
    {
    }

    public static function create(): self
    {
        return new self(ilUdaRepository::create());
    }

    public function repo(): ilUdaRepository
    {
        return $this->repo;
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    // ------------------------------------------------------------------
    // Questions
    // ------------------------------------------------------------------

    /** @return array<string, mixed> a new question of the type, with empty answers to fill in */
    public function newQuestion(string $type): array
    {
        $answers = [];
        for ($i = 0; $i < (self::NEW_ANSWERS[$type] ?? 0); $i++) {
            $answers[] = ['id' => $this->newAnswerId(), 'text' => '', 'correct' => false];
        }
        return $this->normalizeQuestion(['type' => $type, 'title' => '', 'answers' => $answers, 'settings' => []], $type);
    }

    /**
     * Validates a question from the editor. Empty texts are kept (a question
     * may be saved half finished); they are left out when presenting.
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeQuestion(array $input, string $type): array
    {
        $title = $this->cleanText($input['title'] ?? '', self::MAX_TITLE, true);

        $answers = [];
        $seen = [];
        $has_correct = false;
        $raw_answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        foreach (array_slice($raw_answers, 0, self::MAX_ANSWERS[$type] ?? 0) as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $id = is_string($raw['id'] ?? null) && preg_match('/^[a-z0-9]{1,12}$/', $raw['id']) && !isset($seen[$raw['id']])
                ? $raw['id'] : $this->newAnswerId();
            $seen[$id] = true;
            $correct = $type === 'quiz' && !$has_correct && ($raw['correct'] ?? false) === true;
            $has_correct = $has_correct || $correct;
            $answers[] = [
                'id' => $id,
                'text' => $this->cleanText($raw['text'] ?? '', self::MAX_ANSWER_TEXT, false),
                'correct' => $correct,
            ];
        }

        $raw_settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
        $settings = match ($type) {
            'choice' => ['multiple' => ($raw_settings['multiple'] ?? false) === true],
            'cloud' => ['maxWords' => max(1, min(self::MAX_WORDS_LIMIT, (int) ($raw_settings['maxWords'] ?? 3)))],
            'quiz' => ['timeLimit' => in_array((int) ($raw_settings['timeLimit'] ?? self::QUIZ_DEFAULT_LIMIT), self::QUIZ_LIMITS, true)
                ? (int) ($raw_settings['timeLimit'] ?? self::QUIZ_DEFAULT_LIMIT) : self::QUIZ_DEFAULT_LIMIT],
            // all items must be ordered (fixed; also keeps the settings a JSON object)
            default => ['fullOrder' => true],
        };

        return [
            'id' => (int) ($input['id'] ?? 0),
            'type' => $type,
            'title' => $title,
            'answers' => $answers,
            'settings' => $settings,
        ];
    }

    /** @return int[] allowed time limits of quiz questions (seconds, 0 = none) */
    public static function quizLimits(): array
    {
        return self::QUIZ_LIMITS;
    }

    /**
     * Question as the participants see it: without empty answers and
     * without the correct answer of a quiz question.
     * @param array<string, mixed> $q
     * @return array<string, mixed>
     */
    public function publicQuestion(array $q): array
    {
        $answers = [];
        foreach ($this->filledAnswers($q) as $a) {
            $answers[] = ['id' => $a['id'], 'text' => $a['text']];
        }
        return [
            'id' => $q['id'],
            'type' => $q['type'],
            'title' => $q['title'],
            'answers' => $answers,
            'settings' => $q['settings'],
        ];
    }

    /**
     * @param array<string, mixed> $q
     * @return array<int, array{id: string, text: string, correct: bool}>
     */
    private function filledAnswers(array $q): array
    {
        return array_values(array_filter($q['answers'], static fn(array $a): bool => trim((string) $a['text']) !== ''));
    }

    private function newAnswerId(): string
    {
        return substr(bin2hex(random_bytes(6)), 0, 8);
    }

    private function cleanText(mixed $value, int $max, bool $multiline): string
    {
        $text = is_string($value) ? $value : '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = $multiline ? preg_replace("/\n{3,}/", "\n\n", $text) : str_replace("\n", ' ', $text);
        $text = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', (string) $text);
        return mb_substr(trim((string) $text), 0, $max);
    }

    // ------------------------------------------------------------------
    // Sessions (presenter)
    // ------------------------------------------------------------------

    /**
     * The running session of the object, after
     * - ending it if it was forgotten (older than MAX_SESSION_SECONDS),
     * - going to the first question if the current one was deleted,
     * - closing a quiz question whose time is over.
     * @return array<string, int>|null
     */
    public function runningSession(int $obj_id, string $result_mode): ?array
    {
        $session = $this->repo->getRunningSession($obj_id);
        if ($session === null) {
            return null;
        }
        if ($session['started'] < time() - self::MAX_SESSION_SECONDS) {
            $this->endSession($session['id']);
            return null;
        }
        $question = $this->repo->getQuestion($obj_id, $session['quest_id']);
        if ($question === null) {
            $first = $this->repo->getQuestions($obj_id)[0] ?? null;
            if ($first !== null) {
                $this->gotoQuestion($obj_id, $session['id'], $first['id'], $result_mode);
                $session = $this->repo->getSession($obj_id, $session['id']) ?? $session;
            }
            return $session;
        }
        if ($session['voting'] !== 1) {
            return $session;
        }
        $limit = $question['type'] === 'quiz' ? (int) $question['settings']['timeLimit'] : 0;
        if ($limit > 0 && self::nowMs() > $session['q_started'] + $limit * 1000 + self::GRACE_MS) {
            $this->repo->updateSession($session['id'], [
                'voting' => 0,
                'show_results' => $result_mode === self::RESULTS_AUTO ? 1 : $session['show_results'],
            ]);
            $session = $this->repo->getSession($obj_id, $session['id']);
        }
        return $session;
    }

    /**
     * Starts a session with the first question (or continues the running one).
     * @return array<string, int>|null null if there is no question
     */
    public function startSession(int $obj_id, string $result_mode): ?array
    {
        $this->purgeOldResults();
        $running = $this->runningSession($obj_id, $result_mode);
        if ($running !== null) {
            return $running;
        }
        $questions = $this->repo->getQuestions($obj_id);
        if ($questions === []) {
            return null;
        }
        $first = $questions[0];
        $id = $this->repo->createSession($obj_id, $first['id'], $this->initialShowResults($first, $result_mode), self::nowMs());
        return $this->repo->getSession($obj_id, $id);
    }

    /**
     * Shows a question: voting opens, results as set for the presentation
     * (quiz: after the voting). Back to a quiz question that was already
     * answered: it stays closed and shows its solution; "Abstimmung öffnen"
     * starts it again.
     */
    public function gotoQuestion(int $obj_id, int $session_id, int $quest_id, string $result_mode): void
    {
        $question = $this->repo->getQuestion($obj_id, $quest_id);
        if ($question === null) {
            return;
        }
        if ($question['type'] === 'quiz' && $this->repo->countResponses($session_id, $quest_id) > 0) {
            $this->repo->updateSession($session_id, [
                'quest_id' => $quest_id,
                'voting' => 0,
                'show_results' => $result_mode === self::RESULTS_AUTO ? 1 : 0,
            ]);
            return;
        }
        $this->repo->updateSession($session_id, [
            'quest_id' => $quest_id,
            'voting' => 1,
            'show_results' => $this->initialShowResults($question, $result_mode) ? 1 : 0,
            'q_started' => self::nowMs(),
        ]);
    }

    public function setVoting(int $obj_id, int $session_id, bool $open, string $result_mode): void
    {
        $session = $this->repo->getSession($obj_id, $session_id);
        if ($session === null) {
            return;
        }
        $question = $this->repo->getQuestion($obj_id, $session['quest_id']);
        $is_quiz = $question !== null && $question['type'] === 'quiz';
        $values = ['voting' => $open ? 1 : 0];
        if ($open && $is_quiz) {
            // the time limit starts again; the results of a quiz stay hidden while answering
            $values['q_started'] = self::nowMs();
            $values['show_results'] = 0;
        }
        if (!$open && $is_quiz && $result_mode === self::RESULTS_AUTO) {
            $values['show_results'] = 1;
        }
        $this->repo->updateSession($session_id, $values);
    }

    public function setShowResults(int $session_id, bool $show): void
    {
        $this->repo->updateSession($session_id, ['show_results' => $show ? 1 : 0]);
    }

    public function resetQuestion(int $obj_id, int $session_id, string $result_mode): void
    {
        $session = $this->repo->getSession($obj_id, $session_id);
        if ($session === null) {
            return;
        }
        $this->repo->deleteResponsesOfQuestion($session_id, $session['quest_id']);
        $this->gotoQuestion($obj_id, $session_id, $session['quest_id'], $result_mode);
    }

    public function endSession(int $session_id): void
    {
        $this->repo->updateSession($session_id, ['ended' => time(), 'voting' => 0]);
    }

    /** @param array<string, mixed> $question */
    private function initialShowResults(array $question, string $result_mode): bool
    {
        return $result_mode === self::RESULTS_AUTO && $question['type'] !== 'quiz';
    }

    /** milliseconds left of a quiz question with time limit, otherwise null */
    public function remainingMs(array $session, ?array $question): ?int
    {
        if ($question === null || $question['type'] !== 'quiz' || $session['voting'] !== 1) {
            return null;
        }
        $limit = (int) $question['settings']['timeLimit'];
        if ($limit <= 0) {
            return null;
        }
        return max(0, $session['q_started'] + $limit * 1000 - self::nowMs());
    }

    /** deletes results of ended sessions after the period of the plugin configuration */
    public function purgeOldResults(): void
    {
        $days = ilUdaConfig::retentionDays();
        if ($days > 0) {
            $this->repo->purgeEndedBefore(time() - $days * 86400);
        }
    }

    // ------------------------------------------------------------------
    // Participants
    // ------------------------------------------------------------------

    /** stored form of a participant token (never the token itself) */
    public static function hashToken(string $raw): string
    {
        return hash('sha256', ilUdaConfig::salt() . '|' . $raw);
    }

    /**
     * Validates and stores an answer.
     * @return string|null error key (language variable without prefix) or null if saved
     */
    public function submitAnswer(int $obj_id, string $result_mode, string $token, int $quest_id, mixed $answer): ?string
    {
        $session = $this->runningSession($obj_id, $result_mode);
        if ($session === null) {
            return 'ui_err_no_session';
        }
        if ($session['quest_id'] !== $quest_id || $session['voting'] !== 1) {
            return 'ui_err_voting_closed';
        }
        $question = $this->repo->getQuestion($obj_id, $quest_id);
        if ($question === null) {
            return 'ui_err_voting_closed';
        }
        $previous = $this->repo->getResponse($session['id'], $quest_id, $token);
        if ($previous !== null && $question['type'] === 'quiz') {
            return 'ui_err_already_answered';
        }

        $clean = $this->cleanAnswer($question, $answer);
        if ($clean === null) {
            return 'ui_err_invalid_answer';
        }

        $now_ms = self::nowMs();
        $elapsed = max(0, $now_ms - $session['q_started']);
        $points = 0;
        if ($question['type'] === 'quiz') {
            $limit_ms = (int) $question['settings']['timeLimit'] * 1000;
            if ($limit_ms > 0 && $elapsed > $limit_ms + self::GRACE_MS) {
                return 'ui_err_voting_closed';
            }
            $correct = array_column(array_filter($question['answers'], static fn(array $a): bool => $a['correct']), 'id');
            if (in_array($clean[0], $correct, true)) {
                $points = self::QUIZ_POINTS;
                if ($limit_ms > 0) {
                    $points += (int) round(self::QUIZ_BONUS * max(0, $limit_ms - $elapsed) / $limit_ms);
                }
            }
        }

        $this->repo->touchParticipant($session['id'], $token, time());
        $this->repo->saveResponse($session['id'], $quest_id, $token, $clean, $points, $elapsed, time());
        return null;
    }

    /**
     * @param array<string, mixed> $question
     * @return array<int, string>|null list of answer ids (choice, quiz, rank) or words (cloud)
     */
    private function cleanAnswer(array $question, mixed $answer): ?array
    {
        if (!is_array($answer)) {
            return null;
        }
        $answer = array_values($answer);
        $ids = array_column($this->filledAnswers($question), 'id');

        switch ($question['type']) {
            case 'choice':
            case 'quiz':
                $chosen = array_values(array_unique(array_filter($answer, 'is_string')));
                if ($chosen === [] || array_diff($chosen, $ids) !== []) {
                    return null;
                }
                $multiple = $question['type'] === 'choice' && ($question['settings']['multiple'] ?? false);
                if (!$multiple && count($chosen) !== 1) {
                    return null;
                }
                return $chosen;

            case 'rank':
                $order = array_values(array_filter($answer, 'is_string'));
                $sorted_order = $order;
                $sorted_ids = $ids;
                sort($sorted_order);
                sort($sorted_ids);
                return ($ids !== [] && $sorted_order === $sorted_ids) ? $order : null;

            case 'cloud':
                $max = (int) ($question['settings']['maxWords'] ?? 3);
                $words = [];
                $keys = [];
                foreach ($answer as $word) {
                    $word = $this->cleanText($word, self::MAX_WORD, false);
                    $word = (string) preg_replace('/\s+/u', ' ', $word);
                    $key = mb_strtolower($word);
                    if ($word === '' || isset($keys[$key])) {
                        continue;
                    }
                    $keys[$key] = true;
                    $words[] = $word;
                }
                return ($words !== [] && count($words) <= $max) ? $words : null;
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Results
    // ------------------------------------------------------------------

    /**
     * Aggregated results of one question in a session.
     * @param array<string, mixed> $question
     * @return array<string, mixed>
     */
    public function results(int $session_id, array $question): array
    {
        $answers = $this->repo->getAnswers($session_id, $question['id']);
        $total = count($answers);
        $result = ['type' => $question['type'], 'total' => $total];

        switch ($question['type']) {
            case 'choice':
            case 'quiz':
                $counts = [];
                foreach ($answers as $answer) {
                    foreach ((array) $answer as $id) {
                        $counts[$id] = ($counts[$id] ?? 0) + 1;
                    }
                }
                $result['options'] = [];
                foreach ($this->filledAnswers($question) as $a) {
                    $option = ['id' => $a['id'], 'text' => $a['text'], 'count' => $counts[$a['id']] ?? 0];
                    if ($question['type'] === 'quiz') {
                        $option['correct'] = $a['correct'];
                    }
                    $result['options'][] = $option;
                }
                if ($question['type'] === 'quiz') {
                    $result['leaderboard'] = $this->leaderboard($session_id);
                }
                break;

            case 'rank':
                $items = $this->filledAnswers($question);
                $n = count($items);
                $sum = [];
                foreach ($answers as $order) {
                    foreach (array_values((array) $order) as $pos => $id) {
                        $sum[$id] = ($sum[$id] ?? 0) + $pos + 1;
                    }
                }
                $result['items'] = [];
                foreach ($items as $a) {
                    $avg = $total > 0 && isset($sum[$a['id']]) ? $sum[$a['id']] / $total : null;
                    $result['items'][] = [
                        'id' => $a['id'],
                        'text' => $a['text'],
                        'avgRank' => $avg === null ? null : round($avg, 2),
                        // 1 = always first place, 0 = always last place
                        'score' => $avg === null || $n < 2 ? 0 : round(($n - $avg) / ($n - 1), 3),
                    ];
                }
                usort($result['items'], static fn(array $x, array $y): int => $y['score'] <=> $x['score']);
                break;

            case 'cloud':
                $words = [];
                foreach ($answers as $list) {
                    foreach ((array) $list as $word) {
                        $key = mb_strtolower((string) $word);
                        $words[$key]['count'] = ($words[$key]['count'] ?? 0) + 1;
                        $words[$key]['forms'][$word] = ($words[$key]['forms'][$word] ?? 0) + 1;
                    }
                }
                uasort($words, static fn(array $x, array $y): int => $y['count'] <=> $x['count']);
                $result['words'] = [];
                foreach (array_slice($words, 0, self::CLOUD_TOP, true) as $entry) {
                    arsort($entry['forms']);
                    $result['words'][] = ['text' => (string) array_key_first($entry['forms']), 'count' => $entry['count']];
                }
                $result['distinct'] = count($words);
                break;
        }
        return $result;
    }

    /** @return array<int, array{name: string, points: int, token: string}> */
    public function leaderboard(int $session_id, int $top = self::LEADERBOARD_TOP): array
    {
        $totals = $this->repo->getPointTotals($session_id);
        arsort($totals);
        $names = $this->repo->getNicknames($session_id);
        $list = [];
        foreach ($totals as $token => $points) {
            if ($points <= 0) {
                continue;
            }
            $list[] = ['name' => $names[$token] ?? '', 'points' => $points, 'token' => $token];
            if (count($list) >= $top) {
                break;
            }
        }
        return $list;
    }

    /** @return array{total: int, active: int} */
    public function participants(int $session_id): array
    {
        return $this->repo->countParticipants($session_id, time() - self::ACTIVE_SECONDS);
    }
}
