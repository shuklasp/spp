<?php
/**
 * Global HTML Escaper
 * Securely escapes variables for output in views.
 */
function e($value): string
{
    if (is_null($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
}
function tsToD($ts)
{
    $dt = getdate($ts);
    return $dt['mday'] . '/' . $dt['mon'] . '/' . $dt['year'];
}

function tsToHHMM($ts)
{
    return date('G:i', $ts);
}


function getVisitorIP()
{
    // Trust the socket connection implicitly to prevent IP-Spoofing via arbitrary HTTP headers.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Properly validate via PHP's native filtering pipeline
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }

    return 'unknown';
}

function datediff($interval, $datefrom, $dateto, $using_timestamps = false)
{
    /*
    # $interval can be:
    # yyyy - Number of full years
    # q - Number of full quarters
    # m - Number of full months
    # y - Difference between day numbers
    # (eg 1st Jan 2004 is "1", the first day. 2nd Feb 2003 is "33". The datediff is "-32".)
    # d - Number of full days
    # w - Number of full weekdays
    # ww - Number of full weeks
    # h - Number of full hours
    # n - Number of full minutes
    # s - Number of full seconds (default)
    # */
    if (!$using_timestamps) {
        $datefrom = strtotime($datefrom, 0);
        $dateto = strtotime($dateto, 0);
    }
    $difference = $dateto - $datefrom; // Difference in seconds
    switch ($interval) {
        case 'yyyy': // Number of full years

            $years_difference = floor($difference / 31536000);
            if (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom), date("j", $datefrom), date("Y", $datefrom) + $years_difference) > $dateto) {
                $years_difference--;
            }
            if (mktime(date("H", $dateto), date("i", $dateto), date("s", $dateto), date("n", $dateto), date("j", $dateto), date("Y", $dateto) - ($years_difference + 1)) > $datefrom) {
                $years_difference++;
            }
            $datediff = $years_difference;
            break;

        case "q": // Number of full quarters

            $quarters_difference = floor($difference / 8035200);
            while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom) + ($quarters_difference * 3), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
                $months_difference++;
            }
            $quarters_difference--;
            $datediff = $quarters_difference;
            break;

        case "m": // Number of full months

            $months_difference = floor($difference / 2678400);
            while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom) + ($months_difference), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
                $months_difference++;
            }
            $months_difference--;
            $datediff = $months_difference;
            break;

        case 'y': // Difference between day numbers

            $datediff = date("z", $dateto) - date("z", $datefrom);
            break;

        case "d": // Number of full days

            $datediff = floor($difference / 86400);
            break;

        case "w": // Number of full weekdays

            $days_difference = floor($difference / 86400);
            $weeks_difference = floor($days_difference / 7); // Complete weeks
            $first_day = date("w", $datefrom);
            $days_remainder = floor($days_difference % 7);
            $odd_days = $first_day + $days_remainder; // Do we have a Saturday or Sunday in the remainder?
            if ($odd_days > 7) { // Sunday
                $days_remainder--;
            }
            if ($odd_days > 6) { // Saturday
                $days_remainder--;
            }
            $datediff = ($weeks_difference * 5) + $days_remainder;
            break;

        case "ww": // Number of full weeks

            $datediff = floor($difference / 604800);
            break;

        case "h": // Number of full hours

            $datediff = floor($difference / 3600);
            break;

        case "n": // Number of full minutes

            $datediff = floor($difference / 60);
            break;

        default: // Number of full seconds (default)

            $datediff = $difference;
            break;
    }

    return $datediff;

}



function sql_date_shift($date, $shift)
{
    $base = strtotime($date);
    if ($base === false) {
        return null;
    }
    $shifted = strtotime($shift, $base);
    return $shifted !== false ? date("Y-m-d H:i:s", $shifted) : null;
}

function date_shift($date, $shift)
{
    $base = strtotime($date);
    if ($base === false) {
        return null;
    }
    $shifted = strtotime($shift, $base);
    return $shifted !== false ? date("Y-m-d", $shifted) : null;
}

/**
 * Generate an application or external URL using the framework Url engine.
 *
 * @param string $path Target path or external URL
 * @param string|array|null $appName Specific application context or query parameters
 * @param array $queryParams Optional query parameters when $appName is a string
 * @return string
 */
function url(string $path = '', string|array|null $appName = null, array $queryParams = []): string
{
    if (is_array($appName)) {
        $queryParams = $appName;
        $appName = null;
    }
    return \SPP\Core\Url::to($path, $appName, $queryParams);
}

/**
 * Normalize an external URL to guarantee an explicit scheme (https://).
 */
function external_url(?string $url, string $defaultScheme = 'https'): string
{
    return \SPP\Core\Url::external($url, $defaultScheme);
}

/**
 * Retrieve a domain-scoped configuration setting for the active virtual host.
 *
 * @param string $key Setting key (e.g. 'site_name', 'theme', 'logo', 'contact_email')
 * @param mixed $default Default fallback if setting is not defined
 * @param string|null $host Optional specific host to query
 * @return mixed
 */
function domain_setting(string $key, mixed $default = null, ?string $host = null): mixed
{
    if (class_exists('\SPP\Core\Router\DomainRouter')) {
        return \SPP\Core\Router\DomainRouter::getSetting($key, $default, $host);
    }
    return $default;
}

