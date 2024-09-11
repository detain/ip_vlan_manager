<?php
/**
* VLAN to rWhois Generator
* 
* @todo
* * use real or private contact info for each user based on account setting
* if you want i could add vps network definitions as well
*/
echo "Loading rwhoisd JSON Data\n";
if (file_exists('rwhoisd.json')) {
    $json = file_get_contents('rwhoisd.json');    
} else {
    $json = file_get_contents('https://mynew.interserver.net/ajax_rwhois.php');
    file_put_contents('rwhoisd.json', $json);
}  
$json = json_decode($json, true);
$samplePrefix = 'https://raw.githubusercontent.com/arineng/rwhoisd/master/rwhoisd/sample.data/';
$typeDirs = ['domain' => 'a.com', 'net' => 'net-fd00%3A1234%3A%3A-32'];
$domains = ['interserver.net'];
$rwhoisFiles = ['allow', 'conf', 'deny', 'dir', 'root', 'x.dir'];
$defs = [ 'domain' => ['asn', 'contact', 'domain', 'guardian', 'host', 'org', 'referral'], 'net' => ['contact', 'guardian', 'host', 'network', 'referral'] ];
$templates = ['domain' => [], 'net' => []];
$intervals = [ 'refresh' => 3600, 'increment' => 1800, 'retry' => 60, 'ttl' => 86400 ];
$installDir = '/home/rwhois/bin';
$installDir = '/home/rwhois/bin/new';
$serial = date('YmdHis');
$privateData = true;
echo "Setting up rwhoisd in {$installDir}\n";
echo "Downloading Templates\n";
foreach ($rwhoisFiles as $file) {
    $templates['rwhoisd.'.$file] = file_get_contents($samplePrefix.'rwhoisd.'.$file);
}
foreach ($defs as $defType => $typeDefs) {
    foreach ($typeDefs as $def) {
        $templates[$defType][$def] = file_get_contents($samplePrefix.$typeDirs[$defType].'/attribute_defs/'.$def.'.tmpl');
    }
}
echo "Generating Output\n";
$out = [
    'domains' => [],
    'nets' => [],
    'networks' => [],
    'authArea' => [],
    'soa' => "Serial-Number:{$serial}
Refresh-Interval:{$intervals['refresh']}
Increment-Interval:{$intervals['increment']}
Retry-Interval:{$intervals['retry']}
Time-To-Live:{$intervals['ttl']}
Primary-Server:rwhois.trouble-free.net:4321
Hostmaster:hostmaster@interserver.net",
];
echo "Writing IP Blocks\n";
// generate output from data
foreach ($json['ipblocks'] as $blockType => $typeBlocks) {
    foreach ($typeBlocks as $blockId => $blockData) {
        $networks = [];
        $ipblock = $blockData['ipblocks_network'];
        $range = \IPLib\Range\Subnet::parseString($ipblock);
        $contact = 'hostmaster';
        // $contact = $custid;
        $netDir = 'net-'.str_replace($range->toString(), '/', '-');
        $netName = 'NETBLK-'.$range->toString();
        $out['authArea'][] = "type: master
name: {$netName}
data-dir: {$netDir}/data
schema-file: {$netDir}/schema
soa-file: {$netDir}/soa";
        @mkdir($installDir.'/'.$netDir.'/attribute_defs', 0644, true);
        foreach ($defs['net'] as $suffix) {
            @mkdir($installDir.'/'.$netDir.'/data/'.$suffix, 0644, true);
        }
        $out['networks'][] = "Network-Name: NETBLK-{$ipblock}
IP-Network: {$ipblock}
Organization: 777.interserver.net
Tech-Contact: hostmaster.interserver.net
Admin-Contact: {$contact}.interserver.net";
        $schema = "name:network
attributedef:{$netDir}/attribute_defs/network.tmpl
dbdir:{$netDir}/data/network
Schema-Version: {$serial}";
        // write net schema file
        file_put_contents($installDir.'/'.$netDir.'/schema', $schema);
        // write net soa file
        file_put_contents($installDir.'/'.$netDir.'/soa', $soa);
        // write net network.txt
        file_put_contents($installDir.'/'.$netDir.'/data/referral/referral.txt', '');
    }
}
echo "Writiong Domains\n";
foreach ($json['domains'] as $domain) {
    // create attribute_defs and assorted data dirs
    @mkdir($installDir.'/'.$domain.'/attribute_defs', 0644, true);
    foreach ($defs['domain'] as $suffix) {
        mkdir($installDir.'/'.$domain.'/data/'.$suffix, 0644, true);
    }
    $out['authArea'][] = "type: master
    name: {$domain}
    data-dir: {$domain}/data
    schema-file: {$domain}/schema
    soa-file: {$domain}/soa";    
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
    file_put_contents($installDir.'/'.$domain.'/schema', $schema);
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
    file_put_contents($installDir.'/'.$domain.'/data/asn/asn.txt', $asn);
    file_put_contents($installDir.'/'.$domain.'/data/contacts/contacts.txt', implode("\n---\n", $contacts));
    file_put_contents($installDir.'/'.$domain.'/data/domain/domain.txt', $domain);
    file_put_contents($installDir.'/'.$domain.'/data/guardian/guardian.txt', $guardian);
    file_put_contents($installDir.'/'.$domain.'/data/host/host.txt', implode("\n---\n", $contacts));
    file_put_contents($installDir.'/'.$domain.'/data/org/orgl.txt', $org);
    file_put_contents($installDir.'/'.$domain.'/data/referral/referral.txt', $referral);
    foreach ($defs as $defType => $typeDefs) {
        foreach ($typeDefs as $def) {
            file_put_contents($installDir.'/'.$domain.'/attribute_defs/'.$def.'.tmpl', $templates[$defType][$def]);
        }
    }
}
file_put_contents($installDir.'/rwhoisd.auth_area', implode("\n---\n", $authArea));
foreach ($rwhoisFiles as $file) {
    file_put_contents($installDir.'/rwhoisd.'.$file, $templates['rwhoisd.'.$file]);
}
