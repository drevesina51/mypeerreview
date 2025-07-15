<?php
require_once('../../config.php');
require_login();

$contextid = required_param('contextid', PARAM_INT);
$component = required_param('component', PARAM_TEXT);
$filearea  = required_param('filearea',  PARAM_TEXT);
$itemid    = required_param('itemid',    PARAM_INT);
$filepath  = required_param('filepath',  PARAM_PATH);
$filename  = required_param('filename',  PARAM_FILE);

$context = context::instance_by_id($contextid);
if ($context->contextlevel != CONTEXT_MODULE) {
    send_file_not_found();
}

// Проверяем права доступа
$cm = get_coursemodule_from_id('mypeerreview', $context->instanceid, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);

// Дополнительные проверки прав
if ($filearea === 'submissions') {
    require_capability('mod/mypeerreview:view', $context);
} elseif ($filearea === 'gradingcriteria') {
    require_capability('mod/mypeerreview:manage', $context);
} else {
    send_file_not_found();
}

$fs = get_file_storage();
$file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

if (!$file || $file->is_directory()) {
    send_file_not_found();
}

send_stored_file($file, 0, 0, false, ['preview' => false]);