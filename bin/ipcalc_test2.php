<?php
include __DIR__.'/../../../../include/functions.inc.php';
function_requirements('ipcalc');
$db = get_module_db("default");
$maxLoops = 20;
$extras = ['1.2.3.4.5/24', '1.2.3.256/24', '1.2.3.4/25', '0.1.1.1/24', '', false, null, '', ' ', '1.2.3.4', ' 1.2.3.4/24', ':1.2.3.4/24:', true, [], 1.324, 24, '2604:a00::/32'];
$vlans = [];
echo "Loading VLANs...\n";
$db->query("select replace(vlans_networks, ':', '') from vlans");
while ($db->next_record(MYSQL_NUM)) {
    $vlans[] = $db->f(0);
}
$vlans = array_merge($vlans, $extras);
$countChecks = count($vlans) * $maxLoops;
echo "Starting Tests on {$countChecks} IP Block Lookups with each method\n";
foreach (['IPLib', 'NetIPv4', 'IPTools', 'Binary'] as $name) {
    $method = constant('IPCALC_'.strtoupper($name));
    $start = microtime(true);
    $out = [];
    for ($loop = 0; $loop < $maxLoops; $loop++) {
        foreach ($vlans as $vlan) {
            //echo "Working on VLAN ".var_export($vlan,true)."\n";
            $info = ipcalc($vlan, $method);
            $out[is_array($vlan) ? json_encode($vlan) : $vlan] = $info;
        }
    }
    $end = microtime(true);
    $time = $end - $start;
    $file = 'ipcalc_'.strtolower($name).'.json';
    echo "IPCalc [{$method}] Finished {$countChecks} lookups in {$time} seconds.  Results stored in {$file}\n";
    file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT));
}
