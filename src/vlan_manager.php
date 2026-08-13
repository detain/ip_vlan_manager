<?php
/**
* IP Functionality
*
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package IP-VLAN-Manager
* @category IPs
*/

/**
* Loads every switchport once and builds the Vlan-interface tag map from them.
*
* switchports.vlans and switchports.vlans_tag are both comma separated id lists, so doing this in
* PHP off a single SELECT avoids the per-id `find_in_set()` queries this page used to fire - those
* ran once per vlan<->port pair (thousands of them at ~40ms each).
*
* @param object $db          database handle
* @param array  $switchports filled by reference with switchport_id => row
* @return array Vlan-interface switchport_id => list of switchport_ids tagging it
*/
function get_switchport_tag_map($db, array &$switchports)
{
    $tagMap = [];
    $db->query('select * from switchports', __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $switchports[$db->Record['switchport_id']] = $db->Record;
        if (!empty($db->Record['vlans_tag'])) {
            foreach (explode(',', $db->Record['vlans_tag']) as $taggedPortId) {
                $taggedPortId = trim($taggedPortId);
                if ($taggedPortId !== '') {
                    $tagMap[$taggedPortId][] = $db->Record['switchport_id'];
                }
            }
        }
    }
    return $tagMap;
}

/**
* Unlinks a vlan from every switchport that resolves to no asset.
*
* Handles the `action=remove_noasset_ports&vlan_id=N` link rendered in the VLAN Manager options
* column. switchports.vlans is a comma separated list of vlan ids, so removing vlan 5 from
* "1,2,5,8" leaves "1,2,8", and removing the only entry leaves it blank. The vlans row itself is
* never deleted - this only drops the port <-> vlan link.
*
* @param object $db  database handle used to load the switchports
* @param object $db2 second handle, used for the updates
* @param array  $switchNames map of switchmanager.id => display name, for the result message
* @return void
*/
function remove_vlan_from_assetless_ports($db, $db2, array $switchNames)
{
    $request = \MyAdmin\App::variables()->request;
    if (!isset($request['action']) || $request['action'] != 'remove_noasset_ports') {
        return;
    }
    $vlanId = intval($request['vlan_id'] ?? 0);
    if ($vlanId <= 0) {
        add_output('<div class="alert alert-danger">Invalid vlan id.</div>');
        return;
    }
    $removed = [];
    $switchports = [];
    $tagMap = get_switchport_tag_map($db, $switchports);
    foreach ($switchports as $switchportId => $port) {
        $vlanIds = array_filter(array_map('trim', explode(',', (string)$port['vlans'])), 'strlen');
        if (!in_array((string)$vlanId, $vlanIds, true)) {
            continue;
        }
        $hasAsset = !empty($port['asset_id']);
        if (!$hasAsset && preg_match('/vlan\d+/i', $port['port'], $matches)) {
            // Vlan interface: its asset comes from whichever ports tag it.
            foreach ($tagMap[$switchportId] ?? [] as $taggingPortId) {
                if (!empty($switchports[$taggingPortId]['asset_id'])) {
                    $hasAsset = true;
                }
            }
        }
        if ($hasAsset) {
            continue;
        }
        unset($vlanIds[array_search((string)$vlanId, $vlanIds, true)]);
        $newVlans = implode(',', $vlanIds);
        $db2->query("update switchports set vlans='{$newVlans}' where switchport_id={$port['switchport_id']}", __LINE__, __FILE__);
        $removed[] = ($switchNames[$port['switch']] ?? $port['switch']).' '.$port['port'];
        myadmin_log('myadmin', 'info', "Removed vlan_id({$vlanId}) from switchport({$port['switchport_id']}) leaving vlans - '{$newVlans}'", __LINE__, __FILE__);
    }
    if (empty($removed)) {
        add_output('<div class="alert alert-warning">Vlan '.$vlanId.' is not on any ports without an asset.</div>');
    } else {
        add_output('<div class="alert alert-success">Removed vlan '.$vlanId.' from '.htmlspecialchars(implode(', ', $removed)).'</div>');
    }
}

