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
function add_ips_to_server() {
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db(IPS_MODULE);
	$db2 = $db;
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	if (!isset($GLOBALS['tf']->variables->request['ips'])) {
		$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
		while ($db->next_record()) {
			$ipinfo = ipcalc($ipblock);
			$ips = get_ips($ipblock);
			$table = new TFTable;
			$table->add_hidden('ipblock', $ipblock);
			$table->set_title('Add IP(s) To Server');
			$table->add_field('Please Select Server To Add IP(s) To');
			$table->add_field(select_server(0, 'server'));
			$table->add_row();
			$table->add_field('Please Select IP(s) To Add');
			$sel = '<select multiple size=8 name="ips[]">';
			$db2->query("select * from ips where ips_ip in ('" . implode("', '", $ips) . "') and ips_serverid=0", __LINE__, __FILE__);
			while ($db2->next_record()) {
				$sel .= '<option value='.$db2->Record['ips_ip'].'>'.$db2->Record['ips_ip'].'</option>';
			}
			$sel .= '</select>';
			$table->add_field($sel, 'r');
			$table->add_row();
			$table->set_colspan(2);
			$table->add_field($table->make_submit('Add These IP(s)'));
			$table->add_row();
			add_output($table->get_table());
		}
	} else {
		$server = $GLOBALS['tf']->variables->request['server'];
		$server_info = $GLOBALS['tf']->get_server($server);
		$group = get_first_group($server_info['servers_group']);
		if ($server_info) {
			$ips = $GLOBALS['tf']->variables->request['ips'];
			for ($x = 0, $x_max = sizeof($ips); $x < $x_max; $x++) {
				$db->query("update ips set ips_group='{$group}', ips_serverid='{$server_info['servers_serverid']}' where ips_ip='{$ips[$x]}'", __LINE__, __FILE__);
			}
			add_output("IP(s) Successfully Assigned To $server<br>");
			add_output($GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock='.$ipblock), 1));
		} else {
			add_output('Invalid Server ('.$server.')');
		}
	}
}

