<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$mypeerreview = $DB->get_record('mypeerreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/mypeerreview:view', $context);

$submission = $DB->get_record('mypeerreview_submissions', ['id' => $submissionid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $submission->userid]);

$PAGE->set_url('/mod/mypeerreview/view_submission.php', ['id' => $id, 'submissionid' => $submissionid]);
$PAGE->set_title(get_string('viewsubmission', 'mypeerreview'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('viewsubmission', 'mypeerreview'));

// Отображаем информацию о студенте (если не анонимно)
if (!$mypeerreview->anonymous || has_capability('mod/mypeerreview:manage', $context)) {
    echo html_writer::tag('p', get_string('submissionfrom', 'mypeerreview', fullname($user)));
}

// Отображаем содержимое отправки
if ($mypeerreview->submissiontype == 'text') {
    echo $OUTPUT->box(format_text($submission->content, FORMAT_HTML), 'generalbox submissioncontent');
} else {
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_mypeerreview', 'submissions', $submission->id, '/', $submission->filename);
    if ($file) {
        $fileurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), 
                    $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
        echo html_writer::link($fileurl, $file->get_filename(), ['class' => 'btn btn-primary']);
    }
}

echo $OUTPUT->footer();