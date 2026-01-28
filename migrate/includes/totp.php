<?php
/**
 * Standalone TOTP Helper (Google Authenticator compatible)
 */
class TOTP {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16) {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[rand(0, 31)];
        }
        return $secret;
    }

    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode == $code) return true;
        }
        return false;
    }

    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) $timeSlice = floor(time() / 30);
        $secretKey = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode($base32String) {
        $base32String = strtoupper($base32String);
        $base32Chars = self::$base32Chars;
        $base32CharsArray = array_flip(str_split($base32Chars));
        $output = '';
        $v = 0;
        $vBits = 0;
        for ($i = 0, $j = strlen($base32String); $i < $j; $i++) {
            $c = $base32String[$i];
            if ($c == '=') break;
            if (!isset($base32CharsArray[$c])) return false;
            $v = ($v << 5) | $base32CharsArray[$c];
            $vBits += 5;
            if ($vBits >= 8) {
                $vBits -= 8;
                $output .= chr(($v >> $vBits) & 0xFF);
            }
        }
        return $output;
    }

    public static function getQrCodeUrl($name, $issuer, $secret) {
        return "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($name) . "?secret=" . $secret . "&issuer=" . rawurlencode($issuer);
    }
}
