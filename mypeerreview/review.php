<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/mypeerreview/lib.php');
require_once($CFG->dirroot.'/mod/mypeerreview/classes/form/review_form.php');

$id = required_param('id', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$mypeerreview = $DB->get_record('mypeerreview', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

// 1. Получаем submission и проверяем его существование
$submission = $DB->get_record('mypeerreview_submissions', ['id' => $submissionid]);
if (!$submission) {
    throw new moodle_exception('submissionnotfound', 'mypeerreview');
}

// 2. Проверка прав
if (!has_capability('mod/mypeerreview:review', $context)) {
    throw new moodle_exception('nopermissiontoreview', 'mypeerreview');
}

// 3. Проверка что не рецензирует свою работу (кроме преподавателей)
if ($USER->id == $submission->userid && !has_capability('mod/mypeerreview:manage', $context)) {
    throw new moodle_exception('cannotreviewownwork', 'mypeerreview');
}

$PAGE->set_url('/mod/mypeerreview/review.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_title(format_string($mypeerreview->name));
$PAGE->set_heading(format_string($course->fullname));

// 4. Получаем или создаем рецензию
$review = $DB->get_record('mypeerreview_reviews', [
    'submissionid' => $submissionid,
    'reviewerid' => $USER->id,
    'mypeerreviewid' => $mypeerreview->id
]);

if (!$review) {
    $review = new stdClass();
    $review->mypeerreviewid = $mypeerreview->id;
    $review->submissionid = $submissionid;
    $review->reviewerid = $USER->id;
    $review->timecreated = time();
    $review->timemodified = $review->timecreated;
    $review->id = $DB->insert_record('mypeerreview_reviews', $review);
}

// 5. Настройка формы с текущими данными
$formdata = [
    'id' => $id,
    'submissionid' => $submissionid,
    'feedback' => $review->feedback ?? '',
    'grade' => $review->grade ?? null
];
$form = new mypeerreview_review_form(null, $formdata);

// 6. Обработка отправки формы
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]));
} else if ($data = $form->get_data()) {
    $review->feedback = $data->feedback;
    $review->grade = $data->grade;
    $review->timemodified = time();
    $review->completed = 1;

    $DB->update_record('mypeerreview_reviews', $review);

    // 7. Отправка уведомления
    if ($mypeerreview->anonymous) {
        $eventdata = [
            'context' => $context,
            'objectid' => $review->id,
            'userid' => $submission->userid,
            'anonymous' => true
        ];
    } else {
        $eventdata = [
            'context' => $context,
            'objectid' => $review->id,
            'userid' => $submission->userid,
            'relateduserid' => $USER->id
        ];
    }
    
    // Триггерим событие (нужно добавить в classes/event/review_submitted.php)
    \mod_mypeerreview\event\review_submitted::create($eventdata)->trigger();

    redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
        get_string('reviewsubmitted', 'mypeerreview'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// 8. Отображение формы
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewsubmission', 'mypeerreview'));

// 9. Отображение информации о работе
$user = $DB->get_record('user', ['id' => $submission->userid]);
$anonymous = $mypeerreview->anonymous && !has_capability('mod/mypeerreview:manage', $context);

echo '<div class="submission-info">';
if (!$anonymous) {
    echo '<h3>'.get_string('submissionfrom', 'mypeerreview', fullname($user)).'</h3>';
} else {
    echo '<h3>'.get_string('anonymoussubmission', 'mypeerreview').'</h3>';
}

if ($mypeerreview->submissiontype == 'text') {
    echo '<div class="submission-content">'.format_text($submission->content, FORMAT_HTML).'</div>';
} else {
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_mypeerreview', 'submissions', $submission->id, '/', $submission->filename);
    if ($file) {
        $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), 
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
        echo '<div class="submission-file"><a href="'.$url.'" class="btn btn-secondary">'.
             get_string('downloadfile', 'mypeerreview', $file->get_filename()).'</a></div>';
    }
}
echo '</div>';

// 10. Отображение формы рецензирования
$form->display();

echo $OUTPUT->footer();