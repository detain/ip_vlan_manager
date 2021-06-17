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
	* returns whether or not the given IP is valid
	*
	* @param string $ip the ip address to validate
	* @param bool $display_errors whether or not errors are displayed. defaults to true
	* @param bool $support_ipv6 optional , defaults to false, whether or not to support ipv6, only works on php >= 5.2.0
	* @return bool whether or not its a valid ip
	*/
	function validIp($ip, $display_errors = true, $support_ipv6 = false)
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
			if ($support_ipv6 === false || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
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
	if (trim($network) == '') {
		return false;
	}
	$parts = explode('/', $network);
	if (count($parts) > 1) {
		list($block, $bitmask) = $parts;
	} else {
		$block = $parts[0];
		$bitmask = '32';
		$network = $block.'/'.$bitmask;
	}
	if (!validIp($block, false) || !is_numeric($bitmask)) {
		return false;
	}
	if (preg_match('/^(.*)\/32$/', $network, $matches)) {
		return [
			'network' => $matches[1],
			'network_ip' => $matches[1],
			'bitmask' => 32,
			'netmask' => '255.255.255.255',
			'broadcast' => '',
			'hostmin' => $matches[1],
			'hostmax' => $matches[1],
			'first_usable' => $matches[1],
			'gateway' => '',
			'hosts' => 1
		];
	}
	require_once 'Net/IPv4.php';
	$network_object = new Net_IPv4();
	$net = $network_object->parseAddress($network);
	$ipAddress_info = [
		'network' => $net->network.'/'.$net->bitmask,
		'network_ip' => $net->network,
		'bitmask' => $net->bitmask,
		'netmask' => $net->netmask,
		'broadcast' => $net->broadcast,
		'hostmin' => long2ip($net->ip2double($net->network) + 1),
		'hostmax' => long2ip($net->ip2double($net->broadcast) - 1),
		'first_usable' => long2ip($net->ip2double($net->network) + 2),
		'gateway' => long2ip($net->ip2double($net->network) + 1),
		'hosts' => (int)$net->ip2double($net->broadcast) - (int)$net->ip2double($net->network) - 1
	];
	return $ipAddress_info;
	/*
	try {
	$net = \IPTools\Network::parse($network);
	} catch (\Exception $e) {
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
	*/
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
		$client_ips = array_merge($client_ips, get_ips_newer($ipblock, $include_unusable));
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
		$all_ips = array_merge($all_ips, get_ips_newer($ipblock, $include_unusable));
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
		$all_ips = array_merge($all_ips, get_ips2_newer($ipblock, $include_unusable));
	}
	return $all_ips;
}

/**
* @param      $part
* @param      $ipparts
* @param      $maxparts
* @param bool $include_unusable
* @return bool
*/
function check_ip_part($part, $ipparts, $maxparts, $include_unusable = false)
{
	if ($include_unusable) {
		$maxip = 256;
	} else {
		$maxip = 255;
	}
	switch ($part) {
		case 1:
			if ($ipparts[0] < $maxip) {
				if ($ipparts[0] <= $maxparts[0]) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
			break;
		case 2:
			if ($ipparts[1] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1])) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
			break;
		case 3:
			if ($ipparts[2] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2])) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
			break;
		case 4:
			if ($ipparts[3] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2]) && ($ipparts[3] <= $maxparts[3])) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
			break;
	}
}


