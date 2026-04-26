<?php
/**
 * IP Functionality
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @package IP-VLAN-Manager
 * @category IPs
 */

function ip_manager()
{
    \MyAdmin\App::output()->redirect(\MyAdmin\App::link('index.php', 'choice=ip.vlan_manager'));
}
