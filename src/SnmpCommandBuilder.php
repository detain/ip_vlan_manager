<?php
/**
 * Central builder for every SNMP / rancid command spliced from switch data
 * (B009/B151).
 *
 * The legacy sites interpolated request- and DB-sourced tokens (name, ip,
 * snmp_version, snmp_community, location text) straight into command strings
 * handed to Detain\SshPool - root command execution on the NMS relay simply
 * by saving 'public; rm -rf /' as a community string (switch_add/update), or
 * typing it into the add form (switch_add/edit probes run before any save).
 * switch_snmp.php/update_switch_locations.php repeat the shape for CLI runs.
 *
 * Convention borrowed from Detain\Sshwitch (B025 era): every dynamic token
 * passes escapeshellarg(), fixed syntax (flag names, literal paths, rancid's
 * ';' command separator inside ONE quoted -c payload) stays literal. Values
 * that have a closed vocabulary (SNMP version, mibs type directory) are
 * whitelisted instead of quoted - an unknown member is a loud throw, never a
 * quoted payload. rancid location text additionally gets reject-then-quote:
 * ';', quotes, backslash, backtick, '$' and control chars cannot survive the
 * rancid 'cmd1;cmd2' parser anyway, so such rows are refused, not escaped.
 *
 * Pure - no DB, no SSH, no globals; callers feed parsed values and get
 * strings.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Networking
 */

namespace Detain\IpVlanManager;

use InvalidArgumentException;

final class SnmpCommandBuilder
{
    /** closed vocabulary: request/DB snmp_version token => -flag */
    public const VERSION_FLAGS = ['v1' => '-v1', 'v2c' => '-v2c'];

    /** closed vocabulary: switchmanager.type token => observium mibs dir */
    public const MIB_TYPE_DIRS = ['cisco' => 'cisco', 'junos' => 'juniper'];

    private const MIB_BASE_DIRS = '/opt/observium/mibs/rfc:/opt/observium/mibs/net-snmp';

    /**
     * Both historical shapes carried the type directory as the FULL path
     * '/opt/observium/mibs/{cisco|juniper}' as the third -M entry, not the
     * bare directory name (fa671dc^ src/switch_add.php:46; root 6228043eb1^
     * scripts/Networking/switch_snmp.php:31). A bare name resolves against
     * the relay process CWD and fails, so type-dir MIBs silently stop
     * loading; mibsDir()'s value therefore gets this prefix at assembly.
     */
    private const MIB_TYPE_DIR_ROOT = '/opt/observium/mibs/';

    /**
     * @return string the '-v1'/'-v2c' flag for a whitelisted SNMP version
     * @throws InvalidArgumentException on any other version token
     */
    public static function versionFlag($ver): string
    {
        if (!is_string($ver) || !isset(self::VERSION_FLAGS[$ver])) {
            throw new InvalidArgumentException('Unknown SNMP version ' . self::describe($ver) . '; expected v1 or v2c');
        }
        return self::VERSION_FLAGS[$ver];
    }

    /**
     * @return string the literal mibs sub-directory for a whitelisted switch type
     * @throws InvalidArgumentException on any other type token
     */
    public static function mibsDir($type): string
    {
        if (!is_string($type) || !isset(self::MIB_TYPE_DIRS[$type])) {
            throw new InvalidArgumentException('Unknown switch type ' . self::describe($type) . '; expected cisco or junos');
        }
        return self::MIB_TYPE_DIRS[$type];
    }

