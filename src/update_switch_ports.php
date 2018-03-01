<?php

/**
 * updates the switch ports
 *
 * @param bool $verbose wether or not to enable verbose output.
 */
function update_switch_ports($verbose = FALSE) {
	$db = get_module_db('default');
	$db2 = clone $db;
	$vlan_ids = [];
	$switch_ids = [];
	$db->query("select vlans_id from vlans");
	while ($db->next_record(MYSQL_ASSOC))
		$vlan_ids[$db->Record['vlans_id']] = TRUE;
	$lines = explode("\n", trim(getcurlpage('http://nms.is.cc/cacti/servermap.php')));
	$switches = [];
	foreach ($lines as $line) {
		list($graph_id, $switch, $port, $comment) = explode(',', $line);
		if ($switch != '')
			$switches[$switch][$port] = $graph_id;
	}
	foreach ($switches as $switch => $ports) {
		$foundports = [];
		$db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
		if ($db->num_rows() == 0) {
			$db->query(make_insert_query('switchmanager', [
				'id' => NULL,
				'name' => $switch,
				'ports' => count($ports)
			]), __LINE__, __FILE__);
			if ($verbose == TRUE)
				add_output("Created New Switch {$switch} - ");
			$db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
		}
		$db->next_record(MYSQL_ASSOC);
		$switchManager = $db->Record;
		$switch_ids[] = $switchManager['id'];
		if ($verbose == TRUE)
			add_output("Loaded Switch {$switch} - ");
		foreach ($ports as $port => $graph_id) {
			$blade = '';
			$justport = $port;
			if (mb_strrpos($port, '/') > 0) {
				$blade = mb_substr($port, 0, mb_strrpos($port, '/'));
				$justport = mb_substr($port, mb_strlen($blade) + 1);
			}
			if (isset($foundports[$justport]))
				$justport = '';
			else
				$foundports[$justport] = TRUE;
			$db->query("select * from switchports where switch='{$switchManager['id']}' and port='{$port}'", __LINE__, __FILE__);
			if ($db->num_rows() == 0) {
				if ($verbose == TRUE)
					add_output("{$port} +");
				$db->query(make_insert_query('switchports', [
					'switch' => $switchManager['id'],
					'blade' => $blade,
					'justport' => $justport,
					'port' => $port,
					'graph_id' => $graph_id,
					'vlans' => '',
					'location_id' => 0,
				]), __LINE__, __FILE__);
			} else {
				$db->next_record();
				if (($db->Record['blade'] != $blade) || ($db->Record['justport'] != $justport)) {
					if ($verbose == TRUE)
						add_output("\nUpdate BladePort");
					$query = "update switchports set blade='{$blade}', justport='{$justport}' where switch='{$switchManager['id']}' and port='{$port}'";
					//echo $query;
					$db->query($query, __LINE__, __FILE__);
				}
				if ($verbose == TRUE)
					add_output("$port ");
				if ($db->Record['graph_id'] != $graph_id) {
					if ($verbose == TRUE)
						add_output("\nUpdate Graph");
					$query = "update switchports set graph_id='{$graph_id}' where switch='{$switchManager['id']}' and port='{$port}'";
					//echo $query;
					$db->query($query, __LINE__, __FILE__);
				}
				if ($verbose == TRUE)
					add_output("$graph_id ");
			}
			$query = "select * from vlans where vlans_ports like '%:{$switchManager['id']}/{$justport}:%' or vlans_ports like '%:{$switchManager['id']}/{$port}:%'";
			echo "$query\n";
			$db->query($query, __LINE__, __FILE__);
			$vlans = [];
			$location_id = 0;
			while ($db->next_record(MYSQL_ASSOC)) {
				$vlans[] = $db->Record['vlans_id'];
				unset($vlan_ids[$db->Record['vlans_id']]);
				$hostname = str_replace('append ','', $db->Record['vlans_comment']);
				$db2->query("select assets.id from assets, servers  where server_id=assets.order_id and server_hostname='{$hostname}'", __LINE__, __FILE__);
				if ($db2->num_rows() > 0) {
					$db2->next_record();
					//echo "Got assets {$db2->Record['id']} for vlan {$db->Record['vlans_id']}\n";
					$location_id = $db2->Record['id'];
				} else {
					$db2->query("select * from assets where hostname='{$hostname}'");
					if ($db2->num_rows() > 0) {
						$db2->next_record();
						//echo "Got assets {$db2->Record['id']} for vlan {$db->Record['vlans_id']}\n";
						$location_id = $db2->Record['id'];
					}
				}
			}
			if (count($vlans) > 0) {
				if ($verbose == TRUE)
					add_output('('.count($vlans).' Vlans)');
				$vlantext = implode(',', $vlans);
				$db->query("update switchports set vlans='' where vlans='{$vlantext}' and not (switch='{$switchManager['id']}' and port='{$port}')", __LINE__, __FILE__);
				if ($db->affected_rows())
					if ($verbose == TRUE)
						add_output(", Clear Old Vlan");
				if ($location_id > 0) {
					$db->query("update switchports set location_id=0 where location_id='{$location_id}' and not (switch='{$switchManager['id']}' and port='{$port}')", __LINE__, __FILE__);
					if ($db->affected_rows())
						if ($verbose == TRUE)
							add_output(", Clear Old Vlan");
				}
				$db->query("update switchports set vlans='{$vlantext}', location_id='{$location_id}' where switch='{$switchManager['id']}' and port='{$port}'", __LINE__, __FILE__);
				if ($db->affected_rows())
					if ($verbose == TRUE)
						add_output(", Update Vlan".PHP_EOL."update switchports set vlans='{$vlantext}', location_id='{$location_id}' where switch='{$switchManager['id']}' and port='{$port}'".PHP_EOL);
			}
			if ($verbose == TRUE)
				add_output(',');
		}
		if ($verbose == TRUE)
			add_output("\n");
	}
	if (sizeof(array_keys($vlan_ids)) > 0) {
		function_requirements('parse_vlan_ports');
		add_output(sizeof(array_keys($vlan_ids)).' Unmatched VLANs'.PHP_EOL);
		$db->query("select * from vlans where vlans_id in (".implode(',',array_keys($vlan_ids)).")", __LINE__, __FILE__);
		while ($db->next_record(MYSQL_ASSOC)) {
			$switchports = explode(':', $db->Record['vlans_ports']);
			add_output(json_encode($db->Record).PHP_EOL);
			foreach ($switchports as $switchport) {
				if (trim($switchport) == '')
					continue;
				list($switch, $port, $blade, $justport) = parse_vlan_ports($switchport);
				$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and (switchmanager.id='{$switch}' or switchmanager.name='{$switch}') and port='{$port}'", __LINE__, __FILE__);
				if ($db2->num_rows() > 1) {
					add_output("Found Multiple Rows\n");
					while ($db2->next_record(MYSQL_ASSOC))
						add_output(json_encode($db->Record).PHP_EOL);
				} elseif ($db2->num_rows() == 1) {
					$db2->next_record(MYSQL_ASSOC);
					add_output("Found 1 Match".PHP_EOL);
					if ($db2->Record['name'] == $switch && $db2->Record['id'] != $switch) {
						$new = str_replace(':'.$switch.'/', ':'.$db2->Record['id'].'/', $db->Record['vlans_ports']);
						add_output("Fixing Record '{$db->Record['vlans_ports']}' to '{$new}'".PHP_EOL);
						$db2->query("update vlans set vlans_ports='{$new}' where vlans_id={$db->Record['vlans_id']}", __LINE__, __FILE__);
					}
				} else {
					add_output("Found No Match".PHP_EOL);
				}
			}
		}
	} else {
		add_output("No Unmatched Vlans\n");
	}
	$db->query("select * from switchmanager where id not in (". implode(',',$switch_ids).")");
	while ($db->next_record(MYSQL_ASSOC)) {
		add_output(json_encode($db->Record).PHP_EOL);
	}
	//print_r($switches);
	global $output;
	echo str_replace("\n", "<br>\n", $output);
	$output = '';
}
