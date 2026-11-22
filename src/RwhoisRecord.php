<?php
/**
 * Direct rwhoisd record-file writer (B010).
 *
 * vlantorwhois.php used to emit a vlantorwhois.sh shell script whose echo -e
 * lines interpolated raw customer company/address/city/phone strings into
 * double-quoted shell: an embedded "$(cmd)" or "``" in signup-collected data
 * executed at script run time, and a bare newline forged extra rwhois record
 * lines. The shell layer is gone - the generator now writes the rwhoisd data
 * tree directly in PHP (same model as setup_rwhois.php), and every free-text
 * value passes through rwhoisField() so a record line can only ever be a
 * single line of record-charset text.
 *
 * The whitelist deliberately KEEPS ':' (IPv6 prefixes, time-like fields and
 * auth-area values such as 2604:a00::/32 are legitimate rwhois content -
 * stripping colons silently corrupts every IPv6 block, §0.6 finding).
 *
 * Pure where possible; filesystem effects are confined to the explicit
 * ensureDirectory/writeFile calls.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2026
 * @package IP-VLAN-Manager
 * @category Networking
 */

namespace Detain\IpVlanManager;

use InvalidArgumentException;
use RuntimeException;

final class RwhoisRecord
{
    /**
     * Sanitize one free-text value destined for a rwhois record line.
     * Newlines are collapsed (one field = one line), then everything outside
     * the rwhois display charset - letters, digits, space, : . , # & ' ( ) /
     * and - - is dropped. Shell and record-structure metacharacters can no
     * longer survive ($ ; " backtick, control bytes).
     *
     * @param mixed $value
     */
    public static function rwhoisField($value): string
    {
        $text = preg_replace('/[\r\n]+/', ' ', (string)$value);
        $text = preg_replace("/[^A-Za-z0-9 :.,#&'()\/-]/", '', $text);
        return trim($text);
    }

    /**
     * @throws RuntimeException when the directory cannot be created
     */
    public static function ensureDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create rwhoisd directory ' . $dir);
        }
    }

    /**
     * Write one file under $dir; the name must be a bare charset-safe
     * component (no traversal) so a poisoned DB value can never redirect the
     * write outside the rwhoisd tree.
     *
     * @throws InvalidArgumentException on unsafe names
     * @throws RuntimeException on write failure
     */
    public static function writeFile(string $dir, string $file, string $contents): void
    {
        if (preg_match('#^[A-Za-z0-9._:-]+$#', $file) !== 1) {
            throw new InvalidArgumentException('Unsafe rwhoisd file name ' . $file);
        }
        self::ensureDirectory($dir);
        if (file_put_contents($dir . '/' . $file, $contents) === false) {
            throw new RuntimeException('Unable to write rwhoisd file ' . $dir . '/' . $file);
        }
    }

    /**
     * Write a record file from field lines: joined with a trailing newline,
     * matching the one-line-per-field rwhoisd format the shell echo -e
     * version produced.
     *
     * @param array<int,string> $lines
     * @throws InvalidArgumentException on unsafe names
     * @throws RuntimeException on write failure
     */
    public static function writeRecordFile(string $dir, string $file, array $lines): void
    {
        self::writeFile($dir, $file, implode("\n", $lines) . "\n");
    }
}
