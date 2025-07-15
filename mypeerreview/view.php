<?php
require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);
$mypeerreview = $DB->get_record('mypeerreview', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/mypeerreview:view', $context);

$PAGE->set_url('/mod/mypeerreview/view.php', ['id' => $id]);
$PAGE->set_title(format_string($mypeerreview->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($mypeerreview->name));

// Вывод задания
echo '<h4>' . get_string('assignment', 'mypeerreview') . '</h4>';
if (!empty($mypeerreview->assignment)) {
    echo $OUTPUT->box(format_text($mypeerreview->assignment, $mypeerreview->assignmentformat, 
        ['context' => $context]), 'generalbox assignment');
}

// Вывод критериев оценки
echo '<h4>' . get_string('gradingcriteria', 'mypeerreview') . '</h4>';
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_mypeerreview', 'gradingcriteria', 0, 'sortorder, itemid, filepath, filename', false);

if (count($files)) {
    foreach ($files as $file) {
        if ($file->is_directory()) {
            continue;
        }
        
        $filename = $file->get_filename();
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $filename
        );
        
        if ($file->is_valid_image() || strpos($file->get_mimetype(), 'text/') === 0) {
            $content = $file->get_content();
            echo '<div class="criteria-content">' . format_text($content, FORMAT_HTML) . '</div>';
        } else {
            echo '<div class="criteria-file"><a href="' . $fileurl . '" class="btn btn-secondary">' . 
                 get_string('downloadfile', 'mypeerreview', $filename) . '</a></div>';
        }
    }
} else {
    echo '<div class="alert alert-info">' . get_string('nogradingcriteria', 'mypeerreview') . '</div>';
}

// Интерфейс преподавателя
if (has_capability('mod/mypeerreview:manage', $context)) {
    echo $OUTPUT->box_start('generalbox teacherview');
    echo '<h3>' . get_string('teacherinterface', 'mypeerreview') . '</h3>';
    
    // Исправленный SQL-запрос для статистики
    $reviews_stats = $DB->get_records_sql("
        SELECT s.id, s.userid, u.firstname, u.lastname,
               COUNT(r.id) as total_reviews,
               SUM(CASE WHEN r.completed = 1 THEN 1 ELSE 0 END) as completed_reviews,
               AVG(CASE WHEN r.completed = 1 THEN r.grade ELSE NULL END) as avg_grade,
               MAX(r.teachergrade) as teachergrade
        FROM {mypeerreview_submissions} s
        JOIN {user} u ON s.userid = u.id
        LEFT JOIN {mypeerreview_reviews} r ON s.id = r.submissionid
        WHERE s.mypeerreviewid = ?
        GROUP BY s.id, s.userid, u.firstname, u.lastname
    ", [$mypeerreview->id]);

    $submissions = $DB->get_records('mypeerreview_submissions', ['mypeerreviewid' => $mypeerreview->id]);

    if ($submissions) {
        echo '<div class="submissions-table">';
        echo '<table class="generaltable">';
        echo '<thead><tr>';
        echo '<th>' . get_string('student', 'mypeerreview') . '</th>';
        echo '<th>' . get_string('submissiondate', 'mypeerreview') . '</th>';
        echo '<th>' . get_string('submission', 'mypeerreview') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($submissions as $submission) {
            $user = $DB->get_record('user', ['id' => $submission->userid]);
            echo '<tr>';
            echo '<td>' . fullname($user) . '</td>';
            echo '<td>' . userdate($submission->timecreated) . '</td>';
            echo '<td><a href="view_submission.php?id='.$id.'&submissionid='.$submission->id.'" target="_blank">' . 
                 get_string('viewsubmission', 'mypeerreview') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        // Статистика рецензий
        echo '<h4>'.get_string('reviewstats', 'mypeerreview').'</h4>';
        echo '<table class="table table-bordered">';
        echo '<thead><tr>';
        echo '<th>'.get_string('student', 'mypeerreview').'</th>';
        echo '<th>'.get_string('submission', 'mypeerreview').'</th>';
        echo '<th>'.get_string('reviews', 'mypeerreview').'</th>';
        echo '<th>'.get_string('averagegrade', 'mypeerreview').'</th>';
        echo '<th>'.get_string('teachergrade', 'mypeerreview').'</th>';
        echo '<th>'.get_string('actions', 'mypeerreview').'</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($reviews_stats as $stat) {
            $user = $DB->get_record('user', ['id' => $stat->userid]);
            echo '<tr>';
            echo '<td>'.fullname($user).'</td>';
            echo '<td><a href="view_submission.php?id='.$id.'&submissionid='.$stat->id.'" target="_blank">'.
                 get_string('view', 'mypeerreview').'</a></td>';
            echo '<td>'.$stat->completed_reviews.'/'.$stat->total_reviews.'</td>';
            echo '<td>'.($stat->avg_grade ? number_format($stat->avg_grade, 1) : '-').'</td>';
            echo '<td>'.($stat->teachergrade ? $stat->teachergrade.'/'.$mypeerreview->maxgrade : '-').'</td>';
    
            // Вот эта строка заменяется:
            echo '<td><a href="teacher_review.php?id='.$id.'&reviewerid='.$stat->userid.'" class="btn btn-secondary">'.
                 get_string('assessreviews', 'mypeerreview').'</a></td>';
    
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Кнопка запуска рецензирования
        if (!$mypeerreview->reviewstarted) {
            echo '<form method="post" action="start_review.php" class="start-review-form">';
            echo '<input type="hidden" name="id" value="'.$id.'">';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';
            echo '<div class="form-group">';
            echo '<label><input type="checkbox" name="anonymous" value="1"> '.
                 get_string('anonymousreviews', 'mypeerreview').'</label>';
            echo '</div>';
            echo '<input type="submit" class="btn btn-primary" value="'.
                 get_string('startreview', 'mypeerreview').'">';
            echo '</form>';
        }
    } else {
        echo '<p>' . get_string('nosubmissions', 'mypeerreview') . '</p>';
    }

    echo $OUTPUT->box_end();
}

// Интерфейс студента
elseif (has_capability('mod/mypeerreview:submit', $context) && !has_capability('mod/mypeerreview:manage', $context)) {
    echo $OUTPUT->box_start('generalbox studentview');
    echo '<h3>' . get_string('studentinterface', 'mypeerreview') . '</h3>';

    $submission = $DB->get_record('mypeerreview_submissions', [
        'mypeerreviewid' => $mypeerreview->id,
        'userid' => $USER->id
    ]);

    if ($submission) {
        echo '<div class="alert alert-success">' . get_string('alreadysubmitted', 'mypeerreview') . '</div>';
        echo '<h4>' . get_string('yoursubmission', 'mypeerreview') . '</h4>';

        if ($mypeerreview->submissiontype == 'text') {
            echo format_text($submission->content, FORMAT_HTML);
        } else {
            $fileurl = moodle_url::make_pluginfile_url(
                $context->id, 
                'mod_mypeerreview', 
                'submissions', 
                $submission->id, 
                '/', 
                $submission->filename
            );
            echo '<p><a href="' . $fileurl . '" class="btn btn-primary">' . 
                 $submission->filename . '</a></p>';
        }
    } else {
        echo '<form method="post" action="submit.php" enctype="multipart/form-data" class="submission-form">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

        if ($mypeerreview->submissiontype == 'text') {
            echo '<div class="form-group">';
            echo '<label>' . get_string('submissioncontent', 'mypeerreview') . '</label>';
            echo '<textarea name="content" rows="10" class="form-control" required></textarea>';
            echo '</div>';
        } else {
            echo '<div class="form-group">';
            echo '<label>' . get_string('submissionfile', 'mypeerreview') . '</label>';
            echo '<input type="file" name="submissionfile" class="form-control-file" required>';
            echo '</div>';
        }

        echo '<button type="submit" class="btn btn-primary">' . 
             get_string('submitwork', 'mypeerreview') . '</button>';
        echo '</form>';
    }

    // Работы на рецензирование
    $reviews_to_do = $DB->get_records('mypeerreview_reviews', [
        'reviewerid' => $USER->id,
        'completed' => 0,
        'mypeerreviewid' => $mypeerreview->id
    ]);

    if ($reviews_to_do) {
        echo $OUTPUT->box_start('generalbox reviewstodo');
        echo '<h4>' . get_string('reviewstodo', 'mypeerreview') . '</h4>';

        foreach ($reviews_to_do as $review) {
            $submission = $DB->get_record('mypeerreview_submissions', ['id' => $review->submissionid]);
            $submitter = $DB->get_record('user', ['id' => $submission->userid]);

            echo '<div class="submission-to-review">';
            if ($mypeerreview->anonymous) {
                echo '<p>' . get_string('anonymoussubmission', 'mypeerreview') . '</p>';
            } else {
                echo '<p>' . get_string('submissionfrom', 'mypeerreview', fullname($submitter)) . '</p>';
            }

            if ($mypeerreview->submissiontype == 'text') {
                echo '<div class="submission-preview">' . 
                     format_text(shorten_text($submission->content, 200), FORMAT_HTML) . '</div>';
            } else {
                echo '<p>' . $submission->filename . '</p>';
            }

            echo '<a href="review.php?id='.$id.'&submissionid='.$submission->id.'" class="btn btn-primary">' .
                 get_string('doreview', 'mypeerreview') . '</a>';
            echo '</div>';
        }

        echo $OUTPUT->box_end();
    }

    // Полученные рецензии
    if (!empty($submission)) {
        $received_reviews = $DB->get_records('mypeerreview_reviews', [
            'submissionid' => $submission->id,
            'completed' => 1
        ]);

        if ($received_reviews) {
            echo $OUTPUT->box_start('generalbox receivedreviews');
            echo '<h4>' . get_string('yourreviews', 'mypeerreview') . '</h4>';

            $grades = [];
            foreach ($received_reviews as $review) {
                if ($review->grade) {
                    $grades[] = $review->grade;
                }
                
                echo '<div class="review">';
                echo '<div class="review-grade">' . get_string('grade', 'mypeerreview') . ': ' . 
                     ($review->grade ? $review->grade : '-') . '</div>';
                echo '<div class="review-feedback">' . format_text($review->feedback, FORMAT_HTML) . '</div>';
                echo '</div>';
            }

            if (!empty($grades)) {
                echo '<div class="average-grade">' . get_string('averagegrade', 'mypeerreview') . ': ' .
                     number_format(array_sum($grades)/count($grades), 1) . '</div>';
            }

            echo $OUTPUT->box_end();
        }
    }

    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();