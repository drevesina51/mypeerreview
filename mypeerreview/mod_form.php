<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_mypeerreview_mod_form extends moodleform_mod {
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        // Name
        $mform->addElement('text', 'name', get_string('activityname', 'mypeerreview'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description
        $this->standard_intro_elements(get_string('description', 'mypeerreview'));

        // Assignment
        $mform->addElement('editor', 'assignment_editor', get_string('assignment', 'mypeerreview'),
            ['rows' => 10], $this->get_editor_options());
        $mform->setType('assignment_editor', PARAM_RAW);
        $mform->addRule('assignment_editor', get_string('required'), 'required', null, 'client');

        // Submission type
        $mform->addElement('select', 'submissiontype',
            get_string('submissiontype', 'mypeerreview'),
            [
                'text' => get_string('textsubmission', 'mypeerreview'),
                'file' => get_string('filesubmission', 'mypeerreview')
            ]
        );
        $mform->setDefault('submissiontype', 'text');

        // Number of reviews
        $mform->addElement('select', 'numreviews',
            get_string('numreviews', 'mypeerreview'),
            array_combine(range(1,5), range(1,5))
        );
        $mform->setDefault('numreviews', 3);

        $mypeergradeoptions = array_combine(range(1, 100), range(1, 100));
        $mform->addElement('select', 'maxgrade', 
            get_string('maxgrade', 'mypeerreview'), $mypeergradeoptions);
        $mform->setDefault('maxgrade', 10);
        $mform->addHelpButton('maxgrade', 'maxgrade', 'mypeerreview');

        // Grading criteria file
        $acceptedtypes = ['.pdf', '.doc', '.docx', '.txt', '.odt'];
        $mform->addElement('filemanager', 'gradingcriteria_filemanager', 
            get_string('criteriafile', 'mypeerreview'),
            null,
            [
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => 1,
                'accepted_types' => $acceptedtypes
            ]
        );

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    protected function get_editor_options() {
        return [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 0,
            'context' => $this->context,
            'noclean' => true,
            'trusttext' => true
        ];
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
    
        // Получаем context только если есть instance
        if (!empty($this->current->coursemodule)) {
            $context = context_module::instance($this->current->coursemodule);
        
            // Обработка Assignment
            if ($this->current->instance) {
                $draftid_editor = file_get_submitted_draft_itemid('assignment_editor');
                $defaultvalues['assignment_editor'] = [
                    'text' => $defaultvalues['assignment'] ?? '',
                    'format' => $defaultvalues['assignmentformat'] ?? FORMAT_HTML,
                    'itemid' => $draftid_editor
                ];
            
                // Обработка файлов критериев
                $draftitemid = file_get_submitted_draft_itemid('gradingcriteria_filemanager');
                file_prepare_draft_area($draftitemid, $context->id, 'mod_mypeerreview', 'gradingcriteria', 0);
                $defaultvalues['gradingcriteria_filemanager'] = $draftitemid;
            }
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
    
        // Сохраняем данные из редактора assignment
        if (isset($data->assignment_editor)) {
            $data->assignment = $data->assignment_editor['text'];
            $data->assignmentformat = $data->assignment_editor['format'];
        }
    
        // Не удаляем поля, так как они нужны для сохранения
        return $data;
    }
}