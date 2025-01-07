<?php
/**
* Switch VLANs
* @author Joe Huss <detain@interserver.net>
* @copyright 2025
* @package MyAdmin
* @category servers
* 
* - parse switch configurations
*   - supports junos and cisco
*   - parses interfaces and vlans
* - supports ip blocks linked via a vlan interface
* - matches switch configs against db
* - skips private ips
* - identifies missing entries on switch or db
* - provides links to remedy problems
* - skip switches without a config
* - unifies range text to ensure its all formatted the same to ensure matching
* - provides limiting displaying of information
*   - by switch
*   - by ipv4/ipv6
*   - by type (cisco/junos)
*   - by and status (good/bad/missing db/switch)
* - can show the interface/ports switch config in a popup modal
*   - includes config for linked vlans
* - displays asset tied to port and links to the asset_form in a new window
* - buttons and table changes layout/content based on display sizde
* 
* @todo add sorting
* @todo add code to process links
* @todo on the various action pages add a confirmation page/screen which could show more details about what it was going to do
* @todo update switch config data
*/

function actionDeleteDb(&$db, $vlan, $switchId, $portId, $switchData, $portData, $range, $ipv, $ipvSuffix, $strip) {
    // remove the vlans assignment in the switchports table entry
    $db->query("select * from vlans{$ipvSuffix[$ipv]} where vlans{$ipvSuffix[$ipv]}_networks='{$strip[$ipv]}{$vlan}{$strip[$ipv]}'", __LINE__, __FILE__);
    $message = [];
    if ($db->num_rows() > 0) {
        $db->next_record(MYSQL_ASSOC);
        $vlanId = $db->Record['vlans'.$ipvSuffix[$ipv].'_id'];
        $query = "select * from switchports where switchport_id={$portData['switchport_id']}";
        $db->query($query, __LINE__, __FILE__);
        if ($db->num_rows() == 0) {
            $message[] = 'Error loading switchport '.$portData['switchport_id'];
        } else {
            $db->next_record(MYSQL_ASSOC);
            if (!empty($db->Record['vlans'.$ipvSuffix[$ipv]])) {
                $vlanIds = explode(',', $db->Record['vlans'.$ipvSuffix[$ipv]]);
            } else {
                $vlanIds = [];
            }
            $newVlanIds = [];
            foreach ($vlanIds as $tmpVlanId) {
                if ($vlanId != $tmpVlanId) {
                    $newVlanIds[] = $tmpVlanId;
                }
            }
            $vlanIds = implode(',', $newVlanIds);
            $db->query("update switchports set vlans{$ipvSuffix[$ipv]}='{$vlanIds}' where switchport_id={$portData['switchport_id']}", __LINE__, __FILE__);
            $message[] = 'Updated switchports id '.$portData['switchport_id'].' setting vlans'.$ipvSuffix[$ipv].' to '.$vlanIds;
            // and if no other switchports are using the vlan then remove the vlans table entry as well.
            $db->query("select * from switchports where find_in_set({$vlanId}, vlans{$ipvSuffix[$ipv]})", __LINE__, __FILE__);
            if ($db->num_rows() == 0) {
                $db->query("delete from vlans{$ipvSuffix[$ipv]} where vlans{$ipvSuffix[$ipv]}_id={$vlanId}", __LINE__, __FILE__);
                $message[] = 'Deleted vlans'.$ipvSuffix[$ipv].' '.$vlanId.' since nothing was left tied to it'; 
            }
        }
    }
    return $message;
}

function actionAddDb(&$db, $vlan, $switchId, $portId, $switchData, $portData, $range, $ipv, $ipvSuffix, $strip, $ipblocks) {
    // create the vlans table entry and add it to the switchports entry
    $message = [];
    $query = "select * from vlans{$ipvSuffix[$ipv]} where vlans{$ipvSuffix[$ipv]}_networks='{$strip[$ipv]}{$vlan}{$strip[$ipv]}'";
    $db->query($query, __LINE__, __FILE__);
    if ($db->num_rows() > 0) {
        $db->next_record(MYSQL_ASSOC);
        $vlanId = $db->Record['vlans'.$ipvSuffix[$ipv].'_id'];
        $message[] = 'Reused existing vlans'.$ipvSuffix[$ipv].' id '.$vlanId;
    } else {
        $range = \IPLib\Factory::parseRangeString($vlan);
        $newVlan = [
            'vlans'.$ipvSuffix[$ipv].'_block' => 0,
            'vlans'.$ipvSuffix[$ipv].'_networks' => $strip[$ipv].$range->toString().$strip[$ipv],
        ];
        if ($ipv == 4) {                
            foreach ($ipblocks as $ipblockId => $block) {
                if ($block->containsRange($range)) {
                    $newVlan['vlans_block'] = $ipblockId;
                }
            }
        } else {
            $newVlan['vlans6_block'] = explode(':', $vlan)[2];
        }
        $query = make_insert_query('vlans'.$ipvSuffix[$ipv], $newVlan);
        $db->query($query, __LINE__, __FILE__);
        $vlanId = $db->getLastInsertId('vlans'.$ipvSuffix[$ipv], 'vlans'.$ipvSuffix[$ipv].'_id');
        //$message[] = $query;
        $message[] = 'Created new vlans'.$ipvSuffix[$ipv].' id '.$vlanId;
    }
    $query = "select * from switchports where switchport_id={$portData['switchport_id']}";
    $db->query($query, __LINE__, __FILE__);
    if ($db->num_rows() == 0) {
        $message[] = 'Error loading switchport '.$portData['switchport_id'];
    } else {
        $db->next_record(MYSQL_ASSOC);
        if (!empty($db->Record['vlans'.$ipvSuffix[$ipv]])) {
            $vlanIds = explode(',', $db->Record['vlans'.$ipvSuffix[$ipv]]);
        } else {
            $vlanIds = [];
        }
        if (!in_array($vlanId, $vlanIds)) {
            $vlanIds[] = $vlanId;
            $vlanIds = implode(',', $vlanIds);
            $query = "update switchports set vlans{$ipvSuffix[$ipv]}='{$vlanIds}' where switchport_id={$portData['switchport_id']}";
            $db->query($query, __LINE__, __FILE__);
            //$message[] = $query;
            $message[] = 'Updated switchports id '.$portData['switchport_id'].' setting vlans'.$ipvSuffix[$ipv].' to '.$vlanIds;
        }        
    }
    return $message;
}

