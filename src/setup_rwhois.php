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
$domains = ['interserver.net'];
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
foreach ($mkdirs as $mkdir) {
    @mkdir($mkdir, 0644, true);
}
foreach ($domains as $domain) {
    // write domain soa
    file_put_contents($installDir.'/'.$domain.'/soa', $soa);
    // write domain schema
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
Created:{$serial}
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
Created:{$serial}
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
Created:{$serial}
Updated:19961023
Updated-By:hostmaster@{$domain}";
    $guardian = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: {$serial}
Updated: 19961023
Updated-By: hostmaster@{$domain}
Private:true";
    $host[] = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: {$serial}
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
Created: {$serial}
Updated: 19961023
Updated-By: hostmaster@{$domain}";
    $referral = "ID:888.{$domain}
Auth-Area: {$domain}
Guardian:444.{$domain}
Referral:rwhois://rwhois.second.{$domain}:4321/Auth-Area=fddi.{$domain}
Organization:777.{$domain}
Referred-Auth-Area:fddi.{$domain}
Created:{$serial}
Updated:19961023
Updated-By:hostmaster@{$domain}";                             
    
}


file_put_contents($installDir.'/rwhoisd.auth_area', implode("\n---\n", $authArea));
file_put_contents($installDir.'/'.$netDir.'/data/referral/referral.txt', '');
file_put_contents($installDir.'/'.$netDir.'/soa', $soa);
// write net soa file
// write net schema file
// write net network.txt
