<?php
/**
 * Softaculous Related Functionality
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2017
 * @package IP-VLAN-Manager-Softaculous-Licensing
 * @category Licenses
 */

namespace Detain\IpVlanManager;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\IpVlanManager
 */
class Plugin {

	public static $name = 'IP Management';
	public static $description = 'Enables management and allocation of IPs';
	public static $type = 'functionality';

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
	}

	/**
	 * @return array
	 */
	public static function getHooks() {
		return [
			'function.requirements' => [__CLASS__, 'getRequirements'],
			/* 'ui.menu' => [__CLASS__, 'getMenu'] */
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('add_ips', '/../vendor/detain/ip_vlan_manager/src/add_ips.php');
		$loader->add_requirement('add_ips_to_server', '/../vendor/detain/ip_vlan_manager/src/add_ips_to_server.php');
		$loader->add_requirement('add_vlan', '/../vendor/detain/ip_vlan_manager/src/add_vlan.php');
		$loader->add_requirement('alt_ip_manager', '/../vendor/detain/ip_vlan_manager/src/alt_ip_manager.php');
		$loader->add_requirement('delete_vlan', '/../vendor/detain/ip_vlan_manager/src/delete_vlan.php');
		$loader->add_requirement('edit_vlan_comment', '/../vendor/detain/ip_vlan_manager/src/edit_vlan_comment.php');
		$loader->add_requirement('ip_manager', '/../vendor/detain/ip_vlan_manager/src/ip_manager.php');
		$loader->add_requirement('ipblock_viewer', '/../vendor/detain/ip_vlan_manager/src/ipblock_viewer.php');
		$loader->add_requirement('portless_vlans', '/../vendor/detain/ip_vlan_manager/src/portless_vlans.php');
		$loader->add_requirement('switch_manager', '/../vendor/detain/ip_vlan_manager/src/switch_manager.php');
		$loader->add_requirement('vlan_edit_port', '/../vendor/detain/ip_vlan_manager/src/vlan_edit_port.php');
		$loader->add_page_requirement('vlan_manager', '/../vendor/detain/ip_vlan_manager/src/vlan_manager.php');
		$loader->add_requirement('vlan_port_manager', '/../vendor/detain/ip_vlan_manager/src/vlan_port_manager.php');
		$loader->add_requirement('vlan_port_server_manager', '/../vendor/detain/ip_vlan_manager/src/vlan_port_server_manager.php');
		$loader->add_requirement('available_ipblocks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('check_ip_part', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_all_ipblocks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_all_ips2_from_ipblocks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_all_ips_from_ipblocks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_client_ipblocks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_client_ips', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_ipcount_from_netmask', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_ips', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_ips2', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_networks', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_select_ports', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('get_switch_name', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('ipcalc', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('ipcalc_array', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('ipcalc_old', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('ipnetmask2gateway', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('ips_hostname', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('network2gateway', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('parse_vlan_ports', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('subnet2netmask', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_page_requirement('update_switch_ports', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
		$loader->add_requirement('validIp', '/../vendor/detain/ip_vlan_manager/src/ip.functions.inc.php');
	}
}
