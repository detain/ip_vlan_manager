<?php

    // Load Various Functions & Connect to mysql database
    include '../../../../include/functions.inc.php';
    include 'ip.functions.inc.php';
    $db = get_module_db('default');
    $db4 = $db;
    $dbInnertell = get_module_db('innertell');


// set extra to a:1:{s:13:"private_whois";s:1:"1";}
/**
 * @param $extra
 * @return array|mixed
 */
function get_extra($extra)
{
    if ($extra == '') {
        $extra = [];
    } else {
        $extra = myadmin_unstringify($extra);
    }
    return $extra;
}

/**
 * @param $extra
 * @return array|string
 */
function put_extra($extra)
{
    if ($extra == '') {
        $extra = [];
    }
    $extra = myadmin_stringify($extra);
    return $extra;
}
$privateData = true;
$cmds = '';
$total = 0;
$db->query("select * from vlans left join ipblocks on vlans_block=ipblocks_id left join switchports on find_in_set(vlans.vlans_id, switchports.vlans) left join assets on asset_id=id left join servers on servers.server_id=order_id left join accounts on account_id=server_custid where account_id is not null", __LINE__, __FILE__);
while ($db->next_record(MYSQL_ASSOC)) {
    /*
     * vlans - vlans_id, vlans_block, vlans_networks, vlans_ports, vlans_comment, vlans_primary, vlans_ip, 
     * ipblocks - ipblocks_id, ipblocks_network, ipblocks_location, 
     * switchports - switchport_id, switch, blade, justport, port, graph_id, vlans, server_id, asset_id, updated, vlans6, 
     * assets - id, order_id, hostname, status, primary_ipv4, primary_ipv6, mac, datacenter, type_id, asset_tag, rack, row, col, unit_start, unit_end, unit_sub, 
     *    ipmi_mac, ipmi_ip, ipmi_admin_username, ipmi_admin_password, ipmi_client_username, ipmi_client_password, ipmi_updated, ipmi_working, company, 
     *    comments, make, model, description, customer_id, external_id, billing_status, overdue, monthly_price, create_timestamp, update_timestamp, mp_status_updated_at, 
     * servers - server_id, server_hostname, server_custid, server_type, server_currency, server_order_date, server_invoice, server_coupon, server_status, server_root, 
     *    server_dedicated_tag, server_custom_tag, server_comment, server_initial_bill, server_hardware, server_ips, server_monthly_bill, server_setup, 
     *    server_discount, server_rep, server_date, server_total_cost, server_location, server_hardware_ordered, server_billed, server_welcome_email, 
     *    server_dedicated_cpu, server_dedicated_memory, server_dedicated_hd1, server_dedicated_hd2, server_dedicated_bandwidth, server_dedicated_ips, 
     *    server_dedicated_os, server_dedicated_cp, server_dedicated_raid, server_extra, 
     * accounts - account_id, account_lid, account_passwd, account_group, account_status, account_ima, account_name, account_address, account_city, account_state, 
     *    account_zip, account_country, account_phone, account_fraudrecord_score, account_maxmind_riskscore, account_payment_method, account_pin, account_disable_c
    */
    $ipblock = $db->Record['ipblocks_network'];         
    list($ipblock_ip, $ipblock_size) = explode('/', $ipblock);
    $vlan = str_replace(':', '', $db->Record['vlans_networks']);
    list($ipAddress, $size) = explode('/', $vlan);
    $network_info = ipcalc($vlan);
    $maxip = $network_info['broadcast'];
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
    $cmds .= 'mkdir -p /home/rwhois/bin/etc/rwhoisd/net-'.$ipblock_ip.'-'.$ipblock_size.'/data/{network,org};\n'
        . 'cd /home/rwhois/bin/etc/rwhoisd/net-'.$ipblock_ip.'-'.$ipblock_size.';\n'
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
echo "$total\n";
