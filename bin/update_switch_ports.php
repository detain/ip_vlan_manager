#!/usr/bin/env php
<?php
/************************************************************************************\
* MyAdmin                                                                            *
* (c)2017 InterServer, Inc                                                           *
* ---------------------------------------------------------------------------------- *
* Description: update switch ports                                                   *
\************************************************************************************/

// $Source: /var/lib/cvs/troublefree/tempscripts/generate_banks_table.php,v $

define('DEBUG', false);
$GLOBALS['webpage'] = false;

// Load Various Functions & Connect to mysql database
require_once __DIR__ .'/../../../../include/functions.inc.php';
function_requirements('update_switch_ports');
update_switch_ports(true, isset($_SERVER['argv'][1]) && in_array(strtolower($_SERVER['argv'][1]), array('false', 'no', '0')) ? false : true);
