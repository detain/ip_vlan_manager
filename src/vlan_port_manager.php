<?php
/**
 * Manage VLAN assignments on switch ports.
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2024
 * @package IP-VLAN-Manager
 * @category IPs
 */
 
/**
 * @return bool
 * @throws \Exception
 * @throws \SmartyException
 */
function vlan_port_manager()
{
    function_requirements('update_switch_ports');
    $ima = $GLOBALS['tf']->ima;
    $db = get_module_db('default');
    $db2 = $db;
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }

    $ipblock = $GLOBALS['tf']->variables->request['ipblock'];q
    $db->query("SELECT * FROM vlans WHERE vlans_networks LIKE '%:$ipblock:%'", __LINE__, __FILE__);
    
    if (($ipblock == '') || ($db->num_rows() == 0)) {
        add_output('Invalid IP Block');
        return;
    }

    $db->next_record();
    $vlan_id = $db->Record['vlans_id'];

    if (!isset($GLOBALS['tf']->variables->request['ports'])) {
        // Fetch current ports associated with this VLAN from the switchports table
        $db2->query("SELECT switchport_id FROM switchports WHERE FIND_IN_SET('{$vlan_id}', vlans)", __LINE__, __FILE__);
        $currentPorts = [];
        while ($db2->next_record(MYSQL_ASSOC)) {
            $currentPorts[] = $db2->Record['switchport_id'];
        }

        // Generate selection dropdown with current ports pre-selected
        $select = get_select_ports($currentPorts);
        $table = new \TFTable();
        $table->set_title('VLan Port Manager');
        $table->add_hidden('ipblock', $ipblock);
        $table->add_field('IP Block', 'l');
        $table->add_field($ipblock, 'r');
        $table->add_row();
        $table->add_field('Select Switch/Port(s) that the VLan is on', 'l');
        $table->add_field($select, 'r');
        $table->add_row();
        $table->set_colspan(2);
        $table->add_field($table->make_submit('Update This VLan'));
        $table->add_row();
        add_output($table->get_table());
    } else {
        $selectedPorts = $GLOBALS['tf']->variables->request['ports'];

        // Remove the VLAN from all switchports currently associated with it except the selecte ones
        $db2->query("SELECT switchport_id, vlans FROM switchports WHERE FIND_IN_SET('{$vlan_id}', vlans)", __LINE__, __FILE__);
        while ($db2->next_record(MYSQL_ASSOC)) {
            $currentSwitchportId = $db2->Record['switchport_id'];
            $currentVlans = explode(',', $db2->Record['vlans']);

            // Remove the current VLAN from the list if it is not in the list of selected ports
            if (($key = array_search($vlan_id, $currentVlans)) !== false && !in_array($db2->Record['switch'].'/'.$db2->Record['port'], $selectedPorts)) {
                unset($currentVlans[$key]);
                $updatedVlans = implode(',', $currentVlans);
                $db2->query("UPDATE switchports SET vlans='{$updatedVlans}' WHERE switchport_id='{$currentSwitchportId}'", __LINE__, __FILE__);
            }
        }

        // Add the VLAN to the selected ports
        foreach ($selectedPorts as $selectedPort) {
            $selectedSwitch = substr($selectedPort, 0, strpos($selectedPort, '/'));
            $selectedPort = substr($selectedPort, strpos($selectedPort, '/') + 1);
            $db2->query("SELECT switchport_id, vlans FROM switchports WHERE switch='{$selectedSwitch}' and port='{$selectedPort}' and find_in_set({$vlan_id}, vlans) = 0", __LINE__, __FILE__);
            if ($db2->num_rows() > 0) {
                $db2->next_record(MYSQL_ASSOC);
                $newVlans = !empty($db2->Record['vlans']) ? $db2->Record['vlans'] . ",{$vlan_id}" : $vlan_id;
                $db2->query("UPDATE switchports SET vlans='{$newVlans}' WHERE switchport_id='{$db2->Record['switchport_id']}'", __LINE__, __FILE__);
            } else {
                add_output("Warning: Could not find port {$db2->Record['switchport_id']} for VLAN assignment.");
            }
        }

        function_requirements('update_switch_ports');
        update_switch_ports();
        $GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.ipblock_viewer&amp;ipblock='.$ipblock));
    }
}
