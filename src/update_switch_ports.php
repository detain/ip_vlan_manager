<?php

/**
 * updates the switch ports
 *
 * @param bool $verbose wether or not to enable verbose output.
 * @param bool $pullServerMap defaults to true, optional flag allowing disabling of the switch/ports updating via https://nms.is.cc/cacti/servermap.php
 */
function update_switch_ports($verbose = false, $pullServerMap = true)
{
    $db = get_module_db('default');
    $db2 = clone $db;
    if ($pullServerMap !== false) {
        $vlan_ids = [];
        $switch_ids = [];
        $db->query("select vlans_id from vlans");
        while ($db->next_record(MYSQL_ASSOC)) {
            $vlan_ids[$db->Record['vlans_id']] = true;
        }
        $lines = explode("\n", trim(getcurlpage('https://nms.is.cc/cacti/servermap.php')));
        $switches = [];
        foreach ($lines as $line) {
            [$graph_id, $switch, $port, $comment] = explode(',', $line);
            if ($switch != '') {
                $switches[$switch][$port] = $graph_id;
            }
        }
        foreach ($switches as $switch => $ports) {
            $foundports = [];
            $db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
            if ($db->num_rows() == 0) {
                $db->query(make_insert_query('switchmanager', [
                    'id' => null,
                    'name' => $switch,
                    'ports' => count($ports),
                    'updated' => mysql_now(),
                ]), __LINE__, __FILE__);
                if ($verbose == true) {
                    add_output("Created New Switch {$switch} - ");
                }
                $db->query("select * from switchmanager where name='{$switch}'", __LINE__, __FILE__);
            }
            $db->next_record(MYSQL_ASSOC);
            $switchManager = $db->Record;
            $db->query("update switchmanager set updated=now() where id={$switchManager['id']}", __LINE__, __FILE__);
            $switch_ids[] = $switchManager['id'];
            if ($verbose == true) {
                add_output("Loaded Switch {$switch} - ");
            }
            foreach ($ports as $port => $graph_id) {
                $blade = '';
                $justport = $port;
                if (mb_strrpos($port, '/') > 0) {
                    $blade = mb_substr($port, 0, mb_strrpos($port, '/'));
                    $justport = mb_substr($port, mb_strlen($blade) + 1);
                }
                if (isset($foundports[$justport])) {
                    $justport = '';
                } else {
                    $foundports[$justport] = true;
                }
                $db->query("select * from switchports where switch='{$switchManager['id']}' and port='{$port}'", __LINE__, __FILE__);
                if ($db->num_rows() == 0) {
                    if ($verbose == true) {
                        add_output("{$port} +");
                    }
                    $db->query(make_insert_query('switchports', [
                        'switch' => $switchManager['id'],
                        'blade' => $blade,
                        'justport' => $justport,
                        'port' => $port,
                        'graph_id' => $graph_id,
                        'vlans' => '',
                        'asset_id' => 0,
                        'updated' => mysql_now(),
                    ]), __LINE__, __FILE__);
                } else {
                    $db->next_record();
                    if (($db->Record['blade'] != $blade) || ($db->Record['justport'] != $justport)) {
                        if ($verbose == true) {
                            add_output("\nUpdate BladePort");
                        }
                        $query = "update switchports set blade='{$blade}', justport='{$justport}' where switch='{$switchManager['id']}' and port='{$port}'";
                        //echo $query;
                        $db->query($query, __LINE__, __FILE__);
                    }
                    if ($verbose == true) {
                        add_output("$port ");
                    }
                    if ($db->Record['graph_id'] != $graph_id) {
                        if ($verbose == true) {
                            add_output("\nUpdate Graph");
                        }
                        $query = "update switchports set graph_id='{$graph_id}' where switch='{$switchManager['id']}' and port='{$port}'";
                        //echo $query;
                        $db->query($query, __LINE__, __FILE__);
                    }
                    $db->query("update switchports set updated=now() where switchport_id={$db->Record['switchport_id']}", __LINE__, __FILE__);
                    if ($verbose == true) {
                        add_output("$graph_id ");
                    }
                }
                if ($verbose == true) {
                    add_output(',');
                }
            }
            if ($verbose == true) {
                add_output("\n");
            }
        }
        add_output(sizeof(array_keys($vlan_ids)).' Unmatched VLANs'.PHP_EOL);
    }
    global $output;
    echo str_replace("\n", "<br>\n", $output);
    $output = '';
}
