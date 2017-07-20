<?php
/************************************************************************************\
* MyAdmin VLAN Manager                                                               *
* (c)2002-2017 Interserver                                                           *
*
* ---------------------------------------------------------------------------------- *
* Description: IP functions                                                          *
\************************************************************************************/

//define('IPS_MODULE', 'innertell');
define('IPS_MODULE', 'default');

/**
 * updates the switch ports
 *
 * @param bool $verbose wether or not to enable verbose output.
 */
function update_switch_ports($verbose = FALSE) {
	$db = get_module_db(IPS_MODULE);
	$db2 = clone $db;

	$lines = explode("\n", getcurlpage('http://nms.interserver.net/cac/servermap.php'));
	$switches = [];
	foreach ($lines as $line)
	{
		if (trim($line) != '') {
			list($graph, $switch, $port, $comment) = explode(',', $line);
			if ($switch != '')
			{
				$switches[$switch][$port] = $graph;
			}
		}
	}
	foreach ($switches as $switch => $ports)
	{
		$foundports = [];
		$db->query("select * from switchmanager where name='{$switch}'");
		if ($db->num_rows() > 0)
		{
			$db->next_record();
			$row = $db->Record;
			if ($verbose == TRUE)
				add_output("Loaded Switch $switch - ");
		}
		else
		{
			$db->query(make_insert_query('switchmanager', [
				'id' => NULL,
				'name' => $switch,
				'ports' => count($ports)
			                                            ]
			           ), __LINE__, __FILE__);
			$db->query("select * from switchmanager where name='{$switch}'");
			$db->next_record();
			$row = $db->Record;
			if ($verbose == TRUE)
				add_output("Created New Switch {$switch} - ");
		}
		$id = $row['id'];
		foreach ($ports as $port => $graph)
		{
			$blade = '';
			$justport = $port;
			if (mb_strrpos($port, '/') > 0)
			{
				$blade = mb_substr($port, 0, mb_strrpos($port, '/'));
				$justport = mb_substr($port, mb_strlen($blade) + 1);
			}
			if (isset($foundports[$justport]))
			{
				$justport = '';
			}
			else
			{
				$foundports[$justport] = TRUE;
			}
			$db->query("select * from switchports where switch='{$id}' and port='{$port}'");
			if ($db->num_rows() == 0)
			{
				if ($verbose == TRUE)
					add_output("{$port} +");
				$db->query(make_insert_query('switchports', [
					'switch' => $id,
					'blade' => $blade,
					'justport' => $justport,
					'port' => $port,
					'graph_id' => $graph,
					'vlans' => ''
				                                          ]
				           ), __LINE__, __FILE__);
			}
			else
			{
				$db->next_record();
				if (($db->Record['blade'] != $blade) || ($db->Record['justport'] != $justport))
				{
					if ($verbose == TRUE)
						add_output("\nUpdate BladePort");
					$query = "update switchports set blade='{$blade}', justport='{$justport}' where switch='{$id}' and port='{$port}'";
					//echo $query;
					$db->query($query);
				}
				if ($verbose == TRUE)
					add_output("$port ");
				if ($db->Record['graph_id'] != $graph)
				{
					if ($verbose == TRUE)
						add_output("\nUpdate Graph");
					$query = "update switchports set graph_id='{$graph}' where switch='{$id}' and port='{$port}'";
					//echo $query;
					$db->query($query);
				}
				if ($verbose == TRUE)
					add_output("$graph ");
			}
			$query = "select * from vlans where vlans_ports like '%:{$row['id']}/{$justport}:%' or vlans_ports like '%:{$row['id']}/{$port}:%'";
			//echo "$query\n";
			$db->query($query);
			$vlans = [];
			while ($db->next_record())
			{
				$vlans[] = $db->Record['vlans_id'];
			}
			if (count($vlans) > 0)
			{
				if ($verbose == TRUE)
					add_output('('.count($vlans).' Vlans)');
				$vlantext = implode(',', $vlans);
				$db->query("update switchports set vlans='' where vlans='{$vlantext}'");
				$db->query("update switchports set vlans='{$vlantext}' where switch='{$id}' and port='{$port}'");
				if ($db->affected_rows())
					if ($verbose == TRUE)
						add_output("\nUpdate Vlan");
			}
			if ($verbose == TRUE)
				add_output(',');
		}
		if ($verbose == TRUE)
			add_output("\n");
	}
	//print_r($switches);
	global $output;
	echo str_replace("\n", "<br>\n", $output);
	$output = '';
}

