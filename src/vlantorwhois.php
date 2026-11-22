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

use Detain\IpVlanManager\RwhoisRecord;
use Detain\IpVlanManager\VlanSpec;

$db = \MyAdmin\App::db();
$privateData = true;
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
// B010: the emitted vlantorwhois.sh shell script is gone - customer values
// used to be interpolated into double-quoted echo -e lines ($(cmd) executed
// at script run time). The rwhoisd tree is now written directly, the same
// model setup_rwhois.php already uses, so run this script on the rwhois host.
$rwhoisBase = $installDir . '/etc/rwhoisd';
foreach (['interserver.net/attribute_defs', 'interserver.net/data/referral', 'interserver.net/data/contact', 'interserver.net/data/host', 'interserver.net/data/guardian', 'interserver.net/data/org', 'interserver.net/data/asn', 'interserver.net/data/domain'] as $relDir) {
    RwhoisRecord::ensureDirectory($rwhoisBase . '/' . $relDir);
}
$db->query("select * from ipblocks");
$ipblocks = array();
$serial = date('YmdHis') . '000';
$serial = '19961101000000000';
while ($db->next_record())
{
    $ipblock = $db->Record['ipblocks_network'];
    // B010: only a canonical CIDR spec may name directories or fill record
    // lines; malformed blocks are skipped, never interpolated.
    $normalized = VlanSpec::normalize($ipblock);
    if ($normalized === null) {
        echo "Skipping invalid IP block {$ipblock}\n";
        continue;
    }
    $ipblock = $normalized;
    [$ip, $size] = explode('/', $ipblock);
    $network_info = ipcalc($ipblock);
    $totalAvailableIps += is_array($network_info) ? $network_info['hosts'] : 0;
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
    // B010: direct writes replace the mkdir/echo shell pipeline; IPv6 block
    // names keep their ':' (canonical inet6 text is legitimate here).
    $blockBase = $rwhoisBase . '/net-' . $ip . '-' . $size;
    foreach (['attribute_defs', 'data/referral', 'data/network', 'data/org'] as $relDir) {
        RwhoisRecord::ensureDirectory($blockBase . '/' . $relDir);
    }
    RwhoisRecord::writeFile($blockBase, 'schema', $schema . "\n");
    RwhoisRecord::writeFile($blockBase, 'soa', $soa . "\n");
    foreach (['contact', 'guardian', 'host', 'network', 'org', 'referral'] as $tmplName) {
        RwhoisRecord::writeFile($blockBase . '/attribute_defs', $tmplName . '.tmpl', $templates[$tmplName] . "\n");
    }
}
RwhoisRecord::writeFile($installDir, 'rwhoisd.auth_area', $rwhoisd_auth_area . "\n");
echo "Building VLAN data";
$db->query("select * from vlans left join ipblocks on vlans_block=ipblocks_id left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) left join assets on asset_id=id left join servers on servers.server_id=order_id left join accounts on account_id=server_custid where account_id is not null", __LINE__, __FILE__);
echo "\n";
while ($db->next_record(MYSQL_ASSOC)) {
    $ipblock = VlanSpec::normalize($db->Record['ipblocks_network']);
    if ($ipblock === null) {
        echo "Skipping VLAN with invalid parent block {$db->Record['ipblocks_network']}\n";
        continue;
    }
    list($ipblock_ip, $ipblock_size) = explode('/', $ipblock);
    $vlan = VlanSpec::normalize(str_replace(':', '', $db->Record['vlans_networks']));
    if ($vlan === null) {
        echo "Skipping VLAN with invalid network spec\n";
        continue;
    }
    //echo "Generating VLAN {$vlan}\n";
    list($ipAddress, $size) = explode('/', $vlan);
    $network_info = ipcalc($vlan);
    $maxip = is_array($network_info) ? $network_info['broadcast'] : '';
    $totalVlans++;
    
    $total += is_array($network_info) ? $network_info['hosts'] : 0;
    $accountId = intval($db->Record['account_id']);
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
        // B010: the flag-flip branch read an undefined $group - account data
        // must come from the row's own account id.
        $data = \MyAdmin\App::accounts()->read($accountId);
        $org = !empty($data['company']) ? $data['company'] : 'Account ' . $db->Record['account_id'];
    }
    // B010: record lines assembled in PHP; every free-text field is collapsed
    // to one line of rwhois display charset, so no value can add lines or
    // feed a shell (the old echo -e "..." pipeline executed $(cmd) payloads).
    $blockBase = $rwhoisBase . '/net-' . $ipblock_ip . '-' . $ipblock_size;
    $created = is_null($db->Record['server_order_date']) ? '20050101' : date('Ymd', $db->fromTimestamp($db->Record['server_order_date']));
    $updated = date('Ymd');
    $orgLines = [
        'ID: NETBLK-INTSRV.' . RwhoisRecord::rwhoisField($ipblock),
        'Auth-Area: ' . RwhoisRecord::rwhoisField($ipblock),
        'Org-Name: ' . RwhoisRecord::rwhoisField(trim($org)),
        'Street-Address: ' . RwhoisRecord::rwhoisField(trim($data['address'])),
        'City: ' . RwhoisRecord::rwhoisField($data['city']),
        'State: ' . RwhoisRecord::rwhoisField($data['state']),
        'Postal-Code: ' . RwhoisRecord::rwhoisField($data['zip']),
        'Country-Code: ' . RwhoisRecord::rwhoisField($data['country']),
        'Phone: ' . RwhoisRecord::rwhoisField(trim($data['phone'])),
        'Created: ' . $created,
        'Updated: ' . $updated,
    ];
    RwhoisRecord::writeRecordFile($blockBase . '/data/org', $accountId . '.txt', $orgLines);
    $networkLines = [
        'ID: NETBLK-INTSRV.' . RwhoisRecord::rwhoisField($ipblock),
        'Auth-Area: ' . RwhoisRecord::rwhoisField($ipblock),
        'Network-Name: INTSRV-' . RwhoisRecord::rwhoisField($ipAddress),
        'IP-Network: ' . RwhoisRecord::rwhoisField($ipAddress) . '/' . RwhoisRecord::rwhoisField((string)$size),
        'Org-Name: ' . RwhoisRecord::rwhoisField(trim($org)),
        'Street-Address: ' . RwhoisRecord::rwhoisField($data['address']),
        'City: ' . RwhoisRecord::rwhoisField($data['city']),
        'State: ' . RwhoisRecord::rwhoisField($data['state']),
        'Postal-Code: ' . RwhoisRecord::rwhoisField($data['zip']),
        'Country-Code: ' . RwhoisRecord::rwhoisField($data['country']),
        'Created: 20050101',
        'Updated: ' . $updated,
        'Updated-By: abuse@interserver.net',
    ];
    RwhoisRecord::writeRecordFile($blockBase . '/data/network', $ipAddress . '-' . $size . '.txt', $networkLines);
}
echo "Wrote rwhoisd data files directly under {$rwhoisBase}

Totals:
VLANs: {$totalVlans}
IPs Assigneed to VLANs: {$total}
IPs Available: {$totalAvailableIps}
";
