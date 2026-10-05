<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_gradebookxp_cg_set_enabled' => [
        'classname' => 'local_gradebookxp_cg\external\set_enabled',
        'description' => 'Enable or disable the Chronagogik connection for the current user and course',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/gradebookxp_cg:use',
    ],
    'local_gradebookxp_cg_get_status' => [
        'classname' => 'local_gradebookxp_cg\external\get_status',
        'description' => 'Get the current user Chronagogik firmware status',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/gradebookxp_cg:use',
    ],
];
