<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2019
 * @package IP-VLAN-Manager
 * @category IPs
 */

function ip_manager()
{
    $GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=ip.vlan_manager'));
}