/**
* @param      $network
* @param bool $include_unusable
* @return array
*/
function get_ips($network, $include_unusable = false)
{
	$ips = [];
	$network_info = ipcalc($network);
	if ($include_unusable) {
		$minip = $network_info['network_ip'];
		$maxip = $network_info['broadcast'];
	} else {
		$minip = $network_info['hostmin'];
		$maxip = $network_info['hostmax'];
	}
	$minparts = explode('.', $minip);
	$maxparts = explode('.', $maxip);
	$ips = [];
	for ($a = $minparts[0]; check_ip_part(1, [$a], $maxparts, $include_unusable); $a++) {
		for ($b = $minparts[1]; check_ip_part(2, [$a, $b], $maxparts, $include_unusable); $b++) {
			for ($c = $minparts[2]; check_ip_part(3, [$a,$b,$c], $maxparts, $include_unusable); $c++) {
				for ($d = $minparts[3]; check_ip_part(4, [$a,$b,$c,$d], $maxparts, $include_unusable); $d++) {
					$ips[] = $a.'.'.$b.'.'.$c.'.'.$d;
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
function get_ips_new($network, $include_unusable = false)
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
function get_ips_newer($network, $include_unusable = false)
{
	$ips = [];
	$range = \IPLib\Range\Subnet::fromString($network);
	$offsetMax = $range->getSize();
	$offsetStart = 0;
	if (!$include_unusable && $range->getNetworkPrefix() != 32) {
		$offsetStart++;
		$offsetMax--;
	}
	$explodeChar = $range->getAddressType() == 4 ? '.' : ':';
	for ($offset = $offsetStart; $offset < $offsetMax; $offset++) {
		$ipAddress = $range->getAddressAtOffset($offset);
		$ips[] = $ipAddress->toString();
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
	$ips = [];
	$network_info = ipcalc($network);
	if ($include_unusable) {
		$minip = $network_info['network_ip'];
		$maxip = $network_info['broadcast'];
	} else {
		$minip = $network_info['hostmin'];
		$maxip = $network_info['hostmax'];
	}
	$minparts = explode('.', $minip);
	$maxparts = explode('.', $maxip);
	$ips = [];
	for ($a = $minparts[0]; check_ip_part(1, [$a], $maxparts, $include_unusable); $a++) {
		for ($b = $minparts[1]; check_ip_part(2, [$a, $b], $maxparts, $include_unusable); $b++) {
			for ($c = $minparts[2]; check_ip_part(3, [$a,$b,$c], $maxparts, $include_unusable); $c++) {
				for ($d = $minparts[3]; check_ip_part(4, [$a,$b,$c,$d], $maxparts, $include_unusable); $d++) {
					$ips[] = [$a.'.'.$b.'.'.$c.'.'.$d,$a,$b,$c,$d];
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
function get_ips2_new($network, $include_unusable = false)
{
	$ips = [];
	$net = \IPTools\Network::parse($network);
	if (!$include_unusable) {
		$net = $net->getHosts();
	}
	foreach ($net as $ip) {
		$parts = explode($ip->getVersion() == \IPTools\IP::IP_V6 ? ':' : '.', (string)$ip);
		array_unshift($parts, (string)$ip);
		$ips[] = $parts;
	}
	return $ips;
}

/**
* @param      $network
* @param bool $include_unusable
* @return array
*/
function get_ips2_newer($network, $include_unusable = false)
{
	$ips = [];
	$range = \IPLib\Range\Subnet::fromString($network);
	$offsetMax = $range->getSize();
	$offsetStart = 0;
	if (!$include_unusable && $range->getNetworkPrefix() != 32) {
		$offsetStart++;
		$offsetMax--;
	}
	$explodeChar = $range->getAddressType() == 4 ? '.' : ':';
	for ($offset = $offsetStart; $offset < $offsetMax; $offset++) {
		$ipAddress = $range->getAddressAtOffset($offset);
		$parts = explode($explodeChar, $ipAddress->toString());
		$ips[] = [$ipAddress->toString(), (int)$parts[0], (int)$parts[1], (int)$parts[2], (int)$parts[3]];
	}
	return $ips;
}

/**
* returns an array containing the list of ip blocks and a list of iused ips based on the location passed
*
* @param int $location location defaults to 1
* @return array returns array like [$mainBlocks, $usedIps]
*/
function get_mainblocks_and_usedips($location = 1) {
	$db = get_module_db('default');
	// get the ips in use
	$usedIps = [];
	$mainBlocks = [];
	if ($location == 1) { // Secaucus, NJ
		// get the main ipblocks we have routed
		$db->query('select * from ipblocks', __LINE__, __FILE__);
		while ($db->next_record()) {
			$mainBlocks[] = [(int)$db->Record['ipblocks_id'], $db->Record['ipblocks_network']];
		}
	}
	if ($location == 2) { // Los Angeles, CA
		$mainBlocks[] = [7, '173.214.160.0/23'];
		$mainBlocks[] = [8, '206.72.192.0/24'];
		$mainBlocks[] = [12, '162.220.160.0/24'];
		$mainBlocks[] = [15, '104.37.184.0/24'];
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
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 3) { // Equinix, NY4
		$mainBlocks[] = [12, '162.220.161.0/24'];
	} else {
		$reserved = [
			[2732368128, 2732368383], // 162.220.161.0/24 NY4
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 4) { // Hone Live
		$mainBlocks[] = [179, '66.45.241.16/28'];
		$mainBlocks[] = [1281, '69.10.38.128/25'];
		$mainBlocks[] = [2047, '69.10.60.192/26'];
		$mainBlocks[] = [1869, '69.10.56.0/25'];
		$mainBlocks[] = [2159, '68.168.216.0/22'];
		$mainBlocks[] = [2276, '69.10.57.0/24'];
		$mainBlocks[] = [1837, '69.10.52.72/29'];
		$mainBlocks[] = [1981, '69.10.52.112/29'];
		$mainBlocks[] = [1992, '69.10.61.64/26'];
		$mainBlocks[] = [2117, '68.168.212.0/24'];
		$mainBlocks[] = [2045, '69.10.60.0/26'];
		$mainBlocks[] = [2054, '68.168.222.0/24'];
		$mainBlocks[] = [2342, '69.10.53.0/24'];
		$mainBlocks[] = [2592, '209.159.159.0/24'];
		$mainBlocks[] = [3124, '66.23.224.0/24'];
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
			[1158296832, 1158297087], // 69.10.53.0/24 reserved
			[3516899072, 3516899327], // 209.159.159.0/24 reserved
			[1108860928, 1108861183], // 66.23.224.0/24 reserved
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	if ($location == 5) { // Vianim.in
		$mainBlocks[] = [16, '103.237.44.0/22'];
		$mainBlocks[] = [17, '43.243.84.0/22'];
		$mainBlocks[] = [16, '103.48.176.0/22'];
		$mainBlocks[] = [17, '45.113.224.0/22'];
		$mainBlocks[] = [17, '45.126.36.0/22'];
		$mainBlocks[] = [16, '103.197.16.0/22'];
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
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	// la 3
	if ($location == 6) { // Equinix LA3
		$mainBlocks[] = [3, '69.10.50.0/24'];
		$mainBlocks[] = [18, '208.73.200.0/24'];
		$mainBlocks[] = [18, '208.73.201.0/24'];
		$mainBlocks[] = [20, '216.158.224.0/23'];
		$mainBlocks[] = [20, '67.211.208.0/24'];
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
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	// Switch Subnets
	if ($location == 7) { // Switch Subnets
		$mainBlocks[] = [22, '173.225.96.0/24'];
		$mainBlocks[] = [2253, '68.168.214.0/23'];
		$mainBlocks[] = [22, '173.225.97.0/24'];
		$mainBlocks[] = [22, '66.45.224.0/24'];
	} else {
		$reserved = [
			[2917228544, 2917228799], // 173.225.96.0/24
			[2917228800, 2917229055], // 173.225.97.0/24
			[1151915520, 1151916031], // 68.168.214.0/23 reserved
			[1110302720, 1110302975], // 66.45.224.0/24
		];
		foreach ($reserved as $idx => $reserve) {
			for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
				$ipAddress = long2ip($x);
				$usedIps[$ipAddress] = $ipAddress;
			}
		}
	}
	$db->query('select ips_ip from ips where ips_vlan is not null', __LINE__, __FILE__);
	if ($db->num_rows()) {
		while ($db->next_record()) {
			$usedIps[$db->Record['ips_ip']] = $db->Record['ips_ip'];
		}
	}
	return [$mainBlocks, $usedIps];
}

function calculate_free_blocks($mainBlocks, $usedIps) {
	$startIp = false;
	$endIp = false;
	$freeRanges = [];
	$returnRanges = [];
	global $usedIpCounts;
	$usedIpCounts = [];
	foreach ($mainBlocks as $maindata) {
		$ips = get_ips2_newer($maindata[1], true);
		foreach ($ips as $ip) {
			if (isset($usedIps[$ip[0]])) {
				if (!isset($usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]]))
					$usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]] = 0;
				$usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]]++;
				if ($startIp !== false)
					$freeRanges[] = [$startIp, $endIp, $maindata[0]];
				$startIp = false;
				$endIp = false;
			} else {
				if ($startIp === false)
					$startIp = $ip[0];
				$endIp = $ip[0];
			}
		}
		if ($startIp !== false)
			$freeRanges[] = [$startIp, $endIp, $maindata[0]];
		$startIp = false;
		$endIp = false;
	}
	unset($startIp, $endIp, $ips, $ip);
	foreach ($freeRanges as $freeRange) {
		$ranges = \IPLib\Factory::rangesFromBoundaries($freeRange[0], $freeRange[1]);
		foreach ($ranges as $range)
			if (in_array($range->getNetworkPrefix(), [30, 31]))
				for ($x = 0, $xMax = $range->getSize(); $x < $xMax; $x++) {
					$rangeNew = \IPLib\Factory::rangeFromString($range->getAddressAtOffset($x)->toString().'/32');
					$rangeNew->BlockId = $freeRange[2];
					$returnRanges[] = $rangeNew;
				}
			else {
				$range->BlockId = $freeRange[2];
				$returnRanges[] = $range;
			}
	}
	unset($freeRanges, $freeRange, $range, $ranges);
	usort($returnRanges, 'ip_range_sort_desc');
	return $returnRanges;
}

/**
* performs an ascending sort on the given ranges based on block size (ie /24) and number of used ips in its class c
*
* @param \IPLib\Range\RangeInterface[] $ranges
*/
function ip_range_sort_asc(\IPLib\Range\RangeInterface $rangeA, \IPLib\Range\RangeInterface $rangeB) {
	if ($rangeA->getNetworkPrefix() > $rangeB->getNetworkPrefix())
		return 1;
	if ($rangeA->getNetworkPrefix() < $rangeB->getNetworkPrefix())
		return -1;
	$cClassA = substr($rangeA->getStartAddress()->toString(), 0, strrpos($rangeA->getStartAddress()->toString(), '.'));
	$cClassB = substr($rangeB->getStartAddress()->toString(), 0, strrpos($rangeB->getStartAddress()->toString(), '.'));
	global $usedIpCounts;
	$countA = isset($usedIpCounts[$cClassA]) ? $usedIpCounts[$cClassA] : 0;
	$countB = isset($usedIpCounts[$cClassB]) ? $usedIpCounts[$cClassB] : 0;
    return strcmp($countB, $countA);
}

/**
* performs a descending sort on the given ranges based on block size (ie /24) and number of used ips in its class c
*
* @param \IPLib\Range\RangeInterface[] $ranges
*/
function ip_range_sort_desc(\IPLib\Range\RangeInterface $rangeA, \IPLib\Range\RangeInterface $rangeB) {
	if ($rangeA->getNetworkPrefix() < $rangeB->getNetworkPrefix())
		return 1;
	if ($rangeA->getNetworkPrefix() > $rangeB->getNetworkPrefix())
		return -1;
	$cClassA = substr($rangeA->getStartAddress()->toString(), 0, strrpos($rangeA->getStartAddress()->toString(), '.'));
	$cClassB = substr($rangeB->getStartAddress()->toString(), 0, strrpos($rangeB->getStartAddress()->toString(), '.'));
	global $usedIpCounts;
	$countA = isset($usedIpCounts[$cClassA]) ? $usedIpCounts[$cClassA] : 0;
	$countB = isset($usedIpCounts[$cClassB]) ? $usedIpCounts[$cClassB] : 0;
    return strcmp($countB, $countA);
}

/**
* @param int $blocksize
* @param int $location
* @return array
*/
function available_ipblocks($blocksize, $location = 1)
{
	// array of available blocks
	$available = [];
	// first we gotta get how many ips are in the blocksize they requested
	$ipcount = get_ipcount_from_netmask($blocksize) + 2;
	list($mainBlocks, $usedIps) = get_mainblocks_and_usedips($location);
	foreach ($mainBlocks as $maindata) {
		$ipblock_id = $maindata[0];
		$mainblock = $maindata[1];
		// get ips from the main block
		$ips = get_ips2_newer($mainblock, true);
		// next loop through all available ips
		$ipsize = count($ips);
		$found = false;
		$found_count = 0;
		$found_c = '';
		for ($x = 0; $x < $ipsize; $x++) {
			// check if the ips in use already
			if (isset($usedIps[$ips[$x][0]])) {
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
* @param int $blocksize
* @param int $location
* @return array
*/
function available_ipblocks_new($blocksize, $location = 1)
{
	// array of available blocks
	$available = [];
	// first we gotta get how many ips are in the blocksize they requested
	$ipcount = get_ipcount_from_netmask($blocksize) + 2;
	list($mainBlocks, $usedIps) = get_mainblocks_and_usedips($location);
	$freeBlocks = calculate_free_blocks($mainBlocks, $usedIps);
	foreach ($freeBlocks as $freeBlock)
		if ($freeBlock->getNetworkPrefix() == $blocksize)
			$available[] = [$freeBlock->toString(), $freeBlock->BlockId];
		elseif ($freeBlock->getNetworkPrefix() < $blocksize)
			for ($x = 0, $fitBlocks = (get_ipcount_from_netmask($freeBlock->getNetworkPrefix()) + 2) / $ipcount; $x < $fitBlocks; $x++)
				$available[] = [$freeBlock->getAddressAtOffset($x * $ipcount)->toString().'/'.$blocksize, $freeBlock->BlockId];
	return $available;
}
