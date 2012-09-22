<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'qtype_order';
$plugin->version   = 2012070400;

$plugin->requires  = 2012061700;
$plugin->dependencies = array(
    'qtype_match' => 2012061700,
);

$plugin->maturity  = MATURITY_STABLE;
