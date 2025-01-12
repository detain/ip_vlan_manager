<?php
/**
* Switches Management
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package MyAdmin
* @category Networking 
* 
* TODO:
* - delete switch
* - make list of ports long
*/

use Detain\SshPool\SshPool;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;

function switches() {
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
	$db->query("select device_id,hostname,sysName,ip,snmp_community,snmp_version,sysObjectID,version,hardware,vendor,os,status,status_type,serial from observium.devices where ip is not null", __LINE__, __FILE__);
	while ($db->next_record(MYSQL_ASSOC)) {
        $db->Record['id'] = $db->Record['device_id'];
        $db->Record['type'] = $db->Record['vendor'] == 'Cisco' ? 'cisco' : 'junos';
		$observium[$db->Record['id']] = $db->Record;
        if (!array_key_exists($db->Record['ip'], $ips)) {
            $ips[$db->Record['ip']] = [];
        }
        $ips[$db->Record['ip']]['observium'] = $db->Record['device_id']; 
	}
	$db->query("select id,description,hostname,snmp_version,snmp_community,snmp_sysObjectID,last_updated,snmp_sysDescr from cacti.host where disabled != 'on' and hostname != ''", __LINE__, __FILE__);
	while ($db->next_record(MYSQL_ASSOC)) {                                                                               
        $db->Record['ip'] = filter_var($db->Record['hostname'], FILTER_VALIDATE_IP) === false ? gethostbyname($db->Record['hostname']) : $db->Record['hostname'];
        if (strpos($db->Record['snmp_sysDescr'], 'Cisco') !== false) {
            $db->Record['type'] = 'cisco';
        } elseif (strpos($db->Record['snmp_sysDescr'], 'Juniper') !== false) {
            $db->Record['type'] = 'junos'; 
        } elseif (strpos($db->Record['snmp_sysDescr'], 'APC') !== false) {
            $db->Record['type'] = 'apc'; 
        } else {
            $db->Record['type'] = 'unknown';
        }
		$cacti[$db->Record['id']] = $db->Record;
        if (!array_key_exists($db->Record['ip'], $ips)) {
            $ips[$db->Record['ip']] = [];
        }
        $ips[$db->Record['ip']]['cacti'] = $db->Record['id']; 
	}
    $maxId = 1;
	$db3->query("select switchmanager.id,name,ports,updated,ip,asset,type,available,snmp_version,snmp_community,hostname from switchmanager left join assets on asset=assets.id", __LINE__, __FILE__);
	while ($db3->next_record(MYSQL_ASSOC)) {
        if (preg_match('/^(switch)?(?P<id>\d+)n?$/', $db3->Record['name'], $matches)) {
            $id = intval($matches['id']);
            if ($id > $maxId) {
                $maxId = $id;
            }
        }
		$my[$db3->Record['id']] = $db3->Record;
        if (!array_key_exists($db3->Record['ip'], $ips)) {
            $ips[$db3->Record['ip']] = [];
        }
        $ips[$db3->Record['ip']]['my'] = $db3->Record['id']; 
	}
    $newId = $maxId + 1;
    $counts = [3 => [], 2 => [], 1 => []];
    foreach ($ips as $ip => $ipData) {
        $counts[count($ipData)][] = $ip;
    }
    //add_output('1 Count:'.count($counts[1]).'<br>2 Count:'.count($counts[2]).'<br>3 Count:'.count($counts[3]).'<br>');
    $table = new TFTable;
    $table->set_method('GET');
    $table->add_hidden('action', 'add');
    $table->set_title('Switches');
    $table->add_field('Name');
    $table->add_field('Type');
    $table->add_field('IP');
    $table->add_field('Avail');
    $table->add_field('Ver');
    $table->add_field('Community');
    $table->set_colspan(3);        
    $table->add_field('Links');
    $table->set_col_style('min-width: 250px;');
    $table->add_field('Notes');
    $table->add_row();
    $table->add_field($table->make_input('name', $newId, 15));
    $table->add_field(make_select('type', ['cisco', 'junos'], ['Cisco', 'Juniper']));
    $table->add_field($table->make_input('ip', '', 15));
    $table->add_field(make_select('available', [0, 1], ['No', 'Yes'], 1));
    $table->add_field(make_select('ver', ['v1', 'v2c'], ['v1', 'v2c'], 'v2c'));
    $table->add_field($table->make_input('community', $defaultCommunity, 15));
    $table->add_field('&nbsp;');
    $table->add_field($table->make_checkbox('install[]', 'observium', true, 'title="Add To Obsdervium"'));
    $table->add_field($table->make_checkbox('install[]', 'cacti', true, 'title="Add To Cacti"'));
    $table->add_field($table->make_submit('Add Switch', false, true, 'class="btn btn-sm btn-primary"'));
    $table->add_row();
    foreach ($counts as $count => $countIps) {
        foreach ($countIps as $ip) {
            $types = [];
            $snmpVersion = false;
            $snmpCommunity = false;
            $notes = [];
            $switchName = false;
            $switchType = false;
            $links = [
                'my' => '&nbsp;',
                'observium' => '&nbsp;',
                'cacti' => '&nbsp;'
            ];
            if (isset($ips[$ip]['my'])) {
                $id = $ips[$ip]['my'];
                $types[] = $my[$id]['type'];
                $snmpVersion = $my[$id]['snmp_version'];
                $snmpCommunity = $my[$id]['snmp_community'];
                $switchName = $my[$id]['name'];
                $switchType = $my[$id]['type'];
                $links['my'] = $table->make_link('choice=none.switch_edit&id='.$id, 'My');
                if (intval($my[$id]['asset']) > 0) {
                    if (is_null($my[$id]['hostname'])) {
                        $notes[] = 'Points to invalid asset '.$my[$id]['asset'];
                    } else {
                        $notes[] = $table->make_link('choice=none.asset_form&id='.$my[$id]['asset'], 'View Asset', false, 'class="btn btn-primary btn-sm"');
                    }
                } else {
                    $notes[] = 'No Asset Set';
                }
                if ($ip != '') {
                    if (!isset($ips[$ip]['observium'])) {
                        $notes[] = $table->make_link('choice=none.switch_install&install[]=observium&id='.$id, 'Add to Observium', false, 'class="btn btn-primary btn-sm"');
                    }
                    if (!isset($ips[$ip]['cacti'])) {
                        $notes[] = $table->make_link('choice=none.switch_install&install[]=cacti&id='.$id, 'Add to Cacti', false, 'class="btn btn-primary btn-sm"');
                    }
                } else {
                    $notes[] = 'Blank IP.';
                }
                $notes[] = $table->make_link('choice=none.switch_delete&id='.$id, 'Delete', false, 'class="btn btn-danger btn-sm"');
            }
            if (isset($ips[$ip]['observium'])) {
                $id = $ips[$ip]['observium'];
                $types[] = $observium[$id]['type'];
                if ($snmpVersion === false) {
                    $snmpVersion = $observium[$id]['snmp_version'];
                }
                if ($snmpCommunity === false) {
                    $snmpCommunity = $observium[$id]['snmp_community'];
                }
                if ($switchName === false) {
                    $switchName = str_replace('.trouble-free.net','', $observium[$id]['hostname']);
                }
                if ($switchType === false) {
                    $switchType = $observium[$id]['type'];
                }
                if (isset($ips[$ip]['my'])) {
                    $myId = $ips[$ip]['my'];
                    if ($observium[$id]['snmp_version'] != $my[$ips[$ip]['my']]['snmp_version']) {
                        $notes[] = 'SNMP Version Difference Observium "'.$observium[$id]['snmp_version'].'" and My "'.$my[$ips[$ip]['my']]['snmp_version'].'".';
                    }
                    if ($observium[$id]['snmp_community'] != $my[$ips[$ip]['my']]['snmp_community']) {
                        $notes[] = 'SNMP Community Difference Observium "'.$observium[$id]['snmp_community'].'" and My "'.$my[$ips[$ip]['my']]['snmp_community'].'".';
                        //$db3->query("update switchmanager set snmp_version='{$observium[$id]['snmp_version']}', snmp_community='{$observium[$id]['snmp_community']}' where id={$myId}", __LINE__, __FILE__);
                    }
                } else {
                    if (!filter_var($observium[$id]['hostname'], FILTER_VALIDATE_IP)) {
                        $notes[] = $table->make_link('choice=none.switch_add&name='.str_replace('.trouble-free.net','', $observium[$id]['hostname']).'&ip='.$observium[$id]['ip'].'&type='.$observium[$id]['type'].'&ver='.$observium[$id]['snmp_version'].'&community='.$observium[$id]['snmp_community'].(!isset($ips[$ip]['cacti']) ? '&install[]=cacti' : ''), 'Import from Observium', false, 'class="btn btn-primary btn-sm"');
                    } else {
                        $notes[] = 'Observium Devices should use Hostnames.';
                    }
                    
                }
                $links['observium'] = '<a href="https://obs.is.cc/device/device='.$id.'/" target="_blank" title="View '.$observium[$id]['hostname'].' in Observium"><img src="https://obs.is.cc/images/observium-icon.png" style="height: 24px;" alt="Observium"></a>';
            }
            if (isset($ips[$ip]['cacti'])) {
                $id = $ips[$ip]['cacti'];
                if ($cacti[$id]['type'] != 'unknown') {
                    $types[] = $cacti[$id]['type'];
                    if ($switchType === false) {
                        $switchType = $cacti[$id]['type'];
                    }
                }
                $cactiVers = [1 => 'v1', 2 => 'v2c'];
                if ($snmpVersion === false) {
                    $snmpVersion = $cactiVers[$cacti[$id]['snmp_version']];
                }
                if ($snmpCommunity === false) {
                    $snmpCommunity = $cacti[$id]['snmp_community'];
                }
                if ($switchName === false) {
                    $switchName = $cacti[$id]['description'];
                }
                if (isset($ips[$ip]['my'])) {
                    $myId = $ips[$ip]['my'];
                    if ($cactiVers[$cacti[$id]['snmp_version']] != $my[$ips[$ip]['my']]['snmp_version']) {
                        $notes[] = 'SNMP Version Difference Cacti "'.$cactiVers[$cacti[$id]['snmp_version']].'" and My "'.$my[$ips[$ip]['my']]['snmp_version'].'".';
                    }
                    if ($cacti[$id]['snmp_community'] != $my[$ips[$ip]['my']]['snmp_community']) {
                        $notes[] = 'SNMP Community Difference Cacti "'.$cacti[$id]['snmp_community'].'" and My "'.$my[$ips[$ip]['my']]['snmp_community'].'".';
                        //$db3->query("update switchmanager set snmp_community='{$cacti[$id]['snmp_community']}' where id={$myId}", __LINE__, __FILE__);
                    }
                } else {
                    if ($cacti[$id]['type'] != 'unknown') {
                        $notes[] = $table->make_link('choice=none.switch_add&name='.$cacti[$id]['description'].'&ip='.$cacti[$id]['ip'].($cacti[$id]['type'] != 'unknown' ? '&type='.$cacti[$id]['type'] : '').'&ver='.$cactiVers[$cacti[$id]['snmp_version']].'&community='.$cacti[$id]['snmp_community'].(!isset($ips[$ip]['observium']) ? '&install[]=observium' : ''), 'Import from Cacti', false, 'class="btn btn-primary btn-sm"');
                    }
                }
                $links['cacti'] = '<a href="https://nms.is.cc/cacti/host.php?action=edit&id='.$id.'" target="_blank" title="View '.$cacti[$id]['description'].' in Cacti"><img src="https://nms.is.cc/cacti/include/themes/classic/images/cacti_logo.gif" style="height: 24px;" alt="Cact"></a>';
            }
            $table->add_field($switchName);
            $table->add_field($switchType === false ? '&nbsp;' : '<img src="https://obs.is.cc/images/os/'.($switchType == 'junos' ? 'juniper' : $switchType).'.svg" style="max-height: 26px; max-width: 48px;" alt="'.$switchType.'">');
            $table->add_field($ip);
            $table->add_field(isset($ips[$ip]['my']) ? ($my[$ips[$ip]['my']]['available'] == 1 ? '<i class="text-success fa fa-check"></i>' : '<i class="text-danger fa fa-remove"></i>') : '&nbsp;');
            $table->add_field($snmpVersion);
            $table->add_field($snmpCommunity);
            $table->add_field($links['my']);
            $table->add_field($links['observium']);
            $table->add_field($links['cacti']);
            $types = array_unique($types);
            if (count($types) > 1) {
                $notes[] = 'Different Switch Types.';
            }
            $table->add_field(count($notes) > 0 ? implode('&nbsp;', $notes) : '&nbsp;');
            $table->add_row();
        }
    }
    $table->hide_table();
    add_output($table->get_table());
}
