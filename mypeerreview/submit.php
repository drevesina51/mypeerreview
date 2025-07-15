<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$mypeerreview = $DB->get_record('mypeerreview', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/mypeerreview:submit', $context);

if ($mypeerreview->submissiontype == 'file') {
    if (!empty($_FILES['submissionfile']) && $_FILES['submissionfile']['error'] == 0) {
        // ������� ������� ������ � submission
        $submission = new stdClass();
        $submission->mypeerreviewid = $cm->instance;
        $submission->userid = $USER->id;
        $submission->timecreated = time();
        $submission->timemodified = time();
        
        // ��������� ������ � �������� ID
        $submission->id = $DB->insert_record('mypeerreview_submissions', $submission);
        
        // ������ ��������� ���� � ���������� itemid
        $file = $_FILES['submissionfile'];
        $filename = clean_param($file['name'], PARAM_FILE);
        
        $fs = get_file_storage();
        $file_record = [
            'contextid' => $context->id,
            'component' => 'mod_mypeerreview',
            'filearea'  => 'submissions',
            'itemid'   => $submission->id, // ���������� ID submission
            'filepath'  => '/',
            'filename'  => $filename,
            'userid'    => $USER->id
        ];
        
        try {
            $stored_file = $fs->create_file_from_pathname($file_record, $file['tmp_name']);
            
            // ��������� ������ submission � ����������� � �����
            $submission->filename = $filename;
            $submission->filesize = $stored_file->get_filesize();
            $submission->filetype = $stored_file->get_mimetype();
            $submission->itemid = $submission->id; // ��������� itemid
            
            $DB->update_record('mypeerreview_submissions', $submission);
            
            redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
                get_string('submissionsuccess', 'mypeerreview'));
                
        } catch (Exception $e) {
            // ������� ������ submission ���� ���������� ����� �� �������
            $DB->delete_records('mypeerreview_submissions', ['id' => $submission->id]);
            print_error('submissionfailed', 'mypeerreview', '', $e->getMessage());
        }
        
    } else {
        redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
            get_string('nofilesubmission', 'mypeerreview'), null, \core\output\notification::NOTIFY_ERROR);
    }
} else {
    // ��������� ��������� ��������
    $text = required_param('content', PARAM_TEXT);
    
    $submission = new stdClass();
    $submission->mypeerreviewid = $cm->instance;
    $submission->userid = $USER->id;
    $submission->content = $text;
    $submission->timecreated = time();
    $submission->timemodified = time();
    
    $DB->insert_record('mypeerreview_submissions', $submission);
    
    redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
        get_string('submissionsuccess', 'mypeerreview'));
}