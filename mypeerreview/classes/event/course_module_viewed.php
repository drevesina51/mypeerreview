<?php
defined('MOODLE_INTERNAL') || die();

class mod_mypeerreview_event_course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['objecttable'] = 'mypeerreview';
        parent::init();
    }
}