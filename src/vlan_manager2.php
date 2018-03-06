<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2017
 * @package IP-VLAN-Manager
 * @category IPs
 */
/**
 * VLAN Manager
 *
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function vlan_manager2() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	function_requirements('update_switch_ports');
	function_requirements('get_networks');
	//			$smarty = new \TFSmarty;
	//			$smarty->debugging = TRUE;
	//			$smarty->assign('sortcol', 1);
	//			$smarty->assign('sortdir', 0);
	//			$smarty->assign('textextraction', "'complex'");
	$ima = $GLOBALS['tf']->ima;
	$choice = $GLOBALS['tf']->variables->request['choice'];
	global $groupinfo;
	$db = get_module_db('default');
	$db2 = get_module_db('default');
	if (isset($GLOBALS['tf']->variables->request['order']) && $GLOBALS['tf']->variables->request['order'] == 'id')
		$order = 'vlans_id';
	else
		$order = 'vlans_networks';
	$table = new \TFTable;
	$table->set_title('VLan Manager '.pdf_link('choice='.$choice.'&order='.$order));
	$table->set_options('width="100%"');
	/*			$title = array(
	$table->make_link('choice='.$choice.'&order=id', 'VLAN'),
	$table->make_link('choice='.$choice.'&order=ip', 'Network'),
	'Comments',
	'Port(s)'
	);
	*/
	//				'Server(s)'
	//			$smarty->assign('table_header', $title);

	$table->set_bgcolor(3);
	//			$table->add_field($table->make_link('choice='.$choice.'&order=id', 'VLAN'));
	$table->add_field($table->make_link('choice='.$choice.'&order=ip', 'Network'));
	$table->add_field('Comment');
	$table->add_field('Port(s)');
	//			$table->set_colspan(3);
	$table->add_field('Options');
	//			$table->add_field('Server(s)');
	$table->add_row();
	$table->alternate_rows();
	$total_ips = 0;
	$used_ips = 0;
	// get ip block(s)
	$networks = [];
	$db->query('select * from ipblocks order by ipblocks_network', __LINE__, __FILE__);
	$vlans = [];
	while ($db->next_record()) {
		$ipinfo = ipcalc($db->Record['ipblocks_network']);
		$network_id = $db->Record['ipblocks_id'];
		$total_ips += $ipinfo['hosts'];
		$db2->query("select vlans.*,server_id,server_hostname,switch,port,graph_id from vlans left join switchports on (vlans=vlans_id or vlans like concat('%,',vlans_id,',%') or vlans like concat('%,',vlans_id) or vlans like concat (vlans_id, ',%') ) left join assets on assets.id=location_id left join servers on order_id=server_id  where vlans_block='{$network_id}' order by {$order};", __LINE__, __FILE__);
//		$db2->query("select vlans.*,server_id,server_hostname,switch,port,graph_id from vlans left join vlan_locations on vlan_id=vlans_id left join switchports on switchport_id=vlan_switchport left join assets on assets.id=vlan_location left join servers on order_id=server_id where vlans_block='{$network_id}' order by {$order};", __LINE__, __FILE__);
		while ($db2->next_record(MYSQL_ASSOC)) {
			$vlans[$db2->Record['vlans_id']] = $db2->Record;
			$network = get_networks($db2->Record['vlans_networks'], $db2->Record['vlans_id'], $db2->Record['vlans_comment'], $db2->Record['vlans_ports']);
			//_debug_array($network);
			$networks = array_merge($networks, $network);
		}
	}
	$db->query('select count(*) from ips where ips_vlan > 0');
	$db->next_record();
	$used_ips = $db->f(0);
	$networksize = count($networks);
	$rows = [];
	//_debug_array($networks);
	for ($x = 0; $x < $networksize; $x++) {
		$row = [];
		$network = $networks[$x]['network'];
		$vlan = $networks[$x]['vlan'];
		if ($networks[$x]['comment']) {
			$comment = $networks[$x]['comment'];
		} else {
			$comment = 'not set';
		}
		$portdata = explode(':', $networks[$x]['ports']);
		$ports = [];
		$searches = [];
		$servers = [];
		$portdatasize = count($portdata);
		for ($y = 0; $y < $portdatasize; $y++) {
			if ($portdata[$y] != '') {
				list($switch, $port, $blade, $justport) = parse_vlan_ports($portdata[$y]);
				$ports[] = $portdata[$y];
				$searches[] = "(switch='{$switch}' and slot='{$port}')";
			}
		}
		if (null !== $vlans[$vlan]['server_hostname'])
			$servers[] = $vlans[$vlan]['server_hostname'];
		/* commented out 3/11/2017 by joe to get things wworking for the moment
		if (sizeof($searches)) {
			$query = 'select server_hostname from servers where '.implode(' or ', $searches);
			$db2->query($query, __LINE__, __FILE__);
			while ($db2->next_record())
				$servers[] = $db2->Record['server_hostname'];
		}*/
		//					$network_info = $networks_info[$network];
		/*
		$row = array(
		$vlan,
		$network.' ' .
		$table->make_link('choice=ip.ipblock_viewer&amp;ipblock='.$network, '(?)').' ' .
		$table->make_link('choice=ip.add_ips_to_server&amp;ipblock='.$network, '(+IP)').' ' .
		$table->make_link('choice=ip.delete_vlan&amp;ipblock='.$network, '(-)'),
		$table->make_link('choice=ip.edit_vlan_comment&amp;ipblock='.$network, $comment)
		);
		*/
		//					$table->add_field($vlan);

		$table->add_field($network, 'l');
		$table->add_field($table->make_link('choice=ip.edit_vlan_comment&amp;ipblock='.$network, $comment), 'c');

		$editport = FALSE;
		$editserver = FALSE;
		if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network) {
			if (isset($GLOBALS['tf']->variables->request['edit_port'])) {
				if (!isset($GLOBALS['tf']->variables->request['ports'])) {
					$select = get_select_ports($ports);
					$table->add_hidden('edit_port', 1);
					$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
					//								$row[] = $select.'<br>'.$table->make_submit('Set Port(s)');
					$table->add_field($select.'<br>'.$table->make_submit('Set Port(s)'));
					$editport = TRUE;
				} else {
					$ports = ':'.implode(':', $GLOBALS['tf']->variables->request['ports']).':';
					$db2->query("update vlans set vlans_ports='{$ports}' where vlans_networks like '%:{$network}:%' and vlans_id='{$vlan}'", __LINE__, __FILE__);
					function_requirements('update_switch_ports');
					update_switch_ports();
					$ports = $GLOBALS['tf']->variables->request['ports'];
				}
			}
		}
		if (count($ports) == 0)
			$ports[] = '--';
		if (!$editport) {
			$portsize = count($ports);
			for ($y = 0; $y < $portsize; $y++) {
				if (!(mb_strpos($ports[$y], '/') === FALSE)) {
					list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
					$ports[$y] = get_switch_name($switch, TRUE).'/'.$port;
				}
			}
			$table->add_field($table->make_link('choice=ip.vlan_edit_port&amp;ipblock='.$network, implode(', ', $ports)), 'l');
			//						$row[] = $table->make_link('choice=ip.vlan_edit_port=1&amp;ipblock='.$network, implode(', ', $ports));
			//						$row[] = $table->make_link('choice=ip.vlan_manager&amp;edit_port=1&amp;ipblock='.$network, implode(', ', $ports));
			//						$table->add_field($table->make_link('choice=ip.vlan_manager&amp;edit_port=1&amp;ipblock='.$network, implode(', ', $ports)));
		}
		$table->add_field($table->make_link('choice=ip.ipblock_viewer&amp;ipblock='.$network, '(?)') . $table->make_link('choice=ip.add_ips_to_server&amp;ipblock='.$network, '(+IP)') . $table->make_link('choice=ip.delete_vlan&amp;ipblock='.$network, '(-)'), 'c');
		if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network) {
			if (isset($GLOBALS['tf']->variables->request['edit_server'])) {
				if ($ports[0] != '--') {
					if (!isset($GLOBALS['tf']->variables->request['port_0'])) {
						$out = '';
						for ($y = 0, $yMax = count($ports); $y < $yMax; $y++) {
							if (count($ports) > 1)
								$out .= 'Port '.$ports[$y].': ';
							list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
							$query = "select id, server_hostname from servers where switch='{$switch}' and slot='{$port}'";
							$db2->query($query, __LINE__, __FILE__);
							if ($db2->num_rows()) {
								$db2->next_record();
								$server = $db2->Record['server_hostname'];
							} else {
								$server = 0;
							}
							$out .= select_server($server, 'port_'.$y, TRUE);
							if ($y < (count($ports) - 1))
								$out .= '<br>';
						}
						$table->add_hidden('edit_server', 1);
						$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
						//									$row[] = $out.'<br>'.$table->make_submit('Set Server(s)');
						$table->add_field($out.'<br>'.$table->make_submit('Set Server(s)'));
						$editserver = TRUE;
					} else {
						$servers = [];
						for ($y = 0, $yMax = count($ports); $y < $yMax; $y++) {
							$server = $GLOBALS['tf']->variables->request['port_'.$y];
							if ($server != '0') {
								$servers[] = $server;
								list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
								$query = "update servers set switch='', slot='' where switch='{$switch}' and slot='{$port}'";
								$db2->query($query, __LINE__, __FILE__);
								$query = "update servers set switch='{$switch}', slot='{$port}' where server_hostname='{$server}'";
								$db2->query($query, __LINE__, __FILE__);
							}
						}
					}
				} else {
					//								$row[] = '<b>You Must First Assign Port(s)</b>';
					$table->add_field('<b>You Must First Assign Port(s)</b>');
					$editserver = TRUE;
				}
			}
		}
		if (count($servers) == 0)
			$servers[] = '--';
		if (!$editserver) {
			//						$row[] = $table->make_link('choice=ip.vlan_manager&amp;edit_server=1&amp;ipblock='.$network, implode(', ', $servers));
			//						$table->add_field($table->make_link('choice=ip.vlan_manager&amp;edit_server=1&amp;ipblock='.$network, implode(', ', $servers)));
		}
		//					$rows[] = $row;
		$table->add_row();
	}

	//			$smarty->assign('table_rows',$rows);
	//			$table->set_colspan(5);
	//			$table->add_field($smarty->fetch('tablesorter/tablesorter_nopager.tpl'));
	//			$table->add_field($smarty->fetch('tablesorter/tablesorter_nopager.tpl'));
	//			$table->add_row();

	$table->set_colspan(4);
	$table->add_field('Total IPs '.$total_ips, 'l');
	$table->add_row();
	$table->set_colspan(4);
	$table->add_field('Used IPs '.$used_ips.' ('.number_format(($used_ips / $total_ips) * 100, 2).'%) (Rough Estimate, I can get better numbers if you want)', 'l');
	$table->add_row();
	$table->set_colspan(4);
	$table->add_field('Free IPs '.($total_ips - $used_ips).' ('.number_format((($total_ips - $used_ips) / $total_ips) * 100, 2).'%)', 'l');
	$table->add_row();
	$table->set_colspan(4);
	$table->add_field($table->make_link('choice=ip.add_vlan', 'Add New VLAN').'   '.$table->make_link('choice=ip.portless_vlans', 'List Of VLAN Without Port Assignments ').'   '.$table->make_link('choice=ip.vlan_port_server_manager', 'VLAN Port <-> Server Mapper'));
	$table->add_row();

	//			add_output($smarty->fetch('tablesorter/tablesorter.tpl'));
	add_output($table->get_table());
	if (isset($GLOBALS['tf']->variables->request['pdf']) && $GLOBALS['tf']->variables->request['pdf'] == 1)
		$table->get_pdf();
}


