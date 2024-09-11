<?php
/**
* VLAN to rWhois Generator
* 
* @todo
* * adding improved setting up of organizations and contacts.
* * adding ipv6 blocks.
* * improving the vlan listings we have,
* * improving the way the script rebuilds all the data files.
* * fixing some schema definitions
* * use real or private contact info for each user based on account setting
* if you want i could add vps network definitions as well
*/
include_once(__DIR__.'/../../../../include/functions.inc.php');
include_once(__DIR__.'/ip.functions.inc.php');
// initialize variables
$db = $GLOBALS['tf']->db;
$typeDirs = ['domain' => 'a.com', 'net' => 'net-fd00%3A1234%3A%3A-32'];
$contacts = ['hostmaster' => []];
$privateData = true;
$cmds = '';
$total = 0;
$totalVlans = 0;
$totalAvailableIps = 0;
$nets = [];
$ipblocks = [ 4 => [], 6 => [] ];
$serial = date('YmdHis') . '000';
//$serial = '19961101000000000';
$authArea = [];
// gather data
$db->query("select * from ipblocks6", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $db->Record['vlans'] = [];
    $db->Record['referral'] = [];
    $ipblocks[6][$db->Record['ipblocks_id']] = $db->Record;     
}
$db->query("select * from ipblocks");
while ($db->next_record(MYSQL_ASSOC))
{
    $db->Record['vlans'] = [];
    $db->Record['referral'] = [];
    $ipblocks[4][$db->Record['ipblocks_id']] = $db->Record;
}
$db->query("select * from vlans6", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $ipblocks[6][1]['vlans'][$db->Record['vlans6_id']] = $db->Record;     
}
$db->query("select * from vlans", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $ipblocks[4][$db->Record['vlans_block']]['vlans'][$db->Record['vlans_id']] = $db->Record;     
}
// generate output from data
foreach ($ipblocks as $blockType => $typeBlocks) {
    foreach ($typeBlocks as $blockId => $blockData) {
        $networks = [];
        $ipblock = $db->Record['ipblocks_network'];
        $range = \IPLib\Range\Subnet::parseString($ipblock);
        $contact = 'hostmaster';
        // $contact = $custid;
        $netDir = 'net-'.str_replace($range->toString(), '/', '-');
    }
}

/* $range = \IPLib\Range\Subnet::parseString($ipblock);
$range->toString();                         // 69.10.61.64/26       2604:a00::/32
$range->getAddressType();                   // 4                    6
$range->getAddressAtOffset(0)->toString();  // 69.10.61.64          2604:a00::
$range->getAddressAtOffset(1)->toString();  // 69.10.61.65          2604:a00::1
$range->getSize();                          // 64                   79228162514264337593543950336
$range->getNetworkPrefix();                 // 26                   32 
$range->getSubnetMask()->toString();        // 255.255.255.192   
$range->getStartAddress()->toString();      // 69.10.61.64          2604:a00::
$range->getEndAddress()->toString();        // 69.10.61.127         2604:a00:ffff:ffff:ffff:ffff:ffff:ffff  */


