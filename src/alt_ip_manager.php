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
function alt_ip_manager() {
	function_requirements('has_acl');
	if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
		dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
		return FALSE;
	}
	global $groupinfo;
	$ima = $GLOBALS['tf']->ima;
	$db = get_module_db('default');
	$db2 = get_module_db('default');
	if (isset($GLOBALS['tf']->variables->request['ipblock'])) {
		$db->query('select id, server_hostname from servers', __LINE__, __FILE__);
		$serverids = [];
		$serverids[0] = 'None';
		while ($db->next_record()) {
			$serverids[$db->Record['id']] = $db->Record['server_hostname'];
			$server_hostnames[$db->Record['server_hostname']] = $db->Record['id'];
		}
		$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
		if (isset($GLOBALS['tf']->variables->request['server']) && isset($GLOBALS['tf']->variables->request['ip'])) {
			$sid = $server_hostnames[$GLOBALS['tf']->variables->request['server']];
			$ipAddress = $GLOBALS['tf']->variables->request['ip'];
			$query = "update ips set ips_serverid='{$sid}' where ips_ip='{$ipAddress}'";
			$db->query($query, __LINE__, __FILE__);
		}
		$db->query("select *, INET_ATON(ips_ip) as aton from ips where ips_ip like '{$ipblock}.%' order by aton", __LINE__, __FILE__);
		$table = new \TFTable;
		$table->set_title('IP Manager');
		$table->add_field('IP Block');
		$table->set_colspan(2);
		$table->add_field($table->make_link('choice=ip.alt_ip_manager', $ipblock.' (Change)'));
		$table->add_row();
		$table->add_field('IP Address');
		$table->set_colspan(2);
		$table->add_field('Server');
		$table->add_row();
		while ($db->next_record()) {
			$table->add_field($db->Record['ips_ip']);
			if ($GLOBALS['tf']->variables->request['ip'] == $db->Record['ips_ip']) {
				$table->add_hidden('ip', $db->Record['ips_ip']);
				$table->add_hidden('ipblock', $ipblock);
				$table->add_field(select_server($db->Record['ips_serverid'], 'server', 1));
				$table->add_field($table->make_submit('Update Server'));
			} else {
				$table->set_colspan(2);
				$table->add_field($table->make_link('choice=ip.alt_ip_manager&ipblock='.$ipblock.'&ip='.$db->Record['ips_ip'], $serverids[$db->Record['ips_serverid']]));
			}
			$table->add_row();
		}
		add_output($table->get_table());
	} else {
		$table = new \TFTable;
		$table->set_title('IP Block Selection');
		$table->add_field('Select IP Block');
		$db->query('select * from ips order by ips_ip', __LINE__, __FILE__);
		$lastip = '';
		$sel = '<select name=ipblock>';
		while ($db->next_record()) {
			mb_ereg('^([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\.[0-9]{1,3}$', $db->Record['ips_ip'], $ipblock);
			if ($lastip != $ipblock[1]) {
				$sel .= '<option value="'.$ipblock[1].'">'.$ipblock[1].'</option>';
				$lastip = $ipblock[1];
			}
		}
		$sel .= '</select>';
		$table->add_field($sel);
		$table->add_field($table->make_submit('Edit This IP Block'));
		$table->add_row();
		add_output($table->get_table());

	}
}

