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
    // B009/B142-class: this page runs remote device-registration commands;
    // it only executes from the POST form on the Switches page with a valid
    // page token, never from a bookmarkable GET.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf('switch_install')) {
        return;
    }
    $id = intval(\MyAdmin\App::variables()->request['id']);
    $installs = isset(\MyAdmin\App::variables()->request['install']) ? \MyAdmin\App::variables()->request['install'] : [];
    $converter = new AnsiToHtmlConverter();
    $db3->query("select * from switchmanager where id={$id}", __LINE__, __FILE__);
    if ($db3->num_rows() > 0) {
        $db3->next_record(MYSQL_ASSOC);
        // B009: stored row fields must satisfy the same vocabularies the
        // save paths enforce before they are spliced into any command.
        $name = $db3->Record['name'];
        $ip = $db3->Record['ip'];
        $ver = $db3->Record['snmp_version'];
        $community = (string)($db3->Record['snmp_community'] !== null && $db3->Record['snmp_community'] !== '' ? $db3->Record['snmp_community'] : $defaultCommunity);
        $type = $db3->Record['type'];
        if (!SnmpCommandBuilder::validSwitchName($name)) {
            add_output('Invalid Switch Name stored for this device - fix it before installing<br>');
            add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
            return;
        }
        try {
            $getCommand = SnmpCommandBuilder::get($ver, $community, $ip, 'sysDescr.0');
            // resolve the mibs dir eagerly too so a bad stored type fails the
            // same way (sysDescr probe does not take a type dir, walk does)
            SnmpCommandBuilder::mibsDir($type);
        } catch (\InvalidArgumentException $e) {
            add_output('Stored Switch Parameters Invalid - fix them before installing: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '<br>');
            add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
            return;
        }
        $sshpool = new SshPool(SNMP_SSH_HOST, SNMP_SSH_PORT, SNMP_SSH_USER, false, $homeDir.'/.ssh/id_rsa.pub', $homeDir.'/.ssh/id_rsa');
        $sshpool->setMinConfigSize(0);
        $sshpool->setMaxRetries(0);
        $sshpool->setMaxThreads(2);
        $cmd = $getCommand;
        myadmin_log('myadmin', 'debug', "Running {$cmd}", __LINE__, __FILE__);
        $ret = $sshpool->runCommand($cmd);
        if ($ret['exitStatus'] != 0) {
            add_output("There was an error testing the SNMP communications: {$ret['out']} {$ret['err']}<br>");
        } else {
            if (in_array('cacti', $installs)) {
                $deviceTemplate = $type == 'cisco' ? 5 : 1;
                $cmd = SnmpCommandBuilder::addCactiDevice($name, $ip, $deviceTemplate, $id, $ver, $community);
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
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
    return;        
}
