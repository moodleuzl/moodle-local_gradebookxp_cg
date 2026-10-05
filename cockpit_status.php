<?php
define('AJAX_SCRIPT', true);

require_once('../../config.php');

use local_gradebookxp_cg\cockpit_service;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/gradebookxp_cg:use', $context);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
echo json_encode(cockpit_service::get_status($courseid, $USER->id));
