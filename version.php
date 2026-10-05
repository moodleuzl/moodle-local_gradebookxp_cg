<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_gradebookxp_cg';
$plugin->version = 2026100106;
$plugin->requires = 2024042200;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.6';
$plugin->dependencies = [
    'gradereport_gradebook_xp' => 2026091701,
];
