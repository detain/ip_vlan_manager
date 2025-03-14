<?php
/**
* IP functions
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package MyAdmin
* @category IPs 
* 
*/

/**
* returns an array containing the list of ip blocks and a list of iused ips based on the location passed
*
* @param int $location location defaults to 1
* @return array returns array like [$mainBlocks, $usedIps]
*/
function get_mainblocks_and_usedips($location = 1)
{
    $db = get_module_db('default');
    // get the ips in use
    $usedIps = [];
    $mainBlocks = [];
    $reserved = [];
    $others = [];
    // get the main ipblocks we have routed
    $db->query('select * from ipblocks', __LINE__, __FILE__);
    while ($db->next_record()) {
        if ($db->Record['ipblocks_location'] == $location) {
            $mainBlocks[] = [(int)$db->Record['ipblocks_id'], $db->Record['ipblocks_network']];
        } else {
            $others[] = $db->Record['ipblocks_network'];
        }
    }
    foreach ($mainBlocks as $block) {
        $range1 = \IPLib\Factory::parseRangeString($block[1]);
        foreach ($others as $other) {
            $range2 = \IPLib\Factory::parseRangeString($other);
            if ($range1->containsRange($range2)) {
                $reserved[] = [ip2long($range2->getAddressAtOffset(0)->toString()), ip2long($range2->getEndAddress()->toString())];
            }
        }
    }
    foreach ($reserved as $idx => $reserve) {
        for ($x = $reserve[0]; $x < $reserve[1]; $x++) {
            $ipAddress = long2ip($x);
            $usedIps[$ipAddress] = $ipAddress;
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
    $ipcount = get_ipcount_from_netmask($blocksize);
    [$mainBlocks, $usedIps] = get_mainblocks_and_usedips($location);
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
* @param int $blocksize desired block size, ie 32
* @param int $location location id
* @param bool $ipv6 false(default) returns IPv4 blocks, true returns IPv6 blocks
* @return array
*/
function available_ipblocks_new($blocksize, $location = 1, $ipv6 = false)
{
    // array of available blocks
    $available = [];
    // first we gotta get how many ips are in the blocksize they requested
    $ipcount = get_ipcount_from_netmask($blocksize, $ipv6);
    [$mainBlocks, $usedIps] = get_mainblocks_and_usedips($location);
    $freeBlocks = calculate_free_blocks($mainBlocks, $usedIps);
    foreach ($freeBlocks as $freeBlockData) {
        [$freeBlock, $blockId] = $freeBlockData;
        if ($freeBlock->getNetworkPrefix() == $blocksize) {
            $available[] = [$freeBlock->getStartAddress()->toString(), $blockId];
        } elseif ($freeBlock->getNetworkPrefix() < $blocksize) {
            for ($x = 0, $fitBlocks = get_ipcount_from_netmask($freeBlock->getNetworkPrefix(), $ipv6) / $ipcount; $x < $fitBlocks; $x++) {
                $available[] = [$freeBlock->getAddressAtOffset($x * $ipcount)->toString(), $blockId];
            }
        }
    }
    return $available;
}

/**
* generates a list of ip ranges of free ips
*
* @param array $mainBlocks arrray of ipblocks and ids
* @param array $usedIps array of used ips
* @return array
*/
function calculate_free_blocks($mainBlocks, $usedIps)
{
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
                if (!isset($usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]])) {
                    $usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]] = 0;
                }
                $usedIpCounts[$ip[1].'.'.$ip[2].'.'.$ip[3]]++;
                if ($startIp !== false) {
                    $freeRanges[] = [$startIp, $endIp, $maindata[0]];
                }
                $startIp = false;
                $endIp = false;
            } else {
                if ($startIp === false) {
                    $startIp = $ip[0];
                }
                $endIp = $ip[0];
            }
        }
        if ($startIp !== false) {
            $freeRanges[] = [$startIp, $endIp, $maindata[0]];
        }
        $startIp = false;
        $endIp = false;
    }
    unset($startIp, $endIp, $ips, $ip);
    foreach ($freeRanges as $freeRange) {
        $ranges = \IPLib\Factory::rangesFromBoundaries($freeRange[0], $freeRange[1]);
        foreach ($ranges as $range) {
            if (in_array($range->getNetworkPrefix(), [31])) {
                for ($x = 0, $xMax = $range->getSize(); $x < $xMax; $x++) {
                    $rangeNew = \IPLib\Factory::rangeFromString($range->getAddressAtOffset($x)->toString().'/32');
                    //$rangeNew->BlockId = $freeRange[2];
                    $returnRanges[] = [$rangeNew, $freeRange[2]];
                }
            } else {
                //$range->BlockId = $freeRange[2];
                $returnRanges[] = [$range, $freeRange[2]];
            }
        }
    }
    unset($freeRanges, $freeRange, $range, $ranges);
    usort($returnRanges, 'ip_range_sort_desc');
    return $returnRanges;
}

