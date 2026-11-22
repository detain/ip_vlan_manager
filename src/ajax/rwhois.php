<?php
/**
* VLAN to rWhois Generator
*
* Publishes the rwhoisd feed: network blocks, their VLAN sub-ranges and the
* four public geo fields per account. B187: customer PII (login email, name,
* street address, phone, company) and the free-text comment/port columns are
* neither fetched nor published - the SELECTs carry only the network columns
* plus the account id and the geo fields, and every record is additionally
* reduced to the RwhoisFeed allowlist at the sink. When RWHOIS_FEED_TOKEN is
* defined (config.settings.php, owner-side rollout to all hosts) callers that
* do not present it in the X-Rwhois-Token header get a 404; setup_rwhois.php
* sends the header when it has the token too.
*
* @todo
* * use real or private contact info for each user based on account setting
* if you want i could add vps network definitions as well
*/

include_once(__DIR__.'/../../../../../include/functions.inc.php');
include_once(__DIR__.'/../ip.functions.inc.php');

// gate the endpoint before touching the database (B187 fix step 2)
if (!\Detain\IpVlanManager\RwhoisFeed::isAuthorized($_SERVER, defined('RWHOIS_FEED_TOKEN') ? RWHOIS_FEED_TOKEN : '')) {
	http_response_code(404);
	exit;
}

// initialize variables
$db = \MyAdmin\App::db();
$out = [
    'ipblocks' => [ 
        4 => [], 
        6 => [], 
    ],
    'domains' => [
        'interserver.net' => [],
    ],
    'contacts' => [
        1 => [
            'address' => 'PO BOX 1707',
            'city' => 'Englewood Cliffs',
            'state' => 'NJ',
            'zip' => '07632',
            'company' => 'InterServer, Inc',
            'country' => 'US',
            'phone' => '12016051440',
        ],
    ],
];
// gather data: only the columns the feed publishes; a malformed stored
// network logs and skips its row instead of fatalling the whole feed (B187)
$db->query("select ipblocks_id, ipblocks_network from ipblocks6", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $record = $db->Record;
    $record['vlans'] = [];
    $record['referral'] = [];
    if (!\Detain\IpVlanManager\RwhoisFeed::addRange($record['ipblocks_network'], $record)) {
        error_log("rwhois feed: unparseable ipblocks6 network '{$record['ipblocks_network']}' in row {$record['ipblocks_id']} (skipped)");
        continue;
    }
    $out['ipblocks'][6][$record['ipblocks_id']] = \Detain\IpVlanManager\RwhoisFeed::shapeRecord($record);     
}
$db->query("select ipblocks.ipblocks_id, ipblocks.ipblocks_network, accounts.account_id, accounts.account_city, accounts.account_state, accounts.account_zip, accounts.account_country from ipblocks
left join accounts on account_id=ipblocks_custid", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $record = $db->Record;
    $record['vlans'] = [];
    $record['referral'] = [];
    if (!\Detain\IpVlanManager\RwhoisFeed::addRange($record['ipblocks_network'], $record)) {
        error_log("rwhois feed: unparseable ipblocks network '{$record['ipblocks_network']}' in row {$record['ipblocks_id']} (skipped)");
        continue;
    }
    $record = \Detain\IpVlanManager\RwhoisFeed::addContact($record, $out['contacts']);
    $out['ipblocks'][4][$record['ipblocks_id']] = \Detain\IpVlanManager\RwhoisFeed::shapeRecord($record);
}
$db->query("select vlans6.vlans6_id, vlans6.vlans6_networks, accounts.account_id, accounts.account_city, accounts.account_state, accounts.account_zip, accounts.account_country from vlans6 
left join switchports on find_in_set(vlans6.vlans6_id, switchports.vlans6) 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $record = $db->Record;
    if (!\Detain\IpVlanManager\RwhoisFeed::addRange($record['vlans6_networks'], $record)) {
        error_log("rwhois feed: unparseable vlans6 network '{$record['vlans6_networks']}' in row {$record['vlans6_id']} (skipped)");
        continue;
    }
    $record = \Detain\IpVlanManager\RwhoisFeed::addContact($record, $out['contacts']);
    $out['ipblocks'][6][1]['vlans'][$record['vlans6_id']] = \Detain\IpVlanManager\RwhoisFeed::shapeRecord($record);
}
$db->query("select vlans.vlans_id, vlans.vlans_networks, vlans.vlans_block, accounts.account_id, accounts.account_city, accounts.account_state, accounts.account_zip, accounts.account_country from vlans 
left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) 
left join assets on asset_id=id 
left join servers on servers.server_id=order_id 
left join accounts on account_id=server_custid", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC))
{
    $record = $db->Record;
    $record['vlans_networks'] = str_replace(':', '', $record['vlans_networks']);
    if (!\Detain\IpVlanManager\RwhoisFeed::addRange($record['vlans_networks'], $record)) {
        error_log("rwhois feed: unparseable vlans network '{$record['vlans_networks']}' in row {$record['vlans_id']} (skipped)");
        continue;
    }
    $record = \Detain\IpVlanManager\RwhoisFeed::addContact($record, $out['contacts']);
    $out['ipblocks'][4][$record['vlans_block']]['vlans'][$record['vlans_id']] = \Detain\IpVlanManager\RwhoisFeed::shapeRecord($record);     
}
header('Content-type: application/json; charset=UTF-8');
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
