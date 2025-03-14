<?php
include 'include/functions.inc.php';
$db = get_module_db("default");
$vlans = $db->qr("select vlans_networks from vlans");
$out = [];
foreach ($vlans as $idx => $vlanData) {
$vlan = str_replace(':', '', $vlanData['vlans_networks']);
//echo "Working on VLAN {$vlanData['vlans_networks']} => {$vlan}\n";
    if (preg_match('/^(.*)\/32$/', $vlan, $matches)) {
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
$range = \IPLib\Factory::rangeFromString($vlan);
if (is_null($range)) {
  $info = false;
} else {
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
}
    }

$out[$vlan] = $info;
}
file_put_contents('ipcalc_1.json', json_encode($out, JSON_PRETTY_PRINT));