/**
* performs an ascending sort on the given ranges based on block size (ie /24) and number of used ips in its class c
*
* @param array $ranges
*/
function ip_range_sort_asc($rangeA, $rangeB)
{
    if ($rangeA[0]->getNetworkPrefix() > $rangeB[0]->getNetworkPrefix()) {
        return 1;
    }
    if ($rangeA[0]->getNetworkPrefix() < $rangeB[0]->getNetworkPrefix()) {
        return -1;
    }
    $cClassA = substr($rangeA[0]->getStartAddress()->toString(), 0, strrpos($rangeA[0]->getStartAddress()->toString(), '.'));
    $cClassB = substr($rangeB[0]->getStartAddress()->toString(), 0, strrpos($rangeB[0]->getStartAddress()->toString(), '.'));
    global $usedIpCounts;
    $countA = $usedIpCounts[$cClassA] ?? 0;
    $countB = $usedIpCounts[$cClassB] ?? 0;
    return strcmp($countB, $countA);
}

/**
* performs a descending sort on the given ranges based on block size (ie /24) and number of used ips in its class c
*
* @param array $ranges
*/
function ip_range_sort_desc($rangeA, $rangeB)
{
    if ($rangeA[0]->getNetworkPrefix() < $rangeB[0]->getNetworkPrefix()) {
        return 1;
    }
    if ($rangeA[0]->getNetworkPrefix() > $rangeB[0]->getNetworkPrefix()) {
        return -1;
    }
    $cClassA = substr($rangeA[0]->getStartAddress()->toString(), 0, strrpos($rangeA[0]->getStartAddress()->toString(), '.'));
    $cClassB = substr($rangeB[0]->getStartAddress()->toString(), 0, strrpos($rangeB[0]->getStartAddress()->toString(), '.'));
    global $usedIpCounts;
    $countA = $usedIpCounts[$cClassA] ?? 0;
    $countB = $usedIpCounts[$cClassB] ?? 0;
    return strcmp($countB, $countA);
}

/**
* converts a network like say 66.45.228.0/24 into a gateway address
* @param string $network returns a gateway address from a network address in the format of  [network ip]/[subnet] ie  192.168.1.128/23
* @return string gateway address ie 192.168.1.129 or 66.45.228.1
*/
function network2gateway($network)
{
    return \IPLib\Factory::rangeFromString($network)->getAddressAtOffset(1)->toString();
}

/**
* returns a subnet/cidr/network prefix from the netmask
*
* @param string $netmask the subnet mask (ie 255.255.255.0)
*/
function netmask2subnet($netmask)
{
    // xor-ing will give you the inverse mask, log base 2 of that +1 will return the number of bits that are off in the mask and subtracting from 32 gets you the cidr notation
    return 32-log((ip2long($netmask) ^ ip2long('255.255.255.255'))+1, 2);
}

