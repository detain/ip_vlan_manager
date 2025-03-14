<?php
include 'include/functions.inc.php';
$db = get_module_db("default");
$vlans = $db->qr("select vlans_networks from vlans");
function_requirements('ipcalc');
$out = [];
foreach ($vlans as $idx => $vlanData) {
$vlan = str_replace(':', '', $vlanData['vlans_networks']);
//echo "Working on VLAN {$vlanData['vlans_networks']} => {$vlan}\n";
$info = ipcalc($vlan);
$out[$vlan] = $info;
}
file_put_contents('ipcalc_2.json', json_encode($out, JSON_PRETTY_PRINT));
