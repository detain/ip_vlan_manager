<?php
	require_once __DIR__.'/../../include/functions.inc.php';
	$ip = $_SERVER['argv'][1];
	$db = get_module_db('innertell');
	$db->query("select vlans_networks from vlans where vlans_networks like '%{$ip}%';");
	$vlans = [];
	while ($db->next_record()) {
		$vlans[] = str_replace(':', '', $db->Record['vlans_networks']);
	}
	$usage = [];
	$ips = [];
	$ipinfos = [];
	foreach ($vlans as $block) {
		$db->query("select vlans_id from vlans where vlans_networks=':$block:'");
		$db->next_record();
		$vlan = $db->Record['vlans_id'];
		$vblock = ipcalc($block);
		echo '			$mainblocks[] = array('.$vlan.', "'.$block.'");'.PHP_EOL;
		echo '			// '.$block.' reserved
			$reserved = array('.sprintf('%u', ip2long($vblock['network_ip'])).', '.sprintf('%u', ip2long($vblock['broadcast'])).');
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}
';
		//echo $vlan .', '.sprintf("%u", ip2long($vblock['network_ip'])).', '.sprintf("%u", ip2long($vblock['broadcast'])).PHP_EOL;
//		print_r($block);
	}

/*
			$mainblocks[] = array(15,'104.37.184.0/24');

		} else {
			//  104.37.184.0/24 LA reserved
			$reserved = array(1747302400, 1747302655);
			for ($x = $reserved[0]; $x < $reserved[1]; $x++)
			{
				$ip = long2ip($x);
				$usedips[$ip] = $ip;
			}

	$ipinfo['network'] = $lines[3];
	$ipinfo['network_ip'] = $netparts[0];
	$ipinfo['netmask'] = $lines[1];
	$ipinfo['wildcard'] = $lines[2];
	$ipinfo['broadcast'] = $lines[6];
	$ipinfo['hostmin'] = $lines[4];
	$ipinfo['hostmax'] = $lines[5];
	$ipinfo['hosts'] = $lines[7];
*/
