<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2017
 * @package IP-VLAN-Manager
 * @category IPs
 */
/**
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function switch_manager() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	global $groupinfo;
	$db = get_module_db(IPS_MODULE);
	$db2 = get_module_db(IPS_MODULE);
	$ima = $GLOBALS['tf']->ima;
	if (isset($GLOBALS['tf']->variables->request['name']) && isset($GLOBALS['tf']->variables->request['ports'])) {
		$name = $GLOBALS['tf']->variables->request['name'];
		$ports = $GLOBALS['tf']->variables->request['ports'];
		$db->query(make_insert_query('switchmanager', [
			'id' => NULL,
			'name' => $name,
			'ports' => $ports
		                                            ]
		           ), __LINE__, __FILE__);
	}
	$table = new \TFTable;
	$table->set_title('Switches');
	$table->add_field('Internal ID');
	$table->add_field('Switch ID');
	$table->add_field('Total Ports<br>(including uplink)');
	$table->add_field('Usable Ports');
	$table->add_row();
	$nextid = 14;
	$db->query('select * from switchmanager order by id');
	$table->alternate_rows();
	while ($db->next_record()) {
		if ($nextid <= (int)$db->Record['name'])
			$nextid = $db->Record['name'] + 1;
		$table->add_field($db->Record['id']);
		$table->add_field($db->Record['name']);
		$table->add_field($db->Record['ports']);
		$table->add_field($db->Record['ports'] - 1);
		$table->add_row();
	}
	$table->add_field('Add Switch');
	$table->add_field($table->make_input('name', $nextid, 5));
	$table->add_field($table->make_input('ports', 49, 5));
	$table->add_field($table->make_submit('Add Switch'));
	$table->add_row();
	add_output($table->get_table());
}

