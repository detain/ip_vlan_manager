<?php
/************************************************************************************\
* MyAdmin VLAN Manager                                                               *
* (c)2002-2017 Interserver                                                           *
* ---------------------------------------------------------------------------------- *
* Description: IP functions                                                          *
\************************************************************************************/

/**
* converts a network like say 66.45.228.0/24 into a gateway address
* @param string $network returns a gateway address from a network address in the format of  [network ip]/[subnet] ie  192.168.1.128/23
* @return string gateway address ie 192.168.1.129 or 66.45.228.1
*/
function network2gateway($network)
{
	$net = \IPTools\Network::parse($network);
	return (string)$net->getHosts()->getFirstIP();
}

/**
 * @param string $data vlan ports like 171/4 or 171/Ethernet1/7 etc..
 * @return array array of switch / port information
 */
function parse_vlan_ports($data)
{
	$parts2 = explode('/', $data);
	$switch = $parts2[0];
	$port = mb_substr($data, mb_strlen($switch) + 1);
	if (mb_strpos($port, '/') > 0) {
		$blade = mb_substr($port, 0, mb_strrpos($port, '/'));
		$justport = mb_substr($port, mb_strlen($blade) + 1);
	} else {
		$blade = '';
		$justport = $port;
	}
	return [$switch, $port, $blade, $justport];
}

/**
 * @param      $index
 * @param bool $short
 * @return string
 */
function get_switch_name($index, $short = false)
{
	$db = get_module_db('default');
	$db->query("select * from switchmanager where id='{$index}'");
	$db->next_record();
	$switch = $db->Record['name'];
	if ($short == false) {
		return 'Switch '.$switch;
	} else {
		return $switch;
	}
}

/**
 * @param bool|array $ports
 * @param int  $size
 * @return string
 */
function get_select_ports($ports = false, $size = 5, $extra = '')
{
	$db = get_module_db('default');
	if ($ports === false) {
		$ports = [];
	}
	$select = '<select multiple="multiple" size='.$size.' name="ports[]" '.$extra.'>';
	$db->query('select * from switchmanager as sm, switchports as sp where switch=id order by name, port desc');
	while ($db->next_record()) {
		$switch = $db->Record['id'];
		$port = $db->Record['port'];
		if (in_array($switch.'/'.$port, $ports)) {
			$select .= '<option selected value="'.$switch.'/'.$port.'">Switch '.$db->Record['name'].' Port '.$port.'</option>';
		} else {
			$select .= '<option value="'.$switch.'/'.$port.'">Switch '.$db->Record['name'].' Port '.$port.'</option>';
		}
	}
	$select .= '</select>';
	return $select;
}

/**
 * gets the number of ips for a given netmask or bitmask.  can pass it a blocksize (ie 24) or netmask (ie 255.255.255.0)
 *
 * @param $netmask a netmask or block size
 * @return int the number of ips in within a range using this netmask or blocksize
 */
function get_ipcount_from_netmask($netmask)
{
	if (is_numeric($netmask)) {
		$netmask = (string)\IPTools\Network::prefix2netmask($netmask, \IPTools\IP::IP_V4);
	}
	$ip = \IPTools\IP::parse($netmask);
	if ($ip->getVersion() == \IPTools\IP::IP_V6) {
		$net = new \IPTools\Network(new \IPTools\IP('ffff:ffff::'), $ip);        
	} else {
		$net = new \IPTools\Network(new \IPTools\IP('192.168.0.0'), $ip);
	}
	return $net->getHosts()->count();
}

