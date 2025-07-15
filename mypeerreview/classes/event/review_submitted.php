<?php

namespace mod_mypeerreview\event;

defined('MOODLE_INTERNAL') || die();

class review_submitted extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'mypeerreview_reviews';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('eventreviewsubmitted', 'mypeerreview');
    }

    public function get_description() {
        return "User {$this->userid} has submitted a review for submission {$this->objectid}";
    }
}