<?php
defined('MOODLE_INTERNAL') || die();

function ytvideo_add_instance($ytvideo) {
    global $DB;
    $ytvideo->timecreated = time();
    $ytvideo->timemodified = time();
    
    $id = $DB->insert_record('ytvideo', $ytvideo);
    $ytvideo->id = $id;

    if (isset($ytvideo->upload_editor)) {
        $context = context_module::instance($ytvideo->coursemodule);
        $ytvideo->upload = file_save_draft_area_files(
            $ytvideo->upload_editor['itemid'],
            $context->id,
            'mod_ytvideo',
            'upload',
            0,
            array('subdirs' => true),
            $ytvideo->upload_editor['text']
        );
        $ytvideo->uploadformat = $ytvideo->upload_editor['format'];
        $DB->update_record('ytvideo', $ytvideo);
    }
    return $id;
}

function ytvideo_update_instance($ytvideo) {
    global $DB;
    $ytvideo->id = $ytvideo->instance;
    $ytvideo->timemodified = time();
    
    if (isset($ytvideo->upload_editor)) {
        $context = context_module::instance($ytvideo->coursemodule);
        $ytvideo->upload = file_save_draft_area_files(
            $ytvideo->upload_editor['itemid'],
            $context->id,
            'mod_ytvideo',
            'upload',
            0,
            array('subdirs' => true),
            $ytvideo->upload_editor['text']
        );
        $ytvideo->uploadformat = $ytvideo->upload_editor['format'];
    }
    return $DB->update_record('ytvideo', $ytvideo);
}

function ytvideo_delete_instance($id) {
    global $DB;
    if (!$ytvideo = $DB->get_record('ytvideo', array('id' => $id))) {
        return false;
    }

    // CLEANUP: Delete files and progress records
    $cm = get_coursemodule_from_instance('ytvideo', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_ytvideo', 'upload');
    }
    
    $DB->delete_records('ytvideo_progress', array('ytvideoid' => $id));
    $DB->delete_records('ytvideo', array('id' => $id));
    return true;
}

function ytvideo_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'upload') {
        return false;
    }
    require_login($course, true, $cm);
    $itemid = (int)array_shift($args);
    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = empty($args) ? '/' : '/'.implode('/', $args).'/';
    $file = $fs->get_file($context->id, 'mod_ytvideo', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function ytvideo_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO: return false;
        default: return null;
    }
}