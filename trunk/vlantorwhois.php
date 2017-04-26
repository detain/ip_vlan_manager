<?php
	// Load Various Functions & Connect to mysql database
	include('../../../include/functions.inc.php');
	include('ip.functions.inc.php');
	$db = get_module_db('default');
	$db4 = $db;
	$db_innertell = get_module_db('innertell');
	$db_mb            = get_module_db('mb');
	ob_end_flush();


// set extra to a:1:{s:13:"private_whois";s:1:"1";}
/**
 * @param $extra
 * @return array|mixed
 */
function get_extra($extra)
{
    if ($extra == '')
        $extra = [];
    else
        $extra = unserialize($extra);
    return $extra;
}

/**
 * @param $extra
 * @return array|string
 */
function put_extra($extra)
{
    if ($extra == '')
        $extra = [];
    $extra = serialize($extra);
    return $extra;
}

	$db->query('select * from ipblocks');
	$ipblocks = [];
	while ($db->next_record())
	{
		$ipblocks[$db->Record['ipblocks_id']] = $db->Record['ipblocks_network'];
	}

	$db->query("select * from vlans left join ipblocks on ipblocks_id=vlans_block where vlans_comment != 'REUSE' and vlans_id is not null");
	$total = 0;
	while ($db->next_record())
	{
		$found = false;
		if (!isset($ipblocks[$db->Record['vlans_block']]))
		{
			error_log("Skipping VLAN {$db->Record['vlans_id']}, probably not our IPS");
			continue;
		}
		else
		{
			$ipblock = $ipblocks[$db->Record['vlans_block']];
		}
		$ipblockdata = explode('/', $ipblock);
		$ipblock_ip = $ipblockdata[0];
		$ipblock_size = $ipblockdata[1];
		$vlan = str_replace(':', '', $db->Record['vlans_networks']);
		$vlandata = explode('/', $vlan);
		$network_info = ipcalc($vlan);
		$maxip = $network_info['broadcast'];
		$ip = $vlandata[0];
		$size = $vlandata[1];
		$total += trim(`/usr/local/bin/ipcalc -n $ip/$size | grep Hosts | cut -d" " -f2`);
		$cmds = '';
		$server = trim(str_replace(
array('(FIXME Port Unknown)', 'FIXME PORT WRONG', 'FIX ME PORT WRONG', 'reuse', 'append', '[ip transit]'),
array('', '', '', '', '', ''),
ereg_replace('\(.*\)', '', strtolower($db->Record['vlans_comment']))));
//echo "\nServer: $server :";
		echo "echo \"VLAN $vlan SERVER $server\";\n";
		if (preg_match("/^mb(\d)*$/", $server))
		{
/*
select * from users where id=2311;
+----------------+---------+---------+-------+--------+----------+---------------------------+--------------------+------+---------------------+-------------+----------+------+---------------+
| phone          | zipcode | country | state | city   | address2 | address1                  | company            | id   | username            | name        | password | type | modernbill_id |
+----------------+---------+---------+-------+--------+----------+---------------------------+--------------------+------+---------------------+-------------+----------+------+---------------+
| (559) 892-0902 | 93720   | usa     | ca    | fresno |          | 7969 N Blackstone Av #248 | prominent upstairs | 2311 | greg@truecanyon.com | greg lontok | wide12   | NULL |          2427 |
*/
			$db_innertell->query("select * from users where id='" . str_replace('mb', '', $server) . "'");
			$db_innertell->next_record();
				$data = $db_innertell->Record;
			$extra = get_extra($data['extra']);
				if ($data['address1'] == '')
				{
					$data['address1'] = 'na';
				}
				if ($data['city'] == '')
				{
					$data['city'] = 'na';
				}
				if ($data['state'] == '')
				{
					$data['state'] = 'na';
				}
				if ($data['country'] == '')
				{
					$data['country'] = 'US';
				}
				if (trim($data['company']) == '')
				{
					$org = $data['name'];
				}
				else
				{
					$org = $data['company'];
				}
				if (trim($org) == '')
				{
					$org = 'Account' . $data['id'];
				}
				if ($extra['private_whois'] == 1)
				{
//					$org = 'Private Customer';
//					$data['address1'] = 'Private Residence';

				}
				$found = true;
				$cmds .= 'cd /opt/rwhoisd/etc/rwhoisd/net-' . $ipblock_ip . '-' . $ipblock_size . ';\n'
				. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
				. 'Auth-Area: ' . $ipblock . '\n'
				. 'Org-Name: ' . trim($org) . '\n'
				. 'Street-Address: ' . trim($data['address1']) . '\n'
				. 'City: ' . $data['city'] . '\n'
				. 'State: ' . $data['state'] . '\n'
				. 'Postal-Code: ' . $data['zip'] . '\n'
				. 'Country-Code: ' . $data['country'] . '\n'
				. 'Phone: ' . trim($data['phone']) . '\n'
				. 'Created: 20050101\n'
				. 'Updated: ' . date('Ymd') . '" > data/org/' . $data['id'] . '.txt;\n'
				. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
				. 'Auth-Area: ' . $ipblock . '\n'
				. 'Network-Name: INTSRV-' . $ip . '\n'
				. 'IP-Network: ' . $ip . '/' . $size . '\n'
				. 'Org-Name: ' . trim($org) . '\n'
				. 'Street-Address: ' . $data['address1'] . '\n'
				. 'City: ' . $data['city'] . '\n'
				. 'State: ' . $data['state'] . '\n'
				. 'Postal-Code: ' . $data['zip'] . '\n'
				. 'Country-Code: ' . $data['country'] . '\n'
				. 'Created: 20050101\n'
				. 'Updated: ' . date('Ymd') . '\n'
				. 'Updated-By: abuse@interserver.net" > data/network/' . $ip . '-' . $size . '.txt;\n';
		}
		else
		{
				$query = "select id, username, date from servers where server_hostname like '%$server%' or server_hostname='$server'";
				$db->query($query);
//echo $db->num_rows() . "|";
				$dparts = explode('.', $server);
				$dsize = sizeof($dparts);
				echo "echo \"Query: $query (Rows: " . $db->num_rows() . ")\";\n";
				if (($db->num_rows() == 0) && ($dsize > 2))
				{
					$server = $dparts[$dsize - 2] . '.' . $dparts[$dsize - 1];
					$db->query("select id, username, date from servers where server_hostname like '%$server%' or server_hostname='$server'");
					$drows = $db->num_rows();
//					`echo "$server:$drows" >&2`;
				}
				if ($db->num_rows() > 0)
				{
					$db->next_record();
					$serverinfo = $db->Record;

						$db_innertell->query("select * from users where username='" . $serverinfo['username'] . "'");
						if ($db_innertell->num_rows() == 0)
						{
							$db_innertell->query('select * from users where id=9');
						}
						$db_innertell->next_record();
						$data = $db_innertell->Record;
						$extra = get_extra($data['extra']);

					$query = "select * from client_info where client_email='" . $serverinfo['username'] . "'";
					$db_mb->query($query);
					if ($server['username'] == 'services@expressvpn.com')
					{
						echo $query;
					}
					echo "echo \"Query: $query (Rows: " . $db_mb->num_rows() . ")\";\n";
					if ($db_mb->num_rows() > 0)
					{
						$db_mb->next_record();
						$data = $db_mb->Record;
					if ($server['username'] == 'services@expressvpn.com')
					{
						echo $query;
						print_r($data);
					}
						if ($data['client_address'] == '')
						{
							$data['client_address'] = 'na';
						}
						if ($data['client_city'] == '')
						{
							$data['client_city'] = 'na';
						}
						if ($data['client_state'] == '')
						{
							$data['client_state'] = 'na';
						}
						if ($data['client_country'] == '')
						{
							$data['client_country'] = 'US';
						}
						if (trim($data['client_company']) == '')
						{
							$org = $data['client_fname'] . ' ' . $data['client_lname'];
						}
						else
						{
							$org = $data['client_company'];
						}
						if (trim($org) == '')
						{
							$org = 'Account' . $data['client_id'];
						}
						if ((isset($extra['private_whois']) && $extra['private_whois'] == 1) || $data['client_field_10'] == 1)
						{
//							$org = 'Private Customer';
//							$data['address1'] = 'Private Residence';
//							$data['client_address'] = 'Private Residence';
						}
						$found = true;
						$cmds .= 'cd /opt/rwhoisd/etc/rwhoisd/net-' . $ipblock_ip . '-' . $ipblock_size . ';\n'
						. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
						. 'Auth-Area: ' . $ipblock . '\n'
						. 'Org-Name: ' . trim($org) . '\n'
						. 'Street-Address: ' . trim($data['client_address']) . '\n'
						. 'City: ' . trim($data['client_city']) . '\n'
						. 'State: ' . trim($data['client_state']) . '\n'
						. 'Postal-Code: ' . trim($data['client_zip']) . '\n'
						. 'Country-Code: ' . trim($data['client_country']) . '\n'
						. 'Phone: ' . trim($data['client_phone1']) . '\n'
						. 'Created: ' . date('Ymd', $serverinfo['date']) . '\n'
						. 'Updated: ' . date('Ymd') . '" > data/org/' . trim($data['client_id']) . '.txt;\n'
						. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
						. 'Auth-Area: ' . $ipblock . '\n'
						. 'Network-Name: INTSRV-' . $ip . '\n'
						. 'IP-Network: ' . $ip . '/' . $size . '\n'
						. 'Org-Name: ' . trim($org) . '\n'
						. 'Street-Address: ' . trim($data['client_address']) . '\n'
						. 'City: ' . trim($data['client_city']) . '\n'
						. 'State: ' . trim($data['client_state']) . '\n'
						. 'Postal-Code: ' . trim($data['client_zip']) . '\n'
						. 'Country-Code: ' . trim($data['client_country']) . '\n'
						. 'Created: ' . date('Ymd', $serverinfo['date']) . '\n'
						. 'Updated: ' . date('Ymd') . '\n'
						. 'Updated-By: abuse@interserver.net" > data/network/' . $ip . '-' . $size . '.txt;\n';
					}
					else
					{
						$db_innertell->query("select * from users where username='" . $serverinfo['username'] . "'");
						if ($db_innertell->num_rows() == 0)
						{
							$db_innertell->query('select * from users where id=9');
						}
						$db_innertell->next_record();
						$data = $db_innertell->Record;
						$extra = get_extra($data['extra']);
						if ($data['address1'] == '')
						{
							$data['address1'] = 'na';
						}
						if ($data['city'] == '')
						{
							$data['city'] = 'na';
						}
						if ($data['state'] == '')
						{
							$data['state'] = 'na';
						}
						if ($data['country'] == '')
						{
							$data['country'] = 'US';
						}
						if (trim($data['company']) == '')
						{
							$org = $data['name'];
						}
						else
						{
							$org = $data['company'];
						}
						if (trim($org) == '')
						{
							$org = 'Account' . $data['id'];
						}
						if ($extra['private_whois'] == 1)
						{
//							$org = 'Private Customer';
//							$data['address1'] = 'Private Residence';
						}

						$found = true;
						$cmds .= 'cd /opt/rwhoisd/etc/rwhoisd/net-' . $ipblock_ip . '-' . $ipblock_size . ';\n'
						. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
						. 'Auth-Area: ' . $ipblock . '\n'
						. 'Org-Name: ' . trim($org) . '\n'
						. 'Street-Address: ' . trim($data['address1']) . '\n'
						. 'City: ' . $data['city'] . '\n'
						. 'State: ' . $data['state'] . '\n'
						. 'Postal-Code: ' . $data['zipcode'] . '\n'
						. 'Country-Code: ' . $data['country'] . '\n'
						. 'Phone: ' . trim($data['phone']) . '\n'
						. 'Created: 20050101\n'
						. 'Updated: ' . date('Ymd') . '" > data/org/' . $data['id'] . '.txt;\n'
						. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
						. 'Auth-Area: ' . $ipblock . '\n'
						. 'Network-Name: INTSRV-' . $ip . '\n'
						. 'IP-Network: ' . $ip . '/' . $size . '\n'
						. 'Org-Name: ' . trim($org) . '\n'
						. 'Street-Address: ' . $data['address1'] . '\n'
						. 'City: ' . $data['city'] . '\n'
						. 'State: ' . $data['state'] . '\n'
						. 'Postal-Code: ' . $data['zipcode'] . '\n'
						. 'Country-Code: ' . $data['country'] . '\n'
						. 'Created: 20050101\n'
						. 'Updated: ' . date('Ymd') . '\n'
						. 'Updated-By: abuse@interserver.net" > data/network/' . $ip . '-' . $size . '.txt;\n';
					}
				}
				else
				{
					$query = "select * from servers where servers_hostname like '%$server%' or servers_hostname='$server'";
					$db4->query($query);
					if ($db4->num_rows() == 0)
					{
						$group = 162;
					}
					else
					{
						$db4->next_record();
						$serverinfo = $db4->Record;
						$groups = explode(':', $serverinfo['servers_group']);
						$group = $groups[1];
						if ($group == '')
						{
							$group = 162;
						}
					}
							$data = $GLOBALS['tf']->accounts->read($group);
							$data['url'] = str_replace(array(' ', ','), array('_', ''), $data['url']);
							if ($data['address'] == '')
							{
								$data['address'] = 'na';
							}
							if ($data['city'] == '')
							{
								$data['city'] = 'na';
							}
							if ($data['state'] == '')
							{
								$data['state'] = 'na';
							}
							if ($data['country'] == '')
							{
								$data['country'] = 'US';
							}
							if (trim($data['url']) == '')
							{
								$org = $data['account_lid'];
							}
							else
							{
								$org = $data['url'];
							}
							if (trim($org) == '')
							{
								$org = 'Account' . $group;
							}
							$found = true;
							$cmds .= 'cd /opt/rwhoisd/etc/rwhoisd/net-' . $ipblock_ip . '-' . $ipblock_size . ';\n'
							. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
							. 'Auth-Area: ' . $ipblock . '\n'
							. 'Org-Name: ' . trim($org) . '\n'
							. 'Street-Address: ' . trim($data['address']) . '\n'
							. 'City: ' . $data['city'] . '\n'
							. 'State: ' . $data['state'] . '\n'
							. 'Postal-Code: ' . $data['zip'] . '\n'
							. 'Country-Code: ' . $data['country'] . '\n'
							. 'Phone: ' . trim($data['phone']) . '\n'
							. 'Created: 20050101\n'
							. 'Updated: ' . date('Ymd') . '" > data/org/' . $data['url'] . '.txt;\n'
							. 'echo -e "ID: NETBLK-INTSRV.' . $ipblock . '\n'
							. 'Auth-Area: ' . $ipblock . '\n'
							. 'Network-Name: INTSRV-' . $ip . '\n'
							. 'IP-Network: ' . $ip . '/' . $size . '\n'
							. 'Org-Name: ' . trim($org) . '\n'
							. 'Street-Address: ' . $data['address'] . '\n'
							. 'City: ' . $data['city'] . '\n'
							. 'State: ' . $data['state'] . '\n'
							. 'Postal-Code: ' . $data['zip'] . '\n'
							. 'Country-Code: ' . $data['country'] . '\n'
							. 'Created: 20050101\n'
							. 'Updated: ' . date('Ymd') . '\n'
							. 'Updated-By: abuse@interserver.net" > data/network/' . $ip . '-' . $size . '.txt;\n';
/*
						}
						else
						{
							//echo "Couldn't Find Group For Comment/Server/Group $server/" . $serverinfo['servers_hostname'] . "/$group\n";
						}
					}
					else
					{
						//echo "Cant Find Server $server\n";
					}
*/
/**/
				}
		}
/**/
		echo str_replace('\n', "\n", $cmds);
		if ($found === false)
		{
			echo "echo \"Cant Find VLAN $ipblock\";\n";

		}
	}
//	echo "$total\n";
?>
