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
* 
*/
include_once(__DIR__.'/../../../../include/functions.inc.php');
include_once(__DIR__.'/ip.functions.inc.php');

$db = $GLOBALS['tf']->db;
$privateData = true;
$cmds = '';
$total = 0;
$totalVlans = 0;
$totalAvailableIps = 0;
$templates = [
    'referral' => 'attribute:       Referral
attribute-alias: R
description:     Referral to an RWhois server
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   TRUE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Referred-Auth-Area
attribute-alias: RAA
description:     Referred auth-area in domain name or prefix/prefix length notation
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   TRUE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           ALL
type:            TEXT
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    'contact' => 'attribute:       Name
attribute-alias: N
description:     Full name
is-primary-key:  TRUE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           EXACT
type:            TEXT
---
attribute:       Email
attribute-alias: EM
description:     RFC 822 email address
is-primary-key:  TRUE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           EXACT
type:            TEXT
---
attribute:       Type
attribute-alias: T
description:     Individual or role account
format:         re:\(I\|R\)
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       First-Name
attribute-alias: FN
description:     First name
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           EXACT
type:            TEXT
---
attribute:       Last-Name
attribute-alias: LN
description:     Last name
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           EXACT
type:            TEXT
---
attribute:       Middle-Name
attribute-alias: MN
description:     Middle name
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Phone
attribute-alias: P
description:     Phone number
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Fax
attribute-alias: F
description:     Fax number
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Organization
attribute-alias: ORG
description:     Organization
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            ID
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    'guardian' => 'attribute:       Guard-Scheme
attribute-alias: GS
format:             re:\(CRYPT-PW\|PGP\)
description:     Authorization scheme
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Guard-Info
attribute-alias: GI
description:     Authorization information
is-primary-key:  FALSE
is-required:     TRUE
is-private:         TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
is-private:     TRUE
index:           NONE
type:            TEXT
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    'host' => 'attribute:       Host-Name
attribute-alias: HN
description:     Host name
is-primary-key:  TRUE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           EXACT
type:            TEXT
---
attribute:       IP-Address
attribute-alias: IPA
description:     IP address
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   TRUE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           CIDR
type:            TEXT
---
attribute:       Canonical-Name
attribute-alias: CNAME
description:     Canonical name
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   TRUE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           EXACT
type:            TEXT
---
attribute:       Organization
attribute-alias: ORG
description:     Organization
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            ID
---
attribute:       Tech-Contact
attribute-alias: TC
description:     Technical contact
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            ID
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    'network' => 'attribute:       Network-Name
attribute-alias: NN
description:     Network name
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           EXACT
type:            TEXT
---
attribute:       IP-Network
attribute-alias: IPN
description:     IP network in prefix/prefix length notation
is-primary-key:  TRUE
is-required:     TRUE
is-repeatable:   TRUE
is-multi-line:   FALSE
is-hierarchical: TRUE
index:           CIDR
type:            TEXT
---
attribute:       Org-Name
attribute-alias: ORG
description:     Organization
is-primary-key:  FALSE
is-required:     TRUE
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
is-required:     TRUE
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
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    'org' => 'attribute:       Org-Name
attribute-alias: ON
description:     Organization Name
is-primary-key:  TRUE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           ALL
type:            TEXT
---
attribute:       Street-Address
attribute-alias: SA
description:     Street address
is-primary-key:  FALSE
is-required:     TRUE
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
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Phone
attribute-alias: P
description:     Phone Number
is-primary-key:  FALSE
is-required:     TRUE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Fax
attribute-alias: F
description:     Fax
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Created
attribute-alias: CR
description:     Create date
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT
---
attribute:       Updated-By
attribute-alias: UB
description:     Updated by
is-primary-key:  FALSE
is-required:     FALSE
is-repeatable:   FALSE
is-multi-line:   FALSE
is-hierarchical: FALSE
index:           NONE
type:            TEXT',
    '' => '',
];
$rwhoisd_auth_area = 'type: master
name: interserver.net
data-dir: etc/rwhoisd/interserver.net/data
schema-file: etc/rwhoisd/interserver.net/schema
soa-file: etc/rwhoisd/interserver.net/soa
---
';
$installDir = '/home/rwhois/bin';
$cmds .= "cd '{$installDir}/etc/rwhoisd';
mkdir -p interserver.net/{attribute_defs,data/referral,data/contact,data/host,data/guardian,data/org,data/asn,data/domain};
";
$nets = array();
$db->query("select * from ipblocks");
$ipblocks = array();
$serial = date('YmdHis') . '000';
$serial = '19961101000000000';
while ($db->next_record())
{
    $ipblock = $db->Record['ipblocks_network'];
    //echo "Generating IP Block {$ipblock}\n";
    $vlandata = explode('/', $ipblock);
    $ip = $vlandata[0];
    $size = $vlandata[1];
    $nets[] = $ipblock;
    $network_info = ipcalc($ipblock);
    $totalAvailableIps += $network_info['hosts'];
    $rwhoisd_auth_area .= "type: master
name: {$ipblock}
data-dir: etc/rwhoisd/net-{$ip}-{$size}/data
schema-file: etc/rwhoisd/net-{$ip}-{$size}/schema
soa-file: etc/rwhoisd/net-{$ip}-{$size}/soa
---
";
    $schema = "#
# RWhois Main Schema Config File
#
name:network
attributedef:etc/rwhoisd/net-{$ip}-{$size}/attribute_defs/network.tmpl
dbdir:etc/rwhoisd/net-{$ip}-{$size}/data/network
Schema-Version: {$serial}
---
name:referral
attributedef:etc/rwhoisd/net-{$ip}-{$size}/attribute_defs/referral.tmpl
dbdir:etc/rwhoisd/net-{$ip}-{$size}/data/referral
Schema-Version: {$serial}";
    $soa = "Serial-Number: {$serial}
Refresh-Interval: 3600
Increment-Interval: 1800
Retry-Interval: 60
Time-To-Live: 86400
Primary-Server: rwhois.trouble-free.net:4321
Hostmaster: hostmaster@trouble-free.net";
    $cmds .= "mkdir -p net-{$ip}-{$size}/{attribute_defs,data/referral,data/network,data/org};
echo '{$schema}' > net-{$ip}-{$size}/schema;
echo '{$soa}' > net-{$ip}-{$size}/soa;
echo '{$templates['contact']}' > net-{$ip}-{$size}/attribute_defs/contact.tmpl;
echo '{$templates['guardian']}' > net-{$ip}-{$size}/attribute_defs/guardian.tmpl;
echo '{$templates['host']}' > net-{$ip}-{$size}/attribute_defs/host.tmpl;
echo '{$templates['network']}' > net-{$ip}-{$size}/attribute_defs/network.tmpl;
echo '{$templates['org']}' > net-{$ip}-{$size}/attribute_defs/org.tmpl;
echo '{$templates['referral']}' > net-{$ip}-{$size}/attribute_defs/referral.tmpl;\n";        
}
$cmds .= "echo '{$rwhoisd_auth_area}' > {$installDir}/rwhoisd.auth_area;\n";
//$nets = implode(' ', $nets);
//$cmds .= "echo '$nets' > ipblocks.txt;\n";
echo "Building VLAN data";
$db->query("select * from vlans left join ipblocks on vlans_block=ipblocks_id left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) left join assets on asset_id=id left join servers on servers.server_id=order_id left join accounts on account_id=server_custid where account_id is not null", __LINE__, __FILE__);
echo "\n";
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
