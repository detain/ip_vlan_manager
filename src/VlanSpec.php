<?php
/**
 * Canonical VLAN/CIDR spec grammar shared by the ipcalc dispatcher and the
 * switch_vlans boundary guard (B011/B012).
 *
 * Both seats historically trusted "explode + loose validate" pipelines: the
 * ipcalc() dispatcher took the first two '/'-segments and still handed the
 * ORIGINAL string (third segment and all) to the engines, so payloads like
 * 1.2.3.4/24/$(id) reached the backtick shell in ipcalcBinary(); the
 * switch_vlans action leg passed the raw request string straight into
 * Subnet::parseString() and then into WHERE clauses, relying on a
 * crash-to-null for validation.
 *
 * This helper parses once at the boundary and returns either a canonical,
 * dispatch-safe 'address/mask' string or null - the shape cannot be wrong
 * downstream (Parse, Don't Validate). The address is family-detected with
 * filter_var() flags on purpose: validIp($ip, true, true) (support_ipv6)
 * returns true for IPv4 too, so it cannot answer "which family?" and a
 * mask ceiling taken from it would wrongly allow 128-bit masks for IPv4
 * (§0.6 finding) - family and ceiling come from the flags themselves here.
 *
 * Pure - no DB, no globals, no output.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Networking
 */

namespace Detain\IpVlanManager;

final class VlanSpec
{
    /**
     * Whitelist grammar for a CIDR spec: address chars only (digits and the
     * IPv6 alphabet, '.' and ':'), one separator, digits for the mask.
     * Anything with ; | & ` $ ( ) " ' or whitespace fails here, before any
     * engine, shell or SQL sees it.
     */
    public const GRAMMAR = '#^[0-9a-fA-F:.]+/\d{1,3}$#';

    /**
     * Normalize a user/config supplied CIDR string to a canonical
     * 'address/mask' form, or null when it is not a well-formed IPv4/IPv6
     * network spec with an in-family mask.
     *
     * @param mixed $input
     * @return string|null
     */
    public static function normalize($input): ?string
    {
        if (!is_string($input)) {
            return null;
        }
        $spec = trim($input);
        if (preg_match(self::GRAMMAR, $spec) !== 1) {
            return null;
        }
        [$address, $mask] = explode('/', $spec, 2);
        $mask = (int)$mask;
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            if ($mask > 128) {
                return null;
            }
            // compress/expand to filter's canonical textual form
            return inet_ntop(inet_pton($address)) . '/' . $mask;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            if ($mask > 32) {
                return null;
            }
            return $address . '/' . $mask;
        }
        return null;
    }
}
