<?php
/**
 * Output helpers for JS-argument embedding in server-rendered HTML
 * (B012).
 *
 * switch_vlans.php built onClick="showConfig('...','...',['...'])" by
 * concatenating raw strings; switch config descriptions, orig_name and VLAN
 * specs could close the JS string/attribute and run attacker script in the
 * admin session. A plain json_encode() alone is NOT enough inside an
 * attribute: double quotes it produces (\") still terminate an HTML
 * double-quoted attribute (HTML ignores backslash escapes) - the JSON_HEX_*
 * flags turn ", ', < and & into \u escapes so the literal survives to the JS
 * parser only as string content.
 *
 * Pure - encoding only, no escaping of surrounding HTML context.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Rendering
 */

namespace Detain\IpVlanManager;

final class HtmlOut
{
    /**
     * Encode one value as a JSON string literal safe to embed inside a
     * double-quoted HTML attribute and a JS single/double-quoted context.
     *
     * @param mixed $value
     */
    public static function jsString($value): string
    {
        $json = json_encode(
            is_scalar($value) || $value === null ? (string)$value : '',
            JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json === false ? '""' : $json;
    }

    /**
     * JSON array literal of string-encoded values:
     * jsArray(['a','b']) => a bracketed, hex-quoted list for the
     * showConfig(..., ['port','src']) style call sites.
     *
     * @param array<mixed> $values
     */
    public static function jsArray(array $values): string
    {
        return '[' . implode(',', array_map([self::class, 'jsString'], $values)) . ']';
    }

    /**
     * Comma-joined top-level argument list: jsArgs([$a,$b]) => "<a>, <b>".
     *
     * @param array<mixed> $values
     */
    public static function jsArgs(array $values): string
    {
        return implode(', ', array_map([self::class, 'jsString'], $values));
    }

    /**
     * Escape a value for a double-quoted HTML attribute body.
     *
     * @param mixed $value
     */
    public static function attr($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
