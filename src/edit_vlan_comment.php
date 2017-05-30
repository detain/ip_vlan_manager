<?php
/**
 * IP Functionality
 *
 * Last Changed: $LastChangedDate: 2017-05-26 04:36:01 -0400 (Fri, 26 May 2017) $
 * @author detain
 * @version $Revision: 24803 $
 * @copyright 2017
 * @package MyAdmin
 * @category IPs
 */

/**
 * @return bool
 */
function edit_vlan_comment() {
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db(IPS_MODULE);
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return false;
	}
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	// get ip block(s)
	$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
	if ($db->num_rows()) {
		$db->next_record();
		$id = $db->Record['vlans_id'];
		if (!isset($GLOBALS['tf']->variables->request['comment'])) {
			$table = new TFTable;
			$table->set_title('VLAN Comment Editor');
			$table->add_hidden('ipblock', $ipblock);
			$table->add_field('VLAN');
			$table->add_field($ipblock);
			$table->add_row();
			$table->set_colspan(2);
			$table->add_field('Vlan Comment', 'c');
			$table->add_row();
			$table->set_colspan(2);
			$table->add_field('<textarea rows=7 cols=25 name=comment>' . $db->Record['vlans_comment'] . '</textarea>', 'c');
			$table->add_row();
			$table->set_colspan(2);
			$table->add_field($table->make_submit('Update The Comment'));
			$table->add_row();
			add_output($table->get_table());
		} else {
			$comment = $GLOBALS['tf']->variables->request['comment'];
			$db->query("update vlans set vlans_comment='$comment' where vlans_id='$id'", __LINE__, __FILE__);
			function_requirements('update_switch_ports');
			update_switch_ports();
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
		}
	}
}

