<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$anonymous = optional_param('anonymous', 0, PARAM_INT);

// Get course module and mypeerreview record
$cm = get_coursemodule_from_id('mypeerreview', $id, 0, false, MUST_EXIST);
$mypeerreview = $DB->get_record('mypeerreview', ['id' => $cm->instance], '*', MUST_EXIST);

// Require login and manage capability
require_login($cm->course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/mypeerreview:manage', $context); // Только преподаватель может запускать

// Get submissions and enrolled students
$submissions = $DB->get_records('mypeerreview_submissions', ['mypeerreviewid' => $mypeerreview->id]);
$students = get_enrolled_users(context_module::instance($cm->id), 'mod/mypeerreview:review');

// Iterate through students to assign reviews
foreach ($students as $student) {
    // Skip users without review capability
    if (!has_capability('mod/mypeerreview:review', $context, $student)) {
        continue; // Пропускаем пользователей без права рецензирования
    }

    $submission_ids = array_keys($submissions);
    shuffle($submission_ids);

    $assigned = 0;
    foreach ($submission_ids as $submission_id) {
        $submission = $submissions[$submission_id];

        // Don't assign the student's own work for review
        if ($submission->userid == $student->id) {
            continue;
        }

        // Check if this review has already been assigned
        if (!$DB->record_exists('mypeerreview_reviews', [
            'reviewerid' => $student->id,
            'submissionid' => $submission_id,
            'mypeerreviewid' => $mypeerreview->id, // Added to prevent issues with other modules
        ])) {
            $review = new stdClass();
            $review->mypeerreviewid = $mypeerreview->id;
            $review->submissionid = $submission_id;
            $review->reviewerid = $student->id;
            $review->timecreated = time();
            $review->completed = 0;

            $DB->insert_record('mypeerreview_reviews', $review);
            $assigned++;

            if ($assigned >= $mypeerreview->numreviews) {
                break; // Stop assigning reviews if the maximum number has been reached
            }
        }
    }
}

// Update module settings
$mypeerreview->reviewstarted = time();
$mypeerreview->anonymous = $anonymous;
$DB->update_record('mypeerreview', $mypeerreview);

// Redirect back to the module view page
redirect(new moodle_url('/mod/mypeerreview/view.php', ['id' => $id]),
    get_string('reviewstartedsuccess', 'mypeerreview'));