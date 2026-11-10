<#1>
<?php
// Initial step. ARS specific tables follow in step 2.
?>
<#2>
<?php
// one row per Udon ARS object: participation code, colour palette, result mode
if (!$ilDB->tableExists('rep_robj_xuda_data')) {
    $ilDB->createTable('rep_robj_xuda_data', [
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'join_code' => ['type' => 'text', 'length' => 8, 'notnull' => true, 'default' => ''],
        'palette' => ['type' => 'text', 'length' => 20, 'notnull' => true, 'default' => ''],
        'result_mode' => ['type' => 'text', 'length' => 10, 'notnull' => true, 'default' => 'auto'],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xuda_data', ['obj_id']);
    $ilDB->addIndex('rep_robj_xuda_data', ['join_code'], 'i1');
}

// questions ("slides") of an object, in the order of sort_nr
if (!$ilDB->tableExists('rep_robj_xuda_quest')) {
    $ilDB->createTable('rep_robj_xuda_quest', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'sort_nr' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'qtype' => ['type' => 'text', 'length' => 10, 'notnull' => true, 'default' => 'choice'],
        'title' => ['type' => 'text', 'length' => 1000, 'notnull' => false],
        'answers_json' => ['type' => 'clob', 'notnull' => false],
        'settings_json' => ['type' => 'clob', 'notnull' => false],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xuda_quest', ['id']);
    $ilDB->addIndex('rep_robj_xuda_quest', ['obj_id'], 'i1');
    $ilDB->createSequence('rep_robj_xuda_quest');
}

// sessions: one run of the presentation (ended = 0 while it runs)
if (!$ilDB->tableExists('rep_robj_xuda_sess')) {
    $ilDB->createTable('rep_robj_xuda_sess', [
        'id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'obj_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'started' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'ended' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'quest_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'voting' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
        'show_results' => ['type' => 'integer', 'length' => 1, 'notnull' => true, 'default' => 0],
        'q_started' => ['type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0],
        'rev' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xuda_sess', ['id']);
    $ilDB->addIndex('rep_robj_xuda_sess', ['obj_id'], 'i1');
    $ilDB->createSequence('rep_robj_xuda_sess');
}

// participants of a session: only a hashed token and an optional name for the quiz ranking
if (!$ilDB->tableExists('rep_robj_xuda_part')) {
    $ilDB->createTable('rep_robj_xuda_part', [
        'session_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'token' => ['type' => 'text', 'length' => 64, 'notnull' => true],
        'nickname' => ['type' => 'text', 'length' => 40, 'notnull' => false],
        'joined' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'last_seen' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xuda_part', ['session_id', 'token']);
}

// responses: one per participant and question in a session
if (!$ilDB->tableExists('rep_robj_xuda_resp')) {
    $ilDB->createTable('rep_robj_xuda_resp', [
        'session_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'quest_id' => ['type' => 'integer', 'length' => 4, 'notnull' => true],
        'token' => ['type' => 'text', 'length' => 64, 'notnull' => true],
        'answer_json' => ['type' => 'clob', 'notnull' => false],
        'points' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'answer_ms' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
        'created' => ['type' => 'integer', 'length' => 4, 'notnull' => true, 'default' => 0],
    ]);
    $ilDB->addPrimaryKey('rep_robj_xuda_resp', ['session_id', 'quest_id', 'token']);
}
?>
