<?php

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/mypeerreview/lib.php');

$id = required_param('id', PARAM_INT);
$reviewid = required_param('reviewid', PARAM_INT);
$teachergrade = required_param('teachergrade', PARAM_INT);
$teacherfeedback = required_param('teacherfeedback', PARAM_TEXT);

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login();
require_capability('mod/mypeerreview:manage', $context);

$review = $DB->get_record('mypeerreview_reviews', ['id' => $reviewid], '*', MUST_EXIST);
$review->teachergrade = $teachergrade;
$review->teacherfeedback = $teacherfeedback;
$review->timemarked = time();

$DB->update_record('mypeerreview_reviews', $review);

redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
    get_string('gradingsaved', 'mypeerreview'), null, \core\output\notification::NOTIFY_SUCCESS);