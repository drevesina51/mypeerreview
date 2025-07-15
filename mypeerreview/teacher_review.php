<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/mypeerreview/classes/form/teacher_assessment_form.php');

$id = required_param('id', PARAM_INT);
$reviewerid = required_param('reviewerid', PARAM_INT); // Теперь принимаем ID рецензента

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/mypeerreview:manage', $context);

// Получаем все рецензии студента
$reviews = $DB->get_records('mypeerreview_reviews', [
    'reviewerid' => $reviewerid,
    'mypeerreviewid' => $cm->instance
]);

$reviewer = $DB->get_record('user', ['id' => $reviewerid]);

// Форма общей оценки
$form = new teacher_assessment_form(null, [
    'id' => $id,
    'reviewerid' => $reviewerid,
    'reviews' => $reviews
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]));
} else if ($data = $form->get_data()) {
    // Обновляем все рецензии
    foreach ($reviews as $review) {
        $review->teachergrade = $data->teachergrade;
        $review->teacherfeedback = $data->teacherfeedback;
        $review->timemarked = time();
        $DB->update_record('mypeerreview_reviews', $review);
    }
    redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]));
}

// Вывод страницы
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assessreviewsfor', 'mypeerreview', fullname($reviewer)));

// Выводим все рецензии студента
foreach ($reviews as $review) {
    $submission = $DB->get_record('mypeerreview_submissions', ['id' => $review->submissionid]);
    $author = $DB->get_record('user', ['id' => $submission->userid]);
    
    echo '<div class="review">';
    echo '<h4>'.get_string('reviewfor', 'mypeerreview', fullname($author)).'</h4>';
    echo '<p><strong>'.get_string('grade', 'mypeerreview').':</strong> '.$review->grade.'/'.$mypeerreview->maxgrade.'</p>';
    echo '<div class="feedback">'.format_text($review->feedback, FORMAT_HTML).'</div>';
    echo '</div><hr>';
}

$form->display();
echo $OUTPUT->footer();