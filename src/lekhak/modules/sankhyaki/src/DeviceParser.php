<?php
namespace Lekhak\Modules\Sankhyaki;

class DeviceParser {
    public static function parse($userAgent) {
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';
        $device_type = 'Desktop';

        // Detect OS
        if (preg_match('/windows nt 10/i', $userAgent)) $os = 'Windows 10/11';
        elseif (preg_match('/windows nt 6\.3/i', $userAgent)) $os = 'Windows 8.1';
        elseif (preg_match('/windows nt 6\.2/i', $userAgent)) $os = 'Windows 8';
        elseif (preg_match('/windows nt 6\.1/i', $userAgent)) $os = 'Windows 7';
        elseif (preg_match('/mac os x/i', $userAgent)) $os = 'Mac OS X';
        elseif (preg_match('/linux/i', $userAgent)) $os = 'Linux';
        elseif (preg_match('/ubuntu/i', $userAgent)) $os = 'Ubuntu';
        elseif (preg_match('/iphone/i', $userAgent)) $os = 'iOS (iPhone)';
        elseif (preg_match('/ipad/i', $userAgent)) $os = 'iOS (iPad)';
        elseif (preg_match('/android/i', $userAgent)) $os = 'Android';

        // Detect Browser
        if (preg_match('/edge/i', $userAgent) || preg_match('/edg/i', $userAgent)) $browser = 'Edge';
        elseif (preg_match('/opr/i', $userAgent) || preg_match('/opera/i', $userAgent)) $browser = 'Opera';
        elseif (preg_match('/chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/msie/i', $userAgent) || preg_match('/trident/i', $userAgent)) $browser = 'Internet Explorer';

        // Detect Device Type
        if (preg_match('/mobile/i', $userAgent) || preg_match('/android/i', $userAgent) || preg_match('/iphone/i', $userAgent)) {
            $device_type = 'Mobile';
        }
        if (preg_match('/ipad/i', $userAgent) || preg_match('/tablet/i', $userAgent)) {
            $device_type = 'Tablet';
        }

        return [
            'os' => $os,
            'browser' => $browser,
            'device_type' => $device_type
        ];
    }
}
