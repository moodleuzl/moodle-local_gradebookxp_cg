# GradebookXP CG

GradebookXP CG is a privacy-conscious Moodle companion plugin that connects
course-specific GradebookXP results to the Chronagogik H5P cockpit.

The plugin does not modify GradebookXP. Teachers map gradable course activities
to Chronagogik firmware slots, learners opt in per course, and the cockpit
receives only a 4 x 4 matrix containing `true`, `false`, or `null`. It never
receives names, grades, activity titles, or Moodle user IDs.

## Requirements

- Moodle 4.5 or compatible (`2024042200` or newer)
- [GradebookXP](https://github.com/inb-luebeck/gradebook_xp), version
  `2026091701` or newer
- A Chronagogik Cockpit H5P configured with the Moodle course ID

The maintained GradebookXP fork used during development is available at
[therealmadany/gradebook_xp](https://github.com/therealmadany/gradebook_xp).

## Installation

1. Install GradebookXP as `/grade/report/gradebook_xp`.
2. Install this repository as `/local/gradebookxp_cg` or upload a release ZIP
   through Moodle's plugin installer.
3. Complete the Moodle database upgrade under **Site administration →
   Notifications**.
4. Open **GradebookXP CG** in the desired course, enable the integration, and
   map activities to firmware slots.
5. Learners may then activate the connection voluntarily on their GradebookXP
   CG course page.
6. Enter the Moodle course ID in the Chronagogik H5P editor.

The plugin is installed site-wide but remains inactive in every course until a
manager or editing teacher enables it for that course.

## Status endpoint

The authenticated endpoint is:

```text
/local/gradebookxp_cg/cockpit_status.php?courseid=<id>
```

Status values have the following meaning:

- `true`: the mapped activity has a final grade at or above its configured
  positive pass grade;
- `false`: a valid mapping exists but the pass condition has not been met;
- `null`: no valid activity is mapped to this firmware slot.

The endpoint uses the currently authenticated Moodle user and returns no
personally identifying data.

## Development

GitHub Actions runs Moodle Plugin CI against Moodle 4.5 with GradebookXP added
as an extra plugin dependency. PHPUnit tests are located in `tests/`.

## Related project

GradebookXP was developed by INB, University of Lübeck. This companion plugin
is an independent extension and does not alter the upstream plugin.

## License

GNU GPL v3 or later. See [LICENSE.txt](LICENSE.txt).
