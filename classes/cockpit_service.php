<?php
// This file is part of Moodle - https://moodle.org/.

namespace local_gradebookxp_cg;

defined('MOODLE_INTERNAL') || die();

/**
 * Central persistence and status calculation for the Chronagogik cockpit integration.
 *
 * @package local_gradebookxp_cg
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cockpit_service {
    /** Number of cockpit modes. */
    public const MODE_COUNT = 4;

    /** Number of firmware slots per mode. */
    public const VERSION_COUNT = 4;

    /** Mapping table name. */
    private const MAP_TABLE = 'local_gradebookxp_cg_map';

    /** Opt-in table name. */
    private const OPTIN_TABLE = 'local_gradebookxp_cg_optin';

    /** Course activation table name. */
    private const COURSE_TABLE = 'local_gradebookxp_cg_course';

    /** Return whether the extension is enabled in a course. */
    public static function is_course_enabled(int $courseid): bool {
        global $DB;
        return (bool)$DB->get_field(self::COURSE_TABLE, 'enabled', ['courseid' => $courseid]);
    }

    /** Enable or disable the extension for a course. */
    public static function set_course_enabled(int $courseid, bool $enabled): void {
        global $DB;
        $record = $DB->get_record(self::COURSE_TABLE, ['courseid' => $courseid]);
        $now = time();
        if ($record) {
            $record->enabled = (int)$enabled;
            $record->timemodified = $now;
            $DB->update_record(self::COURSE_TABLE, $record);
            return;
        }
        $DB->insert_record(self::COURSE_TABLE, (object)[
            'courseid' => $courseid,
            'enabled' => (int)$enabled,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Get the fixed empty 4 x 4 status matrix.
     *
     * @return array
     */
    public static function empty_status(): array {
        return [
            '1' => [null, null, null, null],
            '2' => [null, null, null, null],
            '3' => [null, null, null, null],
            '4' => [null, null, null, null],
        ];
    }

    /**
     * Return mappings for one course, indexed by mode and firmware version.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_mappings(int $courseid): array {
        global $DB;

        $result = self::empty_status();
        $records = $DB->get_records(self::MAP_TABLE, ['courseid' => $courseid]);
        foreach ($records as $record) {
            $mode = (int)$record->cockpitmode;
            $version = (int)$record->firmwareversion;
            if (self::valid_slot($mode, $version)) {
                $result[(string)$mode][$version - 1] = (int)$record->cmid;
            }
        }
        return $result;
    }

    /**
     * Replace all mappings for a course in one transaction.
     *
     * @param int $courseid
     * @param array $mappings List of mode/version/cmid arrays.
     */
    public static function save_mappings(int $courseid, array $mappings): void {
        global $DB;

        $validcms = self::get_gradable_course_modules($courseid);
        $normalized = [];
        foreach ($mappings as $mapping) {
            $mode = (int)($mapping['mode'] ?? 0);
            $version = (int)($mapping['version'] ?? 0);
            $cmid = (int)($mapping['cmid'] ?? 0);
            if (!self::valid_slot($mode, $version)) {
                throw new \invalid_parameter_exception('Invalid Chronagogik firmware slot.');
            }
            if ($cmid !== 0 && !isset($validcms[$cmid])) {
                throw new \invalid_parameter_exception('The selected course module is not a gradable activity in this course.');
            }
            $normalized[$mode . ':' . $version] = compact('mode', 'version', 'cmid');
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        foreach ($normalized as $mapping) {
            $conditions = [
                'courseid' => $courseid,
                'cockpitmode' => $mapping['mode'],
                'firmwareversion' => $mapping['version'],
            ];
            $existing = $DB->get_record(self::MAP_TABLE, $conditions);
            if ($mapping['cmid'] === 0) {
                if ($existing) {
                    $DB->delete_records(self::MAP_TABLE, ['id' => $existing->id]);
                }
                continue;
            }
            if ($existing) {
                $existing->cmid = $mapping['cmid'];
                $existing->timemodified = $now;
                $DB->update_record(self::MAP_TABLE, $existing);
            } else {
                $record = (object)$conditions;
                $record->cmid = $mapping['cmid'];
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record(self::MAP_TABLE, $record);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Get gradable activity course modules keyed by cmid.
     *
     * Activities without a numeric primary grade item are deliberately excluded.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_gradable_course_modules(int $courseid): array {
        global $CFG, $DB;

        // Grade constants are not loaded on every Moodle page (for example on
        // this local plugin's course configuration page).
        require_once($CFG->libdir . '/gradelib.php');

        $sql = "SELECT cm.id AS cmid, gi.id AS gradeitemid, gi.itemname, gi.itemmodule, gi.gradepass
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {grade_items} gi
                    ON gi.courseid = cm.course
                   AND gi.itemtype = 'mod'
                   AND gi.itemmodule = m.name
                   AND gi.iteminstance = cm.instance
                 WHERE cm.course = :courseid
                   AND cm.deletioninprogress = 0
                   AND gi.gradetype = :gradetype
              ORDER BY gi.itemname ASC, cm.id ASC";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'gradetype' => \GRADE_TYPE_VALUE,
        ]);
        $result = [];
        foreach ($records as $record) {
            $result[(int)$record->cmid] = $record;
        }
        return $result;
    }

    /**
     * Return whether a user enabled the integration for this course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function is_enabled(int $courseid, int $userid): bool {
        global $DB;
        return (bool)$DB->get_field(self::OPTIN_TABLE, 'enabled', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
    }

    /**
     * Persist the user's course-specific activation.
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $enabled
     */
    public static function set_enabled(int $courseid, int $userid, bool $enabled): void {
        global $DB;

        $conditions = ['courseid' => $courseid, 'userid' => $userid];
        $record = $DB->get_record(self::OPTIN_TABLE, $conditions);
        $now = time();
        if ($record) {
            $record->enabled = (int)$enabled;
            $record->timemodified = $now;
            $DB->update_record(self::OPTIN_TABLE, $record);
            return;
        }
        $record = (object)$conditions;
        $record->enabled = (int)$enabled;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record(self::OPTIN_TABLE, $record);
    }

    /**
     * Calculate the privacy-minimised API response for one user and course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function get_status(int $courseid, int $userid): array {
        global $DB;

        // Stop before reading mappings or grades when either activation is disabled.
        if (!self::is_course_enabled($courseid)) {
            return ['enabled' => false, 'cockpit' => null, 'reason' => 'course_disabled'];
        }
        if (!self::is_enabled($courseid, $userid)) {
            return ['enabled' => false, 'cockpit' => null, 'reason' => 'consent_required'];
        }

        $statuses = self::empty_status();
        $mappings = $DB->get_records(self::MAP_TABLE, ['courseid' => $courseid]);
        if (!$mappings) {
            return ['enabled' => true, 'cockpit' => $statuses, 'reason' => null];
        }

        $validcms = self::get_gradable_course_modules($courseid);
        $cmids = array_values(array_unique(array_map(static function($mapping) {
            return (int)$mapping->cmid;
        }, $mappings)));

        $grades = [];
        if ($cmids) {
            [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
            $params['courseid'] = $courseid;
            $params['userid'] = $userid;
            $sql = "SELECT cm.id AS cmid, gg.finalgrade
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {grade_items} gi
                        ON gi.courseid = cm.course
                       AND gi.itemtype = 'mod'
                       AND gi.itemmodule = m.name
                       AND gi.iteminstance = cm.instance
                 LEFT JOIN {grade_grades} gg
                        ON gg.itemid = gi.id AND gg.userid = :userid
                     WHERE cm.course = :courseid
                       AND cm.id $insql";
            $grades = $DB->get_records_sql($sql, $params);
        }

        foreach ($mappings as $mapping) {
            $mode = (int)$mapping->cockpitmode;
            $version = (int)$mapping->firmwareversion;
            $cmid = (int)$mapping->cmid;
            if (!self::valid_slot($mode, $version) || !isset($validcms[$cmid])) {
                continue;
            }
            $gradeitem = $validcms[$cmid];
            $gradepass = (float)$gradeitem->gradepass;
            $finalgrade = isset($grades[$cmid]) ? $grades[$cmid]->finalgrade : null;
            // Match the existing GradebookXP CG rule: a pass grade must be configured and reached.
            $statuses[(string)$mode][$version - 1] = $gradepass > 0
                && $finalgrade !== null
                && (float)$finalgrade >= $gradepass;
        }

        return ['enabled' => true, 'cockpit' => $statuses, 'reason' => null];
    }

    /**
     * Validate a fixed slot coordinate.
     *
     * @param int $mode
     * @param int $version
     * @return bool
     */
    private static function valid_slot(int $mode, int $version): bool {
        return $mode >= 1 && $mode <= self::MODE_COUNT
            && $version >= 1 && $version <= self::VERSION_COUNT;
    }
}
