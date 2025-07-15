<?php
defined('MOODLE_INTERNAL') || die();

function mypeerreview_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GROUPS:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return false;
    }
}

function mypeerreview_add_instance($data, $mform = null) {
    global $DB;
    
    if (isset($data->assignment_editor)) {
        $data->assignment = $data->assignment_editor['text'];
        $data->assignmentformat = $data->assignment_editor['format'];
    }
    
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    
    $id = $DB->insert_record('mypeerreview', $data);
    
    if ($id && $mform) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files(
            $data->gradingcriteria_filemanager,
            $context->id,
            'mod_mypeerreview',
            'gradingcriteria',
            0
        );
    }
    
    return $id;
}

function mypeerreview_update_instance($data, $mform = null) {
    global $DB, $CFG;
    
    $context = context_module::instance($data->coursemodule);
    
    if (isset($data->assignment_editor)) {
        $data->assignment = $data->assignment_editor['text'];
        $data->assignmentformat = $data->assignment_editor['format'];
    }
    
    $data->timemodified = time();
    $data->id = $data->instance;
    
    $result = $DB->update_record('mypeerreview', $data);
    
    if ($result && $mform) {
        file_save_draft_area_files(
            $data->gradingcriteria_filemanager,
            $context->id,
            'mod_mypeerreview',
            'gradingcriteria',
            0
        );
    }
    
    return $result;
}

function mypeerreview_delete_instance($id) {
    global $DB;

    if (!$mypeerreview = $DB->get_record('mypeerreview', ['id' => $id])) {
        return false;
    }

    $context = context_module::instance($mypeerreview->coursemodule);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_mypeerreview', 'gradingcriteria');

    $DB->delete_records('mypeerreview_submissions', ['mypeerreviewid' => $id]);
    $DB->delete_records('mypeerreview_reviews', ['mypeerreviewid' => $id]);

    return $DB->delete_records('mypeerreview', ['id' => $id]);
}

function mypeerreview_get_submissions($mypeerreviewid) {
    global $DB;
    return $DB->get_records('mypeerreview_submissions', ['mypeerreviewid' => $mypeerreviewid]);
}

function mypeerreview_user_has_submitted($mypeerreviewid, $userid) {
    global $DB;
    return $DB->record_exists('mypeerreview_submissions', [
        'mypeerreviewid' => $mypeerreviewid,
        'userid' => $userid
    ]);
}

function mypeerreview_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    
    require_login($course, true, $cm);
    
    if ($filearea === 'submissions') {
        require_capability('mod/mypeerreview:view', $context);
    } elseif ($filearea === 'gradingcriteria') {
        require_capability('mod/mypeerreview:manage', $context);
    } else {
        return false;
    }
    
    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/'.implode('/', $args).'/' : '/';
    
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_mypeerreview', $filearea, $itemid, $filepath, $filename);
    
    if (!$file) {
        return false;
    }
    
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function mypeerreview_distribute_submissions($mypeerreview) {
    global $DB;

    $submissions = $DB->get_records('mypeerreview_submissions', ['mypeerreviewid' => $mypeerreview->id]);
    if (empty($submissions)) {
        mtrace(get_string('nosubmissionsforreview', 'mypeerreview'));
        return;
    }

    $students = get_enrolled_users(context_module::instance($mypeerreview->coursemodule), 'mod/mypeerreview:review');
    if (empty($students)) {
        mtrace(get_string('nostudentsforreview', 'mypeerreview'));
        return;
    }

    $submission_ids = array_keys($submissions);
    shuffle($submission_ids);

    foreach ($students as $student) {
        $reviews_count = $DB->count_records('mypeerreview_reviews', [
            'reviewerid' => $student->id,
            'mypeerreviewid' => $mypeerreview->id
        ]);

        $needed = $mypeerreview->numreviews - $reviews_count;
        if ($needed <= 0) continue;

        $assigned = 0;
        foreach ($submission_ids as $key => $submission_id) {
            $submission = $submissions[$submission_id];
            if ($submission->userid == $student->id) continue;

            if (!$DB->record_exists('mypeerreview_reviews', [
                'submissionid' => $submission_id,
                'reviewerid' => $student->id
            ])) {
                $review = new stdClass();
                $review->mypeerreviewid = $mypeerreview->id;
                $review->submissionid = $submission_id;
                $review->reviewerid = $student->id;
                $review->timecreated = time();
                $review->completed = 0;
                $DB->insert_record('mypeerreview_reviews', $review);

                unset($submission_ids[$key]);
                $assigned++;
                if ($assigned >= $needed) break;
            }
        }
    }
}

// Новая функция для проверки анонимности
function mypeerreview_is_anonymous($mypeerreview, $context) {
    global $USER;
    
    // Преподаватели всегда видят имена
    if (has_capability('mod/mypeerreview:manage', $context)) {
        return false;
    }
    
    // Студенты видят анонимные работы, если включена настройка
    return (bool)$mypeerreview->anonymous;
}

// Функция для получения всех рецензий студента
function mypeerreview_get_student_reviews($mypeerreviewid, $reviewerid) {
    global $DB;
    
    return $DB->get_records('mypeerreview_reviews', [
        'mypeerreviewid' => $mypeerreviewid,
        'reviewerid' => $reviewerid
    ], 'timecreated DESC');
}