/**
 * Retrieve flashed old input value from session or request payload.
 *
 * @param string $field Input field name
 * @param mixed $default Fallback value if field not present
 * @return mixed
 */
function old(string $field, mixed $default = null): mixed
{
    if (isset($_SESSION['_spp_old_input'][$field])) {
        return $_SESSION['_spp_old_input'][$field];
    }
    if (isset($_POST[$field])) {
        return $_POST[$field];
    }
    return $default;
}

/**
 * Retrieve the active application error bag or check for a specific field error.
 *
 * @param string|null $field
 * @return mixed
 */
function errors(?string $field = null): mixed
{
    if (class_exists('\\SPPMod\\SPPView\\SPP_ViewErrors')) {
        $errs = \SPPMod\SPPView\SPP_ViewErrors::getErrors();
        if ($field === null) {
            return $errs;
        }
        return $errs[$field] ?? null;
    }
    return null;
}

/**
 * Formats and prints an ASCII table to standard output for CLI environments.
 *
 * @param array<string> $headers Column header titles
 * @param array<array<string, mixed>|array<int, mixed>> $rows Data rows
 * @return void
 */
function printTable(array $headers, array $rows): void
{
    if (empty($headers) && empty($rows)) {
        return;
    }

    $colWidths = [];
    foreach ($headers as $i => $header) {
        $colWidths[$i] = mb_strlen((string)$header);
    }

    $normalizedRows = [];
    foreach ($rows as $row) {
        $norm = [];
        $i = 0;
        foreach ($headers as $headerKey => $header) {
            $val = '';
            if (is_array($row)) {
                if (array_key_exists($header, $row)) {
                    $val = $row[$header];
                } elseif (array_key_exists($headerKey, $row)) {
                    $val = $row[$headerKey];
                } elseif (array_key_exists($i, $row)) {
                    $val = $row[$i];
                }
            } else {
                $val = (string)$row;
            }

            if (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            } elseif (is_null($val)) {
                $val = 'null';
            } elseif (!is_scalar($val)) {
                $val = json_encode($val, JSON_UNESCAPED_SLASHES);
            } else {
                $val = (string)$val;
            }

            $norm[$i] = $val;
            $colWidths[$i] = max($colWidths[$i] ?? 0, mb_strlen($val));
            $i++;
        }
        $normalizedRows[] = $norm;
    }

    $border = '+';
    foreach ($colWidths as $w) {
        $border .= str_repeat('-', $w + 2) . '+';
    }

    echo $border . "\n";
    echo '|';
    foreach ($headers as $i => $h) {
        echo ' ' . str_pad((string)$h, $colWidths[$i]) . ' |';
    }
    echo "\n" . $border . "\n";

    foreach ($normalizedRows as $row) {
        echo '|';
        foreach ($row as $i => $cell) {
            echo ' ' . str_pad($cell, $colWidths[$i]) . ' |';
        }
        echo "\n";
    }
    echo $border . "\n";
}

/**
 * Generate a canonical URL for a named route or path with parameter substitution.
 *
 * @param string $name Route name or path pattern (e.g. 'users.show', '/users/{id}')
 * @param mixed $parameters Route parameters (scalar ID, array, or SPPEntity)
 * @param bool $absolute Whether to return an absolute URL with scheme and host
 * @return string
 */
function route(string $name, mixed $parameters = [], bool $absolute = false): string
{
    if (!class_exists("\\SPPMod\\SPPRouter\\UrlGenerator")) {
        $genFile = defined("SPP_BASE_DIR") ? SPP_BASE_DIR . "/modules/spp/spprouter/src/UrlGenerator.php" : dirname(__DIR__) . "/modules/spp/spprouter/src/UrlGenerator.php";
        if (file_exists($genFile)) {
            require_once $genFile;
        }
    }

    if (class_exists("\\SPPMod\\SPPRouter\\UrlGenerator")) {
        return \SPPMod\SPPRouter\UrlGenerator::route($name, $parameters, $absolute);
    }

    throw new \InvalidArgumentException("Route [{$name}] not defined.");
}

/**
 * Generate a URL for an application public asset.
 *
 * @param string $path Asset path (e.g. 'css/app.css', 'js/app.js')
 * @return string
 */
function asset(string $path): string
{
    $baseUrl = class_exists('\\SPP\\App') ? \SPP\App::getBaseUrl() : '';
    $cleanPath = ltrim($path, '/');
    return rtrim($baseUrl, '/') . '/' . $cleanPath;
}

/**
 * Retrieve a flashed session message, or null/default if none set.
 *
 * @param string $key Flash message identifier
 * @param mixed $default Fallback value
 * @return mixed
 */
function flash(string $key, mixed $default = null): mixed
{
    if (class_exists('\\SPP\\SPPSession')) {
        if (\SPP\SPPSession::hasFlash($key)) {
            return \SPP\SPPSession::getFlash($key);
        }
    }
    if (isset($_SESSION['_spp_flashes'][$key])) {
        $val = $_SESSION['_spp_flashes'][$key];
        unset($_SESSION['_spp_flashes'][$key]);
        return $val;
    }
    return $default;
}
