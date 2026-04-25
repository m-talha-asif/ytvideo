<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// Get the data sent via POST
$ytvideoid = required_param('ytvideoid', PARAM_INT);
$progress  = required_param('progress', PARAM_INT);
$type      = required_param('type', PARAM_ALPHA); // Expected: 'yt' or 'local'

// Ensure the user is logged in and validate the session key
require_login();
require_sesskey();

global $DB, $USER;

// Look for an existing record
$record = $DB->get_record('ytvideo_progress', array(
    'ytvideoid' => $ytvideoid, 
    'userid'    => $USER->id
));

$data = new stdClass();
$data->ytvideoid    = $ytvideoid;
$data->userid       = $USER->id;
$data->timemodified = time();

// Assign progress ONLY to the correct column. Do not set the other to 0!
if ($type === 'yt') {
    $data->yt_progress = $progress;
} else if ($type === 'local') {
    $data->local_progress = $progress;
}

if ($record) {
    // Update existing record
    $data->id = $record->id;
    // Moodle will only update the fields present in the $data object
    $DB->update_record('ytvideo_progress', $data);
} else {
    // Insert new record (Set the missing property to 0 just for the initial creation)
    if (!isset($data->yt_progress)) { $data->yt_progress = 0; }
    if (!isset($data->local_progress)) { $data->local_progress = 0; }
    
    $DB->insert_record('ytvideo_progress', $data);
}

// Return success status for the fetch request
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
die();