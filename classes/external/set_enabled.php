<?php
namespace local_gradebookxp_cg\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_gradebookxp_cg\cockpit_service;

/** Set the current user's voluntary connection setting. */
class set_enabled extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(\PARAM_INT, 'Course ID'),
            'enabled' => new external_value(\PARAM_BOOL, 'Connection state'),
        ]);
    }

    public static function execute($courseid, $enabled): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'enabled'));
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/gradebookxp_cg:use', $context);
        if (!cockpit_service::is_course_enabled($params['courseid'])) {
            throw new \moodle_exception('coursedisabled', 'local_gradebookxp_cg');
        }
        cockpit_service::set_enabled($params['courseid'], $USER->id, (bool)$params['enabled']);
        return ['enabled' => (bool)$params['enabled']];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'enabled' => new external_value(\PARAM_BOOL, 'Stored connection state'),
        ]);
    }
}
