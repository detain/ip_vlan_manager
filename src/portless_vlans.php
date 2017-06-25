<?php
/**
 * IP Functionality
 *
 * Last Changed: $LastChangedDate: 2017-05-26 04:36:01 -0400 (Fri, 26 May 2017) $
 * @author detain
 * @version $Revision: 24803 $
 * @copyright 2017
 * @package IP-VLAN-Manager
 * @category IPs
 */

/**
 * @return bool
 */
function portless_vlans() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$db = get_module_db(IPS_MODULE);
	$db->query("select * from vlans where vlans_ports='::' order by vlans_networks", __LINE__, __FILE__);
	$table = new TFTable;
	$table->set_title('Port-less VLAN List' . pdf_link('choice=ip.portless_vlans'));
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
			$table->add_field($table->make_link('choice=ip.vlan_port_manager&ipblock=' . $ipblock, 'Configure Port(s)'));
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

