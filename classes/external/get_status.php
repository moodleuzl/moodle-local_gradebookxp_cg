<?php
namespace local_gradebookxp_cg\external;

defined('MOODLE_INTERNAL') || die();

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_gradebookxp_cg\cockpit_service;

/** Return the current user's privacy-minimised firmware status. */
class get_status extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(\PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute($courseid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/gradebookxp_cg:use', $context);
        $status = cockpit_service::get_status($params['courseid'], $USER->id);
        return [
            'enabled' => $status['enabled'],
            'cockpitjson' => $status['cockpit'] === null ? '' : json_encode($status['cockpit']),
            'reason' => $status['reason'] ?? '',
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'enabled' => new external_value(\PARAM_BOOL, 'Effective activation state'),
            'cockpitjson' => new external_value(\PARAM_RAW, 'JSON matrix, empty when disabled'),
            'reason' => new external_value(\PARAM_ALPHANUMEXT, 'Disabled reason, empty when enabled'),
        ]);
    }
}