if (!function_exists('validIp')) {
	/**
	 * validIp()
	 * returns whether or not the given IP is valid
	 *
	 * @param string $ipAddress the ip address to validate
	 * @param bool $display_errors whether or not errors are displayed. defaults to TRUE
	 * @return bool whether or not its a valid ip
	 */
	function validIp($ipAddress, $display_errors = true)
	{
		if (!preg_match("/^[0-9\.]{7,15}$/", $ipAddress)) {
			// don't display errors cuz this gets called w/ a blank entry when people didn't even submit anything yet
			//add_output('<font class="error">IP '.$ipAddress.' Too short/long</font>');
			return false;
		}
		$quads = explode('.', $ipAddress);
		$num_quads = count($quads);
		if ($num_quads != 4) {
			if ($display_errors) {
				add_output('<font class="error">IP '.$ipAddress.' Too many quads</font>');
			}
			return false;
		}
		for ($i = 0; $i < 4; $i++) {
			if ($quads[$i] > 255) {
				if ($display_errors) {
					add_output('<font class="error">IP '.$ipAddress.' number '.$quads[$i].' too high</font>');
				}
				return false;
			}
		}
		return true;
	}
}

 /**
 * Gets the Network information from a network address.
 * Example Response: [
 *   'network'      => '66.45.233.160/28',
 *   'network_ip'   => '66.45.233.160',
 *   'bitmask'      => '28',
 *   'netmask'      => '255.255.255.240',
 *   'broadcast'    => '66.45.233.175',
 *   'hostmin'      => '66.45.233.161',
 *   'hostmax'      => '66.45.233.174',
 *   'first_usable' => '66.45.233.162',
 *   'gateway'      => '66.45.233.161',
 *   'hosts'        => 14
 * ];
 * @param $network string Network address in 1.2.3.4/24 format
 * @return array|bool false on error or returns an array containing the network info
 */
function ipcalc($network)
{
	try {
		$net = \IPTools\Network::parse($network);
	} catch (\Exception $e) {
		myadmin_log('myadmin', 'error', 'ipcalc('.$network.') caught exception: '.$e->getMessage(), __LINE__, __FILE__);
		return false;
	}
	$hosts = $net->getHosts();
	if ($net->getBlockSize() > 1)
		$hosts->next();
	return [
		'network' => $net->getCIDR(),
		'network_ip' => (string)$net->getNetwork(),
		'bitmask' => $net->getPrefixLength(),
		'netmask' => (string)$net->getNetmask(),
		'broadcast' => (string)$net->getBroadcast(),
		'hostmin' => (string)$hosts->getFirstIP(),
		'hostmax' => (string)$hosts->getLastIP(),
		'first_usable' => (string)$hosts->current(),
		'gateway' => (string)$hosts->getFirstIP(),
		'hosts' => $hosts->count(),
	];
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
	$networks = [];
	$parts = explode(':', $text);
	for ($x = 0, $x_max = count($parts); $x < $x_max; $x++) {
		if ($parts[$x] != '') {
			$networks[] = [
				'network' => $parts[$x],
				'vlan' => $vlan,
				'comment' => $comment,
				'ports' => $ports
			];
		}
	}
	return $networks;
}

/**
 * @return array
 */
function get_all_ipblocks()
{
	$db = get_module_db('default');
	$db->query('select ipblocks_network from ipblocks');
	$all_blocks = [];
	while ($db->next_record(MYSQL_ASSOC)) {
		$all_blocks[] = $db->Record['ipblocks_network'];
	}
	return $all_blocks;
}

/**
 * @return array
 */
function get_client_ipblocks()
{
	$ipblocks = [
		'67.217.48.0/20',
		'69.164.240.0/20',
		'74.50.64.0/19',
		'202.53.73.0/24',
		'205.209.96.0/19',
		'216.219.80.0/20'
	];
	return $ipblocks;
}

/**
 * @param bool $include_unusable
 * @return array
 */
function get_client_ips($include_unusable = false)
{
	$ipblocks = get_client_ipblocks();
	$client_ips = [];
	foreach ($ipblocks as $ipblock) {
		$client_ips = array_merge($client_ips, get_ips($ipblock, $include_unusable));
	}
	return $client_ips;
}

/**
 * @param bool $include_unusable
 * @return array
 */
