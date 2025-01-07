<?php

/**
 * updates the switch ports
 *
 * @param bool $verbose wether or not to enable verbose output.
 * @param bool $pullServerMap defaults to true, optional flag allowing disabling of the switch/ports updating via https://nms.is.cc/cacti/servermap.php
 */
function update_switch_ports($verbose = false, $pullServerMap = true)
{
/*
*/
    $db = get_module_db('default');
    $db->query("select id, name from switchmanager", __LINE__, __FILE__);
    $local = [];
    while ($db->next_record(MYSQL_ASSOC)) {
        $local[$db->Record['name']] = ['id' => $db->Record['id'], 'ports' => []];
        $localIdToName[$db->Record['id']] = $db->Record['name'];
    }
    $db->query("select switch, switchport_id, port, graph_id from switchports", __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $local[$localIdToName[$db->Record['switch']]]['ports'][$db->Record['port']] = ['id' => $db->Record['switchport_id'], 'graph_id' => $db->Record['graph_id']];
    }
    $db2 = new \MyDb\Mysqli\Db('cacti', SNMP_MYSQL_USER, SNMP_MYSQL_PASS, SNMP_SSH_HOST);
    $db2->query("select local_graph_id as graph_id,description as switch,field_value as port from graph_templates_graph, graph_local, host, host_snmp_cache where local_graph_id=graph_local.id and graph_local.host_id=host.id and graph_local.host_id=host_snmp_cache.host_id and graph_local.snmp_index=host_snmp_cache.snmp_index and field_name='ifName' order by description, field_value", __LINE__, __FILE__);
    $nowUpdates = [];
    $nowIdx = 0;
    while ($db2->next_record(MYSQL_ASSOC)) {
        if (!isset($local[$db2->Record['switch']])) {
            $db->query(make_insert_query('switchmanager', [
                'id' => null,
                'name' => $db2->Record['switch'],
                'ports' => count($ports),
                'updated' => mysql_now(),
            ]), __LINE__, __FILE__);
            $id = $db->getLastInsertId('switchmanager', 'id');
            $local[$db2->Record['switch']] = ['id' => $id, 'ports' => []];
            $localIdToName[$id] = $db2->Record['switch'];
            if ($verbose == true) {
                add_output("Created New Switch {$db2->Record['switch']} - ");
            }
        } else {
            if ($verbose == true) {
                add_output("Loaded Switch {$db2->Record['switch']} - ");
            }
            $now = mysql_now();
            $db->query("update switchmanager set updated='{$now}' where id={$local[$db2->Record['switch']]['id']}", __LINE__, __FILE__);
        }
        if (!isset($local[$db2->Record['switch']]['ports'][$db2->Record['port']])) {
            if (substr($db2->Record['port'], 0, 4) == 'Vlan') {
                continue;
            } 
            $blade = '';
            $justport = $db2->Record['port'];
            if (mb_strrpos($db2->Record['port'], '/') > 0) {
                $blade = mb_substr($db2->Record['port'], 0, mb_strrpos($db2->Record['port'], '/'));
                $justport = mb_substr($db2->Record['port'], mb_strlen($blade) + 1);
            }
            if ($verbose == true) {
                add_output("{$db2->Record['port']} +");
            }
            $db->query(make_insert_query('switchports', [
                'switch' => $local[$db2->Record['switch']]['id'],
                'blade' => $blade,
                'justport' => $justport,
                'port' => $db2->Record['port'],
                'graph_id' => $db2->Record['graph_id'],
                'vlans' => '',
                'asset_id' => 0,
                'updated' => mysql_now(),
            ]), __LINE__, __FILE__);
            $id = $db->getLastInsertId('switchports', 'switchport_id');
            $local[$db2->Record['switch']]['ports'][$db2->Record['port']] = ['id' => $id, 'graph_id' => $db2->Record['graph_id']];            
        } elseif ($local[$db2->Record['switch']]['ports'][$db2->Record['port']]['graph_id'] != $db2->Record['graph_id']) {
            $now = mysql_now();
            $db->query("update switchports set updated='{$now}', graph_id={$db2->Record['graph_id']} where switchport_id={$local[$db2->Record['switch']]['ports'][$db2->Record['port']]['id']}", __LINE__, __FILE__);
            if ($verbose == true) {
                add_output("{$db2->Record['port']} Update Graph {$db2->Record['graph_id']}\n");
            }            
        } else {
            $nowUpdates[] = $local[$db2->Record['switch']]['ports'][$db2->Record['port']]['id'];
            $nowIdx++;
            if ($nowIdx > 1000) {
                $now = mysql_now();
                $db->query("update switchports set updated='{$now}' where switchport_id in (".implode(',', $nowUpdates).")", __LINE__, __FILE__);
                $nowUpdates = [];
                $nowIdx = 0;                
            } 
        }
    }
    if ($nowIdx > 0) {
        $now = mysql_now();
        $db->query("update switchports set updated='{$now}' where switchport_id in (".implode(',', $nowUpdates).")", __LINE__, __FILE__);
    } 
    global $output;
    echo str_replace("\n", "<br>\n", $output);
    $output = '';
}