    /**
     * Validate an address is a bare IP (no host syntax, no shell surface).
     *
     * @throws InvalidArgumentException when it is not a valid IPv4/IPv6 address
     */
    public static function ip($ip): string
    {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Invalid SNMP host address ' . self::describe($ip));
        }
        return $ip;
    }

    /**
     * snmpbulkwalk in the interactive probe shape used by switch_add/switch_edit.
     */
    public static function walk(string $ver, string $community, string $ip, string $oid, string $type): string
    {
        return '/usr/bin/snmpbulkwalk -t 2 ' . self::versionFlag($ver)
            . ' -c ' . escapeshellarg($community)
            . ' -Pud -OQUs -m IF-MIB -M ' . self::MIB_BASE_DIRS . ':' . self::MIB_TYPE_DIR_ROOT . self::mibsDir($type)
            . ' ' . escapeshellarg(self::ip($ip))
            . ' ' . escapeshellarg($oid);
    }

    /**
     * snmpget shape used by switch_install and update_switch_locations.
     */
    public static function get(string $ver, string $community, string $ip, string $oid): string
    {
        return '/usr/bin/snmpget -t 2 ' . self::versionFlag($ver)
            . ' -c ' . escapeshellarg($community)
            . ' -Pud -OQUs -m IF-MIB -M ' . self::MIB_BASE_DIRS
            . ' ' . escapeshellarg(self::ip($ip))
            . ' ' . escapeshellarg($oid);
    }

    /**
     * snmpbulkwalk polling shape used by scripts/Networking/switch_snmp.php
     * (keeps the historical literal '-M +' path prefix and timing flags).
     */
    public static function bulkwalk(string $ver, string $community, string $ip, string $type): string
    {
        return 'snmpbulkwalk -M +' . self::MIB_BASE_DIRS . ':' . self::MIB_TYPE_DIR_ROOT . self::mibsDir($type)
            . ' -Cr10 -r 2 -t 60 ' . self::versionFlag($ver)
            . ' -c ' . escapeshellarg($community)
            . ' ' . escapeshellarg(self::ip($ip));
    }

    /**
     * cacti CLI device add.
     */
    public static function addCactiDevice(string $name, string $ip, int $template, int $externalId, string $ver, string $community): string
    {
        return 'php /usr/share/cacti/cli/add_device.php'
            . ' --description=' . escapeshellarg($name)
            . ' --ip=' . escapeshellarg(self::ip($ip))
            . ' --template=' . $template
            . ' --external-id=' . $externalId
            . ' --version=' . ($ver === 'v2c' ? 2 : 1)
            . ' --community=' . escapeshellarg($community);
    }

    /**
     * cacti CLI graph add for a resolved numeric host id.
     */
    public static function addCactiGraphs(int $hostId): string
    {
        return 'php /usr/share/cacti/cli/add_graphs.php'
            . ' --host-id=' . $hostId
            . ' --graph-type=ds --graph-template-id=2 --snmp-query-id=1'
            . ' --snmp-query-type-id=24 --snmp-field=ifType --snmp-value=ethernetCsmacd';
    }

    /**
     * observium add_device; caller passes the fully resolved hostname string.
     */
    public static function addObserviumDevice(string $hostname, string $community, string $ver): string
    {
        return 'php /opt/observium/add_device.php '
            . escapeshellarg($hostname)
            . ' ' . escapeshellarg($community)
            . ' ' . ltrim(self::versionFlag($ver), '-');
    }

    /**
     * rancid login command (jlogin/clogin). $commands is the whole
     * ';'-separated rancid command string; it is quoted as ONE argument so
     * shell metacharacters inside it cannot break out - but rancid's own
     * ';' parser means embedded structure must never come from data: pass
     * dynamic text through rancidSafeText() before building.
     */
    public static function rancidLogin(string $binary, string $commands, int $timeout, string $switch): string
    {
        $binary = basename($binary);
        if (!in_array($binary, ['jlogin', 'clogin'], true)) {
            throw new InvalidArgumentException('Unknown rancid login binary ' . self::describe($binary));
        }
        return '/usr/libexec/rancid/' . $binary . ' -autoenable'
            . ' -c ' . escapeshellarg($commands)
            . ' -t ' . $timeout
            . ' ' . escapeshellarg(self::ip($switch));
    }

    /**
     * Data text destined for the inside of a rancid command: ';', quotes,
     * backslash, backtick, '$', and any control character cannot appear
     * (they would either break out of the quoted -c payload or forge extra
     * rancid commands). Returns null when the text is unsafe - callers must
     * refuse the row, not sanitize-and-continue.
     */
    public static function rancidSafeText($text): ?string
    {
        if (!is_string($text) || $text === '') {
            return null;
        }
        if (preg_match('/[\x00-\x1f\x7f;\x27\x22`$\\\\]/', $text) === 1) {
            return null;
        }
        return $text;
    }

    /**
     * Host-name validation for switchmanager.name carriers (B009/B012/B151
     * save-path gate): label-charset only, no shell/SQL/URL surface; this is
     * what stops payloads reaching cacti --description, observium device
     * names, the 'nms/'.$name file sink and every echo of the name.
     */
    public static function validSwitchName($name): bool
    {
        return is_string($name) && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $name) === 1;
    }

    /**
     * File sink guard for the 'nms/'.$name writes: same charset, and a bare
     * basename (no traversal) - used by scripts/Networking/switch_snmp.php.
     */
    public static function safeOutputFileName($name): ?string
    {
        if (!is_string($name) || basename($name) !== $name) {
            return null;
        }
        return self::validSwitchName($name) ? $name : null;
    }

    private static function describe($value): string
    {
        if (!is_scalar($value)) {
            return get_debug_type($value);
        }
        return "'" . substr(str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], (string)$value), 0, 60) . "'";
    }
}
