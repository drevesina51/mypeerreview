<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/backup/moodle2/backup_activity_task.class.php');

class backup_mypeerreview_activity_task extends backup_activity_task {
    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_mypeerreview_activity_structure_step('mypeerreview_structure', 'mypeerreview.xml'));
    }

    static public function encode_content_links($content) {
        return $content;
    }
}