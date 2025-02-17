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
* VLAN Manager
*
* @return bool
* @throws \Exception
* @throws \SmartyException
*/
function vlan_manager()
{
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
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
    $ima = $GLOBALS['tf']->ima;
    $choice = $GLOBALS['tf']->variables->request['choice'];
    global $groupinfo;
    $db = get_module_db('default');
    $db2 = get_module_db('default');
    if (isset($GLOBALS['tf']->variables->request['order']) && $GLOBALS['tf']->variables->request['order'] == 'id') {
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
    $db->query("select * from vlans order by vlans_networks");
    while ($db->next_record(MYSQL_ASSOC)) {
        $db->Record['ports'] = [];
        $db->Record['portsStr'] = [];
        $vlans[$db->Record['vlans_id']] = $db->Record;
    }
    $db->query("select * from switchports");
    while ($db->next_record(MYSQL_ASSOC)) {
        if (!empty($db->Record['vlans'])) {
            $vlanIds = explode(',', $db->Record['vlans']);
            foreach ($vlanIds as $vlanId) {
                if (isset($vlans[$vlanId])) {
                    $vlans[$vlanId]['portsStr'][] = $switchNames[$db->Record['switch']].' '.$db->Record['port'];
                    $vlans[$vlanId]['ports'][] = $db->Record['switchport_id'];
                }
            }
            
        }
        $switchports[$db->Record['switchport_id']] = $db->Record;
    }
    $table = new \TFTable();
    $table->set_title('VLan Manager '.pdf_link('choice='.$choice.'&order='.$order));
    $table->set_options('width="100%"');
    $table->add_field($table->make_link('choice='.$choice.'&order=id', 'ID'));
    $table->add_field($table->make_link('choice='.$choice.'&order=location', 'Location'));
    $table->add_field($table->make_link('choice='.$choice.'&order=ip', 'Network'));
    $table->add_field('Port(s)');
    $table->add_field('Options');
    $table->add_row();
    $table->alternate_rows();
    foreach ($vlans as $vlanId => $vlan) {
        $table->add_field($vlan['vlans_id']);
        $table->add_field(isset($ipblocks[$vlan['vlans_block']]) ? $locations[$ipblocks[$vlan['vlans_block']]['ipblocks_location']] : 'Unknown');
        $table->add_field(str_replace(':', '', $vlan['vlans_networks']));
        $table->add_field(implode(', ', $vlan['portsStr']));
        $table->add_field('&nbsp;');
        $table->add_row();
    }
    add_output($table->get_table());
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
        if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network) {
            if (isset($GLOBALS['tf']->variables->request['edit_port'])) {
                if (!isset($GLOBALS['tf']->variables->request['ports'])) {
                    $select = get_select_ports($ports);
                    $table->add_hidden('edit_port', 1);
                    $table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
                    //								$row[] = $select.'<br>'.$table->make_submit('Set Port(s)');
                    $table->add_field($select.'<br>'.$table->make_submit('Set Port(s)'));
                    $editport = true;
                } else {
                    $ports = ':'.implode(':', $GLOBALS['tf']->variables->request['ports']).':';
                    $db2->query("update vlans set vlans_ports='{$ports}' where vlans_networks like '%:{$network}:%' and vlans_id='{$vlan}'", __LINE__, __FILE__);
                    function_requirements('update_switch_ports');
                    update_switch_ports();
                    $ports = $GLOBALS['tf']->variables->request['ports'];
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
        if (isset($GLOBALS['tf']->variables->request['ipblock']) && $GLOBALS['tf']->variables->request['ipblock'] == $network) {
            if (isset($GLOBALS['tf']->variables->request['edit_server'])) {
                if ($ports[0] != '--') {
                    if (!isset($GLOBALS['tf']->variables->request['port_0'])) {
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
                        $table->add_hidden('ipblock', $GLOBALS['tf']->variables->request['ipblock']);
                        //									$row[] = $out.'<br>'.$table->make_submit('Set Server(s)');
                        $table->add_field($out.'<br>'.$table->make_submit('Set Server(s)'));
                        $editserver = true;
                    } else {
                        $servers = [];
                        for ($y = 0, $yMax = count($ports); $y < $yMax; $y++) {
                            $server = $GLOBALS['tf']->variables->request['port_'.$y];
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
    if (isset($GLOBALS['tf']->variables->request['pdf']) && $GLOBALS['tf']->variables->request['pdf'] == 1) {
        $table->get_pdf();
    }
}
