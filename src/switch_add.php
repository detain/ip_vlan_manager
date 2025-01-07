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
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;

function switch_add() {
    page_title('Switches');
    $db = new \MyDb\Mysqli\Db('observium', SNMP_MYSQL_USER, SNMP_MYSQL_PASS, SNMP_SSH_HOST);
    $db2 = clone $db;
    $db3 = get_module_db('default');
    $homeDir = get_current_user() == 'www-data' ? '/var/www' : '/home/'.get_current_user();
    $defaultCommunity = CLOGIN_SNMP_COMMUNITY;
    $observium = [];
    $cacti = [];
    $my = [];
    $ips = [];
    add_output('<a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br><br>');
    $name = $GLOBALS['tf']->variables->request['name'];
    $ip = $GLOBALS['tf']->variables->request['ip'];
    $installs = isset($GLOBALS['tf']->variables->request['install']) ? $GLOBALS['tf']->variables->request['install'] : [];
    $type = !empty($GLOBALS['tf']->variables->request['type']) ? $GLOBALS['tf']->variables->request['type'] : 'cisco';
    $typeDir = $type == 'junos' ? 'juniper' : $type;
    $ver = !empty($GLOBALS['tf']->variables->request['ver']) ? $GLOBALS['tf']->variables->request['ver'] : 'v2c';
    $community = !empty($GLOBALS['tf']->variables->request['community']) ? $GLOBALS['tf']->variables->request['community'] : $defaultCommunity;
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        add_output('Invalid IP Address '.$ip.'<br>');
        return;
    }
    $sshpool = new SshPool(SNMP_SSH_HOST, SNMP_SSH_PORT, SNMP_SSH_USER, false, $homeDir.'/.ssh/id_rsa.pub', $homeDir.'/.ssh/id_rsa');
    $sshpool->setMinConfigSize(0);
    $sshpool->setMaxRetries(0);
    $sshpool->setMaxThreads(2);
    if (!isset($GLOBALS['tf']->variables->request['ports'])) {
        global $indexes;
        $indexes = [];
        //snmpget -t 2 -v2c -c *********** -Pud -OQUs switch72n.trouble-free.net sysDescr.0
        foreach (['ifEntry' /* , 'ifXEntry' */] as $oid) {
            $cmd = "/usr/bin/snmpbulkwalk -t 2 -{$ver} -c {$community} -Pud -OQUs -m IF-MIB -M /opt/observium/mibs/rfc:/opt/observium/mibs/net-snmp:/opt/observium/mibs/{$typeDir} {$ip} {$oid}";
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $sshpool->addCommand($cmd, null, null, function($cmd, $conId, $data, $exitStatus, $stdout, $stderr) use ($db3, $oid, $name, $ip, $type, $ver, $community) {
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
            $table = new TFTable();
            $table->hide_table();
            $table->add_hidden('action', 'import');
            $table->add_hidden('name', $name);
            $table->add_hidden('ip', $ip);
            $table->add_hidden('type', $type);
            $table->add_hidden('ver', $ver);
            $table->add_hidden('community', $community);
            foreach ($installs as $install)
                $table->add_hidden('install[]', $install);
            $table->set_title('Add Switch '.$name);
            $table->add_field('Name');
            $table->add_field($name);
            $table->add_field('&nbsp;');
            $table->add_field('IP');
            $table->add_field($ip);
            $table->add_field('&nbsp;');
            $table->add_field('Type');
            $table->add_field($type);
            $table->add_row();            
            $table->add_field('SNMP Version');
            $table->add_field($ver);
            $table->add_field('&nbsp;');
            $table->add_field('SNMP Community');
            $table->add_field($community);
            $table->add_field('&nbsp;');
            $table->add_field('SNMP Communication');
            $table->add_field('Working');
            $table->add_row();
            $table->set_colspan(8);
            $table->add_field('Select Ports To Add');
            $table->add_row();
            $first = true;
            $idx = 0;
            //$types = ['mplsTunnel', 'other', 'softwareLoopback', 'tunnel', 'ethernetCsmacd', 'propVirtual', 'ieee8023adLag', 'l2vlan'];
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
                $checked = in_array($data['Type'], ['ethernetCsmacd']) && preg_match('/^(Ethernet|et-|ge-|xe-)/', $name) ? 'checked="checked"' : '';
                $table->set_col_options('style="padding: 0 0 0 0; margin: 0 0 0 0;"');
                $table->add_field('<input type="checkbox" name="ports[]" value="'.$name.'" '.$checked.'>&nbsp;'.$name);
                /*
                $table->add_field($name);
                foreach ($data as $field => $value) {
                    $table->add_field($value);
                }*/
                if ($idx % 8 == 0) {
                    $table->add_row();                
                }
            }
            if ($idx % 8 != 0) {
                while ($idx % 8 != 0) {
                    $idx++;
                    $table->add_field('&nbsp;');
                }
                $table->add_row();                
            }
            $table->set_colspan(8);
            $table->add_field($table->make_submit('Add Switch'));
            $table->add_row();            
            add_output($table->get_table().'<br>');
        }
    } else {
        $ports = $GLOBALS['tf']->variables->request['ports'];
        $db3->query(make_insert_query('switchmanager', [
            'name' => $name,
            'ports' => count($ports),
            'ip' => $ip,
            'type' => $type,
            'available' => 1,
            'snmp_version' => $ver,
            'snmp_community' => $community,
        ]), __LINE__, __FILE__);
        $switchId = $db3->getLastInsertId('switchmanager', 'id');
        foreach ($ports as $port) {
            if (false !== $pos = strrpos($port, '/')) {
                $blade = substr($port, 0, $pos);
                $justport = substr($port, $pos + 1);
            } else {
                $blade = '';
                $justport = $port;
            }
            $db3->query(make_insert_query('switchports', [
                'switch' => $switchId,
                'blade' => $blade,
                'justport' => $justport,
                'port' => $port,
            ]), __LINE__, __FILE__);
        }
        add_output("Added switch {$switchId} {$name}<br>");
        if (in_array('cacti', $installs)) {
            $deviceTemplate = $type == 'cisco' ? 5 : 1;
            $deviceVer = $ver == 'v2c' ? 2 : 1;
            $cmd = "php /usr/share/cacti/cli/add_device.php --description='{$name}' --ip='{$ip}' --template={$deviceTemplate} --external-id={$switchId} --version={$deviceVer} --community='{$community}'";
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Device to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
            $db->query("select id from cacti.host where description='{$name}'", __LINE__, __FILE__);
            $db->next_record(MYSQL_ASSOC);
            add_output("Got Cacti Device ID {$db->Record['id']}<br>");
            $cmd = "php /usr/share/cacti/cli/add_graphs.php --host-id={$db->Record['id']} --graph-type=ds --graph-template-id=2 --snmp-query-id=1 --snmp-query-type-id=24 --snmp-field=ifType --snmp-value=ethernetCsmacd";
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Graphs to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert(['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
        }
        if (in_array('observium', $installs)) {
            $cmd = "php /opt/observium/add_device.php '{$name}.trouble-free.net' '{$community}' {$ver}";
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Device to Observium<br>NOTE: It will take several minutes before the IP shows up properly with Observium<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
        }
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
}
