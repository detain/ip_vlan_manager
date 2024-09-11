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
$json = file_get_contents('https://mynew.interserver.net/ajax_rwhois.php');
file_put_contents('rwhois.json', $json);
//$json = json_decode(file_get_contents('rwhois.json'), true);
$json = json_decode($json, true);
$samplePrefix = 'https://raw.githubusercontent.com/arineng/rwhoisd/master/rwhoisd/sample.data/';
$typeDirs = ['domain' => 'a.com', 'net' => 'net-fd00%3A1234%3A%3A-32'];
$defs = [ 'domain' => ['asn', 'contact', 'domain', 'guardian', 'host', 'org', 'referral'], 'net' => ['contact', 'guardian', 'host', 'network', 'referral'] ];
$templates = ['domain' => [], 'net' => []];
$intervals = [ 'refresh' => 3600, 'increment' => 1800, 'retry' => 60, 'ttl' => 86400 ];
$installDir = '/home/rwhois/bin';
$serial = date('YmdHis');
$privateData = true;
foreach ($defs as $defType => $typeDefs) {
    foreach ($typeDefs as $def) {
        $templates[$defType][$def] = file_get_contents($samplePrefix.$typeDirs[$defType].'/attribute_defs/'.$def.'.tmpl');
    }
}
$out = [
    'domains' => [],
    'nets' => [],
    'authArea' => [],
    'mkdirs' => [],
    'soa' => "Serial-Number:{$serial}
Refresh-Interval:{$intervals['refresh']}
Increment-Interval:{$intervals['increment']}
Retry-Interval:{$intervals['retry']}
Time-To-Live:{$intervals['ttl']}
Primary-Server:rwhois.trouble-free.net:4321
Hostmaster:hostmaster@interserver.net",
];
// generate output from data
foreach ($ipblocks as $blockType => $typeBlocks) {
    foreach ($typeBlocks as $blockId => $blockData) {
        $networks = [];
        $ipblock = $db->Record['ipblocks_network'];
        $range = \IPLib\Range\Subnet::parseString($ipblock);
        $contact = 'hostmaster';
        // $contact = $custid;
        $netDir = 'net-'.str_replace($range->toString(), '/', '-');
        $netName = 'NETBLK-'.$range->toString();
$authArea[] = "type: master
name: {$netName}
data-dir: {$netDir}/data
schema-file: {$netDir}/schema
soa-file: {$netDir}/soa";
        $mkdirs[] = $installDir.'/'.$netDir.'/attribute_defs';
        $mkdirs[] = $installDir.'/'.$netDir.'/data/network';
        $mkdirs[] = $installDir.'/'.$netDir.'/data/referral';
        $networks[] = "Network-Name: NETBLK-{$ipblock}
IP-Network: {$ipblock}
Organization: 777.interserver.net
Tech-Contact: hostmaster.interserver.net
Admin-Contact: {$contact}.interserver.net";
        $schema = "name:network
attributedef:{$netDir}/attribute_defs/network.tmpl
dbdir:{$netDir}/data/network
Schema-Version: {$serial}";                
    }
}
foreach (['interserver.net'] as $domain) {
    $schema = "name:contact
alias:user
alias:person
alias:mailbox
attributedef:{$domain}/attribute_defs/contact.tmpl
dbdir: {$domain}/data/contact
description:User object
# parse-program: contact-parse
Schema-Version: {$serial}
---
name:domain 
attributedef:{$domain}/attribute_defs/domain.tmpl  
dbdir: {$domain}/data/domain
description:Domain object
Schema-Version: {$serial}
---
name:host
attributedef:{$domain}/attribute_defs/host.tmpl
dbdir: {$domain}/data/host
description:Host object
Schema-Version: {$serial}
---
name:asn
attributedef:{$domain}/attribute_defs/asn.tmpl
dbdir: {$domain}/data/asn
description:Autonomous System Number object
Schema-Version: {$serial}
---
name:organization
attributedef:{$domain}/attribute_defs/org.tmpl
dbdir:{$domain}/data/org
description:Organization object
Schema-Version: {$serial}
---
name:guardian
attributedef:{$domain}/attribute_defs/guardian.tmpl
dbdir:{$domain}/data/guardian
description:Guardian Object
Schema-Version: {$serial}
---
name:referral 
attributedef:{$domain}/attribute_defs/referral.tmpl  
dbdir:{$domain}/data/referral
Schema-Version: {$serial}";
    // write domain data dirs
    $asn = "ID:111.{$domain}
Auth-Area:{$domain}
AS-Name:A-AS
AS-Number:6183
Organization:777.{$domain}
Admin-Contact:222.{$domain}
Tech-Contact:222.{$domain}
Created:19961022
Updated:19961023
Updated-by:hostmaster@{$domain}";
    $contacts[] = "ID:222.{$domain}
Auth-Area:{$domain}
Name:Public, John Q.
Email:johnq@{$domain}
Type:I
First-Name:John
Last-Name:Public
Phone:(847)-391-7926
Fax:(847)-338-0340
Organization:777.{$domain}
See-Also:http://www.{$domain}/~johnq
Created:11961022
Updated:11961023
Updated-By:hostmaster@{$domain}";
    $domain = "ID:333.{$domain}
Auth-Area:{$domain}
Guardian:444.{$domain}
Domain-Name: {$domain}
Primary-Server:5551.{$domain}
Secondary-Server:5552.{$domain}
Organization:777.{$domain}
Admin-Contact:222.{$domain}
Tech-Contact:222.{$domain}
Billing-Contact:222.{$domain}
Created:19961022
Updated:19961023
Updated-By:hostmaster@{$domain}";
    $guardian = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: 19961022
Updated: 19961023
Updated-By: hostmaster@{$domain}
Private:true";
    $host[] = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: 19961022
Updated: 19961023
Updated-By: hostmaster@{$domain}
Private:true";
    $org = "ID: 777.{$domain}
Auth-Area: {$domain}
Org-Name: A Communications, Inc.
Street-Address: #600 - 1380 Burrars St.
City: Vaner
State: CM
Postal-Code: V6Z 2H3
Country-Code: NL
Phone: (401) 555-6721
Created: 19961022
Updated: 19961023
Updated-By: hostmaster@{$domain}";
    $referral = "ID:888.{$domain}
Auth-Area: {$domain}
Guardian:444.{$domain}
Referral:rwhois://rwhois.second.{$domain}:4321/Auth-Area=fddi.{$domain}
Organization:777.{$domain}
Referred-Auth-Area:fddi.{$domain}
Created:19961022
Updated:19961023
Updated-By:hostmaster@{$domain}";                             
    
}


foreach ($mkdirs as $mkdir) {
    @mkdir($mkdir, 0644, true);
}
file_put_contents($installDir.'/rwhoisd.auth_area', implode("\n---\n", $authArea));
file_put_contents($installDir.'/'.$netDir.'/data/referral/referral.txt', '');
file_put_contents($installDir.'/'.$netDir.'/soa', $soa);
// write net soa file
// write net schema file
// write net network.txt
// write domain soa
// write domain schema

//$nets = implode(' ', $nets);
//$cmds .= "echo '$nets' > ipblocks.txt;\n";
echo "Building VLAN data";
$db->query("select * from vlans 
left join ipblocks on vlans_block=ipblocks_id 
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
file_put_contents('vlantorwhois.sh', str_replace('\n', "\n", $cmds));
echo "wrote vlantorwhois.sh

Totals:
VLANs: {$totalVlans}
IPs Assigneed to VLANs: {$total}
IPs Available: {$totalAvailableIps}
";
