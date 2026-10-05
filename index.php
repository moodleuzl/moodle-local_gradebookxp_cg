<?php
require_once('../../config.php');

use local_gradebookxp_cg\cockpit_service;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/gradebookxp_cg:use', $context);
if (!cockpit_service::is_course_enabled($courseid)) {
    throw new moodle_exception('coursedisabled', 'local_gradebookxp_cg');
}

$url = new moodle_url('/local/gradebookxp_cg/index.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_gradebookxp_cg'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/gradebookxp_cg/styles.css');

if (optional_param('saveoptin', 0, PARAM_BOOL)) {
    require_sesskey();
    cockpit_service::set_enabled($courseid, $USER->id, optional_param('enabled', 0, PARAM_BOOL));
    redirect($url, get_string('preferencesaved', 'local_gradebookxp_cg'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$enabled = cockpit_service::is_enabled($courseid, $USER->id);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('optintitle', 'local_gradebookxp_cg'));
echo html_writer::tag('p', get_string('optindescription', 'local_gradebookxp_cg'));
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'gradebookxp-cg-optin']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveoptin', 'value' => 1]);
$attributes = ['type' => 'checkbox', 'name' => 'enabled', 'id' => 'gradebookxp-cg-optin', 'value' => 1];
if ($enabled) {
    $attributes['checked'] = 'checked';
}
echo html_writer::empty_tag('input', $attributes);
echo html_writer::tag('label', get_string('optinlabel', 'local_gradebookxp_cg'), ['for' => 'gradebookxp-cg-optin']);
echo html_writer::empty_tag('br');
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
