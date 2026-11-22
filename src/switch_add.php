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
    // B009: every leg of this page either runs remote commands or writes the
    // inventory, so both entry legs must arrive POST with the page token -
    // bookmarkable/cross-site GETs can no longer reach the SNMP probe.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf('switch_add')) {
        return;
    }
    $name = \MyAdmin\App::variables()->request['name'];
    $ip = \MyAdmin\App::variables()->request['ip'];
    $installs = isset(\MyAdmin\App::variables()->request['install']) ? \MyAdmin\App::variables()->request['install'] : [];
    $type = !empty(\MyAdmin\App::variables()->request['type']) ? \MyAdmin\App::variables()->request['type'] : 'cisco';
    $ver = !empty(\MyAdmin\App::variables()->request['ver']) ? \MyAdmin\App::variables()->request['ver'] : 'v2c';
    $community = !empty(\MyAdmin\App::variables()->request['community']) ? \MyAdmin\App::variables()->request['community'] : $defaultCommunity;
    // B009 server-side gates: name, type and version are whitelisted before
    // anything stores, splices or echoes them; the community has no closed
    // vocabulary, so it is only ever passed through escapeshellarg (below and
    // in SnmpCommandBuilder) or the SQL escape builders.
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
    $sshpool = new SshPool(SNMP_SSH_HOST, SNMP_SSH_PORT, SNMP_SSH_USER, false, $homeDir.'/.ssh/id_rsa.pub', $homeDir.'/.ssh/id_rsa');
    $sshpool->setMinConfigSize(0);
    $sshpool->setMaxRetries(0);
    $sshpool->setMaxThreads(2);
    if (!isset(\MyAdmin\App::variables()->request['ports'])) {
        global $indexes;
        $indexes = [];
        //snmpget -t 2 -v2c -c *********** -Pud -OQUs switch72n.trouble-free.net sysDescr.0
        foreach (['ifEntry' /* , 'ifXEntry' */] as $oid) {
            $cmd = SnmpCommandBuilder::walk($ver, $community, $ip, $oid, $type);
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
            $table->set_method('POST');
            $table->csrf('switch_add', false);
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
                $table->add_field('<input type="checkbox" name="ports[]" value="'.\Detain\IpVlanManager\HtmlOut::attr($name).'" '.$checked.'>&nbsp;'.htmlspecialchars($name, ENT_QUOTES));
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
        $ports = \MyAdmin\App::variables()->request['ports'];
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
        $converter = new AnsiToHtmlConverter();
        if (in_array('cacti', $installs)) {
            $deviceTemplate = $type == 'cisco' ? 5 : 1;
            $cmd = SnmpCommandBuilder::addCactiDevice($name, $ip, $deviceTemplate, $switchId, $ver, $community);
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Device to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
            $db->query("select id from cacti.host where description='".$db->real_escape($name)."'", __LINE__, __FILE__);
            $db->next_record(MYSQL_ASSOC);
            add_output("Got Cacti Device ID {$db->Record['id']}<br>");
            $cmd = SnmpCommandBuilder::addCactiGraphs((int)$db->Record['id']);
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Graphs to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
        }
        if (in_array('observium', $installs)) {
            $cmd = SnmpCommandBuilder::addObserviumDevice($name.'.trouble-free.net', $community, $ver);
            myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
            $ret = $sshpool->runCommand($cmd);
            add_output('Added Device to Observium<br>NOTE: It will take several minutes before the IP shows up properly with Observium<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
        }
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
}
