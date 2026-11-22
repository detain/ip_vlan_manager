<?php
/**
 * Pure shaping for the public rwhois JSON feed (ajax/rwhois.php).
 *
 * B187: the feed used to publish every joined accounts.* column and the
 * free-text vlans*.comment/vlans_ports of each record. All record building
 * lives here so the endpoint stays thin and the published shape is
 * unit-testable without booting the app or touching the database.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Networking
 */

namespace Detain\IpVlanManager;

use IPLib\Range\Subnet;

class RwhoisFeed {

	/**
	 * The only keys a block/VLAN record may carry in the public feed:
	 * network data, the internal row ids, the derived range fields and
	 * account_id (the consumer needs it to name each rwhois org). Every
	 * account_* PII column and the free-text comment/port columns are
	 * deliberately absent (B187).
	 */
	public const RECORD_KEYS = [
		'ipblocks_id', 'ipblocks_network',
		'vlans_id', 'vlans_networks', 'vlans_block',
		'vlans6_id', 'vlans6_networks',
		'account_id', 'referral', 'vlans',
		'size', 'gateway', 'prefix', 'subnet', 'start', 'end',
	];

	/**
	 * The only fields a public contact entry may carry. The consumer
	 * (setup_rwhois.php) substitutes placeholders for name, email, phone,
	 * address and company, so those never leave the endpoint (B187).
	 */
	public const CONTACT_KEYS = ['city', 'state', 'zip', 'country'];

	/** Header carrying the feed token, checked by the endpoint gate and sent by setup_rwhois.php. */
	public const TOKEN_HEADER = 'X-Rwhois-Token';

	/**
	 * Reduce a raw DB row to the public allowlist of keys.
	 *
	 * @param array $record
	 * @return array
	 */
	public static function shapeRecord(array $record): array {
		return array_intersect_key($record, array_flip(self::RECORD_KEYS));
	}

	/**
	 * Copy the four geo fields of a row's account into $contacts keyed by
	 * account id (the rwhois org records need them), then strip every other
	 * account_* key from the row. The stripped account_id stays: the
	 * consumer reads it to name the org.
	 *
	 * @param array $record
	 * @param array $contacts feed contacts map, mutated by reference like the old add_contact()
	 * @return array record with the PII keys removed
	 */
	public static function addContact(array $record, array &$contacts): array {
		if (!isset($record['account_id']) || $record['account_id'] === null) {
			return $record;
		}
		$contact = [];
		foreach (self::CONTACT_KEYS as $field) {
			if (isset($record['account_' . $field]) && !empty($record['account_' . $field])) {
				$contact[$field] = $record['account_' . $field];
			}
		}
		$contacts[(int)$record['account_id']] = $contact;
		foreach (array_keys($record) as $key) {
			if ($key !== 'account_id' && strpos($key, 'account_') === 0) {
				unset($record[$key]);
			}
		}
		return $record;
	}

	/**
	 * Add the derived range fields (size/gateway/prefix/subnet/start/end)
	 * to a record. Malformed stored values are the caller's problem: the
	 * feed must survive one bad row instead of fatalling (B187 step 3).
	 *
	 * @param string $ipblock CIDR string as stored
	 * @param array $record mutated by reference
	 * @return bool false when the value cannot be parsed - log and skip the row
	 */
	public static function addRange($ipblock, array &$record): bool {
		$range = Subnet::parseString((string)$ipblock);
		if ($range === null) {
			return false;
		}
		$record['size'] = $range->getSize();
		if ($record['size'] > 1) {
			$record['gateway'] = $range->getAddressAtOffset(1)->toString();
		}
		$record['prefix'] = $range->getNetworkPrefix();
		if ($range->getAddressType() === 4) {
			$record['subnet'] = $range->getSubnetMask()->toString();
		}
		$record['start'] = $range->getStartAddress()->toString();
		$record['end'] = $range->getEndAddress()->toString();
		return true;
	}

	/**
	 * Feed gate: with a configured token every caller must present it
	 * (hash_equals, compared in the endpoint); with no token configured the
	 * feed stays open but PII-free until the owner rolls the define out to
	 * all hosts (§4 step 2 prereq, owner-board decision on the mechanism).
	 *
	 * @param array $server
	 * @param string $configuredToken
	 * @return bool
	 */
	public static function isAuthorized(array $server, $configuredToken): bool {
		$configuredToken = trim((string)$configuredToken);
		if ($configuredToken === '') {
			return true;
		}
		$presentedToken = isset($server['HTTP_X_RWHOIS_TOKEN']) ? trim((string)$server['HTTP_X_RWHOIS_TOKEN']) : '';
		return $presentedToken !== '' && hash_equals($configuredToken, $presentedToken);
	}
}
