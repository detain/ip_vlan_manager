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
function vlan_port_server_manager() {
	function_requirements('update_switch_ports');
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db(IPS_MODULE);
	$db2 = $db;
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$db->query('select * from vlans order by vlans_ports, vlans_networks', __LINE__, __FILE__);
	$table = new \TFTable;
	$table->set_title('VLAN Port <-> Server Mapping Setup');
	$table->add_field('VLAN');
	$table->set_bgcolor(2);
	$table->add_field('Comment');
	$table->set_bgcolor(2);
	$table->add_field('Switch');
	$table->set_bgcolor(2);
	$table->add_field('Port');
	$table->set_bgcolor(2);
	$table->add_field('Server');
	$table->add_row();
	$table->alternate_rows();
	while ($db->next_record()) {
		$networks = explode(':', $db->Record['vlans_networks']);
		$comment = $db->Record['vlans_comment'];
		$network = $networks[1];
		$ports = explode(':', $db->Record['vlans_ports']);
		if ($ports[1] != '') {
			list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[1]);
			if (isset($GLOBALS['tf']->variables->request['vlan_'.$db->Record['vlans_id']])) {
				$server = $GLOBALS['tf']->variables->request['vlan_'.$db->Record['vlans_id']];
				if ($server != '0') {
					$query = "update servers set switch='', slot='' where switch='{$switch}' and slot='{$port}'";
					$db2->query($query, __LINE__, __FILE__);
					$query = "update servers set switch='{$switch}', slot='{$port}' where server_hostname='{$server}'";
					$db2->query($query, __LINE__, __FILE__);
				}
			}
			$query = "select id, server_hostname from servers where switch='{$switch}' and slot='{$port}'";
			$db2->query($query, __LINE__, __FILE__);
			if ($db->num_rows()) {
				$db2->next_record();
				$server = $db2->Record['id'];
			} else {
				$server = 0;
			}
			$table->add_field($network);
			$table->add_field($comment);
			$table->add_field(get_switch_name($switch));
			$table->add_field($port);
			$table->add_field(select_server($server, 'vlan_'.$db->Record['vlans_id'], TRUE));
			$table->add_row();
		}
	}
	$table->set_colspan(5);
	$table->add_field($table->make_submit('Update These Servers'));
	$table->add_row();
	add_output($table->get_table());
}

