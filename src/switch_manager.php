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
function switch_manager()
{
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    global $groupinfo;
    $db = get_module_db('default');
    $db2 = get_module_db('default');
    $ima = $GLOBALS['tf']->ima;
    if (isset($GLOBALS['tf']->variables->request['name']) && isset($GLOBALS['tf']->variables->request['ports'])) {
        $name = $GLOBALS['tf']->variables->request['name'];
        $ip = $GLOBALS['tf']->variables->request['ip'];
        $ports = $GLOBALS['tf']->variables->request['ports'];
        $db->query(make_insert_query('switchmanager',[
            'id' => null,
            'name' => $name,
            'ip' => $ip,
            'ports' => $ports
        ]), __LINE__, __FILE__);
    }
    if (isset($GLOBALS['tf']->variables->request['delete'])) {
        $id = intval($GLOBALS['tf']->variables->request['delete']);
        $db->query("select * from switchports where switch={$id} and (vlans != '' or server_id >= 1 or asset_id >= 1)");
        if ($db->num_rows() > 0) {
            while ($db->next_record(MYSQL_ASSOC)) {
                add_output('Switch Port '.$db->Record['port'].' Vlans "'.$db->Record['vlans'].'" Server "'.$db->Record['server_id'].'" Asset "'.$db->Record['asset_id'].'" still has items linked to it<br>');
            }
        } else {
            $db->query("delete from switchports where switch={$id}");
            $db->query("delete from switch_configs where switch={$id}");
            $db->query("delete from switchmanager where id={$id}");
            add_output('Switch '.$id.' and its Ports are deleted<br>');
        }
    }
    $table = new \TFTable();
    $table->set_title('Switches');
    $table->add_field('Internal ID');
    $table->add_field('Switch Name');
    $table->add_field('Switch IP');
    $table->add_field('Last Updated');
    $table->add_field('Total Ports<br>(including uplink)');
    $table->add_field('Usable Ports');
    $table->add_field('Links');
    $table->add_row();
    $nextid = 14;
    $db->query('select * from switchmanager order by id');
    $table->alternate_rows();
    while ($db->next_record()) {
        if ($nextid <= (int)$db->Record['name']) {
            $nextid = (int)$db->Record['name'] + 1;
        }
        $table->add_field($db->Record['id']);
        $table->add_field($db->Record['name']);
        $table->add_field($db->Record['ip']);
        $table->add_field($db->Record['updated']);
        $table->add_field($db->Record['ports']);
        $table->add_field($db->Record['ports'] - 1);
        $table->add_field($table->make_link('choice=none.switch_manager&delete='.$db->Record['id'], 'Delete'));
        $table->add_row();
    }
    $table->add_field('Add Switch');
    $table->add_field($table->make_input('name', $nextid, 15));
    $table->add_field($table->make_input('ip', '', 15));
    $table->add_field('&nbsp;');
    $table->add_field($table->make_input('ports', 49, 5));
    $table->add_field('&nbsp;');
    $table->add_field($table->make_submit('Add Switch'));
    $table->add_row();
    add_output($table->get_table());
}
