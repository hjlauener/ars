<?php

declare(strict_types=1);

/**
 * Database access of Udon ARS (tables rep_robj_xuda_*). Questions are
 * returned as arrays:
 *   ['id' => int, 'type' => string, 'title' => string,
 *    'answers' => [['id' => string, 'text' => string, 'correct' => bool]],
 *    'settings' => [...]]
 */
class ilUdaRepository
{
    private const T_DATA = 'rep_robj_xuda_data';
    private const T_QUEST = 'rep_robj_xuda_quest';
    private const T_SESS = 'rep_robj_xuda_sess';
    private const T_PART = 'rep_robj_xuda_part';
    private const T_RESP = 'rep_robj_xuda_resp';

    public function __construct(private readonly ilDBInterface $db)
    {
    }

    public static function create(): self
    {
        global $DIC;
        return new self($DIC->database());
    }

    // ------------------------------------------------------------------
    // Object data
    // ------------------------------------------------------------------

    public function createData(int $obj_id, string $palette, string $result_mode): void
    {
        $this->db->insert(self::T_DATA, [
            'obj_id' => ['integer', $obj_id],
            'join_code' => ['text', $this->newJoinCode()],
            'palette' => ['text', $palette],
            'result_mode' => ['text', $result_mode],
        ]);
    }

    /** @return array{join_code: string, palette: string, result_mode: string}|null */
    public function getData(int $obj_id): ?array
    {
        $res = $this->db->queryF(
            'SELECT join_code, palette, result_mode FROM ' . self::T_DATA . ' WHERE obj_id = %s',
            ['integer'],
            [$obj_id]
        );
        $row = $this->db->fetchAssoc($res);
        if (!$row) {
            return null;
        }
        return [
            'join_code' => (string) $row['join_code'],
            'palette' => (string) $row['palette'],
            'result_mode' => (string) $row['result_mode'],
        ];
    }

    public function updateData(int $obj_id, string $palette, string $result_mode): void
    {
        $this->db->update(self::T_DATA, [
            'palette' => ['text', $palette],
            'result_mode' => ['text', $result_mode],
        ], ['obj_id' => ['integer', $obj_id]]);
    }

    public function renewJoinCode(int $obj_id): string
    {
        $code = $this->newJoinCode();
        $this->db->update(self::T_DATA, ['join_code' => ['text', $code]], ['obj_id' => ['integer', $obj_id]]);
        return $code;
    }

    public function lookupObjIdByCode(string $code): int
    {
        $res = $this->db->queryF(
            'SELECT obj_id FROM ' . self::T_DATA . ' WHERE join_code = %s',
            ['text'],
            [$code]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? (int) $row['obj_id'] : 0;
    }

    /** six digits, unique among all objects */
    private function newJoinCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while ($this->lookupObjIdByCode($code) !== 0);
        return $code;
    }

    public function deleteObject(int $obj_id): void
    {
        foreach ($this->getSessions($obj_id) as $session) {
            $this->deleteSession((int) $session['id']);
        }
        $this->db->manipulateF('DELETE FROM ' . self::T_QUEST . ' WHERE obj_id = %s', ['integer'], [$obj_id]);
        $this->db->manipulateF('DELETE FROM ' . self::T_DATA . ' WHERE obj_id = %s', ['integer'], [$obj_id]);
    }