function get_all_ips_from_ipblocks($include_unusable = false)
{
	$all_blocks = get_all_ipblocks();
	$all_ips = [];
	foreach ($all_blocks as $ipblock) {
		$all_ips = array_merge($all_ips, get_ips($ipblock, $include_unusable));
	}
	return $all_ips;
}

/**
 * @param bool $include_unusable
 * @return array
 */
function get_all_ips2_from_ipblocks($include_unusable = false)
{
	$all_blocks = get_all_ipblocks();
	$all_ips = [];
	foreach ($all_blocks as $ipblock) {
		$all_ips = array_merge($all_ips, get_ips2($ipblock, $include_unusable));
	}
	return $all_ips;
}

/**
 * @param      $network
 * @param bool $include_unusable
 * @return array
 */
function get_ips($network, $include_unusable = false)
{
	$ips = [];
	$net = \IPTools\Network::parse($network);
	if (!$include_unusable) {
		$net = $net->getHosts();
	}
	foreach ($net as $ip) {
		$ips[] = (string)$ip;
	}
	return $ips;
}

/**
 * @param      $network
 * @param bool $include_unusable
 * @return array
 */
function get_ips2($network, $include_unusable = false)
{
	return get_ips($network, $include_unusable);
}

/**
 * @param     $blocksize
 * @param int $location
 * @return array
 */
