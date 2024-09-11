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

function add_contact($data, &$contacts) {
    if (!is_null($data['account_id'])) {
        $contact = [];
        foreach (array_keys($contact) as $field) {
            if (isset($data['account_'.$field]) && !empty($data['account_'.$field])) {
                $contact[$field] = $data['account_'.$field];
            }
        }
        $contacts[$data['account_id']] = $contact;        
    }
}

function add_range($ipblock, &$data) {
    $range = \IPLib\Range\Subnet::parseString($ipblock);
    $data['gateway'] = $range->getAddressAtOffset(1)->toString();   // 69.10.61.65          2604:a00::1
    $data['size'] = $range->getSize();                              // 64                   79228162514264337593543950336
    $data['prefix'] = $range->getNetworkPrefix();                   // 26                   32
    if ($range->getAddressType() == 4) { 
        $data['subnet'] = $range->getSubnetMask()->toString();      // 255.255.255.192
    }   
    $data['start'] = $range->getStartAddress()->toString();         // 69.10.61.64          2604:a00::
    $data['end'] = $range->getEndAddress()->toString();             // 69.10.61.127         2604:a00:ffff:ffff:ffff:ffff:ffff:ffff  */
}

include_once(__DIR__.'/../../../../include/functions.inc.php');
include_once(__DIR__.'/ip.functions.inc.php');

// initialize variables
$db = $GLOBALS['tf']->db;
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
    add_range($db->Record['ipblocks_network'], $db->Record);
    $ipblocks[6][$db->Record['ipblocks_id']] = $db->Record;     
}
$db->query("select ipblocks.*, accounty_id, account_address, account_city, account_state, account_zip, account_country, account_phone, account_company from ipblocks
left join accounts on account_id=ipblocks_custid");
while ($db->next_record(MYSQL_ASSOC))
{
    $db->Record['vlans'] = [];
    $db->Record['referral'] = [];
    add_range($db->Record['ipblocks_network'], $db->Record);
    $ipblocks[4][$db->Record['ipblocks_id']] = $db->Record;
    add_contact($db->Record, $contacts);
}
$db->query("select vlans6.*, accounty_id, account_address, account_city, account_state, account_zip, account_country, account_phone, account_company from vlans6 
left join switchports on vlans6.vlans6_id=switchports.vlans6 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    add_range($db->Record['vlans6_networks'], $db->Record);
    $ipblocks[6][1]['vlans'][$db->Record['vlans6_id']] = $db->Record;
    add_contact($db->Record, $contacts);
}
$db->query("select vlans.*, accounty_id, account_address, account_city, account_state, account_zip, account_country, account_phone, account_company from vlans 
left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    add_range(str_replace(':', '', $db->Record['vlans_networks']), $db->Record);
    $ipblocks[4][$db->Record['vlans_block']]['vlans'][$db->Record['vlans_id']] = $db->Record;     
    add_contact($db->Record, $contacts);
}
echo json_encode([
    'contacts' => $contacts,
    'ipblocks' => $ipblocks
]);