function blockIsLocal($block, $ranges) {
    //$block = \IPLib\Factory::parseRangeString($block);
    foreach ($ranges as $id => $range)
        if ($range->containsRange($block))
            return true;
        return false;
}

function parseSwitchBackups($name = '*', &$switchConfigs = [], $new = false) {
    $switches = [];
    $lines = [];
    $linesNew = [];
    foreach (glob('/home/sites/switch_configs/store/'.$name) as $path) {
        if (!is_dir($path)) {
            $name = basename($path);
            $input = file_get_contents($path);
            $input = str_replace("\r", "", $input);
            $switches[(string)$name] = [];
            $switchConfigs[(string)$name] = [];
            if (strpos($input, 'Cisco Nexus') !== false) {
                preg_match_all('/^interface\s(\S+).*(?:\n(?!interface\s).*)*/m', $input, $matches);
                foreach ($matches[0] as $idx => $match) {
                    $interfaceName = (string)$matches[1][$idx];
                    $switchConfigs[$name][$interfaceName] = $match;
                    $switches[$name][$interfaceName] = [];
                    if (preg_match_all('/^\s*description (.*)$/muU', $match, $nmatches)) {
                        $switches[$name][$interfaceName]['description'] = $nmatches[1][0];
                    }
                    if (preg_match_all('/^\s*ipv*6*\saddress\s([0-9a-f][^;\s\{]*)[;\s\{]/mi', $match, $nmatches)) {
                        $switches[$name][$interfaceName]['addresses'] = [];
                        $blocks = $nmatches[1];
                        $switches[$name][$interfaceName] = $blocks;
                        foreach ($blocks as $block) {
                            $range = \IPLib\Range\Subnet::parseString($block);
                            if (!is_null($range)) {
                                $switches[$name][$interfaceName]['addresses'][] = $range->toString();
                                //$lines[] = $name.','.$interfaceName.',a,'.$range->toString().',,';
                            }
                        }
                    }
                    if (preg_match_all('/^\s*switchport (trunk native|trunk allowed|access) vlan ([\d,\-]+)$/mu', $match, $nmatches)) {
                        $switches[$name][$interfaceName]['vlans'] = [];
                        foreach ($nmatches[2] as $idx => $vlanMatchData) {
                            $vlanMatches = explode(',', $vlanMatchData);
                            foreach ($vlanMatches as $vlanMatch) {
                                if (strpos($vlanMatch, '-') !== false) {
                                    $vlanMatchParts = explode('-', $vlanMatch);
                                    for ($vlanId = $vlanMatchParts[0]; $vlanId <= $vlanMatchParts[1]; $vlanId++) {
                                        if (!in_array('Vlan'.$vlanId, $switches[$name][$interfaceName]['vlans'])) {
                                            $switches[$name][$interfaceName]['vlans'][] = 'Vlan'.$vlanId;
                                            //$lines[] = $name.','.$interfaceName.',v,Vlan'.$vlanId.',,';
                                        }
                                    }
                                } else {
                                    if (!in_array('Vlan'.$vlanMatch, $switches[$name][$interfaceName]['vlans'])) {
                                        $switches[$name][$interfaceName]['vlans'][] = 'Vlan'.$vlanMatch;
                                        //$lines[] = $name.','.$interfaceName.',v,Vlan'.$vlanMatch.',,';
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($switches[$name] as $interfaceName => $portData) {
                    if (isset($portData['addresses'])) {
                        foreach ($portData['addresses'] as $block) {
                            $line = [$name, $interfaceName, 'a', $block, $portData['description'] ?? '', []];
                            $lines[] = $line;
                            $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $portData['description'] ?? '', 'src' => []];
                            $linesNew[] = $line;
                        }
                    }
                    if (isset($portData['vlans'])) {
                        foreach ($portData['vlans'] as $vlanId) {
                            if (isset($switches[$name][$vlanId])) {
                                $vlanData = $switches[$name][$vlanId];
                                $line = [$name, $interfaceName, 'v', $vlanId, $vlanData['description'] ?? '', []];
                                //$lines[] = $line;
                                $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'v', 'block' => $vlanId, 'description' => $portData['description'] ?? '', 'src' => []];
                                //$linesNew[] = $line;
                                if (isset($vlanData['addresses'])) {
                                    foreach ($vlanData['addresses'] as $block) {
                                        $line = [$name, $interfaceName, 'a', $block, $portData['description'] ?? '', [$vlanId]];
                                        $lines[] = $line;
                                        $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $portData['description'] ?? '', 'src' => ['Vlan'.$vlanId]];
                                        $linesNew[] = $line;
                                    }
                                }
                            }
                        }
                    }
                }
            } elseif (strpos($input, 'JUNOS') !== false) {
/* juniper interface prefix types
ae (Aggregate Ethernet)  Logical interfaces representing a Link Aggregation Group (LAG) or bundle of Ethernet interfaces.  Purpose: Combines multiple physical links into one logical interface for redundancy and increased bandwidth.
bme (Bridge MAC Ethernet)  Used internally by the device for bridging and MAC learning.  Purpose: Platform-specific internal functions.
cbp (Control Board Processor)  Interfaces representing internal control processors.  Purpose: Device control and management.
dsc (Discard)  Interfaces used to discard traffic.  Purpose: Configured as null interfaces for specific use cases.
em (Ethernet Management)  Dedicated management Ethernet interfaces.  Purpose: Out-of-band management traffic.
esi (Ethernet Segment Identifier)  Related to EVPN (Ethernet VPN) configurations.  Purpose: Identifies Ethernet segments in multi-homing setups.
et (Ethernet)  Interfaces for 40Gbps or 100Gbps Ethernet connections.  Purpose: Ultra-high-speed Ethernet links.
fxp (Management Ethernet)  Management Ethernet interfaces on certain platforms.  Purpose: Similar to em, for management traffic.
ge (Gigabit Ethernet)  Interfaces for 1Gbps Ethernet connections.  Purpose: Standard Ethernet connections.
gr (GRE Tunnel)  Interfaces for Generic Routing Encapsulation (GRE) tunnels.  Purpose: Encapsulates traffic for transport across different networks.
irb (Integrated Routing and Bridging)  Logical interfaces used for routing in VLAN environments.  Purpose: Enables Layer 3 routing for VLANs.
jsrv (Junos Services)  Interfaces for Junos services such as NAT or firewall.  Purpose: Internal service delivery.
lo (Loopback)  Virtual interfaces used for management and routing protocols.  Purpose: Typically assigned a stable IP address for device identification in a network.
lsi (Logical System Interface)  Logical interfaces used internally by the router.  Purpose: Internal communication and processing.
mtun (Multicast Tunnel)  Interfaces for multicast tunnel encapsulations.  Purpose: Supports multicast routing in tunneled environments.
pfe (Packet Forwarding Engine)  Interfaces representing the packet forwarding engine.  Purpose: Used internally for packet processing.
pfh (Packet Forwarding Hardware)  Hardware-level interfaces for packet forwarding.  Purpose: Internal hardware operations.
pime (PIM Encapsulation)  Interfaces for PIM encapsulation functions.  Purpose: Encapsulates multicast traffic.
pimpd (PIM Passive Discovery)  Interfaces related to Protocol Independent Multicast (PIM).  Purpose: Passive discovery for multicast routing.
pip (Physical IP)  Interfaces associated with physical IP functions.  Purpose: Underlying IP-related configurations.
ssxe (Secure Services Ethernet)  High-performance secure Ethernet interfaces on specific platforms.  Purpose: Typically related to encryption and security features.
tap (Tunnel Access Point)  Interfaces for software-defined tunnels.  Purpose: Virtualized tunneling interfaces.
vme (Virtual Management Ethernet)  Virtual management interfaces on virtual chassis or similar environments.  Purpose: Management traffic in a virtualized setup.
vtep (Virtual Tunnel Endpoint)  Interfaces used in VXLAN (Virtual Extensible LAN) configurations.  Purpose: Encapsulates and decapsulates VXLAN traffic.
xe (10-Gigabit Ethernet)  Interfaces for 10Gbps Ethernet connections.  Purpose: High-speed Ethernet links.
*/
                /**
                * interfaces links to ae (always)
                * interfaces units links to vlans
                * interfaces units has addresses 
                * interface ae units links to vlans
                * interface ae units has addresses
                * vlans links to irb units (always)
                * interface irb units links to addresses (always)
                */
                $interfaces = [];
                $vlans = [];
                $vlanIdToName = [];
                $sections = [];
                preg_match_all('/^(?P<section>interfaces|vlans)\s*\{\n(?P<config>.*?)\n^\}/msu', $input, $matches);
                foreach ($matches['section'] as $idx => $sectionName) {
                    $sections[$sectionName] = $matches['config'][$idx];
                }
                foreach ($sections as $sectionName => $sectionConfig) {
                    if (in_array($sectionName, ['interfaces', 'vlans'])) {
                        preg_match_all('/^\s{4}(?P<interface>\S+)\s[^\n]*\{\n(?P<config>.*)\n^\s{4}\}/msuU', $sectionConfig, $matches);
                        foreach ($matches['interface'] as $idx => $interfaceName) {
                            $interfaceConfig = $matches['config'][$idx];
                            $switchConfigs[$name][$interfaceName] = $matches[0][$idx];
                            if ($sectionName == 'interfaces') {
                                $interfaces[$interfaceName] = [];
                                // interfaces native-vlan-id 100;   does not route vlan 100 traffic through it 
                                //preg_match_all('/^\s*(native-vlan-id|vlan-id) (?P<vlan>\d*);$/muU', $interfaceConfig, $nmatches);
                                // interfaces 802.3ad ae40;
                                if (preg_match_all('/^\s*802.3ad (?P<interface>.*);$/muU', $interfaceConfig, $nmatches)) {
                                    $interfaces[$interfaceName]['links'] = [];
                                    foreach ($nmatches['interface'] as $vIdx => $linkInterface) {
                                        $interfaces[$interfaceName]['links'][] = $linkInterface;
                                    }
                                }
                                // interfaces unit 100 {
                                preg_match_all('/^\s{8}unit (?P<unit>\S*) \{\n(?P<config>.*)\n^\s{8}\}/msuU', $interfaceConfig, $nmatches);
                                foreach ($nmatches['unit'] as $vIdx => $unitName) {
                                    $switchConfigs[$name][$interfaceName.'.'.$unitName] = "    {$interfaceName} {\n        ...\n".$nmatches[0][$vIdx]."\n        ...\n    }";
                                    $unitConfig = $nmatches['config'][$vIdx];
                                    $interfaces[$interfaceName]['units'][(string)$unitName] = [];
                                    // interfaces.unit vlan-id 100; // does not route traffic thorugh it
                                    //preg_match_all('/^\s*(vlan-id) (?P<vlan>\d*);$/muU', $interfaceConfig, $nmatches);
                                    // interfaces|interfaces.unit|vlans description text;
                                    if (preg_match_all('/^\s*description (?P<description>.*);$/muU', $unitConfig, $omatches)) {
                                        $interfaces[$interfaceName]['units'][$unitName]['description'] = substr($omatches['description'][0], 0, 1) == '"' ? substr($omatches['description'][0], 1, -1) : $omatches['description'][0];
                                    }
                                    // interfaces.unit vlan { members 23; }   vlan { members 23-43; }   vlan { members [ 23 25 42-66 ]; }
                                    if (preg_match_all('/^\s*vlan \{\n\s*members (?P<members>[^;]*);\n\s*}/muU', $unitConfig, $omatches)) {
                                        $interfaces[$interfaceName]['units'][$unitName]['vlans'] = [];
                                        foreach ($omatches['members'] as $oIdx => $members) {
                                            if (substr($members, 0, 1) == '[') {
                                                $members = substr($members, 2, -2);
                                            }
                                            $members = explode(' ', $members);
                                            foreach ($members as $member) {
                                                if (strpos($member, '-') !== false) {
                                                    $membersParts = explode('-', $member);
                                                    for ($vlanId = $membersParts[0]; $vlanId <= $membersParts[1]; $vlanId++) {
                                                        $interfaces[$interfaceName]['units'][$unitName]['vlans'][] = (string)$vlanId;
                                                    }
                                                } else {
                                                    $interfaces[$interfaceName]['units'][$unitName]['vlans'][] = (string)$member;
                                                }
                                            }
                                        }
                                    }
                                    // interfaces.unit address 1.2.3.0/24;
                                    if (preg_match_all('/^\s*address\s([0-9a-f:\/\.]*)(;|\s\{)/mi', $unitConfig, $omatches)) {
                                        $interfaces[$interfaceName]['units'][$unitName]['addresses'] = [];
                                        $blocks = $omatches[1];
                                        $switches[$name][$interfaceName] = $blocks;
                                        foreach ($blocks as $block) {
                                            if (strpos($block, ':') !== false || strpos($block, '.') !== false) {
                                                $interfaces[$interfaceName]['units'][$unitName]['addresses'][] = $block;
                                                //$lines[] = $name.','.$interfaceName.',a,'.$block.',';
                                            }
                                        }
                                    }
                                    // get rid of the unit from the interface config so we can match only the main interface
                                    $interfaceConfig = str_replace($nmatches[0][$vIdx], '', $interfaceConfig);
                                }
                                // interfaces|interfaces.unit|vlans description text;
                                if (preg_match_all('/^\s*description (?P<description>.*);$/muU', $interfaceConfig, $nmatches)) {
                                    $interfaces[$interfaceName]['description'] = substr($nmatches['description'][0], 0, 1) == '"' ? substr($nmatches['description'][0], 1, -1) : $nmatches['description'][0];
                                }
                            } elseif ($sectionName == 'vlans') {
                                $vlans[$interfaceName] = [];
                                // vlans vlan-id 100;
                                if (preg_match_all('/^\s*(vlan-id) (?P<vlan>\d*);$/muU', $interfaceConfig, $nmatches)) {
                                    $vlans[$interfaceName]['id'] = $nmatches['vlan'][0];
                                    $vlanIdToName[$nmatches['vlan'][0]] = $interfaceName; 
                                }
                                // interfaces|interfaces.unit|vlans description text;
                                if (preg_match_all('/^\s*description (?P<description>.*);$/muU', $interfaceConfig, $nmatches)) {
                                    $vlans[$interfaceName]['description'] = substr($nmatches['description'][0], 0, 1) == '"' ? substr($nmatches['description'][0], 1, -1) : $nmatches['description'][0];
                                }
                                // vlans l3-interface irb.100;
                                if (preg_match_all('/^\s*l3-interface (?P<interface>.*);$/muU', $interfaceConfig, $nmatches)) {
                                    $vlans[$interfaceName]['links'] = [];
                                    $vlans[$interfaceName]['addresses'] = [];
                                    foreach ($nmatches['interface'] as $vIdx => $vlanInterface) {
                                        $vlans[$interfaceName]['links'][] = $vlanInterface;
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($interfaces as $interfaceName => $interfaceData) {
                    // interface xe- ge- et-
                    if (in_array(substr($interfaceName, 0, 2), ['xe', 'ge', 'et'])) {
                        // 802.3ad ae<x>;
                        if (isset($interfaceData['links'])) {
                            foreach ($interfaceData['links'] as $linkInterface) {
                                if (!isset($interfaces[$linkInterface])) {
                                    //echo "Warning {$name} {$interfaceName} is trying to link to {$linkInterface} but it does not exist<br>";
                                } else {
                                    $linkData = $interfaces[$linkInterface];
                                    // interface ae units
                                    if (isset($linkData['units'])) {
                                        foreach ($linkData['units'] as $unitName => $unitData) {
                                            if (isset($unitData['addresses'])) {
                                                foreach ($unitData['addresses'] as $block) {
                                                    $line = [$name, $interfaceName.'.'.$unitName, 'a', $block, $unitData['description'] ?? ($linkData['description'] ?? ''), [$linkInterface]];
                                                    $lines[] = $line;
                                                    $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $unitData['description'] ?? ($linkData['description'] ?? ''), 'src' => [$linkInterface]];
                                                    $linesNew[] = $line;
                                                }
                                            }
                                            if (isset($unitData['vlans'])) {
                                                foreach ($unitData['vlans'] as $vlanId) {
                                                    if (isset($vlanIdToName[$vlanId])) {
                                                        $vlanName = $vlanIdToName[$vlanId];
                                                        if (isset($vlans[$vlanName]['links'])) {
                                                            foreach ($vlans[$vlanName]['links'] as $vlanInterface) {
                                                                $irbUnit = substr($vlanInterface, strpos($vlanInterface, '.') + 1);
                                                                if (isset($interfaces['irb']['units'][$irbUnit]['addresses'])) {
                                                                    foreach ($interfaces['irb']['units'][$irbUnit]['addresses'] as $block) {
                                                                        $vlans[$interfaceName]['addresses'][] = $block;
                                                                        $line = [$name, $interfaceName, 'a', $block, $unitData['description'] ?? ($linkData['description'] ?? ''), [$linkInterface, $vlanName, $vlanInterface]];
                                                                        $lines[] = $line;
                                                                        $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $unitData['description'] ?? ($linkData['description'] ?? ''), 'src' => [$linkInterface, $vlanName, $vlanInterface]];
                                                                        $linesNew[] = $line;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($interfaceData['units'])) {
                            foreach ($interfaceData['units'] as $unitName => $unitData) {
                                if (isset($unitData['addresses'])) {
                                    foreach ($unitData['addresses'] as $block) {
                                        $line = [$name, $interfaceName, 'a', $block, $unitData['description'] ?? ($linkData['description'] ?? ''), []];
                                        $lines[] = $line;
                                        $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $unitData['description'] ?? ($linkData['description'] ?? ''), 'src' => []];
                                        $linesNew[] = $line;
                                    }
                                }
                                if (isset($unitData['vlans'])) {
                                    foreach ($unitData['vlans'] as $vlanId) {
                                        if (isset($vlanIdToName[$vlanId])) {
                                            $vlanName = $vlanIdToName[$vlanId];
                                            if (isset($vlans[$vlanName]['links'])) {
                                                foreach ($vlans[$vlanName]['links'] as $vlanInterface) {
                                                    $irbUnit = substr($vlanInterface, strpos($vlanInterface, '.') + 1);
                                                    if (isset($interfaces['irb']['units'][$irbUnit]['addresses'])) {
                                                        foreach ($interfaces['irb']['units'][$irbUnit]['addresses'] as $block) {
                                                            $vlans[$interfaceName]['addresses'][] = $block;
                                                            $line = [$name, $interfaceName, 'a', $block, $unitData['description'] ?? ($linkData['description'] ?? ''), [$vlanName, $vlanInterface]];
                                                            $lines[] = $line;
                                                            $line = ['name' => $name, 'interface' => $interfaceName, 'type' => 'a', 'block' => $block, 'description' => $unitData['description'] ?? ($linkData['description'] ?? ''), 'src' => [$vlanName, $vlanInterface]];
                                                            $linesNew[] = $line;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $switches[$name] = ['interfaces' => $interfaces, 'vlans' => $vlans];
            } elseif (strpos($input, 'TIMEOUT') !== false || strpos($input, 'Connection refused') !== false) {
            } else {
                echo "Dont know how to handle {$path} - {$input}<br>";
            }
        }
    }
    //\Tracy\Debugger::barDump($switches, 'Switch Config Blocks');
    //\Tracy\Debugger::barDump($lines, 'Parsed Switch Backup Lines');
    if ($new === true) {
        return $llinesNew;
    } else {
        return $lines;
    }
        
}

function switch_vlans()
{
    page_title(_('Switch VLANs'));
    add_js('font-awesome');
    function_requirements('has_acl');
    if ($GLOBALS['tf']->ima != 'admin' || !has_acl('client_billing')) {
        dialog('Not admin', 'Not Admin or you lack the permissions to view this page.');
        return false;
    }
    $db = $GLOBALS['tf']->db;
    $ipblocks = [];
    $db->query('select * from ipblocks', __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $ipblocks[$db->Record['ipblocks_id']] = \IPLib\Factory::parseRangeString($db->Record['ipblocks_network']);
    }
    $ipblocks[0] = \IPLib\Factory::parseRangeString('2604:a00::/32');
    $switchConfigs = [];
    $switches = [];
    $vlans = [4 => [], 6 => []];
    $vlansToId = [];
    $switchVlans = [];        
    $switchNameToId = [];
    $switchportIdToSwitchPort  = [];
    $portIdToName = [];
    $strip = [4 => ':', 6 => ''];
    $ipvSuffix = [4 => '', 6 => '6'];
    $emptySwitchPort = [
        'src' => [],
        'switchvlanids' => [],
        'switchvlans' => [],
        'switchvlans6' => [],
        'switchlinkedvlansrc' => [],
        'switchlinkedvlans' => [],
        'switchlinkedvlans6' => [],
        'switchallvlans' => [],
        'switchallvlans6' => [],
        'switchportvlans' => [],
        'switchportvlans6' => [],
    ];
    $db->query("select * from switchmanager order by name", __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $switch = $db->Record;
        $switch['orig_name'] = $switch['name'];
        $switch['name'] = strtolower(is_numeric(substr($db->Record['name'], 0, 1)) ? 'switch'.$db->Record['name'] : $db->Record['name']);
        $switchNameToId[$switch['name']] = $db->Record['id'];
        $switch['ports'] = [];        
        $switches[$db->Record['id']] = $switch;     
    }
    ksort($switchNameToId);
    $switchNames = array_keys($switchNameToId);
    $switchIds = array_values($switchNameToId);
    array_unshift($switchNames, 'All');
    array_unshift($switchIds, 'all');
    $limits = [
        'vendor' => isset($_REQUEST['l_vendor']) && in_array($_REQUEST['l_vendor'], ['all', 'cisco', 'junos']) ? $_REQUEST['l_vendor'] : 'all',
        'switch' => isset($_REQUEST['l_switch']) && in_array($_REQUEST['l_switch'], $switchIds) ? $_REQUEST['l_switch'] : 'all',
        'ipv' => isset($_REQUEST['l_ipv']) && in_array($_REQUEST['l_ipv'], ['all', 4, 6]) ? $_REQUEST['l_ipv'] : 'all',
        'status' => isset($_REQUEST['l_status']) && in_array($_REQUEST['l_status'], ['all', 'good', 'bad', 'bad_switch', 'bad_db']) ? $_REQUEST['l_status'] : 'all',
    ];
    foreach ($ipvSuffix as $ipv => $suffix) {
        $db->query("select * from vlans{$suffix}", __LINE__, __FILE__);
        while ($db->next_record(MYSQL_ASSOC)) {
            $vlan = $db->Record;
            $range = \IPLib\Range\Subnet::parseString(str_replace($strip[$ipv], '', $db->Record['vlans'.$suffix.'_networks']));
            if (!is_null($range)) {
                $vlan['vlan'] = $range->toString();
                $vlan['ports'] = [];
                $vlansToId[$range->toString()] = $db->Record['vlans'.$suffix.'_id'];
                $vlans[$ipv][$db->Record['vlans'.$suffix.'_id']] = $vlan;
            }     
        }
    }

    $db->query("select switchports.*, id, hostname, status from switchports left join assets on asset_id=id order by port", __LINE__, __FILE__);
    while ($db->next_record(MYSQL_ASSOC)) {
        $switchport = $db->Record;
        $switchport = array_merge($switchport, $emptySwitchPort);
        $portIdToName[$db->Record['switchport_id']] = $db->Record['port'];
        $switches[$db->Record['switch']]['ports'][$db->Record['port']] = $switchport;
        $switchportIdToSwitchPort[$switchport['switchport_id']] = [$switchport['switch'] ,$switchport['port']];     
        foreach ($ipvSuffix as $ipv => $suffix) {
            if (!empty($switchport['vlans'.$suffix])) {
                $vlanIds = explode(',', $switchport['vlans'.$suffix]);
                foreach ($vlanIds as $vlanId) {
                    if (isset($vlans[$ipv][$vlanId])) {
                        $vlans[$ipv][$vlanId]['ports'][] = $switchport['switchport_id'];
                        $switchport['switchportsvlans'.$suffix][] = $vlans[$ipv][$vlanId]['vlan'];
                    } else {
                        // missing vlans $vlanId
                    }
                }
            }
        }                        
    }
    $lines = parseSwitchBackups($limits['switch'] == 'all' ? '*' : $switches[$limits['switch']]['orig_name'], $switchConfigs);
    //$lines = explode("\n", trim(`php /home/sites/switch_configs/parse_switch_interface_vlans.php`));
    foreach ($lines as $line) {
        [$name, $port, $blockType, $block, $description, $src] = $line;
        $name = strtolower(is_numeric(substr($name, 0, 1)) ? 'switch'.$name : $name);
        if (!isset($switchNameToId[$name])) {
            continue;
        }
        $switch = $switchNameToId[$name];
        if (!isset($switches[$switch]['ports'][$port])) {
            $switches[$switch]['ports'][$port] = $emptySwitchPort;
        }
        if ($blockType == 'v') {
            $switches[$switch]['ports'][$port]['switchvlanids'][] = $block;
        } else {
            $range = \IPLib\Range\Subnet::parseString($block);
            if (!is_null($range)) {
                if ($range->getRangeType() == \IPLib\Range\Type::T_PUBLIC) {
                    if (blockIsLocal($range, $ipblocks)) {
                        $switches[$switch]['ports'][$port][$range->getAddressType() == 4 ? 'switchvlans' : 'switchvlans6'][] = $range->toString();
                        $switches[$switch]['ports'][$port]['description'] = $description;
                        $switches[$switch]['ports'][$port]['src'][$range->toString()] = $src;
                        if (!isset($switchVlans[$range->toString()])) {
                            $switchVlans[$range->toString()] = [];
                        }
                        $switchVlans[$range->toString()][] = [$switch, $port];
                    } else {
                        //echo $range->toString()." is not in our local blocks<br>";
                    }
                } 
            }            
        }
    }
    if (isset($_REQUEST['action']) && isset($_REQUEST['vlan'])) {
        $vlan = $_REQUEST['vlan'];
        $switchId = $_REQUEST['switch'];
        $portId = $_REQUEST['port'];
        $switchData = $switches[$switchId];
        $portData = $switches[$switchId]['ports'][$portIdToName[$portId]];
        $range = \IPLib\Range\Subnet::parseString($vlan);
        $ipv = $range->getAddressType();
        $message = [];
        // double check current config b4
        if ($_REQUEST['action'] == 'delete_switch') {
            // remove the address line if its a directly included range, 
            // and if its linked via a vlan offer choices like removing the vlan reference in the interface, or removing the address from the linked vlan, etc

        } elseif ($_REQUEST['action'] == 'add_switch') {
            // add to the interface or if its tied to vlans maybe offer to optionally add to vlan instead

        } elseif ($_REQUEST['action'] == 'delete_db') {
            $message = actionDeleteDb($db, $vlan, $switchId, $portId, $switchData, $portData, $range, $ipv, $ipvSuffix, $strip);
        } elseif ($_REQUEST['action'] == 'add_db') {
            $message = actionAddDb($db, $vlan, $switchId, $portId, $switchData, $portData, $range, $ipv, $ipvSuffix, $strip, $ipblocks);
        } elseif ($_REQUEST['action'] == 'delete_both') {

        }
        $GLOBALS['tf']->redirect($GLOBALS['tf']->link('index.php', 'choice=none.switch_vlans&message='.urlencode(implode('<br>', $message))));
    }
    $table = new TFTable();
    $table->set_title('Switch VLANs');
    $table->add_field('Switch');
    $table->add_field('Port');
    $table->add_field('VLAN');
    $table->add_field('Asset');
    $table->add_field('Health');
    $table->add_field('Links');
    $table->add_row();
    $counts = [false => 0, true => 0];
    $problems = [0 => 0, 1 => 0];
    $switchCounts = ['cisco' => 0, 'junos' => 0];
    $messages = [];
    $fixed = 0;
    $maxFixed = 300;
    foreach ($switchIds as $idx => $switchId) {
        if (!isset($switches[$switchId])) {
            continue;
        }
        $switchData = $switches[$switchId];
        $switchCounts[$switchData['type']]++;
        if (in_array($limits['switch'], ['all', $switchId]) && in_array($limits['vendor'], ['all', $switchData['type']])) {
            foreach ($switchData['ports'] as $port => $portData) {
                // if the switch port is both in the db and on switch
                if (isset($portData['switchport_id']) && isset($portData['src'])) {
                    foreach ($ipvSuffix as $ipv => $suffix) {
                        $portData['switchportsvlans'.$suffix] = [];
                        if (!empty($portData['vlans'.$suffix])) {
                            $vlanIds = explode(',', $portData['vlans'.$suffix]);
                            foreach ($vlanIds as $vlanId) {
                                if (isset($vlans[$ipv][$vlanId])) {
                                    $portData['switchportsvlans'.$suffix][] = $vlans[$ipv][$vlanId]['vlan'];
                                } else {
                                    // missing vlans $vlanId
                                }
                            }
                        }                        
                        // check if IPv is all or IPv6
                        if (in_array($limits['ipv'], ['all', $ipv])) {
                            // vlans on switches
                            foreach ($portData['switchvlans'.$suffix] as $vlan) {
                                // if switch vlan is in switchports table
                                $problem = !in_array($vlan, $portData['switchportsvlans'.$suffix]);
                                // in switch via linked vlan interface on ethernet type interface 
                                $counts[$problem]++;
                                if ($problem) {
                                    $problems[0]++;
                                    $text = 'on switch';
                                    //' ('.count($switchVlans[$vlan]).' ports)';
                                    //if (count($portData['src'][$vlan]) > 0) {
                                        //$text .= ' (via '.implode(',',$portData['src'][$vlan]).')';
                                    //}
                                    $text .= ' but';
                                    // checking if vlan is in db at all
                                    if (isset($vlansToId[$vlan])) {
                                        $vlanId = $vlansToId[$vlan];
                                        $vlanData = $vlans[$ipv][$vlanId];
                                        if (count($vlanData['ports']) > 0) {
                                            $vlanSwitches = [];
                                            foreach ($vlanData['ports'] as $switchportId) {
                                                [$vlanSwitch, $vlanPort] = $switchportIdToSwitchPort[$switchportId];
                                                $vlanSwitches[] = $switches[$vlanSwitch]['name'].'/'.$vlanPort;
                                            }
                                            $text .= ' switchports table '.implode(', ' ,$vlanSwitches).' are pointing to vlan instead';
                                        } else {
                                            $text .= ' switchports table entry missing';
                                        }
                                    } else {
                                        $text .= ' switchports and vlans table entries missing';
                                    }
                                } else {
                                    $text = '<span class="fa fa-check"></span>';
                                }
                                $links = [];
                                if (isset($portData['src'][$vlan])) {
                                    $links[] = '<a class="config-btn btn btn-sm btn-primary" onClick="showConfig('."'".$vlan."', '".$switchData['orig_name']."', ['".$port.(count($portData['src'][$vlan]) > 0 ? "', '".implode("', '", $portData['src'][$vlan]) : '')."']".');"></a>';
                                }
                                if (!is_null($portData['id'])) {
                                    $links[] = $table->make_link('choice=asset_form&id='.$portData['id'], '', false, 'class="asset-btn btn btn-sm btn-info" target="_blank"');
                                }
                                if (in_array($limits['status'], $problem ? ['all', 'bad', 'bad_db'] : ['all', 'good'])) {
                                    $table->add_field($switchData['name']);
                                    $table->add_field($port);
                                    $table->add_field($vlan);
                                    if ($problem) {
                                        $links[] = $table->make_link('choice=none.switch_vlans&action=delete_switch&switch='.$switchId.'&port='.$portData['switchport_id'].'&vlan='.urlencode($vlan), '', false, 'class="del-sw-btn btn btn-sm btn-secondary"');
                                        $links[] = 'or';
                                        $links[] = $table->make_link('choice=none.switch_vlans&action=add_db&switch='.$switchId.'&port='.$portData['switchport_id'].'&vlan='.urlencode($vlan), '', false, 'class="add-db-btn btn btn-sm btn-secondary"');
                                        if (isset($_GET['action']) && $_GET['action'] == 'bulk' && $fixed < $maxFixed) {
                                            $fixed++;
                                            $range = \IPLib\Range\Subnet::parseString($vlan);
                                            $messages = array_merge($messages, actionAddDb($db, $vlan, $switchId, $portData['switchport_id'], $switchData, $portData, $range, $range->getAddressType(), $ipvSuffix, $strip, $ipblocks));
                                        }
                                    } else {
                                        $links[] = $table->make_link('choice=none.switch_vlans&action=delete_both&switch='.$switchId.'&port='.$portData['switchport_id'].'&vlan='.urlencode($vlan), '', false, 'class="del-both-btn btn btn-sm btn-danger"');
                                    }
                                    $table->add_field(is_null($portData['id']) ? '&nbsp;' : ((empty($portData['hostname']) ? '#'.$portData['id'] : $portData['hostname']).' ('.$portData['status'].')'));
                                    $table->add_field($text);
                                    $table->add_field(implode('&nbsp;', $links));
                                    $table->add_row();
                                }
                            }
                            // switchports table vlans not in switches
                            foreach ($portData['switchportsvlans'.$suffix] as $vlan) {
                                if (!in_array($vlan, $portData['switchvlans'.$suffix]) && !in_array($vlan, $portData['switchlinkedvlans'.$suffix])) {
                                    $problem = true;
                                    $counts[$problem]++;
                                    if ($problem) {
                                        $problems[1]++;
                                        if (isset($switchVlans[$vlan])) {
                                            $vlanSwitches = [];
                                            $foundOnThisSwitch = false;
                                            foreach ($switchVlans[$vlan] as [$vlanSwitch, $vlanPort]) {
                                                $vlanSwitches[] = $switches[$vlanSwitch]['name'].'/'.$vlanPort;
                                                if ($vlanSwitch == $switchId) {
                                                    $foundOnThisSwitch = true;
                                                }
                                            }
                                            if ($foundOnThisSwitch === true) {
                                                $text = 'in db and switch but on different switch ports '.implode(', ' ,$vlanSwitches).'';
                                            } else {
                                                $text = 'in db and other switches '.implode(', ' ,$vlanSwitches).'';
                                            }
                                        } else {
                                            $text = 'in db but missing on switches';
                                        }
                                    }
                                    $links = [];
                                    if (isset($portData['src'][$vlan])) {
                                        $links[] = '<a class="config-btn btn btn-sm btn-primary" onClick="showConfig('."'".$vlan."', '".$switchData['orig_name']."', ['".$port.(count($portData['src'][$vlan]) > 0 ? "', '".implode("', '", $portData['src'][$vlan]) : '')."']".');"></a>';
                                    } else {
                                        $srcs = [];
                                        foreach ($portData['src'] as $tmpVlan => $src) {
                                            $srcs = array_merge($srcs, $src);
                                        }
                                        $srcs = array_unique($srcs);
                                        if (count($srcs) > 0) {
                                            $links[] = '<a class="config-btn btn btn-sm btn-primary" onClick="showConfig('."'".$vlan."', '".$switchData['orig_name']."', ['".$port.(count($srcs) > 0 ? "', '".implode("', '", $srcs) : '')."']".');"></a>';
                                        } elseif (isset($switchConfigs[$switchData['orig_name']][$port])) {
                                            $links[] = '<a class="config-btn btn btn-sm btn-primary" onClick="showConfig('."'".$vlan."', '".$switchData['orig_name']."', ['".$port."']".');"></a>';
                                        } else {
                                            $text = 'This port no longer exists on the switch. '.$text;
                                        }
                                    }
                                    if (!is_null($portData['id'])) {
                                        $links[] = $table->make_link('choice=asset_form&id='.$portData['id'], '', false, 'class="asset-btn btn btn-sm btn-info" target="_blank"');
                                    }
                                    if (in_array($limits['status'], $problem ? ['all', 'bad', 'bad_switch'] : ['all', 'good'])) {
                                        $table->add_field($switchData['name']);
                                        $table->add_field($port);
                                        $table->add_field($vlan);
                                        $links[] = $table->make_link('choice=none.switch_vlans&action=delete_db&switch='.$switchId.'&port='.$portData['switchport_id'].'&vlan='.urlencode($vlan), '', false, 'class="del-db-btn btn btn-sm btn-secondary"');
                                        $links[] = 'or';
                                        $links[] = $table->make_link('choice=none.switch_vlans&action=add_switch&switch='.$switchId.'&port='.$portData['switchport_id'].'&vlan='.urlencode($vlan), '', false, 'class="add-sw-btn btn btn-sm btn-secondary"');
                                        $table->add_field(is_null($portData['id']) ? '&nbsp;' : ((empty($portData['hostname']) ? '#'.$portData['id'] : $portData['hostname']).' ('.$portData['status'].')'));
                                        $table->add_field($text);
                                        $table->add_field(implode('&nbsp;', $links));
                                        $table->add_row();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (isset($_GET['action']) && $_GET['action'] == 'bulk') {
        add_output(implode('<br>', $messages).'<br>');
    }
    $table2 = new TFTable();
    $table2->hide_title();
    $table2->set_method('GET');
    $table2->add_field('Vendor');
    $table2->add_field(make_select('l_vendor', ['all', 'cisco', 'junos'], ['All ('.count($switches).')', 'Cisco ('.$switchCounts['cisco'].')', 'Juniper ('.$switchCounts['junos'].')'], $limits['vendor']));
    $table2->add_field('Switch');
    $table2->add_field(make_select('l_switch', $switchIds, $switchNames, $limits['switch']));
    $table2->add_field('IPv4/6');
    $table2->add_field(make_select('l_ipv', ['all', 4, 6], ['All', 4, 6], $limits['ipv']));
    $table2->add_field('Health');
    $table2->add_field(make_select('l_status', ['all', 'good', 'bad', 'bad_db', 'bad_switch'], ['All ('.($counts[true]+$counts[false]).')', 'Good ('.$counts[false].')', 'All Problems ('.$counts[true].')', 'Missing in DB ('.$problems[0].')', 'Missing on Switch ('.$problems[1].')'], $limits['status']));
    $table2->add_field('<input type="submit" value="Update">');
    $table2->add_row();
    add_output('<style>
/* Default text for window width >= 1630px */
.modal-dialog { max-width: 700px; }
.config-btn::after { content: "View Config"; }
.asset-btn::after { content: "View Asset"; }
.del-sw-btn::after { content: "Delete from Switch"; }
.del-db-btn::after { content: "Delete from DB"; }
.add-sw-btn::after { content: "Add to Switch"; }
.add-db-btn::after { content: "Add to DB"; }
.del-both-btn::after { content: "Delete from Switch and DB"; }
.vlans-div table td:nth-child(5) { max-width: 300px; }
.vlans-div table td {
    /* white-space: nowrap; */
    overflow: hidden;
    text-overflow: ellipsis;   /* Adds ellipsis (…) if text overflows */
}

/* Change text for window width < 1630px */
@media (max-width: 1630px) {
    .vlans-div table td:nth-child(1), 
    .vlans-div table td:nth-child(2) { max-width: 125px; }
    .vlans-div table td:nth-child(3) { max-width: 175px; }
    .vlans-div table td:nth-child(4) { max-width: 200px; }
}

/* Apply a max-width of 200px to the 4th column when window is < 1460px */
@media (max-width: 1460px) {
    .config-btn::after { content: "Config"; }
    .asset-btn::after { content: "Asset"; }
    .del-sw-btn::after { content: "- Switch"; }
    .del-db-btn::after { content: "- DB"; }
    .add-sw-btn::after { content: "+ Switch"; }
    .add-db-btn::after { content: "+ DB"; }
    .del-both-btn::after { content: "- Switch & DB"; }
}
</style>');
    if (isset($_REQUEST['message'])) {
        add_output('<div class="alert alert-info" style="width: 700px;">'.strip_tags($_REQUEST['message'], '<br>').'</div>');
    }
    add_output($table2->get_table());
    add_output('<div class="vlans-div">');
    add_output($table->get_table());
    add_output('</div>');
    add_output('<div class="modal fade" id="configModal" tabindex="-1" role="dialog" aria-labelledby="configModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="configModalLabel">Switch Configuration</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-left">
                <!-- Config content will be inserted in the pre -->
                <pre id="configModalBody"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
var switches = [];
function showConfig(vlan, name, ports) {
    if (switchConfigs[name]) {
        var config = "";
        for (var portIdx in ports) {
            if (switchConfigs[name][ports[portIdx]]) {
                config = config + switchConfigs[name][ports[portIdx]] + "\n"; 
            }
        }
        document.getElementById("configModalLabel").innerText = "Switch "+name+" "+vlan+" Configuration"; 
        document.getElementById("configModalBody").innerText = config;
        $("#configModal").modal("show");
    } else {
        alert("Configuration not found for the specified switch "+name+".");
    }
}
$(document).ready(function () {
    switchConfigs = '.json_encode($switchConfigs).';
});
</script>');
    //\Tracy\Debugger::barDump($switches, 'Switches');
}
