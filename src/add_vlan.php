<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package IP-VLAN-Manager
 * @category IPs
 */
/**
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function add_vlan()
{
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    //_debug_array(get_ips2_newer('68.168.208.0/20',TRUE));
    $ima = $GLOBALS['tf']->ima;
    global $groupinfo;
    $db = get_module_db('default');
    $db2 = $db;
    if (!isset($GLOBALS['tf']->variables->request['blocksize'])) {
        $table = new \TFTable();
        $table->set_title('Add New VLAN');
        $table->add_field('Enter Desired Block Size (ie /24)', 'l');
        $table->add_field('/'.$table->make_input('blocksize', '', 5), 'r');
        $table->add_row();
        $table->set_colspan(2);
        $table->add_field($table->make_submit('Proceed To Next Step'));
        $table->add_row();
        add_output($table->get_table()).'<br>';

        $t = new \TFTable();
        $t->set_title('VLAN Cheat Sheet');
        $t->add_field('Block Size');
        $t->add_field('Total IPs');
        $t->add_field('Available IPs');
        $t->add_row();

        $t->add_field('/32');
        $t->add_field('3');
        $t->add_field('1');
        $t->add_row();

        $t->add_field('/30');
        $t->add_field('4');
        $t->add_field('2');
        $t->add_row();

        $t->add_field('/29');
        $t->add_field('8');
        $t->add_field('6');
        $t->add_row();

        $t->add_field('/28');
        $t->add_field('16');
        $t->add_field('14');
        $t->add_row();

        $t->add_field('/27');
        $t->add_field('32');
        $t->add_field('30');
        $t->add_row();

        $t->add_field('/26');
        $t->add_field('64');
        $t->add_field('62');
        $t->add_row();

        $t->add_field('/25');
        $t->add_field('128');
        $t->add_field('126');
        $t->add_row();

        $t->add_field('/24');
        $t->add_field('256');
        $t->add_field('254');
        $t->add_row();

        add_output($t->get_table());
    } else {
        $blocksize = str_replace('/', '', $GLOBALS['tf']->variables->request['blocksize']);
        $blocks = available_ipblocks_new($blocksize);
        if (!isset($GLOBALS['tf']->variables->request['ipaddress'])) {
            // ok we have blocksize now need to determine what vlans are possible
            $ipcount = get_ipcount_from_netmask($blocksize);
            $table = new \TFTable();
            $table->set_title('Add New VLAN');
            $table->add_hidden('blocksize', $blocksize);
            $table->add_field('Block Size', 'l');
            $table->add_field('/'.$blocksize, 'r');
            $table->add_row();
            $table->add_field('Total IPs', 'l');
            $table->add_field($ipcount, 'r');
            $table->add_row();
            $table->add_field('Usable IPs', 'l');
            $table->add_field($ipcount - 2, 'r');
            $table->add_row();
            if (count($blocks) > 0) {
                $table->add_field('Enter Desired IP Block', 'l');
                $sel = '<select name=ipaddress>';
                for ($x = 0, $x_max = count($blocks); $x < $x_max; $x++) {
                    $sel .= '<option value='.$blocks[$x][0].'>'.$blocks[$x][0].'/'.$blocksize.'</option>';
                }
                $sel .= '</select>';
                $table->add_field($sel, 'r');
                //$table->add_field($table->make_input('ipaddress', '', 20), 'r');
                $table->add_row();
                $select = get_select_ports();
                $table->add_field('Select Switch/Port(s) that the VLan is on', 'l');
                $table->add_field($select, 'r');
                $table->add_row();
                $table->set_colspan(2);
                $table->add_field('Vlan Comment', 'c');
                $table->add_row();
                $table->set_colspan(2);
                $table->add_field('<textarea rows=7 cols=25 name=comment></textarea>', 'c');
                $table->add_row();
                $table->set_colspan(2);
                $table->add_field($table->make_submit('Proceed To Next Step'));
                $table->add_row();
            } else {
                $table->set_colspan(2);
                $table->add_field('<b>No Usable Blocks Found Matching This Block Size</b>');
                $table->add_row();
            }
            add_output($table->get_table());
        } else {
            $ports = $GLOBALS['tf']->variables->request['ports'];
            if (count($ports) > 0) {
                $ipaddress = $GLOBALS['tf']->variables->request['ipaddress'];
                $found = false;
                for ($x = 0, $x_max = count($blocks); $x < $x_max; $x++) {
                    if ($blocks[$x][0] == $ipaddress) {
                        $block = $blocks[$x][1];
                        $found = true;
                    }
                }
                $ips = get_ips_newer($ipaddress.'/'.$blocksize, true);
                $db->query("select * from ips left join vlans on ips_vlan=vlans_id where ips_ip in ('" . implode("', '", $ips) . "') and vlans_id is not NULL");
                if ($db->num_rows() > 0) {
                    $found = false;
                    while ($db->next_record()) {
                        echo 'Conflicting IP: '.$db->Record['ips_ip'].'<br>';
                    }
                }
                if (!$found) {
                    echo 'I think this vlan already exists';
                    exit;
                }
                $comment = $GLOBALS['tf']->variables->request['comment'];
                $ports = ':'.implode(':', $ports).':';
                $db->query(make_insert_query(
                    'vlans',
                    [
                    'vlans_id' => null,
                    'vlans_block' => $block,
                    'vlans_networks' => ':'.$ipaddress.'/'.$blocksize.':',
                    'vlans_ports' => $ports,
                    'vlans_comment' => $comment
                                                    ]
                ), __LINE__, __FILE__);
                $vlan = $db->getLastInsertId('vlans', 'vlans_id');
                $query = "select ips_ip from ips where ips_ip in ('" . implode("', '", $ips) . "')";
                $db->query($query, __LINE__, __FILE__);
                $ips2 = [];
                while ($db->next_record()) {
                    $ips2[] = $db->Record['ips_ip'];
                }
                for ($x = 0, $x_max = count($ips); $x < $x_max; $x++) {
                    if (($x == 0) || ($x == (count($ips) - 1))) {
                        $reserved = 1;
                    } else {
                        $reserved = 0;
                    }
                    if (in_array($ips[$x], $ips2)) {
                        $query = "update ips set ips_vlan='{$vlan}', ips_serverid=0, ips_group=0, ips_reserved='{$reserved}' where ips_ip='$ips[$x]'";
                    } else {
                        $query = make_insert_query(
                            'ips',
                            [
                            'ips_ip' => $ips[$x],
                            'ips_vlan' => $vlan,
                            'ips_serverid' => 0,
                            'ips_group' => 0,
                            'ips_reserved' => $reserved
                                                        ]
                        );
                    }
                    $db->query($query, __LINE__, __FILE__);
                }
                add_output('VLAN Created');
                function_requirements('update_switch_ports');
                update_switch_ports();
                $GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
            } else {
                add_output('You must select at least one port');
            }
        }
    }
}
