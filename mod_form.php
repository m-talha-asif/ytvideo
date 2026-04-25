<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_ytvideo_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'youtubeurl', get_string('youtubeurl', 'mod_ytvideo'), ['size'=>'64']);
        $mform->setType('youtubeurl', PARAM_URL);

        $mform->addElement('editor', 'upload_editor', get_string('upload', 'mod_ytvideo'), null, array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true));
        $mform->setType('upload_editor', PARAM_RAW);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {
        if ($this->current->instance) {
            $context = context_module::instance($this->_cm->id);
            $draftitemid = file_get_submitted_draft_itemid('upload_editor');
            $default_values['upload_editor'] = array(
                'text' => file_prepare_draft_area($draftitemid, $context->id, 'mod_ytvideo', 'upload', 0, array('subdirs' => true), $default_values['upload'] ?? ''),
                'format' => $default_values['uploadformat'] ?? FORMAT_HTML,
                'itemid' => $draftitemid
            );
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $has_url = !empty(trim($data['youtubeurl']));
        $upload_text = trim(strip_tags($data['upload_editor']['text']));
        
        // Check if there are any files in the draft area
        $usercontext = context_user::instance($GLOBALS['USER']->id);
        $fs = get_file_storage();
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['upload_editor']['itemid'], 'id', false);

        if (!$has_url && empty($upload_text) && empty($draftfiles)) {
            $errors['youtubeurl'] = get_string('requireone', 'mod_ytvideo');
            $errors['upload_editor'] = get_string('requireone', 'mod_ytvideo');
        }
        return $errors;
    }
}