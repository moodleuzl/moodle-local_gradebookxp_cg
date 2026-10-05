<?php
defined('MOODLE_INTERNAL') || die();

/** Upgrade local_gradebookxp_cg. */
function xmldb_local_gradebookxp_cg_upgrade($oldversion) {
    if ($oldversion < 2026100102) {
        upgrade_plugin_savepoint(true, 2026100102, 'local', 'gradebookxp_cg');
    }
    if ($oldversion < 2026100103) {
        upgrade_plugin_savepoint(true, 2026100103, 'local', 'gradebookxp_cg');
    }
    if ($oldversion < 2026100104) {
        upgrade_plugin_savepoint(true, 2026100104, 'local', 'gradebookxp_cg');
    }
    if ($oldversion < 2026100105) {
        upgrade_plugin_savepoint(true, 2026100105, 'local', 'gradebookxp_cg');
    }
    if ($oldversion < 2026100106) {
        upgrade_plugin_savepoint(true, 2026100106, 'local', 'gradebookxp_cg');
    }
    return true;
}
