<?php
defined('MOODLE_INTERNAL') || die();

class backup_mypeerreview_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $mypeerreview = new backup_nested_element('mypeerreview', ['id'], [
            'course', 'name', 'intro', 'introformat',
            'submissiontype', 'numreviews', 'gradingcriteria',
            'timecreated', 'timemodified', 'assignment'
        ]);

        $mypeerreview->set_source_table('mypeerreview', ['id' => backup::VAR_ACTIVITYID]);
        return $this->prepare_activity_structure($mypeerreview);
    }
}