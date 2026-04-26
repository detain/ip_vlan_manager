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
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function add_ips_to_server()
{
    $ima = \MyAdmin\App::ima();
    $db = get_module_db('default');
    $db2 = $db;
    function_requirements('has_acl');
    if (\MyAdmin\App::ima() != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    $ipblock = \MyAdmin\App::variables()->request['ipblock'];
    if (!isset(\MyAdmin\App::variables()->request['ips'])) {
        $db->query("select * from vlans where vlans_networks like '%:$ipblock:%'", __LINE__, __FILE__);
        while ($db->next_record()) {
            $ipinfo = ipcalc($ipblock);
            $ips = get_ips_newer($ipblock);
            $table = new \TFTable();
            $table->add_hidden('ipblock', $ipblock);
            $table->set_title('Add IP(s) To Server');
            $table->add_field('Please Select Server To Add IP(s) To');
            $table->add_field(select_server(0, 'server'));
            $table->add_row();
            $table->add_field('Please Select IP(s) To Add');
            $sel = '<select multiple size=8 name="ips[]">';
            $db2->query("select * from ips where ips_ip in ('" . implode("', '", $ips) . "') and ips_serverid=0", __LINE__, __FILE__);
            while ($db2->next_record()) {
                $sel .= '<option value='.$db2->Record['ips_ip'].'>'.$db2->Record['ips_ip'].'</option>';
            }
            $sel .= '</select>';
            $table->add_field($sel, 'r');
            $table->add_row();
            $table->set_colspan(2);
            $table->add_field($table->make_submit('Add These IP(s)'));
            $table->add_row();
            add_output($table->get_table());
        }
    } else {
        $server = \MyAdmin\App::variables()->request['server'];
        $server_info = \MyAdmin\App::getServer($server);
        $group = get_first_group($server_info['servers_group']);
        if ($server_info) {
            $ips = \MyAdmin\App::variables()->request['ips'];
            for ($x = 0, $x_max = count($ips); $x < $x_max; $x++) {
                $db->query("update ips set ips_group='{$group}', ips_serverid='{$server_info['servers_serverid']}' where ips_ip='{$ips[$x]}'", __LINE__, __FILE__);
            }
            add_output("IP(s) Successfully Assigned To $server<br>");
            add_output(\MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock='.$ipblock), 1));
        } else {
            add_output('Invalid Server ('.$server.')');
        }
    }
}
