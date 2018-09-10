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
function vlan_port_manager()
{
	function_requirements('update_switch_ports');
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db('default');
	$db2 = $db;
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
	if (($ipblock == '') || ($db->num_rows() == 0)) {
		add_output('Invalid IP Block');
	} else {
		$db->next_record();
		$vlan_id = $db->Record['vlans_id'];
		if (!isset($GLOBALS['tf']->variables->request['ports'])) {
			$ports = explode(':', $db->Record['vlans_ports']);
			$select = get_select_ports($ports);
			$table = new \TFTable;
			$table->set_title('VLan Port Manager');
			$table->add_hidden('ipblock', $ipblock);
			$table->add_field('IP Block', 'l');
			$table->add_field($ipblock, 'r');
			$table->add_row();
			$table->add_field('Select Switch/Port(s) that the VLan is on', 'l');
			$table->add_field($select, 'r');
			$table->add_row();
			$table->set_colspan(2);
			$table->add_field($table->make_submit('Update This VLan'));
			$table->add_row();
			add_output($table->get_table());
		} else {
			$ports = ':'.implode(':', $GLOBALS['tf']->variables->request['ports']).':';
			$db2->query("update vlans set vlans_ports='{$ports}' where vlans_networks like '%:$ipblock:%' and vlans_id='{$vlan_id}'", __LINE__, __FILE__);
			function_requirements('update_switch_ports');
			update_switch_ports();
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock='.$ipblock));
		}
	}
}
