<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class mypeerreview_review_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        // Получаем максимальный балл из настроек активности
        $maxgrade = $customdata['maxgrade'] ?? 10; // Значение по умолчанию, если не передано
    
        // Поле для оценки
        $gradeoptions = array_combine(range(0, $maxgrade), range(0, $maxgrade));
        $mform->addElement('select', 'grade', get_string('grade', 'mypeerreview'), $gradeoptions);
        $mform->setType('grade', PARAM_INT);

        // Скрытые поля
        $mform->addElement('hidden', 'id', $customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'submissionid', $customdata['submissionid']);
        $mform->setType('submissionid', PARAM_INT);

        // Поле для обратной связи
        $mform->addElement('textarea', 'feedback', get_string('feedback', 'mypeerreview'),
            ['rows' => 10, 'cols' => 60]);
        $mform->setType('feedback', PARAM_TEXT);
        $mform->addRule('feedback', get_string('required'), 'required');

        // Поле для оценки
        $maxgrade = $this->_customdata['maxgrade'];
        $gradeoptions = array_combine(range(1, $maxgrade), range(1, $maxgrade));
        $mform->addElement('select', 'grade', get_string('grade', 'mypeerreview'), $gradeoptions);

        // Кнопка отправки
        $this->add_action_buttons(true, get_string('submitreview', 'mypeerreview'));
    }
}