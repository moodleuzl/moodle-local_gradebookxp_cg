<?php
require_once('../../config.php');

use local_gradebookxp_cg\cockpit_service;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/gradebookxp_cg:manage', $context);

$url = new moodle_url('/local/gradebookxp_cg/configure.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('configuration', 'local_gradebookxp_cg'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/gradebookxp_cg/styles.css');

if (optional_param('saveconfig', 0, PARAM_BOOL)) {
    require_sesskey();
    $courseenabled = optional_param('courseenabled', 0, PARAM_BOOL);
    $submitteddata = data_submitted();
    $submitted = isset($submitteddata->mapping) && is_array($submitteddata->mapping)
        ? $submitteddata->mapping
        : [];
    $mappings = [];
    for ($mode = 1; $mode <= 4; $mode++) {
        for ($version = 1; $version <= 4; $version++) {
            $rawcmid = $submitted[$mode][$version] ?? 0;
            // Only the expected scalar leaves are accepted. The service below
            // additionally verifies that the cmid belongs to this course and
            // represents a numeric grade item.
            $cmid = is_scalar($rawcmid) ? clean_param($rawcmid, PARAM_INT) : 0;
            $mappings[] = [
                'mode' => $mode,
                'version' => $version,
                'cmid' => (int)$cmid,
            ];
        }
    }
    cockpit_service::set_course_enabled($courseid, $courseenabled);
    cockpit_service::save_mappings($courseid, $mappings);
    redirect($url, get_string('configsaved', 'local_gradebookxp_cg'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$activities = cockpit_service::get_gradable_course_modules($courseid);
$stored = cockpit_service::get_mappings($courseid);
$options = [0 => get_string('nomapping', 'local_gradebookxp_cg')];
foreach ($activities as $cmid => $activity) {
    $options[$cmid] = format_string($activity->itemname);
}
$modes = [
    1 => get_string('modeassembly', 'local_gradebookxp_cg'),
    2 => get_string('modehmm', 'local_gradebookxp_cg'),
    3 => get_string('modeclustering', 'local_gradebookxp_cg'),
    4 => get_string('modealignment', 'local_gradebookxp_cg'),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('configuration', 'local_gradebookxp_cg'));
echo html_writer::tag('p', get_string('configdescription', 'local_gradebookxp_cg'));
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'gradebookxp-cg-config']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'saveconfig', 'value' => 1]);

$enabledattributes = ['type' => 'checkbox', 'name' => 'courseenabled', 'id' => 'gradebookxp-cg-courseenabled', 'value' => 1];
if (cockpit_service::is_course_enabled($courseid)) {
    $enabledattributes['checked'] = 'checked';
}
echo html_writer::start_div('gradebookxp-cg-course-switch');
echo html_writer::empty_tag('input', $enabledattributes);
echo html_writer::tag('label', get_string('enablecourse', 'local_gradebookxp_cg'),
    ['for' => 'gradebookxp-cg-courseenabled']);
echo html_writer::end_div();

foreach ($modes as $mode => $modename) {
    echo html_writer::start_tag('fieldset', ['class' => 'gradebookxp-cg-mode']);
    echo html_writer::tag('legend', $modename);
    echo html_writer::start_div('gradebookxp-cg-grid');
    for ($version = 1; $version <= 4; $version++) {
        $selected = $stored[(string)$mode][$version - 1];
        $invalid = $selected !== null && !isset($activities[$selected]);
        if ($invalid) {
            $selected = 0;
        }
        $id = 'gradebookxp-cg-' . $mode . '-' . $version;
        echo html_writer::start_div('gradebookxp-cg-slot');
        echo html_writer::tag('label', get_string('firmware', 'local_gradebookxp_cg') . ' ' . $mode . '.' . $version,
            ['for' => $id]);
        echo html_writer::select($options, "mapping[$mode][$version]", $selected ?: 0, false,
            ['id' => $id, 'class' => 'form-control']);
        if ($invalid) {
            echo html_writer::tag('small', get_string('invalidmapping', 'local_gradebookxp_cg'),
                ['class' => 'text-warning']);
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_tag('fieldset');
}
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
