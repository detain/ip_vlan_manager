<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2018
 * @package IP-VLAN-Manager
 * @category IPs
 */
/**
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function vlan_edit_port() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	add_js('bootstrap');
	add_js('select2');
	$GLOBALS['tf']->add_html_head_css_file('https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.11.2/css/bootstrap-select.min.css');
	$GLOBALS['tf']->add_html_head_js_file('https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.11.2/js/bootstrap-select.min.js');
	$db = get_module_db('default');
	$db2 = $db;
	$table = new \TFTable;
	$table->set_title('VLAN Edit Port Assignments');
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	$table->add_hidden('ipblock', $ipblock);
	$table->add_field('IP Block');
	$table->add_field($ipblock);
	$table->add_row();
	$db->query("select * from vlans where vlans_networks like '%:{$ipblock}:%'");
	$db->next_record();
	$vlanInfo = $db->Record;
	function_requirements('get_networks');
	$networks = get_networks($vlanInfo['vlans_networks'], $vlanInfo['vlans_id'], $vlanInfo['vlans_comment'], $vlanInfo['vlans_ports']);
	$networksize = count($networks);
	$rows = [];
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
		$searchs = [];
		$servers = [];
		$portdatasize = count($portdata);
		for ($y = 0; $y < $portdatasize; $y++) {
			if ($portdata[$y] != '') {
				list($switch, $port, $blade, $justport) = parse_vlan_ports($portdata[$y]);
				$ports[] = $portdata[$y];
				$searches[] = "(switch='{$switch}' and slot='{$port}')";
			}
		}
	}
	if (!isset($GLOBALS['tf']->variables->request['ports'])) {
		$select = get_select_ports($ports, 20);
		$table->add_hidden('edit_port', 1);
		$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
		$table->set_colspan(2);
		$table->add_field($select.'<br>'.$table->make_submit('Set Port(s)'));
		$editport = TRUE;
	} else {
		$orig_switchports = [];
		$switchports = [];
		$new_ports = $GLOBALS['tf']->variables->request['ports'];
		$asset_id = null;
		$server_id = null;
		if (isset($GLOBALS['tf']->variables->request['pop_order_id']))
			$server_id = $GLOBALS['tf']->variables->request['pop_order_id'];
		foreach ($new_ports as $switchport) {
			list($switch, $port, $blade, $justport) = parse_vlan_ports($switchport);
			$db2->query("select * from switchports where switch='{$switch}' and port='{$port}'", __LINE__, __FILE__);
			if ($db2->num_rows() > 0) {
				$db2->next_record(MYSQL_ASSOC);
				$switchports[$db2->Record['switchport_id']] = $db2->Record;
			}
		}
		foreach ($ports as $switchport) {
			list($switch, $port, $blade, $justport) = parse_vlan_ports($switchport);
			$db2->query("select * from switchports where switch='{$switch}' and port='{$port}'", __LINE__, __FILE__);
			if ($db2->num_rows() > 0) {
				$db2->next_record(MYSQL_ASSOC);
				$orig_switchports[$db2->Record['switchport_id']] = $db2->Record;
				if (!in_array($db2->Record['switchport_id'], array_keys($switchports))) {
					if (trim($db2->Record['vlans']) != '')
						$vlans = explode(',', trim($db2->Record['vlans']));
					else
						$vlans = [];
					if (($key = array_search($vlanInfo['vlans_id'], $vlans)) !== false)
						unset($vlans[$key]);
					$vlans = implode(',', $vlans);
					if (!is_null($server_id))
						$db2->query("update switchports set vlans='{$vlans}', server_id=null, asset_id=null where switchport_id={$db2->Record['switchport_id']}", __LINE__, __FILE__);
					else
						$db2->query("update switchports set vlans='{$vlans}' where switchport_id={$db2->Record['switchport_id']}", __LINE__, __FILE__);
				}
			}
		}
		foreach ($switchports as $switchport_id => $switchportData) {
			if (!in_array($switchport_id, array_keys($orig_switchports)) || !is_null($server_id)) {
				if (trim($switchportData['vlans']) != '')
					$vlans = explode(',', trim($switchportData['vlans']));
				else
					$vlans = [];
				if (!in_array($vlanInfo['vlans_id'], $vlans))
					$vlans[] = $vlanInfo['vlans_id'];
				$vlans = implode(',', $vlans);
				if (!is_null($server_id))
					$db2->query("update switchports set vlans='{$vlans}',server_id={$server_id} where switchport_id={$switchport_id}", __LINE__, __FILE__);
				else
					$db2->query("update switchports set vlans='{$vlans}' where switchport_id={$switchport_id}", __LINE__, __FILE__);
			}
		}
		$db2->query("update vlans set vlans_ports=':".implode(':', $new_ports).":' where vlans_networks like '%:{$network}:%' and vlans_id='{$vlan}'", __LINE__, __FILE__);

		//function_requirements('update_switch_ports');
		//update_switch_ports();
		if(isset($GLOBALS['tf']->variables->request['source']) && $GLOBALS['tf']->variables->request['source'] == 'popup_order') {
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.view_server_order&id='.$GLOBALS['tf']->variables->request['pop_order_id']));
			return TRUE;
		} else {
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
		}
	}
	$table->add_row();
	if(isset($GLOBALS['tf']->variables->request['source']) && $GLOBALS['tf']->variables->request['source'] == 'popup_order') {
		return TRUE;
	} else {
		add_output($table->get_table());
	}
}

