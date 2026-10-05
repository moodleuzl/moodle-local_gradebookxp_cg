<?php
defined('MOODLE_INTERNAL') || die();

/** Add the private extension to enabled course navigation. */
function local_gradebookxp_cg_extend_navigation_course($navigation, $course, $context) {
    global $DB;

    $canmanage = has_capability('local/gradebookxp_cg:manage', $context);
    $enabled = (bool)$DB->get_field('local_gradebookxp_cg_course', 'enabled', ['courseid' => $course->id]);
    if (!$canmanage && (!$enabled || !has_capability('local/gradebookxp_cg:use', $context))) {
        return;
    }
    $url = $canmanage
        ? new moodle_url('/local/gradebookxp_cg/configure.php', ['courseid' => $course->id])
        : new moodle_url('/local/gradebookxp_cg/index.php', ['courseid' => $course->id]);
    $navigation->add(get_string('pluginname', 'local_gradebookxp_cg'), $url,
        navigation_node::TYPE_CUSTOM, null, 'local_gradebookxp_cg');
}
