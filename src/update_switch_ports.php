<?php

/**
 * updates the switch ports
 *
 * @param bool $verbose wether or not to enable verbose output.
 * @param bool $pullServerMap defaults to true, optional flag allowing disabling of the switch/ports updating via http://nms.is.cc/cacti/servermap.php
 */
function update_switch_ports($verbose = false, $pullServerMap = true)
{
	$db = get_module_db('default');
	$db2 = clone $db;
	if ($pullServerMap !== false) {
		$vlan_ids = [];
		$switch_ids = [];
		$db->query("select vlans_id from vlans");
		while ($db->next_record(MYSQL_ASSOC)) {
			$vlan_ids[$db->Record['vlans_id']] = true;
		}
		$lines = explode("\n", trim(getcurlpage('http://nms.is.cc/cacti/servermap.php')));
		$switches = [];
		foreach ($lines as $line) {
			list($graph_id, $switch, $port, $comment) = explode(',', $line);
			if ($switch != '') {
				$switches[$switch][$port] = $graph_id;
			}
		}
		foreach ($switches as $switch => $ports) {
			$foundports = [];
			$db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
			if ($db->num_rows() == 0) {
				$db->query(make_insert_query('switchmanager', [
					'id' => null,
					'name' => $switch,
					'ports' => count($ports),
					'updated' => mysql_now(),
				]), __LINE__, __FILE__);
				if ($verbose == true) {
					add_output("Created New Switch {$switch} - ");
				}
				$db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
			}
			$db->next_record(MYSQL_ASSOC);
			$switchManager = $db->Record;
			$db->query("update switchmanager set updated=now() where id={$switchManager['id']}", __LINE__, __FILE__);
			$switch_ids[] = $switchManager['id'];
			if ($verbose == true) {
				add_output("Loaded Switch {$switch} - ");
			}
			foreach ($ports as $port => $graph_id) {
				$blade = '';
				$justport = $port;
				if (mb_strrpos($port, '/') > 0) {
					$blade = mb_substr($port, 0, mb_strrpos($port, '/'));
					$justport = mb_substr($port, mb_strlen($blade) + 1);
				}
				if (isset($foundports[$justport])) {
					$justport = '';
				} else {
					$foundports[$justport] = true;
				}
				$db->query("select * from switchports where switch='{$switchManager['id']}' and port='{$port}'", __LINE__, __FILE__);
				if ($db->num_rows() == 0) {
					if ($verbose == true) {
						add_output("{$port} +");
					}
					$db->query(make_insert_query('switchports', [
						'switch' => $switchManager['id'],
						'blade' => $blade,
						'justport' => $justport,
						'port' => $port,
						'graph_id' => $graph_id,
						'vlans' => '',
						'asset_id' => 0,
						'updated' => mysql_now(),
					]), __LINE__, __FILE__);
				} else {
					$db->next_record();
					if (($db->Record['blade'] != $blade) || ($db->Record['justport'] != $justport)) {
						if ($verbose == true) {
							add_output("\nUpdate BladePort");
						}
						$query = "update switchports set blade='{$blade}', justport='{$justport}' where switch='{$switchManager['id']}' and port='{$port}'";
						//echo $query;
						$db->query($query, __LINE__, __FILE__);
					}
					if ($verbose == true) {
						add_output("$port ");
					}
					if ($db->Record['graph_id'] != $graph_id) {
						if ($verbose == true) {
							add_output("\nUpdate Graph");
						}
						$query = "update switchports set graph_id='{$graph_id}' where switch='{$switchManager['id']}' and port='{$port}'";
						//echo $query;
						$db->query($query, __LINE__, __FILE__);
					}
					$db->query("update switchports set updated=now() where switchport_id={$db->Record['switchport_id']}", __LINE__, __FILE__);
					if ($verbose == true) {
						add_output("$graph_id ");
					}
				}
				/*
								//$query = "select * from vlans where vlans_ports like '%:{$switchManager['id']}/{$justport}:%' or vlans_ports like '%:{$switchManager['id']}/{$port}:%'";
								$query = "select * from vlans where vlans_ports like '%:{$switchManager['id']}/{$port}:%'";
								//echo "$query\n";
								$db->query($query, __LINE__, __FILE__);
								$vlans = [];
								$asset_id = 0;
								while ($db->next_record(MYSQL_ASSOC)) {
									$vlans[] = $db->Record['vlans_id'];
									unset($vlan_ids[$db->Record['vlans_id']]);
									$hostname = str_replace('append ', '', $db->Record['vlans_comment']);
									$db2->query("select assets.id from assets, servers  where server_id=assets.order_id and server_hostname='{$hostname}'", __LINE__, __FILE__);
									if ($db2->num_rows() > 0) {
										$db2->next_record();
										//echo "Got assets {$db2->Record['id']} for vlan {$db->Record['vlans_id']}\n";
										$asset_id = $db2->Record['id'];
									} else {
										$db2->query("select * from assets where hostname='{$hostname}'");
										if ($db2->num_rows() > 0) {
											$db2->next_record();
											//echo "Got assets {$db2->Record['id']} for vlan {$db->Record['vlans_id']}\n";
											$asset_id = $db2->Record['id'];
										}
									}
								}
								if (count($vlans) > 0) {
									if ($verbose == true) {
										add_output('('.count($vlans).' Vlans)');
									}
									$vlantext = implode(',', $vlans);
									$db->query("update switchports set vlans='{$vlantext}', asset_id='{$asset_id}' where switch='{$switchManager['id']}' and port='{$port}'", __LINE__, __FILE__);
									if ($db->affectedRows()) {
										if ($verbose == true) {
											add_output(", Update Vlan".PHP_EOL);
										}
									}
								}
				*/
				if ($verbose == true) {
					add_output(',');
				}
			}
			if ($verbose == true) {
				add_output("\n");
			}
		}
		add_output(sizeof(array_keys($vlan_ids)).' Unmatched VLANs'.PHP_EOL);
	}
	/*
	function_requirements('parse_vlan_ports');
	$db->query("select * from vlans", __LINE__, __FILE__);
	$portData = [];
	$serverData = [];
	$assetData = [];
	while ($db->next_record(MYSQL_ASSOC)) {
		$server_id = null;
		$asset_id = null;
		$hostname = str_replace('append ', '', $db->Record['vlans_comment']);
		$db2->query("select * from servers where server_hostname='{$hostname}'");
		if ($db2->num_rows() > 0) {
			$db2->next_record(MYSQL_ASSOC);
			$server_id = $db2->Record['server_id'];
			$db2->query("select * from assets where order_id='{$db2->Record['server_id']}'");
			if ($db2->num_rows() > 0) {
				$db2->next_record(MYSQL_ASSOC);
				$asset_id = $db2->Record['id'];
			} else {
				$db2->query("select * from assets where hostname='{$hostname}'");
				if ($db2->num_rows() > 0) {
					$db2->next_record(MYSQL_ASSOC);
					$asset_id = $db2->Record['id'];
				}
			}
		} else {
			$db2->query("select * from assets where hostname='{$hostname}'");
			if ($db2->num_rows() > 0) {
				$db2->next_record(MYSQL_ASSOC);
				$asset_id = $db2->Record['id'];
				$db2->query("select * from servers where server_id='{$db2->Record['order_id']}'");
				if ($db2->num_rows() > 0) {
					$db2->next_record(MYSQL_ASSOC);
					$server_id = $db2->Record['server_id'];
				}
			}
		}
		$switchports = explode(':', $db->Record['vlans_ports']);
		//add_output(json_encode($db->Record).PHP_EOL);
		foreach ($switchports as $switchport) {
			if (trim($switchport) == '') {
				continue;
			}
			list($switch, $port, $blade, $justport) = parse_vlan_ports($switchport);
			$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and switchmanager.id='{$switch}' and port='{$port}'", __LINE__, __FILE__);
			if ($db2->num_rows() > 0) {
				$db2->next_record(MYSQL_ASSOC);
				if (!isset($portData[$db2->Record['switchport_id']])) {
					$portData[$db2->Record['switchport_id']] = [];
				}
				$portData[$db2->Record['switchport_id']][] = $db->Record['vlans_id'];
				$assetData[$db2->Record['switchport_id']] = $asset_id;
				$serverData[$db2->Record['switchport_id']] = $server_id;
			} else {
				$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and switchmanager.id='{$switch}' and justport='{$port}'", __LINE__, __FILE__);
				if ($db2->num_rows() > 0) {
					$db2->next_record(MYSQL_ASSOC);
					if (!isset($portData[$db2->Record['switchport_id']])) {
						$portData[$db2->Record['switchport_id']] = [];
					}
					$portData[$db2->Record['switchport_id']][] = $db->Record['vlans_id'];
					$assetData[$db2->Record['switchport_id']] = $asset_id;
					$serverData[$db2->Record['switchport_id']] = $server_id;
					$new = str_replace('/'.$db2->Record['justport'].':', '/'.$db2->Record['port'].':', $db->Record['vlans_ports']);
					add_output("Fixing Record '{$db->Record['vlans_ports']}' to '{$new}'".PHP_EOL);
					$db2->query("update vlans set vlans_ports='{$new}' where vlans_ports='{$db->Record['vlans_ports']}'", __LINE__, __FILE__);
				} else {
					$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and switchmanager.name='{$switch}' and port='{$port}'", __LINE__, __FILE__);
					if ($db2->num_rows() > 0) {
						$db2->next_record(MYSQL_ASSOC);
						if (!isset($portData[$db2->Record['switchport_id']])) {
							$portData[$db2->Record['switchport_id']] = [];
						}
						$portData[$db2->Record['switchport_id']][] = $db->Record['vlans_id'];
						$assetData[$db2->Record['switchport_id']] = $asset_id;
						$serverData[$db2->Record['switchport_id']] = $server_id;
						$new = str_replace(':'.$switch.'/', ':'.$db2->Record['id'].'/', $db->Record['vlans_ports']);
						add_output("Fixing Record '{$db->Record['vlans_ports']}' to '{$new}'".PHP_EOL);
						$db2->query("update vlans set vlans_ports='{$new}' where vlans_ports='{$db->Record['vlans_ports']}'", __LINE__, __FILE__);
					} else {
						$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and switchmanager.name='{$switch}' and justport='{$port}'", __LINE__, __FILE__);
						if ($db2->num_rows() > 0) {
							$db2->next_record(MYSQL_ASSOC);
							if (!isset($portData[$db2->Record['switchport_id']])) {
								$portData[$db2->Record['switchport_id']] = [];
							}
							$portData[$db2->Record['switchport_id']][] = $db->Record['vlans_id'];
							$assetData[$db2->Record['switchport_id']] = $asset_id;
							$serverData[$db2->Record['switchport_id']] = $server_id;
							$new = str_replace(':'.$switch.'/', ':'.$db2->Record['id'].'/', $db->Record['vlans_ports']);
							$new = str_replace('/'.$db2->Record['justport'].':', '/'.$db2->Record['port'].':', $new);
							add_output("Fixing Record '{$db->Record['vlans_ports']}' to '{$new}'".PHP_EOL);
							$db2->query("update vlans set vlans_ports='{$new}' where vlans_ports='{$db->Record['vlans_ports']}'", __LINE__, __FILE__);
						} else {
							$db2->query("select * from vlans where vlans_comment in ('{$hostname}', 'append {$hostname}') and vlans_id != {$db->Record['vlans_id']} and vlans_ports != '{$db->Record['vlans_ports']}'");
							if ($db2->num_rows() > 0) {
								$db2->next_record(MYSQL_ASSOC);
								$switchports = explode(':', $db2->Record['vlans_ports']);
								list($switch, $port, $blade, $justport) = parse_vlan_ports($switchports[0]);
								$db2->query("update vlans set vlans_ports='{$db2->Record['vlans_ports']}' where vlans_id={$db->Record['vlans_id']}");
								add_output("Updated VLAN Ports for vlan {$db->Record['vlans_id']} from {$db->Record['vlans_ports']} to {$db2->Record['vlans_ports']}".PHP_EOL);
							} else {
								add_output("Found No Match for Vlan {$db->Record['vlans_id']} on switch {$switch} port {$port}".PHP_EOL);
								$db2->query("select * from servers where server_hostname='{$hostname}'");
								if ($db2->num_rows() > 0) {
									$db2->next_record(MYSQL_ASSOC);
									if (in_array($db2->Record['server_status'], ['canceled', 'deleted', 'expired'])) {
										add_output("Found Vlan {$db->Record['vlans_id']} {$db->Record['vlans_networks']} with no switch({$switch})/port({$port}) match and a old server order {$db2->Record['server_hostname']} with status {$db2->Record['server_status']}".PHP_EOL);
									}
								}
							}
						}
					}
				}
			}
		}
	}
	foreach ($portData as $switchport_id => $vlans) {
		$vlanText = implode(',', $vlans);
		$db->query("select * from switchports where switchport_id={$switchport_id}");
		$db->next_record(MYSQL_ASSOC);
		$updates = [];
		if ($db->Record['asset_id'] != $assetData[$switchport_id]) {
			$updates[] = 'asset_id='.(is_null($assetData[$switchport_id]) ? 'NULL' : $assetData[$switchport_id]);
		}
		if ($db->Record['server_id'] != $serverData[$switchport_id]) {
			$updates[] = 'server_id='.(is_null($serverData[$switchport_id]) ? 'NULL' : $serverData[$switchport_id]);
		}
		if ($db->Record['vlans'] != $vlanText) {
			$updates[] = "vlans='{$vlanText}'";
		}
		if (sizeof($updates) > 0) {
			$query = "update switchports set ".implode(',', $updates)." where switchport_id={$switchport_id}";
			add_output($query.PHP_EOL);
			$db->query($query, __LINE__, __FILE__);
		}
	}
	echo "Updated ".sizeof($portData)." Switchports".PHP_EOL;
	//print_r($switches);
	*/
	global $output;
	echo str_replace("\n", "<br>\n", $output);
	$output = '';
}
