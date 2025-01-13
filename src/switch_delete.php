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

function switch_delete() {
    page_title('Switches');
    $db = get_module_db('default');
    $homeDir = get_current_user() == 'www-data' ? '/var/www' : '/home/'.get_current_user();
    $defaultCommunity = CLOGIN_SNMP_COMMUNITY;
    $observium = [];
    $cacti = [];
    $my = [];
    $ips = [];
    add_output('<a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br><br>');
    $id = intval($GLOBALS['tf']->variables->request['id']);
    $db->query("select * from switchmanager where id={$id}", __LINE__, __FILE__);
    if ($db->num_rows() == 0) {
        add_output('Invalid Switch<br>');
        return;
    }
    $db->next_record(MYSQL_ASSOC);
    $switchInfo = $db->Record;
    $problems = [];
    $db->query("select switchports.*, assets.hostname, assets.status, server_hostname,server_status from switchports left join servers using (server_id) left join assets on asset_id=assets.id where switch={$id} and (vlans != '' or server_id >= 1 or asset_id >= 1)", __LINE__, __FILE__);
    if ($db->num_rows() > 0) {
        while ($db->next_record(MYSQL_ASSOC)) {
            if (!empty($db->Record['vlans'])) {
                $db2->query("select * from vlans where vlans_id in ({$db->Record['vlans']})", __LINE__, __FILE__);
                if ($db2->num_rows() > 0) {
                    while ($db2->next_record(MYSQL_ASSOC)) {
                        $problems[] = 'Switch Port '.$db->Record['port'].' tied to VLAN '.str_replace(':','',$db2->Record['vlans_networks']);
                    }
                }
                $vlanIds = array_merge($vlanIds, impode(',',$db->Record['vlans']));                
            }
            if (!is_null($db->Record['hostname'])) {
                $problems[] = 'Switch Port '.$db->Record['port'].' tied to Asset '.$db->Record['asset_id'].' '.$db->Record['hostname'];
            }
            if (!is_null($db->Record['server_hostname'])) {
                $problems[] = 'Switch Port '.$db->Record['port'].' tied to Server '.$db->Record['server_id'].' '.$db->Record['server_hostname'];
            }
            add_output('Switch Port '.$db->Record['port'].' Vlans "'.$db->Record['vlans'].'" Server "'.$db->Record['server_id'].'" Asset "'.$db->Record['asset_id'].'" still has items linked to it<br>');
        }
    }
    if (count($problems) > 0) {
        add_output('There are several things pointing to the switch ports still:<br>'.implode('<br>', $problems));
    } else {
        $db->query("delete from switchports where switch={$id}");
        $db->query("delete from switch_configs where switch={$id}");
        $db->query("delete from switchmanager where id={$id}");
        add_output('Switch '.$id.' and its Ports are deleted<br>');
    }
    add_output('<br><a href="switches" class="btn btn-primary btn-sm">Return to Switches</a><br>');
}
