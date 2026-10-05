<?php
// This file is part of Moodle - https://moodle.org/.

namespace local_gradebookxp_cg;

defined('MOODLE_INTERNAL') || die();

/** Tests the Chronagogik persistence and status contract. */
class cockpit_service_test extends \advanced_testcase {
    /** @var \stdClass */
    private $course;

    /** @var \stdClass */
    private $user;

    /** Set up one course and learner. */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->user->id, $this->course->id, 'student');
        cockpit_service::set_course_enabled($this->course->id, true);
    }

    /** No mappings is a complete null matrix, not a locked matrix. */
    public function test_new_course_has_null_statuses(): void {
        cockpit_service::set_enabled($this->course->id, $this->user->id, true);
        $status = cockpit_service::get_status($this->course->id, $this->user->id);
        $this->assertTrue($status['enabled']);
        $this->assertSame(cockpit_service::empty_status(), $status['cockpit']);
    }

    /** Disabled users never receive a matrix. */
    public function test_disabled_status_contains_no_firmware_data(): void {
        $this->assertSame([
            'enabled' => false,
            'cockpit' => null,
            'reason' => 'consent_required',
        ], cockpit_service::get_status($this->course->id, $this->user->id));
    }

    /** A disabled course suppresses status data even when the learner previously opted in. */
    public function test_course_activation_is_required(): void {
        cockpit_service::set_enabled($this->course->id, $this->user->id, true);
        cockpit_service::set_course_enabled($this->course->id, false);
        $this->assertSame([
            'enabled' => false,
            'cockpit' => null,
            'reason' => 'course_disabled',
        ], cockpit_service::get_status($this->course->id, $this->user->id));
    }

    /** Opt-in can be enabled and revoked without affecting other users. */
    public function test_optin_is_course_and_user_specific_and_revocable(): void {
        $otheruser = $this->getDataGenerator()->create_user();
        cockpit_service::set_enabled($this->course->id, $this->user->id, true);
        $this->assertTrue(cockpit_service::is_enabled($this->course->id, $this->user->id));
        $this->assertFalse(cockpit_service::is_enabled($this->course->id, $otheruser->id));
        cockpit_service::set_enabled($this->course->id, $this->user->id, false);
        $this->assertFalse(cockpit_service::is_enabled($this->course->id, $this->user->id));
    }

    /** Mappings save, update and remove independently. */
    public function test_mapping_round_trip_and_removal(): void {
        [$cmid] = $this->create_graded_activity('Firmware task', 50.0, null);
        cockpit_service::save_mappings($this->course->id, [
            ['mode' => 2, 'version' => 3, 'cmid' => $cmid],
        ]);
        $mappings = cockpit_service::get_mappings($this->course->id);
        $this->assertSame($cmid, $mappings['2'][2]);

        cockpit_service::save_mappings($this->course->id, [
            ['mode' => 2, 'version' => 3, 'cmid' => 0],
        ]);
        $this->assertNull(cockpit_service::get_mappings($this->course->id)['2'][2]);
    }

    /** True, false and null are calculated independently, including non-sequential slots. */
    public function test_independent_tristate_statuses(): void {
        [$passedcmid] = $this->create_graded_activity('Passed', 50.0, 80.0);
        [$failedcmid] = $this->create_graded_activity('Failed', 50.0, 20.0);
        cockpit_service::save_mappings($this->course->id, [
            ['mode' => 1, 'version' => 1, 'cmid' => $passedcmid],
            ['mode' => 1, 'version' => 2, 'cmid' => $failedcmid],
            ['mode' => 1, 'version' => 3, 'cmid' => $passedcmid],
        ]);
        cockpit_service::set_enabled($this->course->id, $this->user->id, true);

        $status = cockpit_service::get_status($this->course->id, $this->user->id);
        $this->assertSame([true, false, true, null], $status['cockpit']['1']);
    }

    /** A grade item without a pass threshold never unlocks firmware. */
    public function test_missing_pass_grade_is_not_success(): void {
        [$cmid] = $this->create_graded_activity('No threshold', 0.0, 100.0);
        cockpit_service::save_mappings($this->course->id, [
            ['mode' => 4, 'version' => 4, 'cmid' => $cmid],
        ]);
        cockpit_service::set_enabled($this->course->id, $this->user->id, true);
        $status = cockpit_service::get_status($this->course->id, $this->user->id);
        $this->assertFalse($status['cockpit']['4'][3]);
    }

    /**
     * Create an assignment, configure its pass grade and optionally add a final grade.
     *
     * @param string $name
     * @param float $gradepass
     * @param float|null $finalgrade
     * @return array [cmid, gradeitemid]
     */
    private function create_graded_activity(string $name, float $gradepass, ?float $finalgrade): array {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'name' => $name,
            'grade' => 100,
        ]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, $this->course->id, false, \MUST_EXIST);
        $gradeitem = \grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
        ]);
        $gradeitem->gradepass = $gradepass;
        $gradeitem->update();
        if ($finalgrade !== null) {
            $grade = new \grade_grade();
            $grade->itemid = $gradeitem->id;
            $grade->userid = $this->user->id;
            $grade->rawgrade = $finalgrade;
            $grade->finalgrade = $finalgrade;
            $grade->insert();
        }
        return [(int)$cm->id, (int)$gradeitem->id];
    }
}
