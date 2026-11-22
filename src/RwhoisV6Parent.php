<?php
/**
 * Parentage resolution for the public rwhois JSON feed's IPv6 VLAN rows (B094).
 *
 * vlans6 rows carry no reliable numeric link to ipblocks6: legacy rows store a
 * hextet string ('14', 'a00') in vlans6_block and allocator-era rows store the
 * ipblocks_id of the /40 they were carved from (see Ipv6Allocator's vlans6
 * docblock) - so the column cannot be joined at all (B088 removed that fiction
 * from the published relationship metadata). The feed previously parked every
 * IPv6 VLAN under a hardcoded ipblocks6 id 1, mis-attributing the whole carved
 * estate to the legacy 2604:a00::/32 anchor.
 *
 * This mapper decides parentage from containment alone: the smallest published
 * ipblocks6 range that contains the VLAN network wins - correct for hextet-era
 * rows (their networks sit inside the estate), allocator-era rows (their /40 is
 * a published row) and every carve in between. The anchor id (host-configurable
 * IPBLOCKS6_ANCHOR_ID, default 1) is only a fallback for estate-foreign or
 * non-contained rows, and it is honored only when its row is actually
 * published: a feed never fabricates a bucket for a missing ipblocks6 row.
 *
 * Pure - no DB, no globals; the caller supplies the preloaded id => network map.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Networking
 */

namespace Detain\IpVlanManager;

use IPLib\Range\Subnet;

class RwhoisV6Parent
{
	/**
	 * Which ipblocks6 row owns a vlans6 network?
	 *
	 * @param array<int,string> $blocks    published ipblocks6 rows: ipblocks_id => CIDR
	 * @param string            $vlanNetwork vlans6.vlans6_networks (single CIDR; the feed
	 *                                      drops unparseable rows before calling)
	 * @param int               $anchorId  legacy anchor row id (2604:a00::/32 era, id 1
	 *                                     by convention) used only as documented fallback
	 *
	 * @return int|null containing (or fallback-anchor) ipblocks_id, or null when the
	 *                  VLAN cannot be honestly attributed - the caller then skips the
	 *                  row instead of publishing it under a phantom bucket.
	 */
	public static function resolveParentId(array $blocks, string $vlanNetwork, int $anchorId): ?int
	{
		$vlan = Subnet::parseString(trim($vlanNetwork));
		if ($vlan === null) {
			return null;
		}
		$bestId = null;
		$bestPrefix = -1;
		foreach ($blocks as $blockId => $network) {
			$block = Subnet::parseString(trim((string)$network));
			if ($block === null || !$block->containsRange($vlan)) {
				continue;
			}
			$prefix = $block->getNetworkPrefix();
			// strictly-largest prefix wins; among equals, first published wins (deterministic)
			if ($prefix > $bestPrefix) {
				$bestPrefix = $prefix;
				$bestId = (int)$blockId;
			}
		}
		if ($bestId !== null) {
			return $bestId;
		}
		// no containment: fall back to the documented anchor, but never materialize a
		// bucket for a row that is not published
		return array_key_exists($anchorId, $blocks) ? $anchorId : null;
	}
}