/**
* VLAN Manager
*
* @return bool
* @throws \Exception
* @throws \SmartyException
*/
function vlan_manager()
{
    function_requirements('has_acl');
    if (\MyAdmin\App::ima() != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    page_title('VLAN Manager');
    function_requirements('update_switch_ports');
    function_requirements('get_networks');
    //			$smarty = new \TFSmarty;
    //			$smarty->debugging = TRUE;
    //			$smarty->assign('sortcol', 1);
    //			$smarty->assign('sortdir', 0);
    //			$smarty->assign('textextraction', "'complex'");
    $ima = \MyAdmin\App::ima();
    $choice = \MyAdmin\App::variables()->request['choice'];
    global $groupinfo;
    $db = get_module_db('default');
    $db2 = get_module_db('default');
    if (isset(\MyAdmin\App::variables()->request['order']) && \MyAdmin\App::variables()->request['order'] == 'id') {
        $order = 'vlans_id';
    } else {
        $order = 'vlans_networks';
    }
    $locations = [];
    $ipblocks = [];
    $vlans = [];
    $switchports = [];
    $switchNames = [];
    $switches = [];
    $db->query('select * from switchmanager');
    while ($db->next_record(MYSQL_ASSOC)) {
        $switches[$db->Record['id']] = $db->Record;
        $switchNames[$db->Record['id']] = is_numeric($db->Record['name']) ? 'switch'.$db->Record['name'] : $db->Record['name'];
    }
    $db->query("select * from ip_locations");
    while ($db->next_record(MYSQL_ASSOC)) {
        $locations[$db->Record['location_id']] = $db->Record['location_label'];
    }
    $db->query("select * from ipblocks");
    while ($db->next_record(MYSQL_ASSOC)) {
        $ipblocks[$db->Record['ipblocks_id']] = $db->Record;
    }
    remove_vlan_from_assetless_ports($db, $db2, $switchNames);
    $db->query("select * from vlans order by vlans_networks");
    while ($db->next_record(MYSQL_ASSOC)) {
        $db->Record['ports'] = [];
        $db->Record['portsStr'] = [];
        $db->Record['asset_ids'] = [];
        $db->Record['noasset_ports'] = [];
        $vlans[$db->Record['vlans_id']] = $db->Record;
    }
    // One pass over switchports, then everything else is resolved in PHP. The per-vlan
    // "where find_in_set(<id>, vlans_tag)" lookups this replaces were ~40ms each and ran once per
    // vlan<->port pair (thousands of round trips), which is what made this page crawl.
    $tagMap = get_switchport_tag_map($db, $switchports);
    foreach ($switchports as $switchportId => $record) {
        if (empty($record['vlans'])) {
            continue;
        }
        $vlanIds = explode(',', $record['vlans']);
        foreach ($vlanIds as $vlanId) {
            if (isset($vlans[$vlanId])) {
                $vlans[$vlanId]['portsStr'][] = $switchNames[$record['switch']].' '.$record['port'];
                $vlans[$vlanId]['ports'][] = $switchportId;
                // A port counts as "assetless" when nothing resolves to an asset id through it -
                // for a Vlan interface that means none of the ports tagging it have an asset,
                // for a physical port it means the port itself has no asset_id.
                $hasAsset = false;
                if (preg_match('/vlan\d+/i', $record['port'], $matches)) {
                    foreach ($tagMap[$switchportId] ?? [] as $taggingPortId) {
                        if (!empty($switchports[$taggingPortId]['asset_id'])) {
                            $vlans[$vlanId]['asset_ids'][] = '<a href="/admin/asset_form?id='.$switchports[$taggingPortId]['asset_id'].'" target="_blank">'.$switchports[$taggingPortId]['asset_id'].'</a>';
                            $hasAsset = true;
                        }
                    }
                } elseif (!empty($record['asset_id'])) {
                    $vlans[$vlanId]['asset_ids'][] = '<a href="/admin/asset_form?id='.$record['asset_id'].'" target="_blank">'.$record['asset_id'].'</a>';
                    $hasAsset = true;
                }
                if (!$hasAsset) {
                    $vlans[$vlanId]['noasset_ports'][] = $switchNames[$record['switch']].' '.$record['port'];
                }
            }
        }
    }
    $table = new \TFTable();
    $table->set_title('VLan Manager '.pdf_link('choice='.$choice.'&order='.$order));
    $table->set_options('width="100%" id="vlanMangerTBL"');
    foreach ($vlans as $vlanId => $vlan) {
        $ip_block_t = str_replace(':', '', $vlan['vlans_networks']);
        $table->add_field($vlan['vlans_id']);
        $table->add_field(isset($ipblocks[$vlan['vlans_block']]) ? $locations[$ipblocks[$vlan['vlans_block']]['ipblocks_location']] : 'Unknown');
        $table->add_field($ip_block_t);
        $table->add_field(implode(', ', $vlan['portsStr']));
        if (!empty($vlan['asset_ids']))
            $table->add_field(implode(', ', $vlan['asset_ids']));
        else
            $table->add_field('&nbsp;');
        $options =
            $table->make_link('choice=ip.ipblock_viewer&amp;ipblock='.$ip_block_t, '<i class="icon-analyze" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-analyze"></use></svg></i>', false, 'title="View"')
            . $table->make_link('choice=ip.add_ips_to_server&amp;ipblock='.$ip_block_t, '<i class="icon-plus" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-plus"></use></svg></i>', false, 'title="Add IPs"')
            . $table->make_link('choice=ip.delete_vlan&amp;ipblock='.$ip_block_t, '<i class="icon-delete" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-delete"></use></svg></i>', false, 'title="Delete"');
        if (!empty($vlan['noasset_ports'])) {
            // Some of this vlan's ports resolve to no asset at all - offer to unlink the vlan from
            // just those ports (the vlan row itself is left alone).
            $options .= $table->make_link(
                'choice=ip.vlan_manager&amp;action=remove_noasset_ports&amp;vlan_id='.$vlanId,
                '<i class="fa fa-unlink" style="width: 20px; height: 20px;"></i>',
                false,
                'title="'.htmlspecialchars('Remove from port(s) with no asset: '.implode(', ', $vlan['noasset_ports'])).'"'
                .' onclick="return confirm(\'Remove this vlan from the '.count($vlan['noasset_ports']).' port(s) with no asset attached?\');"'
            );
        }
        $table->add_field($options, 'c');
        $table->add_row();
    }
    add_js('datatables');
    add_output($table->get_table());
    $script = '<script>$(document).ready(function(){$("#vlanMangerTBL").DataTable(
        {"columns": [{"title":"ID"}, {"title":"Location"}, {"title":"Network"}, {"title":"Port(s)"}, {"title":"Asset Ids"}, {"title":"Options"}],
        "order": [[2, "asc"]], "pageLength": 1000, lengthMenu: [100, 500, 1000, -1]}
    );});</script>';
    add_output($script);
    return;
    
    
    $total_ips = 0;
    $used_ips = 0;
    // get ip block(s)
    $networks = [];
    $vlanPorts = [];
    $switchNames = [];
    $switches = [];
    $switchPortIds = [];
    $db->query('select * from switchmanager');
    while ($db->next_record(MYSQL_ASSOC)) {
        $switches[$db->Record['id']] = $db->Record;
        $switchNames[$db->Record['id']] = is_numeric($db->Record['name']) ? 'switch'.$db->Record['name'] : $db->Record['name'];
    }
    $db->query('select switchport_id, vlans, switch, port, graph_id, servers.server_id, server_hostname from switchports left join servers using (server_id) where vlans != ""');
    while ($db->next_record(MYSQL_ASSOC)) {
        $vlans = explode(',', $db->Record['vlans']);
        unset($db->Record['vlans']);
        foreach ($vlans as $vlan) {
            if (!isset($vlanPorts[$vlan])) {
                $vlanPorts[$vlan] = [];
            }
            $vlanPorts[$vlan][] = $db->Record;
        }
    }
    
    $db->query('select * from ipblocks order by ipblocks_network', __LINE__, __FILE__);
    $vlans = [];
    while ($db->next_record(MYSQL_ASSOC)) {
        $ipinfo = ipcalc($db->Record['ipblocks_network']);
        $network_id = $db->Record['ipblocks_id'];
        $total_ips += $ipinfo['hosts'];
        $db2->query("select * from vlans where vlans_block='{$network_id}' order by {$order};", __LINE__, __FILE__);
        while ($db2->next_record(MYSQL_ASSOC)) {
            if (isset($vlanPorts[$db2->Record['vlans_id']])) {
                $db2->Record['switchports'] = $vlanPorts[$db2->Record['vlans_id']];
            } else {
                $db2->Record['switchports'] = [];
            }
            $db2->Record['ports'] = [];
            foreach ($db2->Record['switchports'] as $switchport) {
                $switchPortIds[$switchport['switch'].'/'.$switchport['port']] = $switchport['switchport_id'];
                $db2->Record['ports'][] = $switchport['switch'].'/'.$switchport['port'];
            }
            $vlans[$db2->Record['vlans_id']] = $db2->Record;
            $network = get_networks($db2->Record['vlans_networks'], $db2->Record['vlans_id'], '', $db2->Record['ports']);
            //_debug_array($network);
            $networks = array_merge($networks, $network);
        }
    }
    $db->query('select count(*) from ips where ips_vlan is not null');
    $db->next_record();
    $used_ips = $db->f(0);
    $networksize = count($networks);
    $rows = [];
    //_debug_array($networks);
    for ($x = 0; $x < $networksize; $x++) {
        $row = [];
        $network = $networks[$x]['network'];
        $vlan = $networks[$x]['vlan'];
        if ($networks[$x]['comment']) {
            $comment = $networks[$x]['comment'];
        } else {
            $comment = 'not set';
        }
        $ports = [];
        $searches = [];
        $servers = [];
        foreach ($networks[$x]['ports'] as $portData) {
            [$switch, $port, $blade, $justport] = parse_vlan_ports($portData);
            $ports[] = $portData;
            //$searches[] = "(switch='{$switch}' and slot='{$port}')";
        }
        if (isset($vlans[$vlan]['server_hostname']) && null !== $vlans[$vlan]['server_hostname']) {
            $servers[] = $vlans[$vlan]['server_hostname'];
        }
        $table->add_field('"'.$vlan.'"', 'l');
        $table->add_field($network, 'l');
        $table->add_field($table->make_link('choice=ip.edit_vlan_comment&amp;ipblock='.$network, $comment), 'c');
        $editport = false;
        $editserver = false;
        if (isset(\MyAdmin\App::variables()->request['ipblock']) && \MyAdmin\App::variables()->request['ipblock'] == $network) {
            if (isset(\MyAdmin\App::variables()->request['edit_port'])) {
                if (!isset(\MyAdmin\App::variables()->request['ports'])) {
                    $select = get_select_ports($ports);
                    $table->add_hidden('edit_port', 1);
                    $table->add_hidden('ipblock', \MyAdmin\App::variables()->request['ipblock']);
                    //								$row[] = $select.'<br>'.$table->make_submit('Set Port(s)');
                    $table->add_field($select.'<br>'.$table->make_submit('Set Port(s)'));
                    $editport = true;
                } else {
                    $ports = ':'.implode(':', \MyAdmin\App::variables()->request['ports']).':';
                    $db2->query("update vlans set vlans_ports='{$ports}' where vlans_networks like '%:{$network}:%' and vlans_id='{$vlan}'", __LINE__, __FILE__);
                    function_requirements('update_switch_ports');
                    update_switch_ports();
                    $ports = \MyAdmin\App::variables()->request['ports'];
                }
            }
        }
        if (count($ports) == 0) {
            $ports[] = '--';
        }
        if (!$editport) {
            $portsize = count($ports);
            for ($y = 0; $y < $portsize; $y++) {
                if (!(mb_strpos($ports[$y], '/') === false)) {
                    [$switch, $port, $blade, $justport] = parse_vlan_ports($ports[$y]);
                    //$ports[$y] = $switches[$switch]['name'].'/'.$port;
                    $ports[$y] = $switches[$switch]['name'].'/'.$port;
                }
            }
            $table->add_field($table->make_link('choice=ip.vlan_edit_port&amp;ipblock='.$network, implode(', ', $ports)), 'l');
        }
        $table->add_field(
            $table->make_link('choice=ip.ipblock_viewer&amp;ipblock='.$network, '<i class="icon-analyze" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-analyze"></use></svg></i>', false, 'title="View"')
            . $table->make_link('choice=ip.add_ips_to_server&amp;ipblock='.$network, '<i class="icon-plus" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-plus"></use></svg></i>', false, 'title="Add IPs"')
            . $table->make_link('choice=ip.delete_vlan&amp;ipblock='.$network, '<i class="icon-delete" style="width: 20px; height: 20px;"><svg><use xlink:href="/images/myadmin/MyAdmin-Icons.min.svg#icon-delete"></use></svg></i>', false, 'title="Delete"'),
            'c'
        );
        if (isset(\MyAdmin\App::variables()->request['ipblock']) && \MyAdmin\App::variables()->request['ipblock'] == $network) {
            if (isset(\MyAdmin\App::variables()->request['edit_server'])) {
                if ($ports[0] != '--') {
                    if (!isset(\MyAdmin\App::variables()->request['port_0'])) {
                        $out = '';
                        for ($y = 0, $yMax = count($ports); $y < $yMax; $y++) {
                            if (count($ports) > 1) {
                                $out .= 'Port '.$ports[$y].': ';
                            }
                            [$switch, $port, $blade, $justport] = parse_vlan_ports($ports[$y]);
                            $query = "select id, server_hostname from servers where switch='{$switch}' and slot='{$port}'";
                            $db2->query($query, __LINE__, __FILE__);
                            if ($db2->num_rows()) {
                                $db2->next_record();
                                $server = $db2->Record['server_hostname'];
                            } else {
                                $server = 0;
                            }
                            $out .= select_server($server, 'port_'.$y, true);
                            if ($y < (count($ports) - 1)) {
                                $out .= '<br>';
                            }
                        }
                        $table->add_hidden('edit_server', 1);
                        $table->add_hidden('ipblock', \MyAdmin\App::variables()->request['ipblock']);
                        //									$row[] = $out.'<br>'.$table->make_submit('Set Server(s)');
                        $table->add_field($out.'<br>'.$table->make_submit('Set Server(s)'));
                        $editserver = true;
                    } else {
                        $servers = [];
                        for ($y = 0, $yMax = count($ports); $y < $yMax; $y++) {
                            $server = \MyAdmin\App::variables()->request['port_'.$y];
                            if ($server != '0') {
                                $servers[] = $server;
                                [$switch, $port, $blade, $justport] = parse_vlan_ports($ports[$y]);
                                $query = "update servers set switch='', slot='' where switch='{$switch}' and slot='{$port}'";
                                $db2->query($query, __LINE__, __FILE__);
                                $query = "update servers set switch='{$switch}', slot='{$port}' where server_hostname='{$server}'";
                                $db2->query($query, __LINE__, __FILE__);
                            }
                        }
                    }
                } else {
                    //								$row[] = '<b>You Must First Assign Port(s)</b>';
                    $table->add_field('<b>You Must First Assign Port(s)</b>');
                    $editserver = true;
                }
            }
        }
        if (count($servers) == 0) {
            $servers[] = '--';
        }
        $table->add_row();
    }

    $table->set_colspan(5);
    $table->add_field('Total IPs '.$total_ips, 'l');
    $table->add_row();
    $table->set_colspan(5);
    $table->add_field('Used IPs '.$used_ips.' ('.number_format(($used_ips / $total_ips) * 100, 2).'%) (Rough Estimate, I can get better numbers if you want)', 'l');
    $table->add_row();
    $table->set_colspan(5);
    $table->add_field('Free IPs '.($total_ips - $used_ips).' ('.number_format((($total_ips - $used_ips) / $total_ips) * 100, 2).'%)', 'l');
    $table->add_row();
    $table->set_colspan(5);
    $table->add_field($table->make_link('choice=ip.add_vlan', 'Add New VLAN').'   '.$table->make_link('choice=ip.portless_vlans', 'List Of VLAN Without Port Assignments ').'   '.$table->make_link('choice=ip.vlan_port_server_manager', 'VLAN Port <-> Server Mapper'));
    $table->add_row();
    add_output($table->get_table());
    if (isset(\MyAdmin\App::variables()->request['pdf']) && \MyAdmin\App::variables()->request['pdf'] == 1) {
        $table->get_pdf();
    }
}
