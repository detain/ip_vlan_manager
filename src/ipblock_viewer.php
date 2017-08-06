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
 * @throws \Exception
 * @throws \SmartyException
 */
function ipblock_viewer() {
	function_requirements('update_switch_ports');
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db(IPS_MODULE);
	$db2 = $db;
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
	while ($db->next_record()) {
		$ipinfo = ipcalc($ipblock);
		$ips = get_ips($ipblock);
		$table = new TFTable;
		$table->set_title('IP Block Viewer');
		$table->set_colspan(2);
		$table->add_field('IP Network');
		$table->set_colspan(2);
		$table->add_field($ipinfo['network'], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Netmask');
		$table->set_colspan(2);
		$table->add_field($ipinfo['netmask'], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Broadcast');
		$table->set_colspan(2);
		$table->add_field($ipinfo['broadcast'], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Gateway Address');
		$table->set_colspan(2);
		$table->add_field($ipinfo['hostmin'], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('First Address');
		$table->set_colspan(2);
		$table->add_field($ips[array_search($ipinfo['hostmin'], $ips) + 1], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Last Address');
		$table->set_colspan(2);
		$table->add_field($ipinfo['hostmax'], 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Number Of IPs');
		$table->set_colspan(2);
		$table->add_field($ipinfo['hosts'], 'r');
		$table->add_row();
		$db2->query("select * from ips where ips_ip in ('" . implode("', '", $ips) . "') and ips_serverid !=0 order by ips_serverid, ips_ip", __LINE__, __FILE__);
		$usedips = $db2->num_rows();
		$table->set_colspan(2);
		$table->add_field('Used IPs');
		$table->set_colspan(2);
		$table->add_field($usedips.' ('.number_format(($usedips / $ipinfo['hosts']) * 100, 2).'%)', 'r');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field('Free IPs');
		$table->set_colspan(2);
		$table->add_field(($ipinfo['hosts'] - $usedips).' ('.number_format((($ipinfo['hosts'] - $usedips) / $ipinfo['hosts']) * 100, 2).'%)', 'r');
		$table->add_row();
		$table->set_bgcolor(1);
		$table->add_field('Server');
		$table->set_bgcolor(1);
		$table->add_field('IP Address');
		$table->set_colspan(2);
		$table->set_bgcolor(1);
		$table->add_field('Options');
		$table->add_row();
		$table->alternate_rows();
		if ($usedips > 0) {
			while ($db2->next_record()) {
				$server = $GLOBALS['tf']->get_server($db2->Record['ips_serverid']);
				$table->add_field($server['server_hostname']);
				$table->add_field($db2->Record['ips_ip']);
				$table->set_colspan(2);
				$table->add_field('Delete');
				$table->add_row();
			}
		}
		$table->alternate_rows();
		$table->set_colspan(4);
		$table->add_field($table->make_link('choice=ip.add_ips_to_server&amp;ipblock='.$ipblock, 'Add IP(s) To Server'));
		$table->add_row();
		$table->set_colspan(4);
		$table->add_field($table->make_link('choice=ip.vlan_port_manager&amp;ipblock='.$ipblock, 'Manage Ports Connected To VLan'));
		$table->add_row();
		add_output($table->get_table());
	}
}

