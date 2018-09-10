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
function portless_vlans()
{
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	$db = get_module_db('default');
	$db->query("select * from vlans where vlans_ports='::' order by vlans_networks", __LINE__, __FILE__);
	$table = new \TFTable;
	$table->set_title('Port-less VLAN List'.pdf_link('choice=ip.portless_vlans'));
	if ($db->num_rows() > 0) {
		$table->add_field('VLAN');
		$table->set_bgcolor(2);
		$table->add_field('Comment');
		$table->set_bgcolor(2);
		$table->add_field('Options');
		$table->add_row();
		$table->alternate_rows();
		while ($db->next_record()) {
			$ipblock = str_replace(':', '', $db->Record['vlans_networks']);
			$table->add_field($ipblock, 'l');
			$table->add_field($db->Record['vlans_comment']);
			$table->add_field($table->make_link('choice=ip.vlan_port_manager&ipblock='.$ipblock, 'Configure Port(s)'));
			$table->add_row();
		}
	} else {
		$table->add_field('No VLANs without ports assigned to them');
		$table->add_row();
	}
	if ($GLOBALS['tf']->variables->request['pdf'] == 1) {
		$table->get_pdf();
	}
	add_output($table->get_table());
}