foreach ($ipblocks as $ipblock => $blockData) {
    //echo "Generating IP Block {$ipblock}\n";
    $vlandata = explode('/', $ipblock);
    $ip = $vlandata[0];
    $size = $vlandata[1];
    $nets[] = $ipblock;
    $network_info = ipcalc($ipblock);
    $totalAvailableIps += $network_info['hosts'];
}
//$nets = implode(' ', $nets);
//$cmds .= "echo '$nets' > ipblocks.txt;\n";
echo "Building VLAN data";
$db->query("select * from vlans6 
left join switchports on vlans6.vlans6_id=switchports.vlans6 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid 
where account_id is not null", __LINE__, __FILE__);
$db->query("select * from vlans 
left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid 
where account_id is not null", __LINE__, __FILE__);
echo "\n";
/* Table        - Fields
 * vlans        - vlans_id, vlans_block, vlans_networks, vlans_ports, vlans_comment, vlans_primary, vlans_ip, 
 * ipblocks     - ipblocks_id, ipblocks_network, ipblocks_location, 
 * switchports  - switchport_id, switch, blade, justport, port, graph_id, vlans, server_id, asset_id, updated, vlans6,
 * assets       - id, order_id, hostname, status, primary_ipv4, primary_ipv6, mac, datacenter, type_id, asset_tag, 
                    rack, row, col, unit_start, unit_end, unit_sub, ipmi_mac, ipmi_ip, ipmi_admin_username,
                    ipmi_admin_password, ipmi_client_username, ipmi_client_password, ipmi_updated, ipmi_working, 
                    company, comments, make, model, description, customer_id, external_id, billing_status, overdue,
                    monthly_price, create_timestamp, update_timestamp, mp_status_updated_at, 
 * servers      - server_id, server_hostname, server_custid, server_type, server_currency, server_order_date, 
                    server_invoice, server_coupon, server_status, server_root, server_dedicated_tag, server_custom_tag,
                    server_comment, server_initial_bill, server_hardware, server_ips, server_monthly_bill, server_setup,
                    server_discount, server_rep, server_date, server_total_cost, server_location, server_hardware_ordered,
                    server_billed, server_welcome_email, server_dedicated_cpu, server_dedicated_memory, server_dedicated_hd1,
                    server_dedicated_hd2, server_dedicated_bandwidth, server_dedicated_ips, server_dedicated_os, 
                    server_dedicated_cp, server_dedicated_raid, server_extra, 
 * accounts      - account_id, account_lid, account_passwd, account_group, account_status, account_ima, account_name,
                    account_address, account_city, account_state, account_zip, account_country, account_phone,
                    account_fraudrecord_score, account_maxmind_riskscore, account_payment_method, account_pin,
                    account_disable_cc
*/
while ($db->next_record(MYSQL_ASSOC)) {
    $ipblock = $db->Record['ipblocks_network'];         
    list($ipblock_ip, $ipblock_size) = explode('/', $ipblock);
    $vlan = str_replace(':', '', $db->Record['vlans_networks']);
    //echo "Generating VLAN {$vlan}\n";
    list($ipAddress, $size) = explode('/', $vlan);
    $network_info = ipcalc($vlan);
    $maxip = $network_info['broadcast'];
    $totalVlans++;
    
    $total += $network_info['hosts'];
    if ($privateData) {        
        $org = 'Private Customer';
        $data = [
            'address' => 'na',
            'city' => 'na',
            'state' => 'na',
            'country' => 'na',
            'phone' => 'na',
            'zip' => 'na',
            'company' => 'na',
        ];
    } else {
        $data = $GLOBALS['tf']->accounts->read($group);
        $org = !empty($data['company']) ? $data['company'] : 'Account ' . $db->Record['account_id'];
    }
    $cmds .= 'cd '.$installDir.'/etc/rwhoisd/net-'.$ipblock_ip.'-'.$ipblock_size.';\n'
        . 'echo -e "ID: NETBLK-INTSRV.'.$ipblock.'\n'
        . 'Auth-Area: '.$ipblock.'\n'
        . 'Org-Name: '.trim($org).'\n'
        . 'Street-Address: '.trim($data['address']).'\n'
        . 'City: '.$data['city'].'\n'
        . 'State: '.$data['state'].'\n'
        . 'Postal-Code: '.$data['zip'].'\n'
        . 'Country-Code: '.$data['country'].'\n'
        . 'Phone: '.trim($data['phone']).'\n'
        . 'Created: '.(is_null($db->Record['server_order_date']) ? '20050101' : date('Ymd', $db->fromTimestamp($db->Record['server_order_date']))) . '\n'
        . 'Updated: '.date('Ymd').'" > data/org/'.$db->Record['account_id'].'.txt;\n'
        . 'echo -e "ID: NETBLK-INTSRV.'.$ipblock.'\n'
        . 'Auth-Area: '.$ipblock.'\n'
        . 'Network-Name: INTSRV-'.$ipAddress.'\n'
        . 'IP-Network: '.$ipAddress.'/'.$size.'\n'
        . 'Org-Name: '.trim($org).'\n'
        . 'Street-Address: '.$data['address'].'\n'
        . 'City: '.$data['city'].'\n'
        . 'State: '.$data['state'].'\n'
        . 'Postal-Code: '.$data['zip'].'\n'
        . 'Country-Code: '.$data['country'].'\n'
        . 'Created: 20050101\n'
        . 'Updated: '.date('Ymd').'\n'
        . 'Updated-By: abuse@interserver.net" > data/network/'.$ipAddress.'-'.$size.'.txt;\n';
}
