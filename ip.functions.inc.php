<?php
	/************************************************************************************\
	* Trouble Free Admin                                                                 *
	* (c)2002-2013 Interserver                                                           *
	*
	* $Id$
	* $Source$
	* ---------------------------------------------------------------------------------- *
	* Description: IP functions                                                          *
	\************************************************************************************/

	/**
	 * @param $data
	 * @return array
	 */
	function parse_vlan_ports($data)
	{
		$parts2 = explode('/', $data);
		$switch = $parts2[0];
		$port = substr($data, strlen($switch) + 1);
		if (strpos($port, '/') > 0)
		{
			$blade = substr($port, 0, strrpos($port, '/'));
			$justport = substr($port, strlen($blade) + 1);
		}
		else
		{
			$blade = '';
			$justport = $port;
		}
		return array(
			$switch,
			$port,
			$blade,
			$justport);
	}

	/**
	 * @param      $index
	 * @param bool $short
	 * @return string
	 */
	function get_switch_name($index, $short = FALSE)
	{
		$db = clone $GLOBALS['admin_dbh'];
		$db->query("select * from switchmanager where id='$index'");
		$db->next_record();
		$switch = $db->Record['name'];
		if ($short == FALSE)
		{
			return 'Switch ' . $switch;
		}
		else
		{
			return $switch;
		}
	}

	/**
	 * @param bool $ports
	 * @param int  $size
	 * @return string
	 */
	function get_select_ports($ports = FALSE, $size = 5)
	{
		$db = $GLOBALS['admin_dbh'];
		if ($ports === FALSE)
		{
			$ports = array();
		}
		$select = '<select multiple size=' . $size . ' name="ports[]">';
		$db->query("select * from switchmanager as sm, switchports as sp where switch=id order by id, port");
		while ($db->next_record())
		{
			$switch = $db->Record['id'];
			$port = $db->Record['port'];
			if (in_array($switch . '/' . $port, $ports))
			{
				$select .= '<option selected value="' . $switch . '/' . $port . '">Switch ' . $db->Record['name'] . ' Port ' . $port . '</option>';
			}
			else
			{
				$select .= '<option value="' . $switch . '/' . $port . '">Switch ' . $db->Record['name'] . ' Port ' . $port . '</option>';
			}
		}
		$select .= '</select>';
		return $select;
	}

	function switch_manager()
	{
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		$ima = $GLOBALS['tf']->ima;
		if ($ima == 'admin')
		{
			if ($groupinfo['account_id'] == ADMIN_GROUP)
			{
				if (isset($GLOBALS['tf']->variables->request['name']) && isset($GLOBALS['tf']->variables->request['ports']))
				{
					$name = $GLOBALS['tf']->variables->request['name'];
					$ports = $GLOBALS['tf']->variables->request['ports'];
					$db->query("insert into switchmanager values (NULL, '$name', '$ports')");
				}
				$table = new TFTable;
				$table->set_title('Switches');
				$table->add_field('Internal ID');
				$table->add_field('Switch ID');
				$table->add_field('Total Ports<br>(including uplink)');
				$table->add_field('Usable Ports');
				$table->add_row();
				$nextid = 14;
				$db->query("select * from switchmanager order by id");
				$table->alternate_rows();
				while ($db->next_record())
				{
					if ($nextid <= intval($db->Record['name']))
					{
						$nextid = $db->Record['name'] + 1;
					}
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
			else
			{
				add_output('This functionality is reserved for admins');
			}
		}
	}

	function list_revips()
	{
		$db = $GLOBALS['admin_dbh'];
		if (!isset($GLOBALS['tf']->variables->request['server']))
		{
			select_server();
		}
		else
		{
			$server = $GLOBALS['tf']->variables->request['server'];

			$db->query("select servers_serverid from servers where servers_hostname='$server'", __LINE__, __FILE__);
			$db->next_record();
			$serverid = $db->Record;

			$db->query("select ips_ip from ips where ips_serverid='$serverid'", __LINE__, __FILE__);
			while ($db->next_record())
			{
				$row = $db->Record;

				$ipadres = `nslookup -sil $row[0] | grep arpa`;
				if (preg_match("/can/i", $ipadres))
				{
					add_output("$row[0] (ADD REVERSE DNS)<br>");
				}
				else
				{
					add_output("$row[0] <b>-></b> $ipadres<br>");
				}
			}
		}
	}

	function list_ips()
	{
		$db = $GLOBALS['admin_dbh'];
		if (!isset($GLOBALS['tf']->variables->request['server']))
		{
			select_server();
		}
		else
		{
			$server = $GLOBALS['tf']->variables->request['server'];
			if (valid_server($server))
			{
				$db->query("select servers_serverid from servers where servers_hostname='$server'", __LINE__, __FILE__);
				$db->next_record();
				$serverid = $db->f(0);
				$table = new TFTable;
				$table->set_title('IP Usage');
				$table->add_field('Server');
				$table->add_field($server);
				$table->add_row();
				$table->set_colspan(2);
				$table->add_field($table->make_link('choice=none.rebuild_ippool&amp;server=' . $server, 'Rebuild IP Pool'));
				$table->add_row();
				$table->alternate_rows();
				$db->query("select ips_ip from ips where ips_serverid='$serverid'", __LINE__, __FILE__);
				while ($db->next_record())
				{
					$table->set_colspan(2);
					$table->add_field($db->f('ips_ip') . '<br>');
					$table->add_row();
				}
				add_output($table->get_table());
				previous_url($server);
			}
		}
	}

	function delegate_ips()
	{
		$db = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		$ima = $GLOBALS['tf']->ima;
		$choice = $GLOBALS['tf']->variables->request['choice'];
		$num_ips = $GLOBALS['tf']->variables->request['num_ips'];
		$result = $GLOBALS['tf']->variables->request['result'];

		if (!isset($GLOBALS['tf']->variables->request['server']))
		{
			select_server();
		}
		else
		{
			$server = $GLOBALS['tf']->variables->request['server'];
			$GLOBALS['tf']->session->appsession('server', $server);
			if (valid_server($server))
			{
				if (!$num_ips)
				{
					add_output('<form enctype="multipart/form-data" method="post" action="' . $GLOBALS['tf']->link('index.php') . '">');
					add_output("<input type=hidden name=choice value=$choice>");
					add_output("<input type=hidden name=server value=$server>");
					add_output("How many IPs do you want to add to the server?: ");
					add_output("<input type=text name=num_ips><br>");
					add_output('<input type=submit value="Add IPs To Server"><br>');
					add_output('</form>');
				}
				else
				{
					add_output('Adding IPs...');
					$db->query("select servers_serverid from servers where servers_hostname='$server'", __LINE__, __FILE__);
					$db->next_record();
					$serverid = $db->Record;
					if ($GLOBALS['tf']->accounts->data['demo'] == 1)
					{
						add_output("No Updates In Demo Mode");
					}
					else
					{

						$db->query("select * from ips where ips_serverid='0' limit $num_ips", __LINE__, __FILE__);
						while ($db->next_record())
						{
							$row = $db->Record;
							$result2 = `echo "echo $row[0] >> /etc/ips" | /usr/bin/ssh -x -o BatchMode=yes -1 root@$server 2>/dev/null`;
							add_output("($row[0])");
							$db2->query("update ips set ips_serverid='$serverid' where ips_ip='$row[0]'", __LINE__, __FILE__);
						}
						add_output('done adding ips<br>');
						add_output('restarting ipaliases');
						$result = `/usr/bin/ssh -x -o BatchMode=yes -1 root@$server /etc/rc.d/init.d/ipaliases restart 2>/dev/null`;
						add_output('<pre>');
						add_output($result);
						add_output('</pre>');
						add_output('done restarting ipaliases<br>');
					}
				}
			}
		}
	}

	function view_doublebound_ips()
	{
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		$ima = $GLOBALS['tf']->ima;
		if ($ima == 'admin')
		{
			if ($groupinfo['account_id'] == ADMIN_GROUP)
			{
				$db->query("select distinct * from history_log, ips where ips_ip=history_new_value and history_type='doubleboundip' and history_section='servers' group by history_new_value", __LINE__, __FILE__);
			}
			else
			{
				$db->query("select distinct history_log.*, ips.* from history_log, ips, servers where (ips_group='$groupinfo[account_id]' or servers_group like '%:$groupinfo[account_id]:%') and servers_serverid=history_old_value and ips_ip=history_new_value and history_type='doubleboundip' and history_section='servers' group by history_new_value",
					__LINE__, __FILE__);
			}
			if ($db->num_rows() == 0)
			{
				add_output('There are currently NO double-bound IPs (Good Job)<BR>');
			}
			else
			{
				$table = new TFTable;
				$table->set_title('Viewing Double-Bound IPs (' . $db->num_rows() . ' IPs)' . pdf_link('choice=ip.view_doublebound_ips'));
				$table->add_field('IP');
				$table->set_colspan(2);
				$table->set_bgcolor(2);
				$table->add_field('Found On These Servers');
				$table->set_bgcolor(2);
				$table->set_colspan(2);
				$table->add_field('When It Was Detected<BR>');
				$table->add_row();
				$table->alternate_rows();
				$servers = array();
				while ($db->next_record())
				{
					$table->add_field($db->Record['history_new_value']);
					if (!isset($servers[$db->Record['ips_serverid']]))
					{
						$server = $GLOBALS['tf']->get_server($db->Record['ips_serverid']);
						$servers[$db->Record['ips_serverid']] = $server['servers_hostname'];
					}
					if (!isset($servers[$db->Record['history_old_value']]))
					{
						$server = $GLOBALS['tf']->get_server($db->Record['history_old_value']);
						$servers[$db->Record['history_old_value']] = $server['servers_hostname'];
					}
					$table->add_field($servers[$db->Record['ips_serverid']]);
					$table->add_field($servers[$db->Record['history_old_value']]);
					$table->add_field(display_timestamp($db->Record['history_timestamp']));
					$table->add_field($table->make_link('choice=ip.close_doublebound&ip=' . $db->Record['history_new_value'], 'Close Problem'));
					$table->add_row();
				}
				add_output($table->get_table());
				if ($GLOBALS['tf']->variables->request['pdf'] == 1)
				{
					$table->get_pdf();
				}
			}
		}
		else
		{
			add_output('Only admins have access to this list right now.  If you feel one or more of your servers is currently using someone elses IP address(s) OR someone else might be using one of your ip address(s) than please contact us at support@trouble-free.net or fill out a ticket');
		}
	}

	function close_doublebound()
	{
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		$ima = $GLOBALS['tf']->ima;
		$ip = $GLOBALS['tf']->variables->request['ip'];
		if ($GLOBALS['tf']->accounts->data['demo'] == 1)
		{
			add_output("No Updates In Demo Mode");
		}
		else
		{
			// FIXME
			$db->query("delete from history_log where history_new_value='$ip' and history_type='doubleboundip' and history_section='servers'", __LINE__, __FILE__);
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.view_doublebound_ips'));
		}
	}

	function alt_ip_manager()
	{
		global $groupinfo;
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		if ($ima == 'admin')
		{
			if ($groupinfo['account_id'] == ADMIN_GROUP)
			{
				if (isset($GLOBALS['tf']->variables->request['ipblock']))
				{
					$db->query("select servers_serverid, servers_hostname from servers", __LINE__, __FILE__);
					$serverids = array();
					$serverids[0] = 'None';
					while ($db->next_record())
					{
						$serverids[$db->Record['servers_serverid']] = $db->Record['servers_hostname'];
						$servernames[$db->Record['servers_hostname']] = $db->Record['servers_serverid'];
					}
					$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
					if (isset($GLOBALS['tf']->variables->request['server']) && isset($GLOBALS['tf']->variables->request['ip']))
					{
						$sid = $servernames[$GLOBALS['tf']->variables->request['server']];
						$ip = $GLOBALS['tf']->variables->request['ip'];
						$query = "update ips set ips_serverid='$sid' where ips_ip='$ip'";
						$db->query($query, __LINE__, __FILE__);
					}
					$db->query("select *, INET_ATON(ips_ip) as aton from ips where ips_ip like '$ipblock.%' order by aton", __LINE__, __FILE__);
					$table = new TFTable;
					$table->set_title('IP Manager');
					$table->add_field('IP Block');
					$table->set_colspan(2);
					$table->add_field($table->make_link('choice=ip.alt_ip_manager', $ipblock . ' (Change)'));
					$table->add_row();
					$table->add_field('IP Address');
					$table->set_colspan(2);
					$table->add_field('Server');
					$table->add_row();
					while ($db->next_record())
					{
						$table->add_field($db->Record['ips_ip']);
						if ($GLOBALS['tf']->variables->request['ip'] == $db->Record['ips_ip'])
						{
							$table->add_hidden('ip', $db->Record['ips_ip']);
							$table->add_hidden('ipblock', $ipblock);
							$table->add_field(select_server($db->Record['ips_serverid'], 'server', 1));
							$table->add_field($table->make_submit('Update Server'));
						}
						else
						{
							$table->set_colspan(2);
							$table->add_field($table->make_link('choice=ip.alt_ip_manager&ipblock=' . $ipblock . '&ip=' . $db->Record['ips_ip'], $serverids[$db->Record['ips_serverid']]));
						}
						$table->add_row();
					}
					add_output($table->get_table());
				}
				else
				{
					$table = new TFTable;
					$table->set_title('IP Block Selection');
					$table->add_field('Select IP Block');
					$db->query("select * from ips order by ips_ip", __LINE__, __FILE__);
					$lastip = '';
					$sel = '<select name=ipblock>';
					while ($db->next_record())
					{
						ereg('^([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\.[0-9]{1,3}$', $db->Record['ips_ip'], $ipblock);
						if ($lastip != $ipblock[1])
						{
							$sel .= '<option value="' . $ipblock[1] . '">' . $ipblock[1] . '</option>';
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
		}
	}

	function ip_manager()
	{
		$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
		/*		global $groupinfo;
		$db  = $GLOBALS['admin_dbh'];
		$db2 = $GLOBALS['admin_dbh'];
		$ima = $GLOBALS['tf']->ima;

		// get free ip count
		if ($groupinfo['account_id'] == ADMIN_GROUP)
		{
		$db->query("select count(*) from ips where ips_serverid='0'",__LINE__,__FILE__);
		}
		else
		{
		$db->query("select count(*) from ips where ips_serverid='0' and ips_group='$groupinfo[account_id]'",__LINE__,__FILE__);
		}
		$db->next_record();
		$freeips = $db->f(0);

		// get total ips
		if ($groupinfo['account_id'] == ADMIN_GROUP)
		{
		$db->query("select count(*) from ips",__LINE__,__FILE__);
		}
		else
		{
		$db->query("select count(*) from ips where ips_group='$groupinfo[account_id]'",__LINE__,__FILE__);
		}
		$db->next_record();
		$totips = $db->f(0);

		// get used ips
		if ($groupinfo['account_id'] == ADMIN_GROUP)
		{
		$db->query("select count(*) from ips where ips_serverid!='0'",__LINE__,__FILE__);
		}
		else
		{
		$db->query("select count(*) from ips where ips_serverid!='0' and ips_group='$groupinfo[account_id]'",__LINE__,__FILE__);
		}
		$db->next_record();
		$usedips = $db->f(0);

		$table = new TFTable;
		$table->set_title('IP Manager');
		$table->add_field('Total IPs:');
		$table->add_field($totips);
		$table->add_row();
		$table->add_field('Used IPs:');
		$table->add_field($usedips);
		$table->add_row();
		$table->add_field('Free IPs:');
		$table->add_field($freeips);
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_link('choice=add_ips', 'Add New IPs Into Pool'));
		$table->add_row();
		$table->set_colspan(2);
		$table->add_field($table->make_link('choice=ip.view_doublebound_ips', 'View Double-Bound IPs'));
		$table->add_row();
		add_output($table->get_table());

		$table = new TFTable;
		$table->set_title('IP Usage Breakdown');
		$table->add_field('Server Hostname');
		$table->set_bgcolor(2);
		$table->add_field('IPs');
		$table->set_colspan(5);
		$table->set_bgcolor(2);
		$table->add_field('');
		$table->add_row();
		if ($groupinfo['account_id'] == ADMIN_GROUP)
		{
		$db->query("select * from servers order by servers_hostname",__LINE__,__FILE__);
		}
		else
		{
		$db->query("select * from servers where servers_group like '%:$groupinfo[account_id]:%' order by servers_hostname",__LINE__,__FILE__);
		}
		$table->alternate_rows();
		while ($db->next_record())
		{
		$serverinfo = $db->Record;
		$table->add_field($serverinfo['servers_hostname']);
		$db2->query("select count(*) from ips where ips_serverid='$serverinfo[0]'",__LINE__,__FILE__);
		$db2->next_record();
		$ips = $db2->f(0);
		$table->add_field($ips);
		$table->add_field($table->make_link('choice=list_ips&amp;server=' . $serverinfo['servers_hostname'], 'List IPs'));
		$table->add_field($table->make_link('choice=list_revips&amp;server=' . $serverinfo['servers_hostname'], 'Reverse DNS'));
		$table->add_field($table->make_link('choice=delegate_ips&amp;server=' . $serverinfo['servers_hostname'], 'Add IPs'));
		$table->add_field($table->make_link('choice=remove_unused_ips&amp;server=' . $serverinfo['servers_hostname'], 'Remove IPs'));
		$table->add_field($table->make_link('choice=rebuild_ippool&amp;server=' . $serverinfo['servers_hostname'], 'Rebuild IPPool'));
		$table->add_row();
		}
		add_output($table->get_table());
		*/
	}

	/**
	 * @param $netmask
	 * @return int
	 */
	function get_ipcount_from_netmask($netmask)
	{
		$ipinfo = array();
		//error_log("Calling ipcalc here");
		$result = trim(`LANG=C /usr/local/bin/ipcalc -nb 192.168.0.0/$netmask | grep Hosts | cut -d" " -f2`);
		return intval($result);
	}

	/**
	 * @param $networks
	 * @return mixed
	 */
	function ipcalc_array($networks)
	{
		$cmd = "function a() {\n";
		for ($x = 0; $x < sizeof($networks); $x++)
		{
			//error_log("Calling ipcalc here");
			$cmd .= 'LANG=C /usr/local/bin/ipcalc -nb ' . $networks[$x]['network'] . ';echo :-----;';
		}
		$cmd .= "}\n";
		$cmd .= 'a | grep : | sed s#" "#""#g | cut -d= -f1 | cut -d: -f2 | cut -d\( -f1 | cut -dC -f1;';
		$result = trim(`$cmd`);
		$results = explode('-----', $result);
		for ($x = 0; $x < sizeof($networks); $x++)
		{
			$ipinfo = array();
			$lines = explode("\n", trim($results[$x]));
			$netparts = explode('/', $lines[3]);
			$ipinfo['network'] = $lines[3];
			$ipinfo['network_ip'] = $netparts[0];
			$ipinfo['netmask'] = $lines[1];
			$ipinfo['wildcard'] = $lines[2];
			$ipinfo['broadcast'] = $lines[6];
			$ipinfo['hostmin'] = $lines[4];
			$ipinfo['hostmax'] = $lines[5];
			$ipinfo['hosts'] = $lines[7];
			$network_info[$networks[$x]['network']] = $ipinfo;
		}
		return $network_info;
	}

	if (!function_exists('valid_ip'))
	{
		/**
		 * valid_ip()
		 * returns wether or not the given IP is valid
		 *
		 * @param string $ip the ip address to validate
		 * @param bool $display_errors wether or not errors are displayed. defauls to true
		 * @return bool wether or not its a valid ip
		 */
		function valid_ip($ip, $display_errors = true)
		{
			if (!preg_match("/^[0-9\.]{7,15}$/", $ip))
			{
				// dont display errors cuz this gets called w/ a blank entry when people didnt even submit anything yet
				//add_output('<font class="error">IP ' . $ip . ' Too short/long</font>');
				return false;
			}
			$quads = explode('.', $ip);
			$numquads = count($quads);
			if ($numquads != 4)
			{
				if ($display_errors)
					add_output('<font class="error">IP ' . $ip . ' Too many quads</font>');
				return false;
			}
			for ($i = 0; $i < 4; $i++)
			{
				if ($quads[$i] > 255)
				{
					if ($display_errors)
						add_output('<font class="error">IP ' . $ip . ' number ' . $quads[$i] . ' too high</font>');
					return false;
				}
			}
			return true;
		}
	}

	if (!function_exists('ipcalc'))
	{
		/**
		 * @param $network
		 * @return array
		 */
		function ipcalc($network)
		{
			if (trim($network) == '')
				return false;
			$parts = explode('/', $network);
			if (sizeof($parts) > 1)
			{
				list($block, $bitmask) = $parts;
			}
			else
			{
				$block = $parts[0];
				$bitmask = '32';
				$network = $block . '/' . $bitmask;
			}
			if (!valid_ip($block, false) || !is_numeric($bitmask))
				return false;
			if (preg_match('/^(.*)\/32$/', $network, $matches))
				return array(
					'network' => $matches[1],
					'network_ip' => $matches[1],
					'netmask' => '255.255.255.255',
					'broadcast' => '',
					'hostmin' => $matches[1],
					'hostmax' => $matches[1],
					'hosts' => 1,
				);
			require_once ('Net/IPv4.php');
			$network_object = new Net_IPv4();
			$net = $network_object->parseAddress($network);
			//billingd_log("|$network|");
			$ip_info = array(
				'network' => $net->network . '/' . $net->bitmask,
				'network_ip' => $net->network,
				'netmask' => $net->netmask,
				'broadcast' => $net->broadcast,
				'hostmin' => long2ip($net->ip2double($net->network) + 1),
				'hostmax' => long2ip($net->ip2double($net->broadcast) - 1),
				'hosts' => $net->ip2double($net->broadcast) - $net->ip2double($net->network) - 1,
				);
			return $ip_info;
		}

		/**
		 * @param $network
		 * @return array
		 */
		function ipcalc_old($network)
		{
			/* Sample Output from ipcalc
			0	Address:66.45.224.0
			1	Netmask:255.255.240.0
			2	Wildcard:0.0.15.255
			3	Network:66.45.224.0/20
			4	Broadcast:66.45.239.255
			5	HostMin:66.45.224.1
			6	HostMax:66.45.239.254
			7	Hosts/Net:4094
			*/
			$ipinfo = array();
			//error_log("Calling ipcalc here");
			$result = trim(`LANG=C /usr/local/bin/ipcalc -nb $network | grep : | sed s#" "#""#g | cut -d= -f1 | cut -d: -f2 | cut -d\( -f1 | cut -dC -f1`);
			$lines = explode("\n", $result);
			$netparts = explode('/', $lines[3]);
			$ipinfo['network'] = $lines[3];
			$ipinfo['network_ip'] = $netparts[0];
			$ipinfo['netmask'] = $lines[1];
			$ipinfo['wildcard'] = $lines[2];
			$ipinfo['broadcast'] = $lines[6];
			$ipinfo['hostmin'] = $lines[4];
			$ipinfo['hostmax'] = $lines[5];
			$ipinfo['hosts'] = $lines[7];
			//_debug_array($ipinfo);
			return $ipinfo;
		}
	}

	/**
	 * @param        $text
	 * @param int    $vlan
	 * @param string $comment
	 * @param string $ports
	 * @return array
	 */
	function get_networks($text, $vlan = 0, $comment = '', $ports = '')
	{
		$networks = array();
		$parts = explode(':', $text);
		for ($x = 0; $x < sizeof($parts); $x++)
		{
			if ($parts[$x] != '')
			{
				$networks[] = array(
					'network' => $parts[$x],
					'vlan' => $vlan,
					'comment' => $comment,
					'ports' => $ports);
			}
		}
		return $networks;
	}

	/**
	 * @param      $part
	 * @param      $ipparts
	 * @param      $maxparts
	 * @param bool $include_unusable
	 * @return bool
	 */
	function check_ip_part($part, $ipparts, $maxparts, $include_unusable = FALSE)
	{
		if ($include_unusable)
		{
			$maxip = 256;
		}
		else
		{
			$maxip = 255;
		}
		switch ($part)
		{
			case 1:
				if ($ipparts[0] < $maxip)
				{
					if (($ipparts[0] <= $maxparts[0]))
					{
						return TRUE;
					}
					else
					{
						return FALSE;
					}
				}
				else
				{
					return FALSE;
				}
				break;
			case 2:
				if ($ipparts[1] < $maxip)
				{
					if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]))
					{
						return TRUE;
					}
					else
					{
						return FALSE;
					}
				}
				else
				{
					return FALSE;
				}
				break;
			case 3:
				if ($ipparts[2] < $maxip)
				{
					if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2]))
					{
						return TRUE;
					}
					else
					{
						return FALSE;
					}
				}
				else
				{
					return FALSE;
				}
				break;
			case 4:
				if ($ipparts[3] < $maxip)
				{
					if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2]) && ($ipparts[3] <= $maxparts[3]))
					{
						return TRUE;
					}
					else
					{
						return FALSE;
					}
				}
				else
				{
					return FALSE;
				}
				break;
		}
	}

	/**
	 * @return array
	 */
	function get_all_ipblocks()
	{
		$db = get_module_db('admin');
		$db->query("select ipblocks_network from ipblocks");
		$all_blocks = array();
		while ($db->next_record(MYSQL_ASSOC))
			$all_blocks[] = $db->Record['ipblocks_network'];
		return $all_blocks;
	}

	/**
	 * @return array
	 */
	function get_client_ipblocks()
	{
		$ipblocks = array(
			'67.217.48.0/20',
			'69.164.240.0/20',
			'74.50.64.0/19',
			'202.53.73.0/24',
			'205.209.96.0/19',
			'216.219.80.0/20');
		return $ipblocks;
	}

	/**
	 * @param bool $include_unusable
	 * @return array
	 */
	function get_client_ips($include_unusable = false)
	{
		$ipblocks = get_client_ipblocks();
		$client_ips = array();
		foreach ($ipblocks as $ipblock)
		{
			$client_ips = array_merge($client_ips, get_ips($ipblock, $include_unusable));
		}
		return $client_ips;
	}

	/**
	 * @param bool $include_unusable
	 * @return array
	 */
	function get_all_ips_from_ipblocks($include_unusable = FALSE)
	{
		$all_blocks = get_all_ipblocks();
		$all_ips = array();
		foreach ($all_blocks as $ipblock)
			$all_ips = array_merge($all_ips, get_ips($ipblock, $include_unusable));
		return $all_ips;
	}

	/**
	 * @param bool $include_unusable
	 * @return array
	 */
	function get_all_ips2_from_ipblocks($include_unusable = FALSE)
	{
		$all_blocks = get_all_ipblocks();
		$all_ips = array();
		foreach ($all_blocks as $ipblock)
			$all_ips = array_merge($all_ips, get_ips2($ipblock, $include_unusable));
		return $all_ips;
	}

	/**
	 * @param      $network
	 * @param bool $include_unusable
	 * @return array
	 */
	function get_ips($network, $include_unusable = FALSE)
	{
		//echo "$network|$include_unusable|<br>";
		$ips = array();
		$network_info = ipcalc($network);
		//_debug_array($network_info);
		if ($include_unusable)
		{
			$minip = $network_info['network_ip'];
			$maxip = $network_info['broadcast'];
		}
		else
		{
			$minip = $network_info['hostmin'];
			$maxip = $network_info['hostmax'];
		}
		$minparts = explode('.', $minip);
		$maxparts = explode('.', $maxip);
		$ips = array();
		for ($a = $minparts[0]; check_ip_part(1, array($a), $maxparts, $include_unusable); $a++)
		{
			for ($b = $minparts[1]; check_ip_part(2, array($a, $b), $maxparts, $include_unusable); $b++)
			{
				for ($c = $minparts[2]; check_ip_part(3, array(
					$a,
					$b,
					$c), $maxparts, $include_unusable); $c++)
				{
					for ($d = $minparts[3]; check_ip_part(4, array(
						$a,
						$b,
						$c,
						$d), $maxparts, $include_unusable); $d++)
					{
						$ips[] = $a . '.' . $b . '.' . $c . '.' . $d;
					}
				}
			}
		}
		return $ips;
	}

	/**
	 * @param      $network
	 * @param bool $include_unusable
	 * @return array
	 */
	function get_ips2($network, $include_unusable = FALSE)
	{
		//echo "$network|$include_unusable|<br>";
		$ips = array();
		$network_info = ipcalc($network);
		//_debug_array($network_info);
		if ($include_unusable)
		{
			$minip = $network_info['network_ip'];
			$maxip = $network_info['broadcast'];
		}
		else
		{
			$minip = $network_info['hostmin'];
			$maxip = $network_info['hostmax'];
		}
		$minparts = explode('.', $minip);
		$maxparts = explode('.', $maxip);
		$ips = array();
		for ($a = $minparts[0]; check_ip_part(1, array($a), $maxparts, $include_unusable); $a++)
		{
			for ($b = $minparts[1]; check_ip_part(2, array($a, $b), $maxparts, $include_unusable); $b++)
			{
				for ($c = $minparts[2]; check_ip_part(3, array(
					$a,
					$b,
					$c), $maxparts, $include_unusable); $c++)
				{
					for ($d = $minparts[3]; check_ip_part(4, array(
						$a,
						$b,
						$c,
						$d), $maxparts, $include_unusable); $d++)
					{
						$ips[] = array(
							$a . '.' . $b . '.' . $c . '.' . $d,
							$a,
							$b,
							$c,
							$d);
					}
				}
			}
		}
		return $ips;
	}

	/**
	 * @param     $blocksize
	 * @param int $location
	 * @return array
	 */
	function available_ipblocks($blocksize, $location = 1)
	{
		// array of available blocks
		$available = array();
		$db = $GLOBALS['admin_dbh'];
		// first we gotta get how many ips are in the blocksize they requested
		$ipcount = get_ipcount_from_netmask($blocksize) + 2;
		// get the ips in use
		$usedips = array();
		$mainblocks = array();
		if ($location == 1)
		{
			// get the main ipblocks we have routed
			$db->query("select * from ipblocks", __LINE__, __FILE__);
			while ($db->next_record())
			{
				$mainblocks[] = array($db->Record['ipblocks_id'], $db->Record['ipblocks_network']);
			}
		}
		if ($location == 2)
		{
			$mainblocks[] = array(7, '173.214.160.0/23');
			$mainblocks[] = array(8, '206.72.192.0/24');
			$mainblocks[] = array(12, '162.220.160.0/24');
			$mainblocks[] = array(15, '104.37.184.0/24');

		}
		else
		{
			//  104.37.184.0/24 LA reserved
			$reserved = array(1747302400, 1747302655);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/*  added by joe 08/24/11 to temporarily hide  173.214.160.0/23 lax1 ips */
			$reserved = array(2916524033, 2916524542);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 206.72.192.0  LA
			$reserved = array(3460874240, 3460874751);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 162.220.160.0/24 LA
			$reserved = array(2732367872, 2732368127);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}

			// 162.216.112.0/24   LA
			$reserved = array(2732093440, 2732093695);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
		}
		if ($location == 3)
		{
			$mainblocks[] = array(12, '162.220.161.0/24');
		}
		else
		{
			// 162.220.161.0/24 NY4
			$reserved = array(2732368128, 2732368383);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
		}
		if ($location == 4)
		{
			$mainblocks[] = array(179, "66.45.241.16/28");
			$mainblocks[] = array(1281, "69.10.38.128/25");
			$mainblocks[] = array(2047, "69.10.60.192/26");
			$mainblocks[] = array(1869, "69.10.56.0/25");
			$mainblocks[] = array(2159, "68.168.216.0/22");
			$mainblocks[] = array(2276, "69.10.57.0/24");
			$mainblocks[] = array(1837, "69.10.52.72/29");
			$mainblocks[] = array(1981, "69.10.52.112/29");
			$mainblocks[] = array(1992, "69.10.61.64/26");
			$mainblocks[] = array(2117, "68.168.212.0/24");
			$mainblocks[] = array(2045, "69.10.60.0/26");
			$mainblocks[] = array(2054, "68.168.222.0/24");
			$mainblocks[] = array(2253, "68.168.214.0/23");
			$mainblocks[] = array(2342, "69.10.53.0/24");
			$mainblocks[] = array(2592, "209.159.159.0/24");
			$mainblocks[] = array(3124, "66.23.224.0/24");
		}
		else
		{
			// 66.45.241.16/28 reserved
			$reserved = array(1110307088, 1110307103);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.38.128/25 reserved
			$reserved = array(1158293120, 1158293247);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.60.192/26 reserved
			$reserved = array(1158298816, 1158298879);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.56.0/25 reserved
			$reserved = array(1158297600, 1158297727);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 68.168.216.0/22 reserved
			$reserved = array(1151916032, 1151917055);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.57.0/24 reserved
			$reserved = array(1158297856, 1158298111);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.52.72/29 reserved
			$reserved = array(1158296648, 1158296655);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.52.112/29 reserved
			$reserved = array(1158296688, 1158296695);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.61.64/26 reserved
			$reserved = array(1158298944, 1158299007);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 68.168.212.0/24 reserved
			$reserved = array(1151915008, 1151915263);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.60.0/26 reserved
			$reserved = array(1158298624, 1158298687);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 68.168.222.0/24 reserved
			$reserved = array(1151917568, 1151917823);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 68.168.214.0/23 reserved
			$reserved = array(1151915520, 1151916031);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 69.10.53.0/24 reserved
			$reserved = array(1158296832, 1158297087);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 209.159.159.0/24 reserved
			$reserved = array(3516899072, 3516899327);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 66.23.224.0/24 reserved
			$reserved = array(1108860928, 1108861183);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
		}
		if ($location == 5)
		{
			$mainblocks[] = array(16, '103.237.44.0/22');
			$mainblocks[] = array(17, '43.243.84.0/22');
			$mainblocks[] = array(16, '103.48.176.0/22');
			$mainblocks[] = array(17, '45.113.224.0/22');
			$mainblocks[] = array(17, '45.126.36.0/22');
			$mainblocks[] = array(16, '103.197.16.0/22');
		}
		else
		{
			/* 103.237.44.0/22 */
			$reserved = array(1743596544, 1743597567);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/* 43.243.84.0/22 */
			$reserved = array(737367040, 737367040);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/* 103.48.176.0/22 */
			$reserved = array(1731244032, 1731245055);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/* 45.113.224.0/22 */
			$reserved = array(762437632, 762438400);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/* 45.126.36.0/22 */			
			$reserved = array(763241472, 763242495);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			/* 103.197.16.0/22 */
			$reserved = array(1740967936, 1740968959);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
		}
		// la 3
		if ($location == 6)
		{
			$mainblocks[] = array(3, '69.10.50.0/24');
			$mainblocks[] = array(18, '208.73.200.0/24');
			$mainblocks[] = array(18,'208.73.201.0/24');
			$mainblocks[] = array(20, '216.158.224.0/23');
		}
		else
		{
			// 69.10.50.0/24
			$reserved = array(1158296064, 1158296319);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 208.73.200.0/24
			$reserved = array(3494496256, 3494496511);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 208.73.201.0/24
			$reserved = array(3494496512, 3494496767);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
			// 216.158.224.0/23
			$reserved = array(3634290688,3634291199);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
		}
		/* 45.126.36.0/22 */
