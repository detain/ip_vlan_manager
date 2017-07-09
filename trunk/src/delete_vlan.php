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
function delete_vlan() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	$ima = $GLOBALS['tf']->ima;
	global $groupinfo;
	$db = get_module_db(IPS_MODULE);
	$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
	if (!isset($GLOBALS['tf']->variables->request['sure']) || $GLOBALS['tf']->variables->request['sure'] != 'yes') {
		$table = new TFTable;
		$table->set_title('Delete VLan');
		$table->add_hidden('ipblock', $ipblock);
		if (isset($_SERVER['HTTP_REFERER']))
			$table->add_hidden('httpreferer', $_SERVER['HTTP_REFERER']);
		$table->set_colspan(2);
		$table->add_field(nl2br(wordwrap('<b>WARNING: THIS WILL NOT REMOVE IPS FROM ROUTER. DO NOT USE THIS FEATURE UNLESS YOU HAVE ALREADY REMOVED THE IPS FROM ROUTER.</b>')));
		$table->add_row();
		$table->add_field('Vlan');
		$table->add_field($ipblock);
		$table->add_row();
		$table->add_field('Go Ahead And Delete');
		$table->add_field('<select name=sure>'.'<option value=no>No</option>'.'<option value=yes>Yes</option>'.'</select>');
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_submit('Delete This Vlan'));
		$table->add_row();
		add_output($table->get_table());
	} else {
		$query = "select * from vlans where vlans_networks=':$ipblock:'";
		$db->query($query, __LINE__, __FILE__);
		$db->next_record();
		$id = $db->Record['vlans_id'];
		$query = "delete from vlans where vlans_networks=':$ipblock:'";
		$db->query($query, __LINE__, __FILE__);
		$query = "update ips set ips_vlan=0 where ips_vlan='$id'";
		$db->query($query, __LINE__, __FILE__);
		function_requirements('update_switch_ports');
		update_switch_ports();
		if (isset($_REQUEST['httpreferer']))
			$GLOBALS['tf']->redirect($_REQUEST['httpreferer']);
		else
			$GLOBALS['tf']->redirect($table->make_link('index.php', 'choice=none.vlan_manager'));
	}
}