/**
* converts a network like say 66.45.228.0/24 into a gateway address
* @param string $network returns a gateway address from a network address in the format of  [network ip]/[subnet] ie  192.168.1.128/23
* @return string gateway address ie 192.168.1.129 or 66.45.228.1
*/
function network2gateway($network) {
	list($ipAddress, $subnet) = explode('/', $network);
	return ipnetmask2gateway($ipAddress, subnet2netmask($subnet));
}

/**
* converts a ip address and netmask into a gateway address
* @param string $ipAddress the networks ip address or any ip within the network
* @param string $netmask the netmask for the network ie 255.255.255.0
* @return string gateway address ie 192.168.1.129 or 66.45.228.1
*/
function ipnetmask2gateway($ipAddress, $netmask) {
	return long2ip((ip2long($ipAddress) & ip2long($netmask))+1);
}

/**
* converts a subnet into a netmask ie 24 to convert a /24 to 255.255.255.0
* @param string $subnet the network size
* @return string the netmask for the ip block
*/
function subnet2netmask($subnet) {
	return long2ip(-1 << (32 - (int)$ipAddress_subnet));
}

/**
 * @param $data
 * @return array
 */
function parse_vlan_ports($data) {
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
function get_switch_name($index, $short = FALSE) {
	$db = get_module_db(IPS_MODULE);
	$db->query("select * from switchmanager where id='{$index}'");
	$db->next_record();
	$switch = $db->Record['name'];
	if ($short == FALSE) {
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
function get_select_ports($ports = FALSE, $size = 5) {
	$db = get_module_db(IPS_MODULE);
	if ($ports === FALSE) {
		$ports = [];
	}
	$select = '<select multiple="multiple" size='.$size.' name="ports[]">';
	$db->query('select * from switchmanager as sm, switchports as sp where switch=id order by id desc');
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
 * @param $netmask
 * @return int
 */
function get_ipcount_from_netmask($netmask) {
	$ipinfo = [];
	//error_log("Calling ipcalc here");
	$path = INCLUDE_ROOT.'/../scripts/licenses';
	$result = trim(`LANG=C $path/ipcalc -nb 192.168.0.0/$netmask | grep Hosts | cut -d" " -f2`);
	return (int)$result;
}

/**
 * @param $networks
 * @return mixed
 */
function ipcalc_array($networks) {
	$cmd = "function a() {\n";
	$path = INCLUDE_ROOT.'/../scripts/licenses';
	for ($x = 0, $x_max = count($networks); $x < $x_max; $x++) {
		//error_log("Calling ipcalc here");
		$cmd .= 'LANG=C $path/ipcalc -nb '.$networks[$x]['network'].';echo :-----;';
	}
	$cmd .= "}\n";
	$cmd .= 'a | grep : | sed s#" "#""#g | cut -d= -f1 | cut -d: -f2 | cut -d\( -f1 | cut -dC -f1;';
	$result = trim(`$cmd`);
	$results = explode('-----', $result);
	for ($x = 0, $x_max = count($networks); $x < $x_max; $x++) {
		$ipinfo = [];
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

if (!function_exists('validIp')) {
	/**
	 * validIp()
	 * returns whether or not the given IP is valid
	 *
	 * @param string $ipAddress the ip address to validate
	 * @param bool $display_errors whether or not errors are displayed. defaults to TRUE
	 * @return bool whether or not its a valid ip
	 */
	function validIp($ipAddress, $display_errors = TRUE) {
		if (!preg_match("/^[0-9\.]{7,15}$/", $ipAddress)) {
			// don't display errors cuz this gets called w/ a blank entry when people didn't even submit anything yet
			//add_output('<font class="error">IP '.$ipAddress.' Too short/long</font>');
			return FALSE;
		}
		$quads = explode('.', $ipAddress);
		$num_quads = count($quads);
		if ($num_quads != 4) {
			if ($display_errors)
				add_output('<font class="error">IP '.$ipAddress.' Too many quads</font>');
			return FALSE;
		}
		for ($i = 0; $i < 4; $i++) {
			if ($quads[$i] > 255) {
				if ($display_errors)
					add_output('<font class="error">IP '.$ipAddress.' number '.$quads[$i].' too high</font>');
				return FALSE;
			}
		}
		return TRUE;
	}
}

if (!function_exists('ipcalc')) {
	/**
	 * @param $network
	 * @return array|bool
	 */
	function ipcalc($network) {
		if (trim($network) == '')
			return FALSE;
		$parts = explode('/', $network);
		if (count($parts) > 1) {
			list($block, $bitmask) = $parts;
		} else {
			$block = $parts[0];
			$bitmask = '32';
			$network = $block.'/'.$bitmask;
		}
		if (!validIp($block, FALSE) || !is_numeric($bitmask))
			return FALSE;
		if (preg_match('/^(.*)\/32$/', $network, $matches))
			return [
				'network' => $matches[1],
				'network_ip' => $matches[1],
				'netmask' => '255.255.255.255',
				'broadcast' => '',
				'hostmin' => $matches[1],
				'hostmax' => $matches[1],
				'hosts' => 1
			];
		require_once 'Net/IPv4.php';
		$network_object = new Net_IPv4();
		$net = $network_object->parseAddress($network);
		//billingd_log("|$network|", __LINE__, __FILE__);
		$ipAddress_info = [
			'network' => $net->network.'/'.$net->bitmask,
			'network_ip' => $net->network,
			'netmask' => $net->netmask,
			'broadcast' => $net->broadcast,
			'hostmin' => long2ip($net->ip2double($net->network) + 1),
			'hostmax' => long2ip($net->ip2double($net->broadcast) - 1),
			'hosts' => $net->ip2double($net->broadcast) - $net->ip2double($net->network) - 1
		];
		return $ipAddress_info;
	}

	/**
	 * @param $network
	 * @return array
	 */
	function ipcalc_old($network) {
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
		$path = INCLUDE_ROOT.'/../scripts/licenses';
		$ipinfo = [];
		//error_log("Calling ipcalc here");
		$result = trim(`LANG=C $path/ipcalc -nb $network | grep : | sed s#" "#""#g | cut -d= -f1 | cut -d: -f2 | cut -d\( -f1 | cut -dC -f1`);
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
function get_networks($text, $vlan = 0, $comment = '', $ports = '') {
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
 * @param      $part
 * @param      $ipparts
 * @param      $maxparts
 * @param bool $include_unusable
 * @return bool
 */
function check_ip_part($part, $ipparts, $maxparts, $include_unusable = FALSE) {
	if ($include_unusable) {
		$maxip = 256;
	} else {
		$maxip = 255;
	}
	switch ($part) {
		case 1:
			if ($ipparts[0] < $maxip) {
				if ($ipparts[0] <= $maxparts[0]) {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
			break;
		case 2:
			if ($ipparts[1] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1])) {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
			break;
		case 3:
			if ($ipparts[2] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2])) {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
			break;
		case 4:
			if ($ipparts[3] < $maxip) {
				if (($ipparts[0] <= $maxparts[0]) && ($ipparts[1] <= $maxparts[1]) && ($ipparts[2] <= $maxparts[2]) && ($ipparts[3] <= $maxparts[3])) {
					return TRUE;
				} else {
					return FALSE;
				}
			} else {
				return FALSE;
			}
			break;
	}
}

/**
 * @return array
 */
function get_all_ipblocks() {
	$db = get_module_db(IPS_MODULE);
	$db->query('select ipblocks_network from ipblocks');
	$all_blocks = [];
	while ($db->next_record(MYSQL_ASSOC))
		$all_blocks[] = $db->Record['ipblocks_network'];
	return $all_blocks;
}

/**
 * @return array
 */
function get_client_ipblocks() {
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
function get_client_ips($include_unusable = FALSE) {
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
function get_all_ips_from_ipblocks($include_unusable = FALSE) {
	$all_blocks = get_all_ipblocks();
	$all_ips = [];
	foreach ($all_blocks as $ipblock)
		$all_ips = array_merge($all_ips, get_ips($ipblock, $include_unusable));
	return $all_ips;
}

/**
 * @param bool $include_unusable
 * @return array
 */
function get_all_ips2_from_ipblocks($include_unusable = FALSE) {
	$all_blocks = get_all_ipblocks();
	$all_ips = [];
	foreach ($all_blocks as $ipblock)
		$all_ips = array_merge($all_ips, get_ips2($ipblock, $include_unusable));
	return $all_ips;
}

/**
 * @param      $network
 * @param bool $include_unusable
 * @return array
 */
function get_ips($network, $include_unusable = FALSE) {
	//echo "$network|$include_unusable|<br>";
	$ips = [];
	$network_info = ipcalc($network);
	//_debug_array($network_info);
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
			for ($c = $minparts[2]; check_ip_part(3, [
				$a,
				$b,
				$c
			], $maxparts, $include_unusable); $c++) {
				for ($d = $minparts[3]; check_ip_part(4, [
					$a,
					$b,
					$c,
					$d
				], $maxparts, $include_unusable); $d++) {
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
function get_ips2($network, $include_unusable = FALSE) {
	//echo "$network|$include_unusable|<br>";
	$ips = [];
	$network_info = ipcalc($network);
	//_debug_array($network_info);
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
			for ($c = $minparts[2]; check_ip_part(3, [
				$a,
				$b,
				$c
			], $maxparts, $include_unusable); $c++) {
				for ($d = $minparts[3]; check_ip_part(4, [
					$a,
					$b,
					$c,
					$d
				], $maxparts, $include_unusable); $d++) {
					$ips[] = [
						$a.'.'.$b.'.'.$c.'.'.$d,
						$a,
						$b,
						$c,
						$d
					];
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
function available_ipblocks($blocksize, $location = 1) {
	// array of available blocks
	$available = [];
	$db = get_module_db(IPS_MODULE);
	// first we gotta get how many ips are in the blocksize they requested
	$ipcount = get_ipcount_from_netmask($blocksize) + 2;
	// get the ips in use
	$usedips = [];
	$mainblocks = [];
	if ($location == 1) {
		// get the main ipblocks we have routed
		$db->query('select * from ipblocks', __LINE__, __FILE__);
		while ($db->next_record()) {
			$mainblocks[] = [$db->Record['ipblocks_id'], $db->Record['ipblocks_network']];
		}
	}
	if ($location == 2) {
		$mainblocks[] = [7, '173.214.160.0/23'];
		$mainblocks[] = [8, '206.72.192.0/24'];
		$mainblocks[] = [12, '162.220.160.0/24'];
		$mainblocks[] = [15, '104.37.184.0/24'];

	} else {
		//  104.37.184.0/24 LA reserved
		$reserved = [1747302400, 1747302655];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/*  added by joe 08/24/11 to temporarily hide  173.214.160.0/23 lax1 ips */
		$reserved = [2916524033, 2916524542];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 206.72.192.0  LA
		$reserved = [3460874240, 3460874751];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 162.220.160.0/24 LA
		$reserved = [2732367872, 2732368127];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}

		// 162.216.112.0/24   LA
		$reserved = [2732093440, 2732093695];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	}
	if ($location == 3) {
		$mainblocks[] = [12, '162.220.161.0/24'];
	} else {
		// 162.220.161.0/24 NY4
		$reserved = [2732368128, 2732368383];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	}
	if ($location == 4) {
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
		// 66.45.241.16/28 reserved
		$reserved = [1110307088, 1110307103];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.38.128/25 reserved
		$reserved = [1158293120, 1158293247];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.60.192/26 reserved
		$reserved = [1158298816, 1158298879];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.56.0/25 reserved
		$reserved = [1158297600, 1158297727];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 68.168.216.0/22 reserved
		$reserved = [1151916032, 1151917055];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.57.0/24 reserved
		$reserved = [1158297856, 1158298111];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.52.72/29 reserved
		$reserved = [1158296648, 1158296655];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.52.112/29 reserved
		$reserved = [1158296688, 1158296695];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.61.64/26 reserved
		$reserved = [1158298944, 1158299007];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 68.168.212.0/24 reserved
		$reserved = [1151915008, 1151915263];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.60.0/26 reserved
		$reserved = [1158298624, 1158298687];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 68.168.222.0/24 reserved
		$reserved = [1151917568, 1151917823];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 68.168.214.0/23 reserved
		$reserved = [1151915520, 1151916031];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 69.10.53.0/24 reserved
		$reserved = [1158296832, 1158297087];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 209.159.159.0/24 reserved
		$reserved = [3516899072, 3516899327];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 66.23.224.0/24 reserved
		$reserved = [1108860928, 1108861183];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	}
	if ($location == 5) {
		$mainblocks[] = [16, '103.237.44.0/22'];
		$mainblocks[] = [17, '43.243.84.0/22'];
		$mainblocks[] = [16, '103.48.176.0/22'];
		$mainblocks[] = [17, '45.113.224.0/22'];
		$mainblocks[] = [17, '45.126.36.0/22'];
		$mainblocks[] = [16, '103.197.16.0/22'];
	} else {
		/* 103.237.44.0/22 */
		$reserved = [1743596544, 1743597567];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/* 43.243.84.0/22 */
		$reserved = [737367040, 737367040];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/* 103.48.176.0/22 */
		$reserved = [1731244032, 1731245055];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/* 45.113.224.0/22 */
		$reserved = [762437632, 762438400];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/* 45.126.36.0/22 */
		$reserved = [763241472, 763242495];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		/* 103.197.16.0/22 */
		$reserved = [1740967936, 1740968959];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	}
	// la 3
	if ($location == 6) {
		$mainblocks[] = [3, '69.10.50.0/24'];
		$mainblocks[] = [18, '208.73.200.0/24'];
		$mainblocks[] = [18, '208.73.201.0/24'];
		$mainblocks[] = [20, '216.158.224.0/23'];
		$mainblocks[] = [20, '67.211.208.0/24'];
	} else {
		// 69.10.50.0/24
		$reserved = [1158296064, 1158296319];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 208.73.200.0/24
		$reserved = [3494496256, 3494496511];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 208.73.201.0/24
		$reserved = [3494496512, 3494496767];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 216.158.224.0/23
		$reserved = [3634290688, 3634291199];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 67.211.208.0/24
		$reserved = [1137954816, 1137955071];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
	}
	// Switch Subnets
	if ($location == 7) {
		$mainblocks[] = [22, '173.225.96.0/24'];
		$mainblocks[] = [22, '173.225.97.0/24'];
	} else {
		// 173.225.96.0/24
		$reserved = [2917228544, 2917228799];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
		}
		// 173.225.97.0/24
		$reserved = [2917228800, 2917229055];
		for ($x = $reserved[0]; $x < $reserved[1]; $x++) {
			$ipAddress = long2ip($x);
			$usedips[$ipAddress] = $ipAddress;
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
	$db->query('select ips_ip from ips where ips_vlan > 0', __LINE__, __FILE__);
	if ($db->num_rows()) {
		while ($db->next_record()) {
			$usedips[$db->Record['ips_ip']] = $db->Record['ips_ip'];
		}
	}
	foreach ($mainblocks as $maindata) {
		$ipblock_id = $maindata[0];
		$mainblock = $maindata[1];
		// get ips from the main block
		$ips = get_ips2($mainblock, TRUE);
		// next loop through all available ips
		$ipsize = count($ips);
		$found = FALSE;
		$found_count = 0;
		$found_c = '';
		$path = INCLUDE_ROOT.'/../scripts/licenses';
		for ($x = 0; $x < $ipsize; $x++) {
			// check if the ips in use already
			if (isset($usedips[$ips[$x][0]])) {
				$found = FALSE;
				$found_count = 0;
			} else {
				$c = $ips[$x][3];
				if ($found && ($blocksize >= 24) && ($found_c != $c)) {
					$found = FALSE;
					$found_count = 0;
				}
				if (!$found) {
					if ($blocksize <= 24) {
						if ($ips[$x][4] == 0) {
							//error_log("Calling ipcalc here");
							$cmd = 'LANG=C '.$path.'/ipcalc -n -b '.$ips[$x][0].'/'.$blocksize.' | grep Network: | cut -d: -f2';
							if (trim(`$cmd`) == $ips[$x][0].'/'.$blocksize) {
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
						$found = FALSE;
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
function ips_hostname($hostname) {
	$db = clone get_module_db(IPS_MODULE);
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
					$parts[$x] = get_switch_name($switch, TRUE).'/'.$port;
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
			$out .= $db->Record['vlans_comment'] . "\n{$db->Record['vlans_networks']}\n{$db->Record['vlans_ports']}\n" . $graph_id . "\n";
		}
	} else {
		$out .= "No vlans found\n";
	}
	return trim($out);
}
