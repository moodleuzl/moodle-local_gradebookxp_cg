<?php
namespace local_gradebookxp_cg;

defined('MOODLE_INTERNAL') || die();

/** Output hook callbacks for the GradebookXP CG integration. */
final class hook_callbacks {
    /**
     * Add the voluntary connection switch to the learner's own GradebookXP radar page.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $COURSE, $PAGE, $USER;

        if (!$PAGE->has_set_url() || $PAGE->url->get_path() !== '/grade/report/gradebook_xp/index.php') {
            return;
        }
        if (empty($COURSE->id) || $COURSE->id == \SITEID) {
            return;
        }
        $context = \context_course::instance($COURSE->id);
        if (!has_capability('local/gradebookxp_cg:use', $context)
                || !cockpit_service::is_course_enabled($COURSE->id)) {
            return;
        }
        $vieweduserid = optional_param('userid', $USER->id, \PARAM_INT);
        if ($vieweduserid !== (int)$USER->id) {
            return;
        }

        $enabled = cockpit_service::is_enabled($COURSE->id, $USER->id);
        $attributes = [
            'type' => 'checkbox',
            'id' => 'gradebookxp-cg-radar-optin',
            'class' => 'gradebookxp-cg-radar-checkbox',
        ];
        if ($enabled) {
            $attributes['checked'] = 'checked';
        }
        $html = \html_writer::start_tag('section', [
            'class' => 'gradebookxp-cg-radar-optin',
            'aria-labelledby' => 'gradebookxp-cg-radar-title',
        ]);
        $html .= \html_writer::tag('h3', get_string('optintitle', 'local_gradebookxp_cg'),
            ['id' => 'gradebookxp-cg-radar-title']);
        $html .= \html_writer::empty_tag('input', $attributes);
        $html .= \html_writer::tag('label', get_string('optinlabel', 'local_gradebookxp_cg'),
            ['for' => 'gradebookxp-cg-radar-optin']);
        $html .= \html_writer::tag('p', get_string('optindescription', 'local_gradebookxp_cg'),
            ['class' => 'text-muted']);
        $html .= \html_writer::tag('div', '', [
            'id' => 'gradebookxp-cg-radar-status',
            'class' => 'small',
            'aria-live' => 'polite',
        ]);
        $html .= \html_writer::end_tag('section');

        $PAGE->requires->css('/local/gradebookxp_cg/styles.css');
        $PAGE->requires->js_call_amd('local_gradebookxp_cg/optin', 'init', [[
            'courseid' => $COURSE->id,
        ]]);
        $hook->add_html($html);
    }
}
