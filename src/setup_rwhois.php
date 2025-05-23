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
    $json = file_get_contents('https://mynew.interserver.net/ajax/rwhois.php');
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
    'orgs' => [],
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
$templates['net']['network'] .= '---
attribute:       Abuse-Email
attribute-alias: AE
description:     Abuse Email
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Street-Address
attribute-alias: SA
description:     Street address
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   TRUE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       City
attribute-alias: C
description:     City
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       State
attribute-alias: ST
description:     State
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Postal-Code
attribute-alias: PC
description:     Postal code
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Country-Code
attribute-alias: CC
description:     Country code
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
';
foreach ($json['ipblocks'] as $blockType => $typeBlocks) {
    foreach ($typeBlocks as $blockId => $blockData) {
        $networks = [];
        $ipblock = $blockData['ipblocks_network'];
        $contact = 'hostmaster';
        $orgName = 'org';
        // $contact = $custid;
        $netDir = 'net-'.str_replace('/', '-', $ipblock);
        $netName = 'NETBLK-'.$ipblock;
        //mkdir($installDir.'/'.$netDir, 0774, true);
        $out['authArea'][] = "type: master
name: {$ipblock}
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
Organization: {$orgName}.interserver.net
Street-Address: PO Box 1707
City: Englewood Cliffs
State: NJ
Postal-Code: 07632
Country-Code: US
Abuse-Email: abusencc@interserver.net
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
            $orgName = 'org';
            if (!is_null($vlan['account_id'])) {
                $orgName = $vlan['account_id'];
                $contact = 'client'.$vlan['account_id'];                
            }
            $networks[] = "Network-Name: NETBLK-{$ipblock}
IP-Network: {$ipblock}
Organization: {$orgName}.interserver.net
Abuse-Email: abusencc@interserver.net
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
        foreach ($defs['net'] as $def) {
            file_put_contents($installDir.'/'.$netDir.'/attribute_defs/'.$def.'.tmpl', $templates['net'][$def]);
        }
    }
}
$contactFields = [
'Name' => 'name',
'Email' => 'email',
'Phone' => 'phone',
];
$orgFields = [
'Org-Name' => 'company',
'Street-Address' => 'address',
'City' => 'city',
'State' => 'state',
'Postal-Code' => 'zip',
'Country-Code' => 'country',
'Phone' => 'phone',
];
$domain = 'interserver.net';
$out['orgs'][] ="ID: org.{$domain}
Auth-Area: {$domain}
Org-Name: InterServer
Street-Address: PO Box 1707
City: Englewood Cliffs
State: NJ
Postal-Code: 07632
Country-Code: US
Phone: 12016051440
Created: {$serial}
Updated: {$serial}
Updated-By: hostmaster@{$domain}";
$out['contacts'][] = "ID:hostmaster.{$domain}
Auth-Area:{$domain}
Name:Hostmaster
Email:hostmaster@{$domain}
Type:R
Phone:12016051440
Organization:org.interserver.net
See-Also:http://www.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";
foreach ($json['contacts'] as $custid => $data) {
    $contact = "ID:client{$custid}.{$domain}
Auth-Area:{$domain}
Type:I";
    $org = "ID:{$custid}.{$domain}
Auth-Area:{$domain}";
    $data['email'] = 'hostmaster+'.$custid.'@interserver.net';
    if (!isset($data['company']) || empty($data['company'])) {
        $data['company'] = 'Private Organization';
    }
    if (!isset($data['name']) || empty($data['name'])) {
        $data['name'] = 'Private Customer';
    }
    if (!isset($data['phone']) || empty($data['phone'])) {
        $data['phone'] = 'Private Customer';
    }
    if (!isset($data['address']) || empty($data['address'])) {
        $data['address'] = 'Private Residence';
    }
    foreach ($contactFields as $field => $from) {
        if (isset($data[$from]))  {
            $contact .= "\n{$field}:{$data[$from]}"; 
        }
    }
    foreach ($orgFields as $field => $from) {
        if (isset($data[$from]))  {
            $org .= "\n{$field}:{$data[$from]}"; 
        }
    }
    $contact .= "
Organization:{$custid}.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";
    $org .= "
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";
    $out['contacts'][] = $contact; 
    $out['orgs'][] = $org; 
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
/*
---
name:host
attributedef:{$domain}/attribute_defs/host.tmpl
dbdir: {$domain}/data/host
description:Host object
Schema-Version: {$serial}
*/
    file_put_contents($installDir.'/'.$domain.'/schema', $schema);
    // write domain data dirs
    $asn = "ID:111.{$domain}
Auth-Area:{$domain}
AS-Name:Interserver, Inc
AS-Number:19318
Organization:org.{$domain}
Admin-Contact:hostmaster.{$domain}
Tech-Contact:hostmaster.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-by:hostmaster@{$domain}";
    $domainData = "ID:333.{$domain}
Auth-Area:{$domain}
Guardian:444.{$domain}
Domain-Name: {$domain}
Primary-Server:5551.{$domain}
Secondary-Server:5552.{$domain}
Organization:org.{$domain}
Admin-Contact:hostmaster.{$domain}
Tech-Contact:hostmaster.{$domain}
Billing-Contact:hostmaster.{$domain}
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
    $referral = "ID:888.{$domain}
Auth-Area: {$domain}
Guardian:444.{$domain}
Referral:rwhois://rwhois.second.{$domain}:4321/Auth-Area=fddi.{$domain}
Organization:org.{$domain}
Referred-Auth-Area:fddi.{$domain}
Created:{$serial}
Updated:{$serial}
Updated-By:hostmaster@{$domain}";                             
    file_put_contents($installDir.'/'.$domain.'/data/asn/asn.txt', $asn);
    file_put_contents($installDir.'/'.$domain.'/data/contact/contact.txt', implode("\n---\n", $out['contacts']));
    file_put_contents($installDir.'/'.$domain.'/data/domain/domain.txt', $domainData);
    file_put_contents($installDir.'/'.$domain.'/data/guardian/guardian.txt', $guardian);
    //file_put_contents($installDir.'/'.$domain.'/data/host/host.txt', $out['contacts'][0]);
    file_put_contents($installDir.'/'.$domain.'/data/org/org.txt', implode("\n---\n", $out['orgs']));
    file_put_contents($installDir.'/'.$domain.'/data/referral/referral.txt', $referral);
    foreach ($defs['domain'] as $def) {
        file_put_contents($installDir.'/'.$domain.'/attribute_defs/'.$def.'.tmpl', $templates['domain'][$def]);
    }
}
file_put_contents($installDir.'/rwhoisd.auth_area', implode("\n---\n", $out['authArea']));
$templates['rwhoisd.conf'] = str_replace([
    '/home/databases/rwhoisd', 
    '# local-host: rwhois.a.com', 
    '# server-type: inetd', 
    'userid: rwhoisd', 
    'hostmaster@a.com'
    ], [
    $installDir, 
    'local-host: rwhois.trouble-free.net', 
    'server-type: inetd', 
    'userid: rwhois', 
    'abusencc@interserver.net'
    ], $templates['rwhoisd.conf']);
foreach ($rwhoisFiles as $file) {
    file_put_contents($installDir.'/rwhoisd.'.$file, $templates['rwhoisd.'.$file]);
}
echo `cd {$installDir}
for i in \$(grep ^name rwhoisd.auth_area|cut -d" " -f2); do
  bin/rwhois_indexer -c rwhoisd.conf -i -v -A \$i -C network -s txt;
done
bin/rwhois_indexer -c rwhoisd.conf -i -v -A interserver.net -s txt
`;
