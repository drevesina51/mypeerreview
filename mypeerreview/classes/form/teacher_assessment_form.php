<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class teacher_assessment_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;
        
        // Teacher grade
        $mform->addElement('select', 'teachergrade', 
            get_string('teachergrade', 'mypeerreview'),
            range(0, $customdata['maxgrade'])
        );
        $mform->setDefault('teachergrade', 0);
        
        // Teacher feedback
        $mform->addElement('textarea', 'teacherfeedback', 
            get_string('teacherfeedback', 'mypeerreview'),
            ['rows' => 5, 'cols' => 60]
        );
        
        // Hidden fields
        $mform->addElement('hidden', 'id', $customdata['id']);
        $mform->addElement('hidden', 'reviewerid', $customdata['reviewerid']);
        $mform->setType('id', PARAM_INT);
        $mform->setType('reviewerid', PARAM_INT);
        
        $this->add_action_buttons(true, get_string('saveassessment', 'mypeerreview'));
    }
}