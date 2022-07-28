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
 */
function add_ips()
{
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('system_config')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    global $groupinfo;
    $db = get_module_db('default');
    $color1 = COLOR1;
    $color3 = COLOR2;
    $color2 = COLOR3;
    $choice = $GLOBALS['tf']->variables->request['choice'];
    if (!isset($GLOBALS['tf']->variables->request['ipclass'])) {
        add_output('<TABLE>'.'<TR bgcolor="'.$color3.'" align=center><TD colspan=2>IP Address Addition Menu</TD></TR>'.'<TR bgcolor="'.$color1 .
            '" align=center><TD colspan=2>Adding A Single Class C</TD></TR>'.'<form enctype="multipart/form-data" method="post" action="'.$GLOBALS['tf']->link('index.php').'">' .
            "<input type=hidden name=choice value=$choice>".'<TR><TD bgcolor="'.$color2.'">'.'Enter First 3 Set Of IPs In The Class C (ie 216.74.109):'.'</TD><TD bgcolor="'.$color3.'">' .
            '<input type=text name=ipclass>'.'</TD></TR>'.'<TR bgcolor="'.$color1.'" align=center><TD colspan=2>'.'<input type=submit value="Add This Class C">'.'</TD></TR>'.'</FORM>' .
            '<TR><TD colspan=2>&nbsp;</TD></TR>'.'<TR bgcolor="'.$color1.'" align=center><TD colspan=2>I Want To Add Less Than A Class C</TD></TR>' .
            '<form enctype="multipart/form-data" method="post" action="'.$GLOBALS['tf']->link('index.php').'">'."<input type=hidden name=choice value=$choice>".'<TR><TD bgcolor="'.$color2.'">' .
            'Enter First 3 Set Of IPs In The Class C (ie 216.74.109):'.'</TD><TD bgcolor="'.$color3.'">'.'<input type=text name=ipclass>'.'</TD></TR>'.'<TR><TD bgcolor="'.$color2.'">' .
            'Enter Lowest IP In The Range (ie 2):'.'</TD><TD bgcolor="'.$color3.'">'.'<input type=text name=iplow>'.'</TD></TR>'.'<TR><TD bgcolor="'.$color2.'">' .
            'Enter Highest IP In The Range (ie 254):'.'</TD><TD bgcolor="'.$color3.'">'.'<input type=text name=iphigh>'.'</TD></TR>'.'<TR bgcolor="'.$color1.'" align=center><TD colspan=2>' .
            '<input type=submit value="Add This Range">'.'</TD></TR>'.'</FORM>'.'</TABLE>');
    } else {
        $ipclass = $GLOBALS['tf']->variables->request['ipclass'];
        add_output('Adding IPs: ');
        if (!isset($GLOBALS['tf']->variables->request['iplow'])) {
            $iplow = 2;
        } else {
            $iplow = $GLOBALS['tf']->variables->request['iplow'];
        }

        if (!isset($GLOBALS['tf']->variables->request['iphigh'])) {
            $iphigh = 254;
        } else {
            $iphigh = $GLOBALS['tf']->variables->request['iphigh'];
        }

        $new_ips = 0;
        for ($num = $iplow; $num < ($iphigh + 1); $num++) {
            $ipAddress = $ipclass.'.'.$num;
            $db->query("select * from ips where ips_ip='{$ipAddress}'", __LINE__, __FILE__);
            if ($db->num_rows() == 0) {
                if ($GLOBALS['tf']->accounts->data['demo'] == 1) {
                    add_output('No Updates In Demo Mode');
                } else {
                    $db->query(make_insert_query(
                        'ips',
                        [
                        'ips_ip' => $ipAddress,
                        'ips_serverid' => 0,
                        'ips_group' => $groupinfo['account_id']
                                                      ]
                    ), __LINE__, __FILE__);
                    $new_ips++;
                    add_output('+');
                }
            } else {
                add_output('-');
            }
        }
        add_output('done<br>');
        add_output('<br>');
        add_output($new_ips.'New IPs Added<br>');
        add_output('<br>');
        add_output('- = IP Already In Database<br>');
        add_output('+ = IP New To Database<br>');
        add_output('<br>');
    }
}
