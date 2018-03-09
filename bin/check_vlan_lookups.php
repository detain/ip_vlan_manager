<?php
require_once __DIR__.'/../../include/functions.inc.php';

$db = get_module_db('innertell');
$db2 = clone $db;
$switchport_vlans = [];
$db->query("select assets.id as asset_id, order_id, server_hostname from servers, assets where servers.status='active' and assets.order_id=servers.server_id");
while ($db->next_record(MYSQL_ASSOC)) {
	$order = $db->Record;
	$ip = gethostbyname($db->Record['server_hostname']);
	if ($ip == $db->Record['server_hostname'])
		continue;
	$network = get_server_ip_info($ip);
	$filename = "https://my.interserver.net/vlan_ips.php?vlan_comment={$order['server_hostname']}";
	$curl = curl_init($filename);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	ob_start();
	curl_exec($curl);
	curl_close($curl);
	$raw_network_info = ob_get_contents();
	ob_end_clean();
	$has_ip = false;
	if (trim($raw_network_info) != 'No vlans found') {
		$has_ip = true;
		$network_array = explode ("\n", $raw_network_info);
		$ip_array = explode (':', $network_array[1]);
		$vlan = $ip_array[1];
		$switch_array = explode (':', $network_array[2]);
		$switchport = $switch_array[1];
		$graph_id = $network_array[3];
		$ip_array2 = explode ('/', $vlan);
		$ip_address = long2ip(ip2long($ip_array2[0]) +2);
		$subnet_size = $ip_array2[1];
		$netmask = long2ip(-1 << (32 - (int)$subnet_size));
		$gateway = long2ip((ip2long($ip_address) & ip2long($netmask))+1);
		$ip_js = '';
		$ipranges = [];
		$ipranges[] = explode("\n", trim(`LANG=C /usr/local/bin/ipcalc -nb {$vlan} | grep -e HostMin -e HostMax | awk '{ print \$2 }'`));
		$last_usable = $ipranges[0][1];
		if (isset($_GET['kvm_setup'])) {
			$firstip = long2ip(ip2long($ip_address)+1);
			$broadcast = long2ip(ip2long($last_usable)+1);
			$lastip = $last_usable;
		}
		$addon_vlans = [];
		$db->query("select * from vlans where vlans_comment like '%ppend%{$order['server_hostname']}%'", __LINE__, __FILE__);
		$vlan_idx = 0;
		if ($db->num_rows() > 0) {
			while ($db->next_record(MYSQL_ASSOC)) {
				$ipnetwork = str_replace(':', '', $db->Record['vlans_networks']);
				$ipinfo = ipcalc($ipnetwork);
				$ipinfo['first_ip'] = long2ip(ip2long($ipinfo['hostmin'])+1);
				$addon_vlans[] = $ipinfo;
				if (isset($_GET['kvm_setup'])) {
					$network = long2ip(ip2long($ipinfo['gateway'])-1);
					$kvm_script .= 'comes from elsewhere, remindm me to update this.';
				}
			}
		}
		if (isset($_GET['kvm_setup']))
			$kvm_script .= 'comes from elsewhere, remindm me to update this.';
		$smarty->assign('addon_vlans', $addon_vlans);
	} elseif (isset($assets) && !in_array($assets['primary_ipv4'], [null, ''])) {
		if (isset($_REQUEST['drop_ip']) && $_REQUEST['drop_ip'] == true) {
			$db->query("update assets set primary_ipv4='' where  order_id = '{$order['id']}'", __LINE__, __FILE__);
		} else {
			$db->query("select * from ips left join vlans on vlans_id=ips_vlan where ips_ip='{$assets['primary_ipv4']}'", __LINE__, __FILE__);
			if ($db->num_rows() > 0) {
				$has_ip = true;
				$db->next_record(MYSQL_ASSOC);
				$vlans = explode(':', $db->Record['vlans_networks']);
				//$vlan = $vlans[1];
				$network_info = ipcalc($vlans[1]);
				$smarty->assign('network_info', $network_info);
			}
		}
	}


	$vlans = explode(',', trim($db->Record['vlans']));
	foreach ($vlans as $vlan)
		if (trim($vlan) != '')
			$switchport_vlans[$vlan] = $db->Record;
}
$db->query('select * from vlans');
$vlans = [];
while ($db->next_record(MYSQL_ASSOC)) {
	$found = [];
	$server = trim(str_replace(['append '], [], strtolower($db->Record['vlans_comment'])));
	$db2->query("select * from assets where hostname='{$server}'");
	if ($db2->num_rows() == 0) {
		$db2->query("select * from assets where hostname='{$server}' or hostname like '%{$server}' or hostname like '{$server}%'");
		//if ($db2->num_rows() > 0)
			//echo "found server {$server} match with like matching in assets for vlan {$db->Record['vlans_networks']}\n";
	}
	if ($db2->num_rows() > 0) {
		$db2->next_record(MYSQL_ASSOC);
		$found['asset_id'] = $db2->Record['id'];
		//echo "Found server {$server} in assets for vlan {$db->Record['vlans_networks']}\n";
	} else {
		$db2->query("select * from servers where server_hostname='{$server}'");
		if ($db2->num_rows() == 0) {
			$db2->query("select * from servers where server_hostname='{$server}' or server_hostname like '%{$server}' or server_hostname like '{$server}%'");
			//if ($db2->num_rows() > 0)
				//echo "found server {$server} match with like matching in servers for vlan {$db->Record['vlans_networks']}\n";
		}
		if ($db2->num_rows() > 0) {
			$db2->next_record(MYSQL_ASSOC);
			$found['order_id'] = $db2->Record['id'];
			echo "Found server {$server} in servers for vlan {$db->Record['vlans_networks']}\n";
		} else {
			echo "Cannot find server {$server} for vlan {$db->Record['vlans_networks']}\n";
		}
	}
	if (isset($switchport_vlans[$db->Record['vlans_id']])) {
		$found['switchport_id'] = $switchport_vlans[$db->Record['vlans_id']]['switchport_id'];
		//echo "found switch/port {$switchport_vlans[$db->Record['vlans_id']]['switch']}/{$switchport_vlans[$db->Record['vlans_id']]['port']} match for vlan {$db->Record['vlans_networks']}\n";
	} else {
		$ports = explode(':', $db->Record['vlans_ports']);
		foreach ($ports as $port) {
			if (trim($port) != '') {
				if (preg_match('/^([^\/]+)\/(.*)$/m', $port, $matches)) {
					$vlan_switch = $matches[1];
					$vlan_port = $matches[2];
					$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and name='{$vlan_switch}' and port='{$vlan_port}'");
					if ($db2->num_rows() == 0)
						$db2->query("select * from switchports, switchmanager where switchports.switch=switchmanager.id and name='{$vlan_switch}' and justport='{$vlan_port}'");
					if ($db2->num_rows() == 0)
						$db2->query("select * from switchports where switch='{$vlan_switch}' and port='{$vlan_port}'");
					if ($db2->num_rows() == 0)
						$db2->query("select * from switchports where switch='{$vlan_switch}' and justport='{$vlan_port}'");
					if ($db2->num_rows() > 0) {
						$db2->next_record(MYSQL_ASSOC);
						$found['switchport_id'] = $db2->Record['switchport_id'];
						//echo "found switch/port {$port} match for vlan {$db->Record['vlans_networks']}\n";
					} else {
						echo "Cannot find switch {$vlan_switch} port {$vlan_port} entry in switchports for vlan {$db->Record['vlans_networks']}\n";
					}
				} else {
					echo "Cannot find switch/port in {$port} for vlan {$db->Record['vlans_networks']}\n";
				}

			}
		}
	}
	if (count($found) > 0)
		$vlans[$db->Record['vlans_id']] = $found;
}
foreach ($vlans as $vlan_id => $vlan_data) {
	$update_fields = [];
	$fields = [
		'vlan_asset_id' => ['null'],
		'vlan_id' => $vlan_id
	];
	if (isset($vlan_data['asset_id'])) {
		$fields['vlan_location'] = $vlan_data['asset_id'];
		if (isset($vlan_data['switchport_id'])) {
			$fields['vlan_switchport'] = $vlan_data['switchport_id'];
			$update_fields['vlan_switchport'] = $vlan_data['switchport_id'];
		}
		if (count($update_fields) == 0)
			$update_fields = false;
		$query = make_insert_query('vlan_locations', $fields, $update_fields);
		//echo "Query: {$query}\n";
		$db->query($query);
	}
}
//print_r($vlans);
