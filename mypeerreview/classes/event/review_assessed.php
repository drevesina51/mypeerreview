<?php
namespace mod_mypeerreview\event;

defined('MOODLE_INTERNAL') || die();

class review_assessed extends \core\event\base {
    protected function init() {
        $this->data['objecttable'] = 'mypeerreview_reviews';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return get_string('eventreviewassessed', 'mypeerreview');
    }

    public function get_description() {
        return "The teacher with id {$this->userid} assessed the review with id {$this->objectid}";
    }
}