function available_ipblocks($blocksize, $location = 1)
{
	// array of available blocks
	$available = [];
	$db = get_module_db('default');
	// first we gotta get how many ips are in the blocksize they requested
	$ipcount = get_ipcount_from_netmask($blocksize) + 2;
	// get the ips in use
	$usedips = [];
	$mainblocks = [];
	if ($location == 1) { // Secaucus, NJ
		// get the main ipblocks we have routed
		$db->query('select * from ipblocks', __LINE__, __FILE__);
		while ($db->next_record()) {
			$mainblocks[] = [$db->Record['ipblocks_id'], $db->Record['ipblocks_network']];
		}
	}
	if ($location == 2) { // Los Angeles, CA
		$mainblocks[] = [7, '173.214.160.0/23'];
		$mainblocks[] = [8, '206.72.192.0/24'];
		$mainblocks[] = [12, '162.220.160.0/24'];
		$mainblocks[] = [15, '104.37.184.0/24'];
	} else {
		$reserved = [
			[1747302400, 1747302655], // 104.37.184.0/24 LA reserved
			[2916524033, 2916524542], // added by joe 08/24/11 to temporarily hide  173.214.160.0/23 lax1 ips
			[3460874240, 3460874751], // 206.72.192.0  LA
			[2732367872, 2732368127], // 162.220.160.0/24 LA
			[2732093440, 2732093695], // 162.216.112.0/24   LA
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 3) { // Equinix, NY4
		$mainblocks[] = [12, '162.220.161.0/24'];
	} else {
		$reserved = [
			[2732368128, 2732368383], // 162.220.161.0/24 NY4
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 4) { // Hone Live
		$mainblocks[] = [179, '66.45.241.16/28'];
		$mainblocks[] = [1281, '69.10.38.128/25'];
		$mainblocks[] = [2047, '69.10.60.192/26'];
		$mainblocks[] = [1869, '69.10.56.0/25'];
		$mainblocks[] = [2159, '68.168.216.0/22'];
		$mainblocks[] = [2276, '69.10.57.0/24'];
		$mainblocks[] = [1837, '69.10.52.72/29'];
		$mainblocks[] = [1981, '69.10.52.112/29'];
		$mainblocks[] = [1992, '69.10.61.64/26'];
		$mainblocks[] = [2117, '68.168.212.0/24'];
		$mainblocks[] = [2045, '69.10.60.0/26'];
		$mainblocks[] = [2054, '68.168.222.0/24'];
		$mainblocks[] = [2253, '68.168.214.0/23'];
		$mainblocks[] = [2342, '69.10.53.0/24'];
		$mainblocks[] = [2592, '209.159.159.0/24'];
		$mainblocks[] = [3124, '66.23.224.0/24'];
	} else {
		$reserved = [
			[1110307088, 1110307103], // 66.45.241.16/28 reserved
			[1158293120, 1158293247], // 69.10.38.128/25 reserved
			[1158298816, 1158298879], // 69.10.60.192/26 reserved
			[1158297600, 1158297727], // 69.10.56.0/25 reserved
			[1151916032, 1151917055], // 68.168.216.0/22 reserved
			[1158297856, 1158298111], // 69.10.57.0/24 reserved
			[1158296648, 1158296655], // 69.10.52.72/29 reserved
			[1158296688, 1158296695], // 69.10.52.112/29 reserved
			[1158298944, 1158299007], // 69.10.61.64/26 reserved
			[1151915008, 1151915263], // 68.168.212.0/24 reserved
			[1158298624, 1158298687], // 69.10.60.0/26 reserved
			[1151917568, 1151917823], // 68.168.222.0/24 reserved
			[1151915520, 1151916031], // 68.168.214.0/23 reserved
			[1158296832, 1158297087], // 69.10.53.0/24 reserved
			[3516899072, 3516899327], // 209.159.159.0/24 reserved
			[1108860928, 1108861183], // 66.23.224.0/24 reserved
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 5) { // Vianim.in
		$mainblocks[] = [16, '103.237.44.0/22'];
		$mainblocks[] = [17, '43.243.84.0/22'];
		$mainblocks[] = [16, '103.48.176.0/22'];
		$mainblocks[] = [17, '45.113.224.0/22'];
		$mainblocks[] = [17, '45.126.36.0/22'];
		$mainblocks[] = [16, '103.197.16.0/22'];
	} else {
		$reserved = [
			[1743596544, 1743597567], /* 103.237.44.0/22 */
			[737367040, 737367040], /* 43.243.84.0/22 */
			[1731244032, 1731245055], /* 103.48.176.0/22 */
			[762437632, 762438400], /* 45.113.224.0/22 */
			[763241472, 763242495], /* 45.126.36.0/22 */
			[1740967936, 1740968959], /* 103.197.16.0/22 */
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	// la 3
	if ($location == 6) { // Equinix LA3
		$mainblocks[] = [3, '69.10.50.0/24'];
		$mainblocks[] = [18, '208.73.200.0/24'];
		$mainblocks[] = [18, '208.73.201.0/24'];
		$mainblocks[] = [20, '216.158.224.0/23'];
		$mainblocks[] = [20, '67.211.208.0/24'];
	} else {
		$reserved = [
			[1158296064, 1158296319], // 69.10.50.0/24
			[3494496256, 3494496511], // 208.73.200.0/24
			[3494496512, 3494496767], // 208.73.201.0/24
			[3634290688, 3634291199], // 216.158.224.0/23
			[1137954816, 1137955071], // 67.211.208.0/24
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	// Switch Subnets
	if ($location == 7) { // Switch Subnets
		$mainblocks[] = [22, '173.225.96.0/24'];
		$mainblocks[] = [22, '173.225.97.0/24'];
		$mainblocks[] = [22, '66.45.224.0/24'];
	} else {
		$reserved = [
			[2917228544, 2917228799], // 173.225.96.0/24
			[2917228800, 2917229055], // 173.225.97.0/24
			[1110302720, 1110302975], // 66.45.224.0/24
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedips[$ipAddress] = $ipAddress;
			}
		}
	}
	/* 45.126.36.0/22 */
	/*		$reserved = array(763241472, 763242495);
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	*/
	/* 199.231.191.0/24 reserved */
	/* mike says ok to remove 2/24/2017
	$reserved = array(3353853696, 3353853951);
	for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
		$ipAddress = long2ip($x);
		$usedips[$ipAddress] = $ipAddress;
	} */
	/* 66.23.225.0/24 reserved cogent */
	/*  mike says we can undo this - 2/24/2017
	$reserved = array(1108861184, 1108861439);
	for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
		$ipAddress = long2ip($x);
		$usedips[$ipAddress] = $ipAddress;
	}
	*/
	$db->query('select ips_ip from ips where ips_vlan is not null', __LINE__, __FILE__);
	if ($db->num_rows()) {
		while ($db->next_record()) {
			$usedips[$db->Record['ips_ip']] = $db->Record['ips_ip'];
		}
	}
	foreach ($mainblocks as $maindata) {
		$ipblock_id = $maindata[0];
		$mainblock = $maindata[1];
		// get ips from the main block
		$ips = get_ips2($mainblock, true);
		// next loop through all available ips
		$ipsize = count($ips);
		$found = false;
		$found_count = 0;
		$found_c = '';
		for ($x = 0; $x < $ipsize; $x++) {
			// check if the ips in use already
			if (isset($usedips[$ips[$x][0]])) {
				$found = false;
				$found_count = 0;
			} else {
				$c = $ips[$x][3];
				if ($found && ($blocksize >= 24) && ($found_c != $c)) {
					$found = false;
					$found_count = 0;
				}
				if (!$found) {
					if ($blocksize <= 24) {
						if ($ips[$x][4] == 0) {
							$ipcalc = ipcalc($ips[$x][0].'/'.$blocksize);
							if ($ipcalc['network'] == $ips[$x][0].'/'.$blocksize) {
								$found = $ips[$x][0];
								$found_c = $c;
							}
						}
					} else {
						if ($ips[$x][4] % $ipcount == 0) {
							$found = $ips[$x][0];
							$found_c = $c;
						}
					}
				}
				if ($found) {
					$found_count++;
					if ($found_count == $ipcount) {
						$available[] = [$found, $ipblock_id];
						$found = false;
						$found_count = 0;
					}
				}
			}
		}
	}
	return $available;
}




/**
 * @param $hostname
 * @return string
 */
function ips_hostname($hostname)
{
	$db = clone get_module_db('default');
	$db2 = clone $db;
	$comment = $db->real_escape($hostname);
	$query = "select * from vlans where vlans_comment='{$comment}'";
	$db->query($query);
	$out = '';
	if ($db->num_rows() == 0) {
		$query = "select * from vlans where vlans_comment like '%{$comment}%'";
		$db->query($query);
	}
	if ($db->num_rows() > 0) {
		while ($db->next_record(MYSQL_ASSOC)) {
			//		$db->Record['vlans_id'];
			$parts = explode(':', $db->Record['vlans_ports']);
			for ($x = 0, $x_max = count($parts); $x < $x_max; $x++) {
				if (mb_strpos($parts[$x], '/')) {
					list($switch, $port, $blade, $justport) = parse_vlan_ports($parts[$x]);
					$parts[$x] = get_switch_name($switch, true).'/'.$port;
				}
			}
			$vlan = $db->Record['vlans_id'];
			$query = "select graph_id from switchports where switch='{$switch}' and port='{$port}'";
			//echo $query;
			$db2->query($query);
			if ($db2->num_rows() > 0) {
				$db2->next_record();
				//print_r($db2->Record);
				$graph_id = $db2->Record['graph_id'];
			} else {
				$query = "select graph_id from switchports where switch='{$switch}' and (port='{$port}' || justport='{$justport}')";
				//echo $query;
				$db2->query($query);
				if ($db2->num_rows() > 0) {
					$db2->next_record();
					//print_r($db2->Record);
					$graph_id = $db2->Record['graph_id'];
				} else {
					$graph_id = 0;
				}
			}
			$db->Record['vlans_ports'] = implode(':', $parts);
			$out .= $db->Record['vlans_comment'] . "\n{$db->Record['vlans_networks']}\n{$db->Record['vlans_ports']}\n" . $graph_id.PHP_EOL;
		}
	} else {
		$out .= "No vlans found\n";
	}
	return trim($out);
}
