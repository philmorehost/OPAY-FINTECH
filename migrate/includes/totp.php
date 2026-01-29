<?php
/**
 * Simple TOTP (Time-based One-Time Password) implementation
 * for Google Authenticator support.
 */

if (!class_exists('TOTP')) {
    class TOTP {
        protected static $_codeLength = 6;

        public static function generateSecret($secretLength = 16) {
            $validChars = self::_getBase32LookupTable();
            unset($validChars[32]);
            $secret = '';
            for ($i = 0; $i < $secretLength; $i++) {
                $secret .= $validChars[array_rand($validChars)];
            }
            return $secret;
        }

        public static function getCode($secret, $timeSlice = null) {
            if ($timeSlice === null) {
                $timeSlice = floor(time() / 30);
            }
            $secretKey = self::_base32Decode($secret);
            $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
            $hmac = hash_hmac('SHA1', $time, $secretKey, true);
            $offset = ord(substr($hmac, -1)) & 0x0F;
            $hashpart = substr($hmac, $offset, 4);
            $value = unpack('N', $hashpart);
            $value = $value[1];
            $value = $value & 0x7FFFFFFF;
            $modulo = pow(10, self::$_codeLength);
            return str_pad($value % $modulo, self::$_codeLength, '0', STR_PAD_LEFT);
        }

        public static function getQrCodeUrl($name, $title, $secret) {
            $urlencoded = urlencode('otpauth://totp/'.$name.'?secret='.$secret.'&issuer='.urlencode($title));
            return 'https://api.qrserver.com/v1/create-qr-code/?data='.$urlencoded.'&size=200x200';
        }

        public static function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null) {
            if ($currentTimeSlice === null) {
                $currentTimeSlice = floor(time() / 30);
            }
            for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
                $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
                if (self::timingSafeEquals($calculatedCode, $code)) {
                    return true;
                }
            }
            return false;
        }

        protected static function timingSafeEquals($safeString, $userString) {
            if (function_exists('hash_equals')) {
                return hash_equals($safeString, $userString);
            }
            $safeLen = strlen($safeString);
            $userLen = strlen($userString);
            if ($userLen != $safeLen) return false;
            $result = 0;
            for ($i = 0; $i < $userLen; $i++) {
                $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
            }
            return $result === 0;
        }

        protected static function _base32Decode($secret) {
            if (empty($secret)) return '';
            $base32chars = self::_getBase32LookupTable();
            $base32charsFlipped = array_flip($base32chars);
            $paddingCharCount = substr_count($secret, $base32chars[32]);
            $allowedValues = array(6, 4, 3, 1, 0);
            if (!in_array($paddingCharCount, $allowedValues)) return false;
            for ($i = 0; $i < 4; $i++) {
                if ($paddingCharCount == $allowedValues[$i] &&
                    substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) return false;
            }
            $secret = str_replace('=', '', $secret);
            $secret = str_split($secret);
            $binaryString = "";
            for ($i = 0; $i < count($secret); $i = $i + 8) {
                $x = "";
                if (!isset($secret[$i]) || !in_array($secret[$i], $base32chars)) return false;
                for ($j = 0; $j < 8; $j++) {
                    $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
                }
                $eightBits = str_split($x, 8);
                for ($z = 0; $z < count($eightBits); $z++) {
                    $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : "";
                }
            }
            return $binaryString;
        }

        protected static function _getBase32LookupTable() {
            return array(
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', // 7
                'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
                'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
                '='  // padding char
            );
        }
    }
}
