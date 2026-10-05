<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for 'gradebook_xp'
 *
 * @package local_gradebookxp_cg
 * @copyright INB University of Luebeck
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_gradebookxp_cg\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/** Privacy provider for the optional Chronagogik activation setting. */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** @return collection */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_gradebookxp_cg_optin', [
            'courseid' => 'privacy:metadata:optin:courseid',
            'userid' => 'privacy:metadata:optin:userid',
            'enabled' => 'privacy:metadata:optin:enabled',
            'timecreated' => 'privacy:metadata:optin:timecreated',
            'timemodified' => 'privacy:metadata:optin:timemodified',
        ], 'privacy:metadata:optin');
        return $collection;
    }

    /** @param int $userid @return contextlist */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_gradebookxp_cg_optin} optin
                    ON optin.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel AND optin.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => \CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /** @param approved_contextlist $contextlist */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== \CONTEXT_COURSE) {
                continue;
            }
            $record = $DB->get_record('local_gradebookxp_cg_optin', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
            if ($record) {
                writer::with_context($context)->export_data([
                    get_string('optintitle', 'local_gradebookxp_cg'),
                ], (object)[
                    'enabled' => transform::yesno($record->enabled),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ]);
            }
        }
    }

    /** @param \context $context */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === \CONTEXT_COURSE) {
            $DB->delete_records('local_gradebookxp_cg_optin', ['courseid' => $context->instanceid]);
        }
    }

    /** @param approved_contextlist $contextlist */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $courseids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === \CONTEXT_COURSE) {
                $courseids[] = $context->instanceid;
            }
        }
        if ($courseids) {
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
            $params['userid'] = $contextlist->get_user()->id;
            $DB->delete_records_select('local_gradebookxp_cg_optin',
                "userid = :userid AND courseid $insql", $params);
        }
    }

    /** @param userlist $userlist */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel === \CONTEXT_COURSE) {
            $userlist->add_from_sql('userid',
                'SELECT userid FROM {local_gradebookxp_cg_optin} WHERE courseid = :courseid',
                ['courseid' => $context->instanceid]);
        }
    }

    /** @param approved_userlist $userlist */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== \CONTEXT_COURSE || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED, 'user');
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_gradebookxp_cg_optin',
            "courseid = :courseid AND userid $insql", $params);
    }
}