/*		$reserved = array(763241472, 763242495);
		for ($x = $reserved[0]; $x < $reserved[1]; $x++)
		{
			$ip = long2ip($x);
			$usedips[$ip] = $ip;
		}
*/
		/* 199.231.191.0/24 reserved */
		$reserved = array(3353853696, 3353853951);
		for ($x = $reserved[0]; $x < $reserved[1]; $x++)
		{
			$ip = long2ip($x);
			$usedips[$ip] = $ip;
		}
		/* 66.23.225.0/24 reserved cogent/currenx */
		$reserved = array(1108861184, 1108861439);
		for ($x = $reserved[0]; $x < $reserved[1]; $x++)
		{
			$ip = long2ip($x);
			$usedips[$ip] = $ip;
		}

		$db->query("select ips_ip from ips2 where ips_vlan > 0", __LINE__, __FILE__);
		if ($db->num_rows())
		{
			while ($db->next_record())
			{
				$usedips[$db->Record['ips_ip']] = $db->Record['ips_ip'];
			}
		}
		foreach ($mainblocks as $maindata)
		{
			$ipblock_id = $maindata[0];
			$mainblock = $maindata[1];
			// get ips from the main block
			$ips = get_ips2($mainblock, TRUE);
			// next loop through all available ips
			$ipsize = sizeof($ips);
			$found = FALSE;
			$found_count = 0;
			$found_c = '';
			for ($x = 0; $x < $ipsize; $x++)
			{
				// check if the ips in use already
				if (isset($usedips[$ips[$x][0]]))
				{
					$found = FALSE;
					$found_count = 0;
				}
				else
				{
					$c = $ips[$x][3];
					if (($found) && ($blocksize >= 24) && ($found_c != $c))
					{
						$found = FALSE;
						$found_count = 0;
					}
					if (!$found)
					{
						if ($blocksize <= 24)
						{
							if ($ips[$x][4] == 0)
							{
								//error_log("Calling ipcalc here");
								$cmd = 'LANG=C /usr/local/bin/ipcalc -n -b ' . $ips[$x][0] . '/' . $blocksize . ' | grep Network: | cut -d: -f2';
								if (trim(`$cmd`) == $ips[$x][0] . "/" . $blocksize)
								{
									$found = $ips[$x][0];
									$found_c = $c;
								}
							}
						}
						else
						{
							if ($ips[$x][4] % $ipcount == 0)
							{
								$found = $ips[$x][0];
								$found_c = $c;
							}
						}
					}
					if ($found)
					{
						$found_count++;
						if ($found_count == $ipcount)
						{
							$available[] = array($found, $ipblock_id);
							$found = FALSE;
							$found_count = 0;
						}
					}
				}
			}
		}
		return $available;
	}

	function delete_vlan()
	{
		$ima = $GLOBALS['tf']->ima;
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
		if ($ima == 'admin')
		{
			if ($GLOBALS['tf']->variables->request['sure'] != 'yes')
			{
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
				$table->add_field('<select name=sure>' . '<option value=no>No</option>' . '<option value=yes>Yes</option>' . '</select>');
				$table->add_row();
				$table->set_colspan(2);
				$table->add_field($table->make_submit('Delete This Vlan'));
				$table->add_row();
				add_output($table->get_table());
			}
			else
			{
				$query = "select * from vlans where vlans_networks=':$ipblock:'";
				$db->query($query, __LINE__, __FILE__);
				$db->next_record();
				$id = $db->Record['vlans_id'];
				$query = "delete from vlans where vlans_networks=':$ipblock:'";
				$db->query($query, __LINE__, __FILE__);
				$query = "update ips2 set ips_vlan=0 where ips_vlan='$id'";
				$db->query($query, __LINE__, __FILE__);
				`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
				if (isset($_REQUEST['httpreferer']))
					$GLOBALS['tf']->redirect($_REQUEST['httpreferer']);
				else
					$GLOBALS['tf']->redirect($table->make_link('index.php', 'choice=none.vlan_manager'));
			}
		}
	}

	function add_vlan()
	{
		//_debug_array(get_ips2('68.168.208.0/20',TRUE));
		$ima = $GLOBALS['tf']->ima;
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			if (!isset($GLOBALS['tf']->variables->request['blocksize']))
			{
				$table = new TFTable;
				$table->set_title('Add New VLAN');
				$table->add_field('Enter Desired Block Size (ie /24)', 'l');
				$table->add_field('/' . $table->make_input('blocksize', '', 5), 'r');
				$table->add_row();
				$table->set_colspan(2);
				$table->add_field($table->make_submit('Proceed To Next Step'));
				$table->add_row();
				add_output($table->get_table()) . '<br>';

				$t = new TFTable;
				$t->set_title('VLAN Cheat Sheet');
				$t->add_field('Block Size');
				$t->add_field('Total IPs');
				$t->add_field('Available IPs');
				$t->add_row();

				$t->add_field('/32');
				$t->add_field('3');
				$t->add_field('1');
				$t->add_row();

				$t->add_field('/30');
				$t->add_field('4');
				$t->add_field('2');
				$t->add_row();

				$t->add_field('/29');
				$t->add_field('8');
				$t->add_field('6');
				$t->add_row();

				$t->add_field('/28');
				$t->add_field('16');
				$t->add_field('14');
				$t->add_row();

				$t->add_field('/27');
				$t->add_field('32');
				$t->add_field('30');
				$t->add_row();

				$t->add_field('/26');
				$t->add_field('64');
				$t->add_field('62');
				$t->add_row();

				$t->add_field('/25');
				$t->add_field('128');
				$t->add_field('126');
				$t->add_row();

				$t->add_field('/24');
				$t->add_field('256');
				$t->add_field('254');
				$t->add_row();

				add_output($t->get_table());
			}
			else
			{
				$blocksize = str_replace('/', '', $GLOBALS['tf']->variables->request['blocksize']);
				$blocks = available_ipblocks($blocksize);
				if (!isset($GLOBALS['tf']->variables->request['ipaddress']))
				{
					// ok we have blocksize now need to determine what vlans are possible
					$ipcount = get_ipcount_from_netmask($blocksize);
					$table = new TFTable;
					$table->set_title('Add New VLAN');
					$table->add_hidden('blocksize', $blocksize);
					$table->add_field('Block Size', 'l');
					$table->add_field('/' . $blocksize, 'r');
					$table->add_row();
					$table->add_field('Total IPs', 'l');
					$table->add_field($ipcount + 2, 'r');
					$table->add_row();
					$table->add_field('Usable IPs', 'l');
					$table->add_field($ipcount, 'r');
					$table->add_row();
					if (sizeof($blocks) > 0)
					{
						$table->add_field('Enter Desired IP Block', 'l');
						$sel = '<select name=ipaddress>';
						for ($x = 0; $x < sizeof($blocks); $x++)
						{
							$sel .= '<option value=' . $blocks[$x][0] . '>' . $blocks[$x][0] . '/' . $blocksize . '</option>';
						}
						$sel .= '</select>';
						$table->add_field($sel, 'r');
						//$table->add_field($table->make_input('ipaddress', '', 20), 'r');
						$table->add_row();
						$select = get_select_ports();
						$table->add_field('Select Switch/Port(s) that the VLan is on', 'l');
						$table->add_field($select, 'r');
						$table->add_row();
						$table->set_colspan(2);
						$table->add_field('Vlan Comment', 'c');
						$table->add_row();
						$table->set_colspan(2);
						$table->add_field('<textarea rows=7 cols=25 name=comment></textarea>', 'c');
						$table->add_row();
						$table->set_colspan(2);
						$table->add_field($table->make_submit('Proceed To Next Step'));
						$table->add_row();
					}
					else
					{
						$table->set_colspan(2);
						$table->add_field('<b>No Usable Blocks Found Matching This Block Size</b>');
						$table->add_row();
					}
					add_output($table->get_table());
				}
				else
				{
					$ports = $GLOBALS['tf']->variables->request['ports'];
					if (sizeof($ports) > 0)
					{
						$ipaddress = $GLOBALS['tf']->variables->request['ipaddress'];
						$found = false;
						for ($x = 0; $x < sizeof($blocks); $x++)
						{
							if ($blocks[$x][0] == $ipaddress)
							{
								$block = $blocks[$x][1];
								$found = true;
							}
						}
						$ips = get_ips($ipaddress . '/' . $blocksize, TRUE);
						$db->query("select * from ips2 left join vlans on ips_vlan=vlans_id where ips_ip in ('" . implode("', '", $ips) . "') and vlans_id is not NULL");
						if ($db->num_rows() > 0)
						{
							$found = false;
							while ($db->next_record())
							{
								echo "Conflicting IP: " . $db->Record['ips_ip'] . '<br>';
							}
						}
						if (!$found)
						{
							echo 'I think this vlan already exists';
							exit;
						}
						$comment = $GLOBALS['tf']->variables->request['comment'];
						$ports = ':' . implode(':', $ports) . ':';
						$query = "insert into vlans values (NULL, $block, ':$ipaddress/$blocksize:', '$ports', '$comment')";
						$db->query($query, __LINE__, __FILE__);
						$vlan = $db->get_last_insert_id('vlans', 'vlans_id');
						$query = "select ips_ip from ips2 where ips_ip in ('" . implode("', '", $ips) . "')";
						$db->query($query, __LINE__, __FILE__);
						$ips2 = array();
						while ($db->next_record())
						{
							$ips2[] = $db->Record['ips_ip'];
						}
						for ($x = 0; $x < sizeof($ips); $x++)
						{
							if (($x == 0) || ($x == (sizeof($ips) - 1)))
							{
								$reserved = 1;
							}
							else
							{
								$reserved = 0;
							}
							if (in_array($ips[$x], $ips2))
							{
								$query = "update ips2 set ips_vlan='$vlan', ips_serverid=0, ips_group=0, ips_reserved='$reserved' where ips_ip='$ips[$x]'";
							}
							else
							{
								$query = "insert into ips2 values ('$ips[$x]', '$vlan', 0, 0, $reserved)";
							}
							$db->query($query, __LINE__, __FILE__);
						}
						add_output('VLAN Created');
						`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
						$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
					}
					else
					{
						add_output('You must select at least one port');
					}
				}
			}
		}
	}

	function portless_vlans()
	{
		$db = $GLOBALS['admin_dbh'];
		$db->query("select * from vlans where vlans_ports='::' order by vlans_networks", __LINE__, __FILE__);
		$table = new TFTable;
		$table->set_title('Port-less VLAN List' . pdf_link('choice=ip.portless_vlans'));
		if ($db->num_rows() > 0)
		{
			$table->add_field('VLAN');
			$table->set_bgcolor(2);
			$table->add_field('Comment');
			$table->set_bgcolor(2);
			$table->add_field('Options');
			$table->add_row();
			$table->alternate_rows();
			while ($db->next_record())
			{
				$ipblock = str_replace(':', '', $db->Record['vlans_networks']);
				$table->add_field($ipblock, 'l');
				$table->add_field($db->Record['vlans_comment']);
				$table->add_field($table->make_link('choice=ip.vlan_port_manager&ipblock=' . $ipblock, 'Configure Port(s)'));
				$table->add_row();
			}
		}
		else
		{
			$table->add_field('No VLANs without ports assigned to them');
			$table->add_row();
		}
		if ($GLOBALS['tf']->variables->request['pdf'] == 1)
		{
			$table->get_pdf();
		}
		add_output($table->get_table());
	}

	function vlan_edit_port()
	{
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		$table = new TFTable;
		$table->set_title('VLAN Edit Port Assignments');
		$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
		$table->add_hidden('ipblock', $ipblock);
		$table->add_field('IP Block');
		$table->add_field($ipblock);
		$table->add_row();
		$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'");
		$db->next_record();
		$networks = get_networks($db->Record['vlans_networks'], $db->Record['vlans_id'], $db->Record['vlans_comment'], $db->Record['vlans_ports']);
		$networksize = sizeof($networks);
		$rows = array();
		for ($x = 0; $x < $networksize; $x++)
		{
			$row = array();
			$network = $networks[$x]['network'];
			$vlan = $networks[$x]['vlan'];
			if ($networks[$x]['comment'])
			{
				$comment = $networks[$x]['comment'];
			}
			else
			{
				$comment = 'not set';
			}
			$portdata = explode(':', $networks[$x]['ports']);
			$ports = array();
			$searchs = array();
			$servers = array();
			$portdatasize = sizeof($portdata);
			for ($y = 0; $y < $portdatasize; $y++)
			{
				if ($portdata[$y] != '')
				{
					list($switch, $port, $blade, $justport) = parse_vlan_ports($portdata[$y]);
					$ports[] = $portdata[$y];
					$searchs[] = "(servers_switch='$switch' and servers_slot='$port')";
				}
			}
		}
		if (!isset($GLOBALS['tf']->variables->request['ports']))
		{
			$select = get_select_ports($ports, 40);
			$table->add_hidden('edit_port', 1);
			$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
			$table->set_colspan(2);
			$table->add_field($select . '<br>' . $table->make_submit('Set Port(s)'));
			$editport = TRUE;
		}
		else
		{
			$ports = ':' . implode(':', $GLOBALS['tf']->variables->request['ports']) . ':';
			$query = "update vlans set vlans_ports='$ports' where vlans_networks like '%:$network:%' and vlans_id='$vlan'";
			$db2->query($query, __LINE__, __FILE__);
			`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
			$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
		}
		$table->add_row();
		add_output($table->get_table());
	}

	function vlan_manager()
	{
		//			$smarty = new TFSmarty;
		//			$smarty->debugging = true;
		//			$smarty->assign('sortcol', 1);
		//			$smarty->assign('sortdir', 0);
		//			$smarty->assign('textextraction', "'complex'");
		$ima = $GLOBALS['tf']->ima;
		$choice = $GLOBALS['tf']->variables->request['choice'];
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			if (isset($GLOBALS['tf']->variables->request['order']) && $GLOBALS['tf']->variables->request['order'] == 'id')
				$order = 'vlans_id';
			else
				$order = 'vlans_networks';
			$table = new TFTable;
			$table->set_title('VLan Manager ' . pdf_link('choice=' . $choice . '&order=' . $order));
			$table->set_options('width="100%"');
			/*			$title = array(
			$table->make_link('choice=' . $choice . '&order=id', 'VLAN'),
			$table->make_link('choice=' . $choice . '&order=ip', 'Network'),
			'Comments',
			'Port(s)'
			);
			*/
			//				'Server(s)'
			//			$smarty->assign('table_header', $title);

			$table->set_bgcolor(3);
			//			$table->add_field($table->make_link('choice=' . $choice . '&order=id', 'VLAN'));
			$table->add_field($table->make_link('choice=' . $choice . '&order=ip', 'Network'));
			$table->add_field('Comment');
			$table->add_field('Port(s)');
			//			$table->set_colspan(3);
			$table->add_field('Options');
			//			$table->add_field('Server(s)');
			$table->add_row();
			$table->alternate_rows();
			$total_ips = 0;
			$used_ips = 0;
			// get ip block(s)
			$networks = array();
			$db->query("select * from ipblocks order by ipblocks_network", __LINE__, __FILE__);
			while ($db->next_record())
			{
				$ipinfo = ipcalc($db->Record['ipblocks_network']);
				$network_id = $db->Record['ipblocks_id'];
				$total_ips += $ipinfo['hosts'];
				$db2->query("select * from vlans where vlans_block='$network_id' order by $order", __LINE__, __FILE__);
				while ($db2->next_record())
				{
					$network = get_networks($db2->Record['vlans_networks'], $db2->Record['vlans_id'], $db2->Record['vlans_comment'], $db2->Record['vlans_ports']);
					$networks = array_merge($networks, $network);
				}
			}
			//_debug_array($networks);
			//			$networks_info = ipcalc_array($networks);
			//debug_array($networks_info);
			//			$keys = array_keys($networks_info);
			//			$keysize = sizeof($keys);
			//			for ($x = 0; $x < $keysize; $x++)
			//			{
			//				$used_ips += $networks_info[$keys[$x]]['hosts'];
			//			}
			$db->query("select count(*) from ips2 where ips_vlan > 0");
			$db->next_record();
			$used_ips = $db->f(0);
			$networksize = sizeof($networks);
			$rows = array();
			for ($x = 0; $x < $networksize; $x++)
			{
				$row = array();
				$network = $networks[$x]['network'];
				$vlan = $networks[$x]['vlan'];
				if ($networks[$x]['comment'])
				{
					$comment = $networks[$x]['comment'];
				}
				else
				{
					$comment = 'not set';
				}
				$portdata = explode(':', $networks[$x]['ports']);
				$ports = array();
				$searchs = array();
				$servers = array();
				$portdatasize = sizeof($portdata);
				for ($y = 0; $y < $portdatasize; $y++)
				{
					if ($portdata[$y] != '')
					{
						list($switch, $port, $blade, $justport) = parse_vlan_ports($portdata[$y]);
						$ports[] = $portdata[$y];
						$searchs[] = "(servers_switch='$switch' and servers_slot='$port')";
					}
				}
				if (sizeof($searchs))
				{
					$query = "select servers_hostname from servers where " . implode(' or ', $searchs);
					$db2->query($query, __LINE__, __FILE__);
					while ($db2->next_record())
					{
						$servers[] = $db2->Record['servers_hostname'];
					}
				}
				//					$network_info = $networks_info[$network];
				/*
				$row = array(
				$vlan,
				$network . ' ' .
				$table->make_link('choice=ip.ipblock_viewer&amp;ipblock=' . $network, '(?)') . ' ' .
				$table->make_link('choice=ip.add_ips_to_server&amp;ipblock=' . $network, '(+IP)') . ' ' .
				$table->make_link('choice=ip.delete_vlan&amp;ipblock=' . $network, '(-)'),
				$table->make_link('choice=ip.edit_vlan_comment&amp;ipblock=' . $network, $comment)
				);
				*/
				//					$table->add_field($vlan);

				$table->add_field($network, 'l');
				$table->add_field($table->make_link('choice=ip.edit_vlan_comment&amp;ipblock=' . $network, $comment), 'c');

				$editport = FALSE;
				$editserver = FALSE;
				if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network)
				{
					if (isset($GLOBALS['tf']->variables->request['edit_port']))
					{
						if (!isset($GLOBALS['tf']->variables->request['ports']))
						{
							$select = get_select_ports($ports);
							$table->add_hidden('edit_port', 1);
							$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
							//								$row[] = $select . '<br>' . $table->make_submit('Set Port(s)');
							$table->add_field($select . '<br>' . $table->make_submit('Set Port(s)'));
							$editport = TRUE;
						}
						else
						{
							$ports = ':' . implode(':', $GLOBALS['tf']->variables->request['ports']) . ':';
							$db2->query("update vlans set vlans_ports='$ports' where vlans_networks like '%:$network:%' and vlans_id='$vlan'", __LINE__, __FILE__);
							`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
							$ports = $GLOBALS['tf']->variables->request['ports'];
						}
					}
				}
				if (sizeof($ports) == 0)
				{
					$ports[] = '--';
				}
				if (!$editport)
				{
					$portsize = sizeof($ports);
					for ($y = 0; $y < $portsize; $y++)
					{
						if (!(strpos($ports[$y], '/') === false))
						{
							list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
							$ports[$y] = get_switch_name($switch, TRUE) . '/' . $port;
						}
					}
					$table->add_field($table->make_link('choice=ip.vlan_edit_port&amp;ipblock=' . $network, implode(', ', $ports)), 'l');
					//						$row[] = $table->make_link('choice=ip.vlan_edit_port=1&amp;ipblock=' . $network, implode(', ', $ports));
					//						$row[] = $table->make_link('choice=ip.vlan_manager&amp;edit_port=1&amp;ipblock=' . $network, implode(', ', $ports));
					//						$table->add_field($table->make_link('choice=ip.vlan_manager&amp;edit_port=1&amp;ipblock=' . $network, implode(', ', $ports)));
				}
				$table->add_field($table->make_link('choice=ip.ipblock_viewer&amp;ipblock=' . $network, '(?)') . $table->make_link('choice=ip.add_ips_to_server&amp;ipblock=' . $network, '(+IP)') . $table->make_link('choice=ip.delete_vlan&amp;ipblock=' .
					$network, '(-)'), 'c');
				if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network)
				{
					if (isset($GLOBALS['tf']->variables->request['edit_server']))
					{
						if ($ports[0] != '--')
						{
							if (!isset($GLOBALS['tf']->variables->request['port_0']))
							{
								$out = '';
								for ($y = 0; $y < sizeof($ports); $y++)
								{
									if (sizeof($ports) > 1)
									{
										$out .= 'Port ' . $ports[$y] . ': ';
									}
									list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
									$query = "select servers_serverid, servers_hostname from servers where servers_switch='$switch' and servers_slot='$port'";
									$db2->query($query, __LINE__, __FILE__);
									if ($db2->num_rows())
									{
										$db2->next_record();
										$server = $db2->Record['servers_serverid'];
									}
									else
									{
										$server = 0;
									}
									$out .= select_server($server, 'port_' . $y, TRUE);
									if ($y < (sizeof($ports) - 1))
									{
										$out .= '<br>';
									}
								}
								$table->add_hidden('edit_server', 1);
								$table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
								//									$row[] = $out . '<br>' . $table->make_submit('Set Server(s)');
								$table->add_field($out . '<br>' . $table->make_submit('Set Server(s)'));
								$editserver = TRUE;
							}
							else
							{
								$servers = array();
								for ($y = 0; $y < sizeof($ports); $y++)
								{
									$server = $GLOBALS['tf']->variables->request['port_' . $y];
									if ($server != '0')
									{
										$servers[] = $server;
										list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[$y]);
										$query = "update servers set servers_switch='', servers_slot='' where servers_switch='$switch' and servers_slot='$port'";
										$db2->query($query, __LINE__, __FILE__);
										$query = "update servers set servers_switch='$switch', servers_slot='$port' where servers_hostname='$server'";
										$db2->query($query, __LINE__, __FILE__);
									}
								}
							}
						}
						else
						{
							//								$row[] = '<b>You Must First Assign Port(s)</b>';
							$table->add_field('<b>You Must First Assign Port(s)</b>');
							$editserver = TRUE;
						}
					}
				}
				if (sizeof($servers) == 0)
				{
					$servers[] = '--';
				}
				if (!$editserver)
				{
					//						$row[] = $table->make_link('choice=ip.vlan_manager&amp;edit_server=1&amp;ipblock=' . $network, implode(', ', $servers));
					//						$table->add_field($table->make_link('choice=ip.vlan_manager&amp;edit_server=1&amp;ipblock=' . $network, implode(', ', $servers)));
				}
				//					$rows[] = $row;
				$table->add_row();
			}

			//			$smarty->assign('table_rows',$rows);
			//			$table->set_colspan(5);
			//			$table->add_field($smarty->fetch('tablesorter_nopager.tpl'));
			//			$table->add_field($smarty->fetch('tablesorter_nopager.tpl'));
			//			$table->add_row();

			$table->set_colspan(4);
			$table->add_field('Total IPs ' . $total_ips, 'l');
			$table->add_row();
			$table->set_colspan(4);
			$table->add_field('Used IPs ' . $used_ips . ' (' . number_format((($used_ips / $total_ips) * 100), 2) . '%) (Rough Estimate, I can get better numbers if you want)', 'l');
			$table->add_row();
			$table->set_colspan(4);
			$table->add_field('Free IPs ' . ($total_ips - $used_ips) . ' (' . number_format(((($total_ips - $used_ips) / $total_ips) * 100), 2) . '%)', 'l');
			$table->add_row();
			$table->set_colspan(4);
			$table->add_field($table->make_link('choice=ip.add_vlan', 'Add New VLAN') . '   ' . $table->make_link('choice=ip.portless_vlans', 'List Of VLAN Without Port Assignments ') . '   ' . $table->make_link
				('choice=ip.vlan_port_server_manager', 'VLAN Port <-> Server Mapper'));
			$table->add_row();

			//			add_output($smarty->fetch('tablesorter.tpl'));
			add_output($table->get_table());
			if (isset($GLOBALS['tf']->variables->request['pdf']) && $GLOBALS['tf']->variables->request['pdf'] == 1)
			{
				$table->get_pdf();
			}
		}
		else
		{
			add_output('Please contact support regarding adding additional IPs');
		}
	}

	function edit_vlan_comment()
	{
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		if ($ima == 'admin')
		{
			$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
			// get ip block(s)
			$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
			if ($db->num_rows())
			{
				$db->next_record();
				$id = $db->Record['vlans_id'];
				if (!isset($GLOBALS['tf']->variables->request['comment']))
				{
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
				}
				else
				{
					$comment = $GLOBALS['tf']->variables->request['comment'];
					$db->query("update vlans set vlans_comment='$comment' where vlans_id='$id'", __LINE__, __FILE__);
					`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
					$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
				}
			}
		}
	}

	function vlan_port_server_manager()
	{
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			$db->query("select * from vlans order by vlans_ports, vlans_networks", __LINE__, __FILE__);
			$table = new TFTable;
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
			while ($db->next_record())
			{
				$networks = explode(':', $db->Record['vlans_networks']);
				$comment = $db->Record['vlans_comment'];
				$network = $networks[1];
				$ports = explode(':', $db->Record['vlans_ports']);
				if ($ports[1] != '')
				{
					list($switch, $port, $blade, $justport) = parse_vlan_ports($ports[1]);
					if (isset($GLOBALS['tf']->variables->request['vlan_' . $db->Record['vlans_id']]))
					{
						$server = $GLOBALS['tf']->variables->request['vlan_' . $db->Record['vlans_id']];
						if ($server != '0')
						{
							$query = "update servers set servers_switch='', servers_slot='' where servers_switch='$switch' and servers_slot='$port'";
							$db2->query($query, __LINE__, __FILE__);
							$query = "update servers set servers_switch='$switch', servers_slot='$port' where servers_hostname='$server'";
							$db2->query($query, __LINE__, __FILE__);
						}
					}
					$query = "select servers_serverid, servers_hostname from servers where servers_switch='$switch' and servers_slot='$port'";
					$db2->query($query, __LINE__, __FILE__);
					if ($db->num_rows())
					{
						$db2->next_record();
						$server = $db2->Record['servers_serverid'];
					}
					else
					{
						$server = 0;
					}
					$table->add_field($network);
					$table->add_field($comment);
					$table->add_field(get_switch_name($switch));
					$table->add_field($port);
					$table->add_field(select_server($server, 'vlan_' . $db->Record['vlans_id'], TRUE));
					$table->add_row();
				}
			}
			$table->set_colspan(5);
			$table->add_field($table->make_submit('Update These Servers'));
			$table->add_row();
			add_output($table->get_table());
		}
	}

	function vlan_port_manager()
	{
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
			$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
			if (($ipblock == '') || ($db->num_rows() == 0))
			{
				add_output('Invalid IP Block');
			}
			else
			{
				$db->next_record();
				$vlan_id = $db->Record['vlans_id'];
				if (!isset($GLOBALS['tf']->variables->request['ports']))
				{
					$ports = explode(':', $db->Record['vlans_ports']);
					$select = get_select_ports($ports);
					$table = new TFTable;
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
				}
				else
				{
					$ports = ':' . implode(':', $GLOBALS['tf']->variables->request['ports']) . ':';
					$db2->query("update vlans set vlans_ports='$ports' where vlans_networks like '%:$ipblock:%' and vlans_id='$vlan_id'", __LINE__, __FILE__);
					`cd /home/admin/troublefree/tempscripts; ./update_switch_ports.php >/dev/null 2>&1`;
					$GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock=' . $ipblock));
				}
			}
		}
	}

	function ipblock_viewer()
	{
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
			$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
			while ($db->next_record())
			{
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
				$db2->query("select * from ips2 where ips_ip in ('" . implode("', '", $ips) . "') and ips_serverid !=0 order by ips_serverid, ips_ip", __LINE__, __FILE__);
				$usedips = $db2->num_rows();
				$table->set_colspan(2);
				$table->add_field('Used IPs');
				$table->set_colspan(2);
				$table->add_field($usedips . ' (' . number_format((($usedips / $ipinfo['hosts']) * 100), 2) . '%)', 'r');
				$table->add_row();
				$table->set_colspan(2);
				$table->add_field('Free IPs');
				$table->set_colspan(2);
				$table->add_field(($ipinfo['hosts'] - $usedips) . ' (' . number_format(((($ipinfo['hosts'] - $usedips) / $ipinfo['hosts']) * 100), 2) . '%)', 'r');
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
				if ($usedips > 0)
				{
					while ($db2->next_record())
					{
						$server = $GLOBALS['tf']->get_server($db2->Record['ips_serverid']);
						$table->add_field($server['servers_hostname']);
						$table->add_field($db2->Record['ips_ip']);
						$table->set_colspan(2);
						$table->add_field('Delete');
						$table->add_row();
					}
				}
				$table->alternate_rows();
				$table->set_colspan(4);
				$table->add_field($table->make_link('choice=ip.add_ips_to_server&amp;ipblock=' . $ipblock, 'Add IP(s) To Server'));
				$table->add_row();
				$table->set_colspan(4);
				$table->add_field($table->make_link('choice=ip.vlan_port_manager&amp;ipblock=' . $ipblock, 'Manage Ports Connected To VLan'));
				$table->add_row();
				add_output($table->get_table());

			}
		}
		else
		{
			add_output('Please contact support regarding adding additional IPs');
		}
	}

	function add_ips_to_server()
	{
		$ima = $GLOBALS['tf']->ima;
		$db = $GLOBALS['admin_dbh'];
		$db2 = $db;
		if ($ima == 'admin')
		{
			$ipblock = $GLOBALS['tf']->variables->request['ipblock'];
			if (!isset($GLOBALS['tf']->variables->request['ips']))
			{
				$db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
				while ($db->next_record())
				{
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
					$db2->query("select * from ips2 where ips_ip in ('" . implode("', '", $ips) . "') and ips_serverid=0", __LINE__, __FILE__);
					while ($db2->next_record())
					{
						$sel .= '<option value=' . $db2->Record['ips_ip'] . '>' . $db2->Record['ips_ip'] . '</option>';
					}
					$sel .= '</select>';
					$table->add_field($sel, 'r');
					$table->add_row();
					$table->set_colspan(2);
					$table->add_field($table->make_submit('Add These IP(s)'));
					$table->add_row();
					add_output($table->get_table());
				}
			}
			else
			{
				$server = $GLOBALS['tf']->variables->request['server'];
				$server_info = $GLOBALS['tf']->get_server($server);
				$group = get_first_group($server_info['servers_group']);
				if ($server_info)
				{
					$ips = $GLOBALS['tf']->variables->request['ips'];
					for ($x = 0; $x < sizeof($ips); $x++)
					{
						$db->query("update ips2 set ips_group='$group', ips_serverid='$server_info[servers_serverid]' where ips_ip='$ips[$x]'", __LINE__, __FILE__);
					}
					add_output("IP(s) Successfully Assigned To $server<br>");
					add_output($GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock=' . $ipblock), 1));
				}
				else
				{
					add_output('Invalid Server (' . $server . ')');
				}
			}

		}
		else
		{
			add_output('Please contact support regarding adding additional IPs');
		}
	}

	function add_ips()
	{
		global $groupinfo;
		$db = $GLOBALS['admin_dbh'];
		$color1 = COLOR1;
		$color3 = COLOR2;
		$color2 = COLOR3;
		$choice = $GLOBALS['tf']->variables->request['choice'];
		if (!isset($GLOBALS['tf']->variables->request['ipclass']))
		{
			add_output('<TABLE>' . '<TR bgcolor="' . $color3 . '" align=center><TD colspan=2>IP Address Addition Menu</TD></TR>' . '<TR bgcolor="' . $color1 .
				'" align=center><TD colspan=2>Adding A Single Class C</TD></TR>' . '<form enctype="multipart/form-data" method="post" action="' . $GLOBALS['tf']->link('index.php') . '">' .
				"<input type=hidden name=choice value=$choice>" . '<TR><TD bgcolor="' . $color2 . '">' . 'Enter First 3 Set Of IPs In The Class C (ie 216.74.109):' . '</TD><TD bgcolor="' . $color3 . '">' .
				'<input type=text name=ipclass>' . '</TD></TR>' . '<TR bgcolor="' . $color1 . '" align=center><TD colspan=2>' . '<input type=submit value="Add This Class C">' . '</TD></TR>' . '</FORM>' .
				'<TR><TD colspan=2>&nbsp;</TD></TR>' . '<TR bgcolor="' . $color1 . '" align=center><TD colspan=2>I Want To Add Less Than A Class C</TD></TR>' .
				'<form enctype="multipart/form-data" method="post" action="' . $GLOBALS['tf']->link('index.php') . '">' . "<input type=hidden name=choice value=$choice>" . '<TR><TD bgcolor="' . $color2 . '">' .
				'Enter First 3 Set Of IPs In The Class C (ie 216.74.109):' . '</TD><TD bgcolor="' . $color3 . '">' . '<input type=text name=ipclass>' . '</TD></TR>' . '<TR><TD bgcolor="' . $color2 . '">' .
				'Enter Lowest IP In The Range (ie 2):' . '</TD><TD bgcolor="' . $color3 . '">' . '<input type=text name=iplow>' . '</TD></TR>' . '<TR><TD bgcolor="' . $color2 . '">' .
				'Enter Highest IP In The Range (ie 254):' . '</TD><TD bgcolor="' . $color3 . '">' . '<input type=text name=iphigh>' . '</TD></TR>' . '<TR bgcolor="' . $color1 . '" align=center><TD colspan=2>' .
				'<input type=submit value="Add This Range">' . '</TD></TR>' . '</FORM>' . '</TABLE>');
		}
		else
		{
			$ipclass = $GLOBALS['tf']->variables->request['ipclass'];
			add_output('Adding IPs: ');
			if (!isset($GLOBALS['tf']->variables->request['iplow']))
			{
				$iplow = 2;
			}
			else
			{
				$iplow = $GLOBALS['tf']->variables->request['iplow'];
			}

			if (!isset($GLOBALS['tf']->variables->request['iphigh']))
			{
				$iphigh = 254;
			}
			else
			{
				$iphigh = $GLOBALS['tf']->variables->request['iphigh'];
			}

			$new_ips = 0;
			for ($num = $iplow; $num < ($iphigh + 1); $num++)
			{
				$ip = $ipclass . '.' . $num;
				$db->query("select * from ips where ips_ip='$ip'", __LINE__, __FILE__);
				if ($db->num_rows() == 0)
				{
					if ($GLOBALS['tf']->accounts->data['demo'] == 1)
					{
						add_output("No Updates In Demo Mode");
					}
					else
					{
						$db->query("insert into ips values('$ip', '0', '$groupinfo[account_id]')", __LINE__, __FILE__);
						$new_ips++;
						add_output('+');
					}
				}
				else
				{
					add_output('-');
				}
			}
			add_output('done<br>');
			add_output('<br>');
			add_output($new_ips . 'New IPs Added<br>');
			add_output('<br>');
			add_output('- = IP Already In Database<br>');
			add_output('+ = IP New To Database<br>');
			add_output('<br>');
		}
	}

	function unblock_ip_do()
	{
		if (!$GLOBALS['tf']->variables->request['server'])
		{
			select_server();
		}
		else
		{
			if (isset($GLOBALS['tf']->variables->request['IPBLOCK']))
			{
				$ip = $GLOBALS['tf']->variables->request['IPBLOCK'];
				$server = $GLOBALS['tf']->variables->request['server'];
				$GLOBALS['tf']->session->appsession('server', $server);
				if (valid_server($server))
				{
					if ($GLOBALS['tf']->accounts->data['demo'] == 1)
					{
						add_output("No Updates In Demo Mode");
					}
					else
					{
						$cmd = '';
						if (ereg("^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}$", $ip))
						{
							$cmd = '/sbin/route del -host ' . $ip;
						}
						elseif (ereg("^[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\-[0-9]{1,3}$", $ip))
						{
							$front = trim(`echo "$ip" | cut -d- -f1`);
							$start = trim(`echo "$front" | cut -d. -f4`);
							$ugh = trim(`echo "$ip" | cut -d. -f1-3`);
							$end = trim(`echo "$ip" | cut -d- -f2`);
							$cmd = '/sbin/route add -del ' . $front . '; \n';
							for ($x = $start; $x <= $end; $x++)
							{
								$cmd .= '/sbin/route del -host ' . $ugh . '.' . $x . '; \n';
							}
						}
						else
						{
							add_output('Error: ' . $ip . ' is an incorrect format for an ip address.');
							return 0;
						}
						$result = ssh($server, $cmd);
						$table = new TFTable;
						$table->set_title('Unblocking IP ' . $server);
						if ($result == '')
						{
							$table->add_field('IP ' . $ip . ' now unblocked.');
							$table->add_row();
						}
						else
						{
							$table->add_field('There appears to have been an error');
							$table->add_row();
							$table->add_field($result);
							$table->add_row();
						}
						add_output($table->get_table());
						add_output('<br>');
						previous_url($server);
					}
				}
			}
			else
			{
				add_output('Error: Variable IPBLOCK not passed.');
			}
		}
	}

	/**
	 * @param $hostname
	 * @return string
	 */
	function ips_hostname($hostname)
	{
		$db = clone $GLOBALS['admin_dbh'];
		$db2 = clone $db;
		$comment = $db->real_escape($hostname);
		$query = "select * from vlans where vlans_comment='$comment'";
		$db->query($query);
		$out = '';
		if ($db->num_rows() == 0)
		{
			$query = "select * from vlans where vlans_comment like '%$comment%'";
			$db->query($query);
		}
		if ($db->num_rows() > 0)
		{
			while ($db->next_record(MYSQL_ASSOC))
			{
				//		$db->Record['vlans_id'];
				$parts = explode(':', $db->Record['vlans_ports']);
				for ($x = 0; $x < sizeof($parts); $x++)
				{
					if (strpos($parts[$x], '/'))
					{
						list($switch, $port, $blade, $justport) = parse_vlan_ports($parts[$x]);
						$parts[$x] = get_switch_name($switch, TRUE) . '/' . $port;
					}
				}
				$vlan = $db->Record['vlans_id'];
				$query = "select graph_id from switchports where switch='$switch' and port='$port'";
				//echo $query;
				$db2->query($query);
				if ($db2->num_rows() > 0)
				{
					$db2->next_record();
					//print_r($db2->Record);
					$graph_id = $db2->Record['graph_id'];
				}
				else
				{
					$query = "select graph_id from switchports where switch='$switch' and (port='$port' || justport='$justport')";
					//echo $query;
					$db2->query($query);
					if ($db2->num_rows() > 0)
					{
						$db2->next_record();
						//print_r($db2->Record);
						$graph_id = $db2->Record['graph_id'];
					}
					else
					{
						$graph_id = 0;
					}
				}
				$db->Record['vlans_ports'] = implode(':', $parts);
				$out .= $db->Record['vlans_comment'] . "\n" . $db->Record['vlans_networks'] . "\n" . $db->Record['vlans_ports'] . "\n" . $graph_id . "\n";
				;
			}
		}
		else
		{
			$out .= "No vlans found\n";
		}
		return trim($out);

	}
?>