    // ------------------------------------------------------------------
    // Questions
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> questions in their order */
    public function getQuestions(int $obj_id): array
    {
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::T_QUEST . ' WHERE obj_id = %s ORDER BY sort_nr, id',
            ['integer'],
            [$obj_id]
        );
        $questions = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $questions[] = $this->questionFromRow($row);
        }
        return $questions;
    }

    /** @return array<string, mixed>|null */
    public function getQuestion(int $obj_id, int $id): ?array
    {
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::T_QUEST . ' WHERE obj_id = %s AND id = %s',
            ['integer', 'integer'],
            [$obj_id, $id]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? $this->questionFromRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function questionFromRow(array $row): array
    {
        $answers = json_decode((string) ($row['answers_json'] ?? ''), true);
        $settings = json_decode((string) ($row['settings_json'] ?? ''), true);
        return [
            'id' => (int) $row['id'],
            'type' => (string) $row['qtype'],
            'title' => (string) ($row['title'] ?? ''),
            'answers' => is_array($answers) ? $answers : [],
            'settings' => is_array($settings) ? $settings : [],
        ];
    }

    /**
     * @param array<string, mixed> $question normalised question (id ignored)
     */
    public function addQuestion(int $obj_id, array $question): int
    {
        $id = $this->db->nextId(self::T_QUEST);
        $res = $this->db->queryF(
            'SELECT MAX(sort_nr) m FROM ' . self::T_QUEST . ' WHERE obj_id = %s',
            ['integer'],
            [$obj_id]
        );
        $row = $this->db->fetchAssoc($res);
        $this->db->insert(self::T_QUEST, [
            'id' => ['integer', $id],
            'obj_id' => ['integer', $obj_id],
            'sort_nr' => ['integer', ((int) ($row['m'] ?? 0)) + 10],
            'qtype' => ['text', $question['type']],
            'title' => ['text', $question['title']],
            'answers_json' => ['clob', json_encode($question['answers'], JSON_UNESCAPED_UNICODE)],
            'settings_json' => ['clob', json_encode((object) $question['settings'], JSON_UNESCAPED_UNICODE)],
        ]);
        return $id;
    }

    /** @param array<string, mixed> $question normalised question */
    public function updateQuestion(int $obj_id, array $question): void
    {
        $this->db->update(self::T_QUEST, [
            'title' => ['text', $question['title']],
            'answers_json' => ['clob', json_encode($question['answers'], JSON_UNESCAPED_UNICODE)],
            'settings_json' => ['clob', json_encode((object) $question['settings'], JSON_UNESCAPED_UNICODE)],
        ], [
            'obj_id' => ['integer', $obj_id],
            'id' => ['integer', (int) $question['id']],
        ]);
    }

    public function deleteQuestion(int $obj_id, int $id): void
    {
        $this->db->manipulateF(
            'DELETE FROM ' . self::T_QUEST . ' WHERE obj_id = %s AND id = %s',
            ['integer', 'integer'],
            [$obj_id, $id]
        );
    }

    /** @param int[] $ids question ids in the new order */
    public function reorderQuestions(int $obj_id, array $ids): void
    {
        $nr = 10;
        foreach ($ids as $id) {
            $this->db->update(
                self::T_QUEST,
                ['sort_nr' => ['integer', $nr]],
                ['obj_id' => ['integer', $obj_id], 'id' => ['integer', (int) $id]]
            );
            $nr += 10;
        }
    }

    // ------------------------------------------------------------------
    // Sessions
    // ------------------------------------------------------------------

    /** @return array<string, int>|null the running session of the object */
    public function getRunningSession(int $obj_id): ?array
    {
        $this->db->setLimit(1, 0);
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::T_SESS . ' WHERE obj_id = %s AND ended = 0 ORDER BY id DESC',
            ['integer'],
            [$obj_id]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? $this->sessionFromRow($row) : null;
    }

    /** @return array<string, int>|null the newest session of the object (running or ended) */
    public function getLatestSession(int $obj_id): ?array
    {
        $this->db->setLimit(1, 0);
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::T_SESS . ' WHERE obj_id = %s ORDER BY id DESC',
            ['integer'],
            [$obj_id]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? $this->sessionFromRow($row) : null;
    }

    /** @return array<string, int>|null */
    public function getSession(int $obj_id, int $id): ?array
    {
        $res = $this->db->queryF(
            'SELECT * FROM ' . self::T_SESS . ' WHERE obj_id = %s AND id = %s',
            ['integer', 'integer'],
            [$obj_id, $id]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? $this->sessionFromRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function sessionFromRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'obj_id' => (int) $row['obj_id'],
            'started' => (int) $row['started'],
            'ended' => (int) $row['ended'],
            'quest_id' => (int) $row['quest_id'],
            'voting' => (int) $row['voting'],
            'show_results' => (int) $row['show_results'],
            'q_started' => (int) $row['q_started'],
            'rev' => (int) $row['rev'],
        ];
    }

    /**
     * Sessions of an object, newest first, with number of participants.
     * @return array<int, array<string, int>>
     */
    public function getSessions(int $obj_id): array
    {
        $res = $this->db->queryF(
            'SELECT s.*, (SELECT COUNT(*) FROM ' . self::T_PART . ' p WHERE p.session_id = s.id) parts,'
            . ' (SELECT COUNT(*) FROM ' . self::T_RESP . ' r WHERE r.session_id = s.id) resps'
            . ' FROM ' . self::T_SESS . ' s WHERE s.obj_id = %s ORDER BY s.id DESC',
            ['integer'],
            [$obj_id]
        );
        $sessions = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $sessions[] = $this->sessionFromRow($row) + [
                'participants' => (int) $row['parts'],
                'responses' => (int) $row['resps'],
            ];
        }
        return $sessions;
    }

    public function createSession(int $obj_id, int $quest_id, bool $show_results, int $now_ms): int
    {
        $id = $this->db->nextId(self::T_SESS);
        $this->db->insert(self::T_SESS, [
            'id' => ['integer', $id],
            'obj_id' => ['integer', $obj_id],
            'started' => ['integer', (int) floor($now_ms / 1000)],
            'ended' => ['integer', 0],
            'quest_id' => ['integer', $quest_id],
            'voting' => ['integer', 1],
            'show_results' => ['integer', $show_results ? 1 : 0],
            'q_started' => ['integer', $now_ms],
            'rev' => ['integer', 1],
        ]);
        return $id;
    }

    /**
     * Changes the state of a session and increases its revision, so that
     * the devices of the participants notice the change.
     * @param array<string, int> $values column => value (quest_id, voting, show_results, q_started, ended)
     */
    public function updateSession(int $id, array $values): void
    {
        $sets = [];
        $types = [];
        $data = [];
        foreach ($values as $column => $value) {
            if (!in_array($column, ['quest_id', 'voting', 'show_results', 'q_started', 'ended'], true)) {
                continue;
            }
            $sets[] = $column . ' = %s';
            $types[] = 'integer';
            $data[] = $value;
        }
        $types[] = 'integer';
        $data[] = $id;
        $this->db->manipulateF(
            'UPDATE ' . self::T_SESS . ' SET ' . implode(', ', array_merge($sets, ['rev = rev + 1']))
            . ' WHERE id = %s',
            $types,
            $data
        );
    }

    public function deleteSession(int $id): void
    {
        foreach ([self::T_RESP, self::T_PART] as $table) {
            $this->db->manipulateF('DELETE FROM ' . $table . ' WHERE session_id = %s', ['integer'], [$id]);
        }
        $this->db->manipulateF('DELETE FROM ' . self::T_SESS . ' WHERE id = %s', ['integer'], [$id]);
    }

    /** deletes ended sessions (of all objects) that ended before $before (unix time) */
    public function purgeEndedBefore(int $before): void
    {
        $res = $this->db->queryF(
            'SELECT id FROM ' . self::T_SESS . ' WHERE ended > 0 AND ended < %s',
            ['integer'],
            [$before]
        );
        $ids = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $ids[] = (int) $row['id'];
        }
        foreach ($ids as $id) {
            $this->deleteSession($id);
        }
    }

    // ------------------------------------------------------------------
    // Participants
    // ------------------------------------------------------------------

    public function touchParticipant(int $session_id, string $token, int $now): void
    {
        $res = $this->db->queryF(
            'SELECT last_seen FROM ' . self::T_PART . ' WHERE session_id = %s AND token = %s',
            ['integer', 'text'],
            [$session_id, $token]
        );
        $row = $this->db->fetchAssoc($res);
        if (!$row) {
            $this->db->replace(self::T_PART, [
                'session_id' => ['integer', $session_id],
                'token' => ['text', $token],
            ], [
                'nickname' => ['text', null],
                'joined' => ['integer', $now],
                'last_seen' => ['integer', $now],
            ]);
            return;
        }
        // write at most every few seconds (every device polls about once a second)
        if ((int) $row['last_seen'] < $now - 4) {
            $this->db->update(self::T_PART, ['last_seen' => ['integer', $now]], [
                'session_id' => ['integer', $session_id],
                'token' => ['text', $token],
            ]);
        }
    }

    public function setNickname(int $session_id, string $token, string $nickname): void
    {
        $this->db->update(self::T_PART, ['nickname' => ['text', $nickname === '' ? null : $nickname]], [
            'session_id' => ['integer', $session_id],
            'token' => ['text', $token],
        ]);
    }

    public function getNickname(int $session_id, string $token): string
    {
        $res = $this->db->queryF(
            'SELECT nickname FROM ' . self::T_PART . ' WHERE session_id = %s AND token = %s',
            ['integer', 'text'],
            [$session_id, $token]
        );
        $row = $this->db->fetchAssoc($res);
        return $row ? (string) ($row['nickname'] ?? '') : '';
    }

    /** @return array{total: int, active: int} */
    public function countParticipants(int $session_id, int $active_since): array
    {
        $res = $this->db->queryF(
            'SELECT COUNT(*) total, SUM(CASE WHEN last_seen >= %s THEN 1 ELSE 0 END) active FROM '
            . self::T_PART . ' WHERE session_id = %s',
            ['integer', 'integer'],
            [$active_since, $session_id]
        );
        $row = $this->db->fetchAssoc($res);
        return ['total' => (int) ($row['total'] ?? 0), 'active' => (int) ($row['active'] ?? 0)];
    }

    /** @return array<string, string> token => nickname (only participants with a name) */
    public function getNicknames(int $session_id): array
    {
        $res = $this->db->queryF(
            'SELECT token, nickname FROM ' . self::T_PART . ' WHERE session_id = %s AND nickname IS NOT NULL',
            ['integer'],
            [$session_id]
        );
        $names = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $names[(string) $row['token']] = (string) $row['nickname'];
        }
        return $names;
    }

    /** @return string[] tokens in the order of joining */
    public function getParticipantTokens(int $session_id): array
    {
        $res = $this->db->queryF(
            'SELECT token FROM ' . self::T_PART . ' WHERE session_id = %s ORDER BY joined, token',
            ['integer'],
            [$session_id]
        );
        $tokens = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $tokens[] = (string) $row['token'];
        }
        return $tokens;
    }

    // ------------------------------------------------------------------
    // Responses
    // ------------------------------------------------------------------

    /** @return array{answer: mixed, points: int, answer_ms: int}|null */
    public function getResponse(int $session_id, int $quest_id, string $token): ?array
    {
        $res = $this->db->queryF(
            'SELECT answer_json, points, answer_ms FROM ' . self::T_RESP
            . ' WHERE session_id = %s AND quest_id = %s AND token = %s',
            ['integer', 'integer', 'text'],
            [$session_id, $quest_id, $token]
        );
        $row = $this->db->fetchAssoc($res);
        if (!$row) {
            return null;
        }
        return [
            'answer' => json_decode((string) $row['answer_json'], true),
            'points' => (int) $row['points'],
            'answer_ms' => (int) $row['answer_ms'],
        ];
    }

    public function saveResponse(int $session_id, int $quest_id, string $token, mixed $answer, int $points, int $answer_ms, int $now): void
    {
        $this->db->replace(self::T_RESP, [
            'session_id' => ['integer', $session_id],
            'quest_id' => ['integer', $quest_id],
            'token' => ['text', $token],
        ], [
            'answer_json' => ['clob', json_encode($answer, JSON_UNESCAPED_UNICODE)],
            'points' => ['integer', $points],
            'answer_ms' => ['integer', $answer_ms],
            'created' => ['integer', $now],
        ]);
    }

    /** @return array<int, mixed> decoded answers of one question */
    public function getAnswers(int $session_id, int $quest_id): array
    {
        $res = $this->db->queryF(
            'SELECT answer_json FROM ' . self::T_RESP . ' WHERE session_id = %s AND quest_id = %s',
            ['integer', 'integer'],
            [$session_id, $quest_id]
        );
        $answers = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $answers[] = json_decode((string) $row['answer_json'], true);
        }
        return $answers;
    }

    /** @return array<int, array<string, mixed>> all responses of a session (for the export) */
    public function getAllResponses(int $session_id): array
    {
        $res = $this->db->queryF(
            'SELECT quest_id, token, answer_json, points, answer_ms, created FROM ' . self::T_RESP
            . ' WHERE session_id = %s ORDER BY created, token',
            ['integer'],
            [$session_id]
        );
        $rows = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $rows[] = [
                'quest_id' => (int) $row['quest_id'],
                'token' => (string) $row['token'],
                'answer' => json_decode((string) $row['answer_json'], true),
                'points' => (int) $row['points'],
                'answer_ms' => (int) $row['answer_ms'],
                'created' => (int) $row['created'],
            ];
        }
        return $rows;
    }

    /** @return array<string, int> token => sum of quiz points in the session (only people who answered a quiz question) */
    public function getPointTotals(int $session_id): array
    {
        $res = $this->db->queryF(
            'SELECT r.token, SUM(r.points) total FROM ' . self::T_RESP . ' r'
            . ' JOIN ' . self::T_QUEST . " q ON q.id = r.quest_id AND q.qtype = 'quiz'"
            . ' WHERE r.session_id = %s GROUP BY r.token',
            ['integer'],
            [$session_id]
        );
        $totals = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $totals[(string) $row['token']] = (int) $row['total'];
        }
        return $totals;
    }

    public function deleteResponsesOfQuestion(int $session_id, int $quest_id): void
    {
        $this->db->manipulateF(
            'DELETE FROM ' . self::T_RESP . ' WHERE session_id = %s AND quest_id = %s',
            ['integer', 'integer'],
            [$session_id, $quest_id]
        );
    }

    public function dropTables(): void
    {
        foreach ([self::T_DATA, self::T_QUEST, self::T_SESS, self::T_PART, self::T_RESP] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->dropTable($table);
            }
        }
        foreach ([self::T_QUEST, self::T_SESS] as $table) {
            if ($this->db->sequenceExists($table)) {
                $this->db->dropSequence($table);
            }
        }
    }
}
