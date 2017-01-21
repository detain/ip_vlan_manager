<?php
	/************************************************************************************\
	* Trouble Free Admin                                                                 *
	* (c)2002-2016 Interserver                                                           *
	* ---------------------------------------------------------------------------------- *
	* Description: update switch ports                                                   *
	\************************************************************************************/

	// $Id: generate_banks_table.php,v 1.1.1.1 2007-04-14 22:47:56 detain Exp $
	// $Source: /var/lib/cvs/troublefree/tempscripts/generate_banks_table.php,v $

	define('DEBUG', false);

	// Load Various Functions & Connect to mysql database
	require_once dirname(__FILE__) . '/../../functions.inc.php';

	function_requirements('update_switch_ports');
	update_switch_ports(true);
