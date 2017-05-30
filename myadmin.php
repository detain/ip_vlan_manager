<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_softaculous define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'IP Management',
	'description' => 'Enables management and allocation of IPs',
	'help' => '',
	'module' => '',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-softaculous-licensing',
	'repo' => 'https://github.com/detain/myadmin-softaculous-licensing',
	'version' => '1.0.3',
	'type' => 'functionality',
	'hooks' => [
		'function.requirements' => ['Detain\IpVlanManager\Plugin', 'Requirements'],
	/*	'ui.menu' => ['Detain\IpVlanManager\Plugin', 'Menu'] */
	],
];
