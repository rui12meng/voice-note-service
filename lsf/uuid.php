<?php
namespace Lsf;

/**
 * Uuid操作类
 * @author
 * $Id: uuid.php $
 */

class Uuid
{
    /* ================= UUID v4 ================= */

    public static function v4(): string
    {
        $bytes = random_bytes(16);

        // version = 4
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // variant = RFC 4122
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return self::format($bytes);
    }

    /* ================= UUID v7 ================= */

    private static int $lastMs = 0;
    private static int $seq = 0;

    public static function v7(): string
    {
        $nowMs = (int) floor(microtime(true) * 1000);

        // 同一毫秒内递增序列（12 bits）
        if ($nowMs === self::$lastMs) {
            self::$seq = (self::$seq + 1) & 0x0fff;
        } else {
            self::$lastMs = $nowMs;
            self::$seq = random_int(0, 0x0fff);
        }

        /*
         * UUID v7 layout:
         * - 48 bits: unix timestamp (ms)
         * - 4 bits : version (0111)
         * - 12 bits: subsec / sequence
         * - 62 bits: random
         */

        $time = $nowMs & 0xFFFFFFFFFFFF;

        $bytes = random_bytes(16);

        // time (48 bits)
        $bytes[0] = chr(($time >> 40) & 0xff);
        $bytes[1] = chr(($time >> 32) & 0xff);
        $bytes[2] = chr(($time >> 24) & 0xff);
        $bytes[3] = chr(($time >> 16) & 0xff);
        $bytes[4] = chr(($time >> 8) & 0xff);
        $bytes[5] = chr($time & 0xff);

        // version (7) + high 4 bits of seq
        $bytes[6] = chr(0x70 | ((self::$seq >> 8) & 0x0f));
        // low 8 bits of seq
        $bytes[7] = chr(self::$seq & 0xff);

        // variant RFC 4122
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return self::format($bytes);
    }

    /* ================= helpers ================= */

    private static function format(string $bytes): string
    {
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}