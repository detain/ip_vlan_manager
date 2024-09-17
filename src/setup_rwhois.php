<?php
/**
* VLAN to rWhois Generator
* 
* @todo
* - iterate vlans and genrate nets/orgs
* - use real or private contact info for each user based on account setting
* ? if you want i could add vps network definitions as well
* 
*/

//echo "Loading rwhoisd JSON Data\n";
if (file_exists('rwhoisd.json')) {
    $json = file_get_contents('rwhoisd.json');    
} else {
    $json = file_get_contents('https://mynew.interserver.net/ajax_rwhois.php');
    file_put_contents('rwhoisd.json', $json);
}  
$json = json_decode($json, true);
$samplePrefix = 'https://raw.githubusercontent.com/arineng/rwhoisd/master/rwhoisd/sample.data/';
$typeDirs = ['domain' => 'a.com', 'net' => 'net-fd00%3A1234%3A%3A-32'];
$rwhoisFiles = ['allow', 'conf', 'deny', 'dir', 'root', 'x.dir'];
$defs = [ 'domain' => ['asn', 'contact', 'domain', 'guardian', 'host', 'org', 'referral'], 'net' => ['contact', 'guardian', 'host', 'network', 'referral'] ];
$templates = ['domain' => [], 'net' => []];
$intervals = [ 'refresh' => 3600, 'increment' => 1800, 'retry' => 60, 'ttl' => 86400 ];
$installDir = '/home/rwhois/bin';
$serial = date('YmdHis');
$privateData = true;
//echo "Setting up rwhoisd in {$installDir}\n";
//echo "Downloading Templates\n";
foreach ($rwhoisFiles as $file) {
    $templates['rwhoisd.'.$file] = file_get_contents($samplePrefix.'rwhoisd.'.$file);
}
foreach ($defs as $defType => $typeDefs) {
    foreach ($typeDefs as $def) {
        $templates[$defType][$def] = file_get_contents($samplePrefix.$typeDirs[$defType].'/attribute_defs/'.$def.'.tmpl');
    }
}
//mkdir($installDir, 0774, true);
//echo "Generating Output\n";
$out = [
    'contacts' => [],
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
//echo "Writing IP Blocks\n";
// generate output from data
foreach ($json['ipblocks'] as $blockType => $typeBlocks) {
    foreach ($typeBlocks as $blockId => $blockData) {
        $networks = [];
        $ipblock = $blockData['ipblocks_network'];
        $contact = 'hostmaster';
        // $contact = $custid;
        $netDir = 'net-'.str_replace('/', '-', $ipblock);
        $netName = 'NETBLK-'.$ipblock;
        //mkdir($installDir.'/'.$netDir, 0774, true);
        $out['authArea'][] = "type: master
name: {$netName}
data-dir: {$netDir}/data
schema-file: {$netDir}/schema
soa-file: {$netDir}/soa";
        //mkdir($installDir.'/'.$netDir.'/attribute_defs', 0774, true);
        foreach ($defs['net'] as $suffix) {
            //mkdir($installDir.'/'.$netDir.'/data/'.$suffix, 0774, true);
            echo `mkdir -p "{$installDir}/{$netDir}/data/{$suffix}"`;
        }
        $networks[] = "Network-Name: NETBLK-{$ipblock}
IP-Network: {$ipblock}
Organization: 777.interserver.net
Tech-Contact: hostmaster.interserver.net
Admin-Contact: {$contact}.interserver.net";
        $schema = "name:network
attributedef:{$netDir}/attribute_defs/network.tmpl
dbdir:{$netDir}/data/network
Schema-Version: {$serial}";
        foreach ($blockData['vlans'] as $vlanId => $vlan) {
            if ($blockType == 4) {
                $ipblock = $vlan['vlans_networks'];
            } else {
                $ipblock = $vlan['vlans6_networks'];
            }
            $contact = 'hostmaster';
            if (!is_null($vlan['account_id'])) {
                $contact = $vlan['account_id'];                
            }
            $networks[] = "Network-Name: NETBLK-{$ipblock}
IP-Network: {$ipblock}
Organization: 777.interserver.net
Tech-Contact: hostmaster.interserver.net
Admin-Contact: {$contact}.interserver.net";            
        }
        echo `mkdir -p "{$installDir}/{$netDir}/data/referral" "{$installDir}/{$netDir}/data/network" "{$installDir}/{$netDir}/attribute_defs"`;
        // write net schema file
        file_put_contents($installDir.'/'.$netDir.'/schema', $schema);
        // write net soa file
        file_put_contents($installDir.'/'.$netDir.'/soa', $out['soa']);
        // write net network.txt
        file_put_contents($installDir.'/'.$netDir.'/data/referral/referral.txt', '');
        file_put_contents($installDir.'/'.$netDir.'/data/network/network.txt', implode("\n---\n", $networks));        
    }
}
$fields = [
'Name' => 'name',
'Email' => 'email',
'Street-Address' => 'address',
'City' => 'city',
'State' => 'state',
'Postal-Code' => 'zip',
'Country-Code' => 'country',
'Phone' => 'phone',
];
$domain = 'interserver.net';
foreach ($json['contacts'] as $custid => $data) {
    $contact = "ID:{$custid}.{$domain}
Auth-Area:{$domain}
Type:I";
    if (!isset($data['name'])) {
        $data['name'] = 'Private Customer';
    }
    if (!isset($data['address'])) {
        $data['address'] = 'Private Residence';
    }
    foreach ($fields as $field => $from) {
        if (isset($data[$from]))  {
            $contact .= "\n{$field}:{$data[$from]}"; 
        }
    }
    $contact .= "
Organization:777.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";
    $out['contacts'][] = $contact; 
}
//echo "Writing Domains\n";
foreach ($json['domains'] as $domain => $domData) {
    echo `mkdir -p "{$installDir}/{$domain}/attribute_defs"`;
    // create attribute_defs and assorted data dirs
    //mkdir($installDir.'/'.$domain.'/attribute_defs', 0774, true);
    foreach ($defs['domain'] as $suffix) {
        //mkdir($installDir.'/'.$domain.'/data/'.$suffix, 0774, true);
        echo `mkdir -p "{$installDir}/{$domain}/data/{$suffix}"`;
    }
    $out['authArea'][] = "type: master
    name: {$domain}
    data-dir: {$domain}/data
    schema-file: {$domain}/schema
    soa-file: {$domain}/soa";    
    // write domain soa
    file_put_contents($installDir.'/'.$domain.'/soa', $out['soa']);
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
AS-Name:Interserver, Inc
AS-Number:19318
Organization:777.{$domain}
Admin-Contact:222.{$domain}
Tech-Contact:222.{$domain}
Created:{$seriasl}
Updated:{$serial}
Updated-by:hostmaster@{$domain}";
    $domainData = "ID:333.{$domain}
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
Updated:{$serial}
Updated-By:hostmaster@{$domain}";
    $guardian = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: {$serial}
Updated: {$serial}
Updated-By: hostmaster@{$domain}
Private:true";
    $host[] = "ID: 444.{$domain}
Auth-Area: {$domain}
Guard-Scheme: PW
Guard-Info: passwd
Created: {$serial}
Updated: {$serial}
Updated-By: hostmaster@{$domain}
Private:true";
    $org = "ID: 777.{$domain}
Auth-Area: {$domain}
Org-Name: InterServer
Street-Address: 110 Meadowlands Parkway, Suite 100
City: Secaucus
State: NJ
Postal-Code: 07094
Country-Code: US
Phone: 12016051440
Created: {$serial}
Updated: {$serial}s
Updated-By: hostmaster@{$domain}";
    $referral = "ID:888.{$domain}
Auth-Area: {$domain}
Guardian:444.{$domain}
Referral:rwhois://rwhois.second.{$domain}:4321/Auth-Area=fddi.{$domain}
Organization:777.{$domain}
Referred-Auth-Area:fddi.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";                             
    file_put_contents($installDir.'/'.$domain.'/data/asn/asn.txt', $asn);
    file_put_contents($installDir.'/'.$domain.'/data/contact/contact.txt', implode("\n---\n", $out['contacts']));
    file_put_contents($installDir.'/'.$domain.'/data/domain/domain.txt', $domainData);
    file_put_contents($installDir.'/'.$domain.'/data/guardian/guardian.txt', $guardian);
    file_put_contents($installDir.'/'.$domain.'/data/host/host.txt', $out['contacts'][0]);
    file_put_contents($installDir.'/'.$domain.'/data/org/org.txt', $org);
    file_put_contents($installDir.'/'.$domain.'/data/referral/referral.txt', $referral);
    foreach ($defs as $defType => $typeDefs) {
        foreach ($typeDefs as $def) {
            file_put_contents($installDir.'/'.$domain.'/attribute_defs/'.$def.'.tmpl', $templates[$defType][$def]);
        }
    }
}
file_put_contents($installDir.'/rwhoisd.auth_area', implode("\n---\n", $out['authArea']));
foreach ($rwhoisFiles as $file) {
    file_put_contents($installDir.'/rwhoisd.'.$file, $templates['rwhoisd.'.$file]);
}
