<?php
/**
 * IP Functionality
 *
 * Last Changed: $LastChangedDate: 2017-05-26 04:36:01 -0400 (Fri, 26 May 2017) $
 * @author detain
 * @copyright 2017
 * @package IP-VLAN-Manager
 * @category IPs
 */

/**
 * @return bool
 */
function vlan_edit_port() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$GLOBALS['tf']->add_html_head_css('<link href="//netdna.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.6.3/css/bootstrap-select.min.css" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" />');
	$GLOBALS['tf']->add_html_head_js('<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>');
	$db = get_module_db(IPS_MODULE);
	$db2 = $db;
	$table = new TFTable;
	$table->set_title('VLAN Edit Port Assignments');
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	$table->add_hidden('ipblock', $ipblock);
	$table->add_field('IP Block');
	$table->add_field($ipblock);
	$table->add_row();
	$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'");
	$db->next_record();
	$networks = get_networks($db->Record['vlans_networks'], $db->Record['vlans_id'], $db->Record['vlans_comment'], $db->Record['vlans_ports']);
	$networksize = sizeof($networks);
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
		$portdatasize = sizeof($portdata);
		for ($y = 0; $y < $portdatasize; $y++) {
			if ($portdata[$y] != '') {
				list($switch, $port, $blade, $justport) = parse_vlan_ports($portdata[$y]);
				$ports[] = $portdata[$y];
				$searches[] = "(switch='{$switch}' and slot='{$port}')";
			}
		}
	}
	if (!isset($GLOBALS['tf']->variables->request['ports'])) {
		$select = get_select_ports($ports, 40);
		$table->add_hidden('edit_port', 1);
		$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
		$table->set_colspan(2);
		$table->add_field($select.'<br>'.$table->make_submit('Set Port(s)'));
		$editport = TRUE;
	} else {
		$ports = ':'.implode(':', $GLOBALS['tf']->variables->request['ports']).':';
		$query = "update vlans set vlans_ports='$ports' where vlans_networks like '%:$network:%' and vlans_id='$vlan'";
		$db2->query($query, __LINE__, __FILE__);
		//function_requirements('update_switch_ports');
		//update_switch_ports();
		if(isset($GLOBALS['tf']->variables->request['source']) && $GLOBALS['tf']->variables->request['source'] == 'popup_order') {
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('view_order.php', 'id='.$GLOBALS['tf']->variables->request['pop_order_id']));
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

