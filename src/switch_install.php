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

function switch_install() {
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
    $id = intval($GLOBALS['tf']->variables->request['id']);
    $installs = isset($GLOBALS['tf']->variables->request['install']) ? $GLOBALS['tf']->variables->request['install'] : [];
    $converter = new AnsiToHtmlConverter();
    $db3->query("select * from switchmanager where id={$id}", __LINE__, __FILE__);
    if ($db3->num_rows() > 0) {
        $db3->next_record(MYSQL_ASSOC);
        $sshpool = new SshPool(SNMP_SSH_HOST, SNMP_SSH_PORT, SNMP_SSH_USER, false, $homeDir.'/.ssh/id_rsa.pub', $homeDir.'/.ssh/id_rsa');
        $sshpool->setMinConfigSize(0);
        $sshpool->setMaxRetries(0);
        $sshpool->setMaxThreads(2);
        $cmd = "/usr/bin/snmpget -t 2 -{$db3->Record['snmp_version']} -c {$db3->Record['snmp_community']} -Pud -OQUs -m IF-MIB -M /opt/observium/mibs/rfc:/opt/observium/mibs/net-snmp {$db3->Record['ip']} sysDescr.0";
        myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
        $ret = $sshpool->runCommand($cmd);
        if ($ret['exitStatus'] != 0) {
            add_output("There was an error testing the SNMP communications: {$ret['out']} {$ret['err']}<br>");
        } else {
            if (in_array('cacti', $installs)) {
                $deviceTemplate = $db3->Record['type'] == 'cisco' ? 5 : 1;
                $deviceVer = $db3->Record['snmp_version'] == 'v2c' ? 2 : 1;
                $cmd = "php /usr/share/cacti/cli/add_device.php --description='{$db3->Record['name']}' --ip='{$db3->Record['ip']}' --template={$deviceTemplate} --external-id={$id} --version={$deviceVer} --community='{$db3->Record['snmp_community']}'";
                myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
                $ret = $sshpool->runCommand($cmd);
                add_output('Added Device to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
                $db->query("select id from cacti.host where description='{$db3->Record['name']}'", __LINE__, __FILE__);
                $db->next_record(MYSQL_ASSOC);
                add_output("Got Cacti Device ID {$db->Record['id']}<br>");
                $cmd = "php /usr/share/cacti/cli/add_graphs.php --host-id={$db->Record['id']} --graph-type=ds --graph-template-id=2 --snmp-query-id=1 --snmp-query-type-id=24 --snmp-field=ifType --snmp-value=ethernetCsmacd";
                $ret = $sshpool->runCommand($cmd);
                add_output('Added Graphs to Cacti<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert(['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
            }
            if (in_array('observium', $installs)) {
                $cmd = "php /opt/observium/add_device.php '{$db3->Record['name']}.trouble-free.net' '{$db3->Record['snmp_community']}' {$db3->Record['snmp_version']}";
                myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
                $ret = $sshpool->runCommand($cmd);
                add_output('Added Device to Observium<br>NOTE: It will take several minutes before the IP shows up properly with Observium<br><pre style="background-color: black; overflow: auto; padding: 10px 15px; font-family: monospace;">'.$converter->convert($ret['out'])."\n".$converter->convert($ret['err']).'</pre><br>');
            }                
        }            
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
    return;        
}