/**
* gets the number of ips for a given netmask or bitmask.  can pass it a blocksize (ie 24) or netmask (ie 255.255.255.0)
*
* @param string|int $netmask a netmask or block size
* @param bool $ipv6 false(default) returns IPv4 blocks, true returns IPv6 blocks
* @return int the number of ips in within a range using this netmask or blocksize
*/
function get_ipcount_from_netmask($netmask, $ipv6 = false)
{
    if (!is_numeric($netmask)) {
        $netmask = netmask2subnet($netmask);
    }
    $netmask = (int)$netmask;
    $range = \IPLib\Factory::rangeFromString(($ipv6 === false ? '10.0.0.0/' : '1::/').$netmask);
    if (is_null($range)) {
        return 0;
    }
    $count = $range->getSize();
    return $count;
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
function get_ips_newer($network, $include_unusable = false)
{
    $ips = [];
    $range = \IPLib\Range\Subnet::fromString($network);
    if (is_null($range)) {
        return $ips;
    }
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
function get_ips2_newer($network, $include_unusable = false)
{
    $ips = [];
    $range = \IPLib\Range\Subnet::fromString($network);
    if (is_null($range)) {
        return $ips;
    }
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
    if ($db->num_rows() > 0) {
        $db->next_record();
        $switch = $db->Record['name'];
        if ($short == false) {
            return 'Switch '.$switch;
        } else {
            return $switch;
        }
    } else {
        myadmin_log('myadmin', 'error', "get_switch_name({$index}) called with no matching id", __LINE__, __FILE__);
        return 'Switch DB ID '.$index;
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
        if (is_array($ports) && in_array($switch.'/'.$port, $ports)) {
            $select .= '<option selected value="'.$switch.'/'.$port.'">Switch '.$db->Record['name'].' Port '.$port.'</option>';
        } else {
            $select .= '<option value="'.$switch.'/'.$port.'">Switch '.$db->Record['name'].' Port '.$port.'</option>';
        }
    }
    $select .= '</select>';
    return $select;
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
        [$block, $bitmask] = $parts;
    } else {
        $block = $parts[0];
        $bitmask = '32';
        $network = $block.'/'.$bitmask;
    }
    if (!validIp($block, false) || !is_numeric($bitmask)) {
        return false;
    }
    if (preg_match('/^(.*)\/32$/', $network, $matches)) {
        $info = [
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
    } else {
        $info = ipcalcIPLib($network);
        $info = ipcalcNetIPv4($network);
        $info = ipcalcIPTools($network);
        $info = ipcalcBinary($network);
    }
    return $info;
}

/**
* Gets the Network information from a network address using the IPLib library.
* 
* @param string $network network address in form of 66.45.228.160/28
* @return bool|array false on error or returns an array containing the network info
*/
function ipcalcIPLib($network) {
    $range = \IPLib\Factory::rangeFromString($network);
    if (is_null($range)) {
        return false;
    }
    $info = [
        'network' => $range->toString(),
        'network_ip' => $range->getStartAddress()->toString(),
        'bitmask' => $range->getNetworkPrefix(),
        'netmask' => $range->getSubnetMask()->toString(),
        'broadcast' => $range->getEndAddress()->toString(),
        'hostmin' => $range->getAddressAtOffset(1)->toString(),
        'hostmax' => $range->getAddressAtOffset($range->getSize() - 2)->toString(),
        'first_usable' => $range->getSize() > 2 ? $range->getAddressAtOffset(2)->toString() : false,
        'gateway' => $range->getAddressAtOffset(1)->toString(),
        'hosts' => $range->getSize() - 2,
    ];
    return $info;
}

/**
* Gets the Network information from a network address using the Net/IPv4 library.
* 
* @param string $network network address in form of 66.45.228.160/28
* @return bool|array false on error or returns an array containing the network info
*/
function ipcalcNetIPv4($network) {
    require_once 'Net/IPv4.php';
    $network_object = new Net_IPv4();
    $net = $network_object->parseAddress($network);
    $info = [
        'network' => $net->network.'/'.$net->bitmask,
        'network_ip' => $net->network,
        'bitmask' => (int)$net->bitmask,
        'netmask' => $net->netmask,
        'broadcast' => $net->broadcast,
        'hostmin' => long2ip($net->ip2double($net->network) + 1),
        'hostmax' => long2ip($net->ip2double($net->broadcast) - 1),
        'first_usable' => $net->bitmask == 31 ? false : long2ip($net->ip2double($net->network) + 2),
        'gateway' => long2ip($net->ip2double($net->network) + 1),
        'hosts' => (int)$net->ip2double($net->broadcast) - (int)$net->ip2double($net->network) - 1
    ];
    return $info;

}

/**
* Gets the Network information from a network address using the IPTools library.
* 
* @param string $network network address in form of 66.45.228.160/28
* @return bool|array false on error or returns an array containing the network info
*/
function ipcalcIPTools($network) {
    try {
        $net = \IPTools\Network::parse($network);
    } catch (\Exception $e) {
        return false;
    }
    $hosts = $net->getHosts();
    if ($net->getBlockSize() > 1)
        $hosts->next();
    $info = [
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
    return $info;
}

/**
* Gets the Network information from a network address using the ipcalc Binary from url in link below
*  
* @link https://raw.githubusercontent.com/kjokjo/ipcalc/refs/heads/master/ipcalc* 
* @param string $network network address in form of 66.45.228.160/28
* @return bool|array false on error or returns an array containing the network info
*/
function ipcalcBinary($network) {
    $result = trim(`LANG=C ipcalc -nb {$network} | grep : | sed s#" "#""#g | cut -d= -f1 | cut -d: -f2 | cut -d\( -f1 | cut -dC -f1`);
    $lines = explode("\n", $result);
    $netparts = explode('/', $lines[3]);
    $info = [
        'network' => $lines[3],
        'network_ip' => $netparts[0],
        'bitmask' => $netparts[1],
        'netmask' => $lines[1],
        'wildcard' => $lines[2],
        'broadcast' => $lines[6],
        'hostmin' => $lines[4],
        'hostmax' => $lines[5],
        'hosts' => $lines[7],
    ];
    return $info;    

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
function ipcalc_old($network)
{
    if (trim($network) == '') {
        return false;
    }
    $parts = explode('/', $network);
    if (count($parts) > 1) {
        [$block, $bitmask] = $parts;
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
        'bitmask' => (int)$net->bitmask,
        'netmask' => $net->netmask,
        'broadcast' => $net->broadcast,
        'hostmin' => long2ip($net->ip2double($net->network) + 1),
        'hostmax' => long2ip($net->ip2double($net->broadcast) - 1),
        'first_usable' => $net->bitmask == 31 ? false : long2ip($net->ip2double($net->network) + 2),
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
* returns an array of all the ip blocks in the database
*
* @return array array of ip blocks
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
* returns array of client ip blocks
*
* @return array array of client ip blocks
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
