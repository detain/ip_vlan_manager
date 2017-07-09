<?php
	/************************************************************************************\
	* Trouble Free Admin                                                                 *
	* (c)2002-2016 Interserver                                                           *
	* ---------------------------------------------------------------------------------- *
	* Description: update switch ports                                                   *
	\************************************************************************************/

	// $Source: /var/lib/cvs/troublefree/tempscripts/generate_banks_table.php,v $

	define('DEBUG', FALSE);

	// Load Various Functions & Connect to mysql database
	require_once dirname(__FILE__).'/../../../include/functions.inc.php';

	function_requirements('update_switch_ports');
	update_switch_ports(true);
