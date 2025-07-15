<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_mypeerreview_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025010604) {
        // Добавляем поле maxgrade в таблицу mypeerreview
        $table = new xmldb_table('mypeerreview');
        $field = new xmldb_field('maxgrade', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '10', 'anonymous');
        
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Добавляем поля для оценки преподавателя
        $table = new xmldb_table('mypeerreview_reviews');
        $field1 = new xmldb_field('teachergrade', XMLDB_TYPE_INTEGER, '3', null, null, null, null, 'completed');
        $field2 = new xmldb_field('teacherfeedback', XMLDB_TYPE_TEXT, null, null, null, null, null, 'teachergrade');
        $field3 = new xmldb_field('timemarked', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'teacherfeedback');
        
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
            $dbman->add_field($table, $field2);
            $dbman->add_field($table, $field3);
        }
        $DB->execute("UPDATE {mypeerreview} SET maxgrade = 10 WHERE maxgrade IS NULL");
        upgrade_mod_savepoint(true, 2025010604, 'mypeerreview');
    }

    return true;
}