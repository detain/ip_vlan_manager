#!/usr/bin/php -q
<?php
	/************************************************************************************\
	* Trouble Free Admin                                                                 *
	* (c)2002-2004 Interserver                                                           *
	* ---------------------------------------------------------------------------------- *
	* Description: find / fix common billing problems                                    *
	\************************************************************************************/

	// $Id: generate_banks_table.php,v 1.1.1.1 2007-04-14 22:47:56 detain Exp $
	// $Source: /var/lib/cvs/troublefree/tempscripts/generate_banks_table.php,v $

	// accounts (id, login, pass, groups)
	// accounts_ext (id, key, value)

	define('DEBUG',FALSE);

	// Load Various Functions & Connect to mysql database
	include('../include/functions.inc.php');
	ob_end_flush();
	$db = $GLOBALS['tf']->db;
	$db2 = $db;

	$lines = explode("\n", trim(`curl -s http://nms.interserver.net/cac/servermap.php`));
	$switches = array();
	foreach ($lines as $line)
	{
		$parts = explode(',', $line);
		list($graph, $switch, $port, $comment) = $parts;
		if ($switch != '')
		{
			$switches[$switch][$port] = $graph;
		}
	}
	foreach ($switches as $switch => $ports)
	{
		$foundports = array();
		$db->query("select * from switchmanager where name='$switch'");
		if ($db->num_rows() > 0)
		{
			$db->next_record();
			$row = $db->Record;
			echo "Loaded Switch $switch - ";
		}
		else
		{
			$db->query("insert into switchmanager values (NULL, '$switch', " . sizeof($ports) . ')');
			$db->query("select * from switchmanager where name='$switch'");
			$db->next_record();
			$row = $db->Record;
			echo "Created New Switch $switch - ";
		}
		$id = $row['id'];
		foreach ($ports as $port => $graph)
		{
			$blade = '';
			$justport = $port;
			if (strrpos($port, '/') > 0)
			{
				$blade = substr($port, 0, strrpos($port, '/'));
				$justport = substr($port, strlen($blade) + 1);
			}
			if (isset($foundports[$justport]))
			{
				$justport = '';
			}
			else
			{
				$foundports[$justport] = true;
			}
			$db->query("select * from switchports where switch='$id' and port='$port'");
			if ($db->num_rows() == 0)
			{
				echo "$port +";
				$db->query("insert into switchports values ('$id', '$blade', '$justport', '$port', '$graph', '')");
			}
			else
			{
				$db->next_record();
				if (($db->Record['blade'] != $blade) || ($db->Record['justport'] != $justport))
				{
echo "\nUpdate BladePort";
					$query = "update switchports set blade='$blade', justport='$justport' where switch='$id' and port='$port'";
					//echo $query;
					$db->query($query);
				}
				echo "$port ";
				if ($db->Record['graph_id'] != $graph)
				{
echo "\nUpdate Graph";
					$query = "update switchports set graph_id='$graph' where switch='$id' and port='$port'";
					//echo $query;
					$db->query($query);
				}
				echo "$graph ";
			}
			$query = "select * from vlans where vlans_ports like '%:$row[id]/$justport:%' or vlans_ports like '%:$row[id]/$port:%'";
			//echo "$query\n";
			$db->query($query);
			$vlans = array();
			while ($db->next_record())
			{
				$vlans[] = $db->Record['vlans_id'];
			}
			if (sizeof($vlans) > 0)
			{
				echo '(' . sizeof($vlans) . ' Vlans)';
				$vlantext = implode(',', $vlans);
				$db->query("update switchports set vlans='$vlantext' where switch='$id' and port='$port'");
				if ($db->affected_rows())
					echo "\nUpdate Vlan";
			}
			echo ',';
		}
		echo "\n";
	}
//	print_r($switches);
?>
