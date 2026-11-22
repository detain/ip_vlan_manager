<?php
/**
* Switch 
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package MyAdmin
* @category Networking
* 
*/

use Detain\SshPool\SshPool;
use Detain\IpVlanManager\SnmpCommandBuilder;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;

function switch_edit() {
    page_title('Switches');
    $db = get_module_db('default');
    $homeDir = get_current_user() == 'www-data' ? '/var/www' : '/home/'.get_current_user();
    $defaultCommunity = CLOGIN_SNMP_COMMUNITY;
    $observium = [];
    $cacti = [];
    $my = [];
    $ips = [];
    add_output('<a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br><br>');
    $id = intval(\MyAdmin\App::variables()->request['id']);
    $db->query("select * from switchmanager where id={$id}", __LINE__, __FILE__);
    if ($db->num_rows() == 0) {
        add_output('Invalid Switch<br>');
        return;
    }
    $db->next_record(MYSQL_ASSOC);
    $switchInfo = $db->Record;
    $portToId = [];
    $db->query("select switchport_id, port from switchports where switch={$id}", __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $portToId[$db->Record['port']] = $db->Record['switchport_id'];
    }
    if (!isset(\MyAdmin\App::variables()->request['name'])) {
        $table = new TFTable;
        $table->hide_table();
        $table->set_method('POST');
        $table->csrf('switch_edit', false);
        $table->add_hidden('id', $id);
        $table->set_title('Edit Switch');
        $table->add_field('ID');
        $table->add_field($switchInfo['id']);
        $table->add_field('&nbsp;');
        $table->add_field('Name');
        $table->add_field($table->make_input('name', $switchInfo['name'], 15));
        $table->add_field('&nbsp;');
        $table->add_field('IP');
        $table->add_field($table->make_input('ip', $switchInfo['ip'], 15));
        $table->add_row();
        $table->add_field('Updated');
        $table->add_field($switchInfo['updated']);
        $table->add_field('&nbsp;');
        $table->add_field('Type');
        $table->add_field(make_select('type', ['cisco', 'junos'], ['Cisco', 'Juniper'], $switchInfo['type']));
        $table->add_field('&nbsp;');
        $table->add_field('Asset');
        $table->add_field($table->make_input('asset', $switchInfo['asset'], 15));
        $table->add_row();
        $table->add_field('Available');
        $table->add_field(make_select('available', ['0', '1'], ['No', 'Yes'], $switchInfo['available']));
        $table->add_field('&nbsp;');
        $table->add_field('SNMP Version');
        $table->add_field(make_select('snmp_version', ['v1', 'v2c'], ['v1', 'v2c'], $switchInfo['snmp_version']));
        $table->add_field('&nbsp;');
        $table->add_field('SNMP Community');
        $table->add_field($table->make_input('snmp_community', $switchInfo['snmp_community'], 15));
        $table->add_row();
        $sshpool = new SshPool(SNMP_SSH_HOST, SNMP_SSH_PORT, SNMP_SSH_USER, false, $homeDir.'/.ssh/id_rsa.pub', $homeDir.'/.ssh/id_rsa');
        $sshpool->setMinConfigSize(0);
        $sshpool->setMaxRetries(0);
        $sshpool->setMaxThreads(2);
        global $indexes;
        $indexes = [];
        //snmpget -t 2 -v2c -c *********** -Pud -OQUs switch72n.trouble-free.net sysDescr.0
        // B009: the stored version/community/type must satisfy the same closed
        // vocabularies the save path enforces; legacy dirty rows surface an
        // error here instead of splicing the value into a remote command.
        try {
            $probeCommands = [];
            foreach (['ifEntry' /* , 'ifXEntry' */] as $oid) {
                $probeCommands[] = SnmpCommandBuilder::walk($switchInfo['snmp_version'], $switchInfo['snmp_community'], $switchInfo['ip'], $oid, $switchInfo['type']);
            }
        } catch (\InvalidArgumentException $e) {
            $probeCommands = [];
            add_output('Stored Switch Parameters Invalid - fix them before probing: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '<br>');
        }
        foreach ($probeCommands as $cmd) {
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $sshpool->addCommand($cmd, null, null, function($cmd, $conId, $data, $exitStatus, $stdout, $stderr) {
                global $indexes;
                if ($exitStatus == 0) {
                    if (preg_match_all('/^(?P<field>\S+)\.(?P<index>\d+) = (?P<value>.*)$/muU', $stdout, $matches)) {
                        foreach ($matches['field'] as $idx => $field) {
                            $index = $matches['index'][$idx];
                            $value = $matches['value'][$idx];
                            if (!isset($indexes[$index])) {
                                $indexes[$index] = [];
                            }
                            $field = substr($field, 2);
                            $indexes[$index][$field] = $value;
                        }
                    }
                } else {
                    add_output('There was an error with SNMP communications on the device.<br>'.$stdout.$stderr.'<br>');
                }
            });
        }
        $sshpool->run();
        if (count($indexes) > 0) {
            $interfaces = [];
            foreach ($indexes as $index => $values) {
                $interface = $values['Descr'];
                unset($values['Name']);
                unset($values['Index']);
                unset($values['Descr']);
                //ksort($values);
                $interfaces[$interface] = $values;
            }
            ksort($interfaces);
            $table->set_colspan(8);
            $table->add_field('Select Ports To Add');
            $table->add_row();
            $first = true;
            $idx = 0;
            //$types = ['mplsTunnel', 'other', 'softwareLoopback', 'tunnel', 'ethernetCsmacd', 'propVirtual', 'ieee8023adLag', 'l2vlan'];
            $extraPorts = array_diff(array_keys($portToId), array_keys($interfaces));
            foreach ($interfaces as $name => $data) {
                $idx++;
                /*if ($first == true) {
                    $table->add_field('&nbsp;');
                    $table->add_field('Port');
                    foreach (array_keys($data) as $value) {
                        $table->add_field($value);
                    }
                    $table->add_row();
                    $first = false;
                }*/
                $shouldCheck = in_array($data['Type'], ['ethernetCsmacd']) && preg_match('/^(Ethernet|et-|ge-|xe-)/', $name);
                $checked = in_array($name, array_keys($portToId));
                $table->set_col_options('style="padding: 0 0 0 0; margin: 0 0 0 0;"');
                $table->add_field('<input type="checkbox" name="ports[]" value="'.$name.'" '.($checked ? 'checked="checked"' : '').'>&nbsp;'.$name.(!$checked && $shouldCheck ? '<br>(should check)' : ''));
                /*
                $table->add_field($name);
                foreach ($data as $field => $value) {
                    $table->add_field($value);
                }*/
                if ($idx % 8 == 0) {
                    $table->add_row();                
                }
            }
            if (count($extraPorts) > 0) {
                foreach ($extraPorts as $name) {
                    $idx++;
                    $table->set_col_options('style="padding: 0 0 0 0; margin: 0 0 0 0;"');
                    $table->add_field('<input type="checkbox" name="ports[]" value="'.$name.'" checked="checked">&nbsp;'.$name.'<br>(not found)');
                    if ($idx % 8 == 0) {
                        $table->add_row();
                    }
                }
            }
            if ($idx % 8 != 0) {
                while ($idx % 8 != 0) {
                    $idx++;
                    $table->add_field('&nbsp;');
                }
                $table->add_row();                
            }
        }
        $table->set_colspan(8);
        $table->add_field($table->make_submit('Update Switch'));
        $table->add_row();            
        add_output($table->get_table().'<br>');
    } else {
        // B009: the save leg mutates the inventory -> POST + page token only;
        // the ip/type/version/name stored here feed the probe commands above
        // on every later render, so they are whitelisted at the save gate.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf('switch_edit')) {
            return;
        }
        $name = \MyAdmin\App::variables()->request['name'];
        $ip = \MyAdmin\App::variables()->request['ip'];
        $available = \MyAdmin\App::variables()->request['available'];
        $asset = intval(\MyAdmin\App::variables()->request['asset']);
        $type = !empty(\MyAdmin\App::variables()->request['type']) ? \MyAdmin\App::variables()->request['type'] : 'cisco';
        $ver = !empty(\MyAdmin\App::variables()->request['snmp_version']) ? \MyAdmin\App::variables()->request['snmp_version'] : 'v2c';
        $community = !empty(\MyAdmin\App::variables()->request['snmp_community']) ? \MyAdmin\App::variables()->request['snmp_community'] : $defaultCommunity;
        if (!SnmpCommandBuilder::validSwitchName($name)) {
            add_output('Invalid Switch Name<br>');
            return;
        }
        try {
            SnmpCommandBuilder::ip($ip);
            SnmpCommandBuilder::versionFlag($ver);
            SnmpCommandBuilder::mibsDir($type);
        } catch (\InvalidArgumentException $e) {
            add_output('Invalid Switch Parameters ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '<br>');
            return;
        }
        if (!in_array((string)$available, ['0', '1'], true)) {
            add_output('Invalid Available Flag<br>');
            return;
        }
        $updates = [];
        foreach (['name', 'ip', 'type', 'asset', 'available', 'snmp_version', 'snmp_community'] as $field) {
            if (!empty(\MyAdmin\App::variables()->request[$field]) && \MyAdmin\App::variables()->request[$field] != $switchInfo[$field]) {
                $esc = $db->real_escape(\MyAdmin\App::variables()->request[$field]);
                $updates[] = "{$field}='{$esc}'";
            }
        }
        if (isset(\MyAdmin\App::variables()->request['ports'])) {
            $ports = \MyAdmin\App::variables()->request['ports'];
            if (count($ports) != $switchInfo['ports']) {
                $updates[] = "ports=".count($ports);
            }
            $delPorts = array_diff(array_keys($portToId), $ports);
            $addPorts = array_diff($ports, array_keys($portToId));
        } else {
            $delPorts = [];
            $addPorts = [];            
        }
        if (count($updates) == 0 && count($delPorts) == 0 && count($addPorts) == 0) {
            add_output('Nothing To Update');
        } else {
            $db->query("update switchmanager set ".implode(', ', $updates)." where id={$id}", __LINE__, __FILE__);
            if (count($delPorts) > 0) {
                add_output("delete from switchports where port='{$port}' and switch='{$switch}'<br>");
            }
            if (count($addPorts) > 0) {
                foreach ($addPorts as $port) {
                    if (false !== $pos = strrpos($port, '/')) {
                        $blade = substr($port, 0, $pos);
                        $justport = substr($port, $pos + 1);
                    } else {
                        $blade = '';
                        $justport = $port;
                    }
                    $db->query(make_insert_query('switchports', [
                        'switch' => $id,
                        'blade' => $blade,
                        'justport' => $justport,
                        'port' => $port,
                    ]), __LINE__, __FILE__);
                }
            }
        }
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
}
