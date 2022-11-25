<?php

use App\Core\User;
use App\Helpers\GuzzleHttp\GuzzleResponseMiddleware;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\Type\Repositories\TypeRepository;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

// in old CRM - helper::seconds_to_time
function secondsToTime($value)
{
    $negative = ($value < 0);
    if ($negative) {
        $value = -$value;
    }
    $hours = (int) ($value / 3600);
    $hours = ($hours < 10) ? '0'.$hours : $hours;
    $minutes = (int) (($value - ($hours * 3600)) / 60);
    $minutes = ($minutes < 10) ? '0'.$minutes : $minutes;
    $seconds = $value - ($hours * 3600) - ($minutes * 60);
    $seconds = ($seconds < 10) ? '0'.$seconds : $seconds;

    return ($negative ? '-' : '').$hours.':'.$minutes.':'.$seconds;
}

/**
 * Convert days into seconds
 *
 * @param  int  $days
 *
 * @return int
 */
function daysToSeconds($days)
{
    // 24 * 60 * 60 = 86400
    return $days * 86400;
}

function timeToSeconds($value)
{
    $list = explode(':', $value);
    $seconds = 0;

    $seconds += $list[0] * 60 * 60;
    $seconds += $list[1] * 60;
    $seconds += $list[2];

    return $seconds;
}

/**
 * Verify if datetime (or date) is empty ('0000-00-00 00:00:00' or '0000-00-00'
 * is considered also as empty)
 *
 * @param $dateTime
 *
 * @return bool
 */
function isEmptyDateTime($dateTime)
{
    return (($dateTime === getZeroDateTime())
        || ($dateTime === getZeroDate())
        || empty($dateTime));
}

/**
 * Get zero datetime value (0000-00-00 00:00:00)
 *
 * @return string
 */
function getZeroDateTime()
{
    return '0000-00-00 00:00:00';
}

/**
 * If date is zero then return null else return date
 *
 * @param $date
 *
 * @return null|string
 */
function getDateOrNull($date)
{
    if ($date === getZeroDate()) {
        return null;
    }

    return $date;
}

/**
 * If date time is zero then return null else return date
 *
 * @param $dateTime
 *
 * @return null|string
 */
function getDateTimeOrNUll($dateTime)
{
    if ($dateTime === getZeroDateTime()) {
        return null;
    }

    return $dateTime;
}

/**
 * Verify if date is empty ('0000-00-00' is considered also as empty)
 *
 * @param $date
 *
 * @return bool
 */
function isEmptyDate($date)
{
    return (($date === getZeroDate()) || empty($date));
}

/**
 * Get zero date value (0000-00-00)
 *
 * @return string
 */
function getZeroDate()
{
    return '0000-00-00';
}

/**
 * Returns array with week START and END date for given date
 *
 * @param  string  $date
 * @param  string  $format  - output date format, default 'Y-m-d'
 * @param  bool  $eachDay
 *
 * @return array($startWeekDate, $endWeekDate)
 */
function getWeekRange($date, $format = 'Y-m-d', $eachDay = false)
{
    $ts = strtotime($date);
    $start = ((int) date('w', $ts) === 1) ? $ts : strtotime('last monday', $ts);
    if ($eachDay === true) {
        return [
            date($format, $start),
            date($format, strtotime('next tue', $start)),
            date($format, strtotime('next wed', $start)),
            date($format, strtotime('next thu', $start)),
            date($format, strtotime('next fri', $start)),
            date($format, strtotime('next sat', $start)),
            date($format, strtotime('next sun', $start)),
        ];
    }

    return [
        date($format, $start),
        date($format, strtotime('next sunday', $start)),
    ];
}

/**
 * Get array with month START and END date for given date
 *
 * @param  string  $date
 * @param  string  $format  - output date format, default 'Y-m-d'
 *
 * @return array[$startMonthDate, $endMonthDate]
 */
function getMonthRange($date, $format = 'Y-m-d')
{
    $dt = strtotime($date);

    return [
        date($format, strtotime('first day of this month', $dt)),
        date($format, strtotime('last day of this month', $dt)),
    ];
}

/**
 * Get distance between to geo coordinates using great circle distance formula
 *
 * @param  float  $lat1
 * @param  float  $lat2
 * @param  float  $lon1
 * @param  float  $lon2
 * @param  string  $unit  M=miles, K=kilometers, N=nautical miles, I=inches, F=feet
 *
 * @return float
 */
function getGeoDistance($lat1, $lon1, $lat2, $lon2, $unit = 'M')
{
    if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2)
        || !is_numeric($lon2)
    ) {
        return -1;
    }
    // calculate miles
    $M = 69.09 * rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1
                - $lon2))));

    switch (strtoupper($unit)) {
        case 'K':
            // kilometers
            return $M * 1.609344;
            break;
        case 'N':
            // nautical miles
            return $M * 0.868976242;
            break;
        case 'F':
            // feet
            return $M * 5280;
            break;
        case 'I':
            // inches
            return $M * 63360;
            break;
        case 'M':
        default:
            // miles
            return $M;
            break;
    }
}

/**
 * Generate sample description
 *
 * @param  int  $minWords  Minimum number of words
 * @param  int  $maxWords  Maximum number of words
 * @param  int  $minWord  Minimum word length
 * @param  int  $maxWord  Maximum word length (max 35)
 *
 * @return string
 */
function generateSampleDescription(
    $minWords = 50,
    $maxWords = 333,
    $minWord = 5,
    $maxWord = 25
) {
    $description = '';
    $words = mt_rand($minWords, $maxWords);
    for ($i = 0; $i < $words; ++$i) {
        $description .= generateRandomString(mt_rand($minWord, $maxWord));
        $separator = mt_rand(0, 100);
        if ($separator < 70) {
            $description .= ' ';
        } elseif ($separator < 80) {
            $description .= '. ';
        } elseif ($separator < 95) {
            $description .= ', ';
        } else {
            $description .= '?';
        }
    }

    return $description;
}

function generateRandomString($length = 8)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Get person name by personId
 *
 * @param $personId
 *
 * @return mixed
 */
function getPersonName($personId)
{
    $personRepo = App::make(PersonRepository::class);

    return $personRepo->getPersonName($personId);
}

/**
 * Get id of Type by type key
 *
 * @param  string  $typeKey
 * @param  bool  $withChildren
 *
 * @return int|array|null
 */
function getTypeIdByKey($typeKey, $withChildren = false)
{
    /** @var TypeRepository $typeRepo */
    $typeRepo = App::make(TypeRepository::class);

    try {
        return $typeRepo->getIdByKey($typeKey, $withChildren);
    } catch (\PDOException $e) {
    }
}

/**
 * Get value of Type by type id
 *
 * @param  int  $typeId
 *
 * @return int|null
 */
function getTypeValueById($typeId)
{
    /** @var TypeRepository $typeRepo */
    $typeRepo = App::make(TypeRepository::class);

    return $typeRepo->getValueById($typeId);
}

/**
 * Get value of Type by type key
 *
 * @param  string  $typeKey
 *
 * @return int|null
 */
function getTypeValueByKey($typeKey)
{
    /** @var TypeRepository $typeRepo */
    $typeRepo = App::make(TypeRepository::class);

    return $typeRepo->getValueByKey($typeKey);
}

/**
 * Get key of Type by type id
 *
 * @param  int  $typeId
 *
 * @return string|null
 */
function getTypeKeyById($typeId)
{
    /** @var TypeRepository $typeRepo */
    $typeRepo = App::make(TypeRepository::class);

    return $typeRepo->getKeyById($typeId);
}

/**
 * Get currently logged user person_id or 0 if user is not logged
 *
 * @return int
 */
function getCurrentPersonId()
{
    if (Auth::check()) {
        /** @var \App\Core\User $user */
        $user = Auth::user();

        return $user->getPersonId();
    }

    return 0;
}

/**
 * Get id of current logged user 0 if user is not logged
 *
 * @return int
 */
function getCurrentUserId()
{
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();

        return $user->id;
    }

    return 0;
}

if (!function_exists('array_group_by')) {
    /**
     * Groups an array by a given key.
     *
     * Groups an array into arrays by a given key, or set of keys, shared between all array members.
     *
     * @param  array  $array  The array to have grouping performed on.
     * @param  mixed  $key,...  The key to group or split by. Can be a _string_,
     *                       an _integer_, a _float_, or a _callable_.
     *
     *                       If the key is a callback, it must return
     *                       a valid key from the array.
     *
     *                       If the key is _NULL_, the iterated element is skipped.
     *
     *                       ```
     *                       string|int callback ( mixed $item )
     *                       ```
     *
     * @return array|null Returns a multidimensional array or `null` if `$key` is invalid.
     */
    function array_group_by(array $array, $key)
    {
        if (!is_string($key) && !is_int($key) && !is_float($key) && !is_callable($key)) {
            trigger_error('array_group_by(): The key should be a string, an integer, or a callback', E_USER_ERROR);

            return null;
        }
        $func = (!is_string($key) && is_callable($key) ? $key : null);
        $_key = $key;
        // Load the new array, splitting by the target key
        $grouped = [];
        foreach ($array as $value) {
            $key = null;
            if (is_callable($func)) {
                $key = $func($value);
            } elseif (is_object($value) && isset($value->{$_key})) {
                $key = $value->{$_key};
            } elseif (isset($value[$_key])) {
                $key = $value[$_key];
            }
            if ($key === null) {
                continue;
            }
            $grouped[$key][] = $value;
        }
        // Recursively build a nested grouping if more parameters are supplied
        // Each grouped array value is grouped according to the next sequential key
        if (func_num_args() > 2) {
            $args = func_get_args();
            foreach ($grouped as $key => $value) {
                $params = array_merge([$value], array_slice($args, 2, func_num_args()));
                $grouped[$key] = array_group_by(...$params);
            }
        }

        return $grouped;
    }
}

/**
 * Check data for UTF encoding issues
 *
 * @param  array  $data
 */
function checkUtf($data)
{
    foreach ($data as $i => $row) {
        $result = @json_encode($row);

        if ($result !== false) {
            continue;
        }

        echo "\nEncoding error on row `{$i}`!";

        if (is_array($row)) {
            foreach ($row as $key => $value) {
                $result2 = @json_encode($value);

                if ($result2 !== false) {
                    continue;
                }

                echo "Found error in attribute: `{$key}`:\n".print_r($value, true)."\n";
            }
        }

        echo 'DONE\n';
        die();
    }

    echo "Nothing to do\n";
    die();
}

/**
 * Remove non-utf8 characters in a string
 *
 * @param  string  $string
 * @param  bool  $strict  remove all non-ASCII characters
 *
 * @return string
 */
function clean_string($string, $strict = false)
{
    $s = trim($string);
    $s = iconv('UTF-8', 'UTF-8//IGNORE', $s); // drop all non utf-8 characters

    if ($strict) {
        $s = preg_replace('/[[:^print:]]/', '', $s);
    } else {
        // this is some bad utf-8 byte sequence that makes mysql complain - control and formatting
        $s = preg_replace(
            '/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xC3[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/',
            ' ',
            $s
        );
    }

    $s = preg_replace('/\s+/', ' ', $s); // reduce all multiple whitespace to a single space

    return $s;
}

/**
 * Converting hex color to brightnes
 *
 * @param $htmlCode
 *
 * @return object
 */
function HTMLToRGBToHSL($htmlCode)
{
    if ($htmlCode[0] == '#') {
        $htmlCode = substr($htmlCode, 1);
    }

    if (strlen($htmlCode) == 3) {
        $htmlCode = $htmlCode[0].$htmlCode[0].$htmlCode[1].$htmlCode[1].$htmlCode[2].$htmlCode[2];
    }

    $r = hexdec($htmlCode[0].$htmlCode[1]);
    $g = hexdec($htmlCode[2].$htmlCode[3]);
    $b = hexdec($htmlCode[4].$htmlCode[5]);

    $RGB = $b + ($g << 0x8) + ($r << 0x10);
    $r = 0xFF & ($RGB >> 0x10);
    $g = 0xFF & ($RGB >> 0x8);
    $b = 0xFF & $RGB;

    $r = ((float) $r) / 255.0;
    $g = ((float) $g) / 255.0;
    $b = ((float) $b) / 255.0;

    $maxC = max($r, $g, $b);
    $minC = min($r, $g, $b);

    $l = ($maxC + $minC) / 2.0;

    if ($maxC == $minC) {
        $s = 0;
        $h = 0;
    } else {
        if ($l < .5) {
            $s = ($maxC - $minC) / ($maxC + $minC);
        } else {
            $s = ($maxC - $minC) / (2.0 - $maxC - $minC);
        }
        if ($r == $maxC) {
            $h = ($g - $b) / ($maxC - $minC);
        }
        if ($g == $maxC) {
            $h = 2.0 + ($b - $r) / ($maxC - $minC);
        }
        if ($b == $maxC) {
            $h = 4.0 + ($r - $g) / ($maxC - $minC);
        }

        $h = $h / 6.0;
    }

    $h = (int) round(255.0 * $h);
    $s = (int) round(255.0 * $s);
    $l = (int) round(255.0 * $l);

    return (object) ['hue' => $h, 'saturation' => $s, 'lightness' => $l];
}

/**
 * Convert bytes to human
 *
 * @param     $bytes
 * @param  int  $precision
 * @param  int  $block
 *
 * @return string
 */
function bytesToHuman($bytes, $precision = 2, $block = 1024)
{
    $units = ['B', 'KB', 'MB', 'GB'];

    for ($i = 0; $bytes > $block; $i++) {
        $bytes /= $block;
    }

    return round($bytes, $precision).' '.$units[$i];
}

/**
 * Calculating the distance between two points
 *
 * @param  float  $latFrom
 * @param  float  $lonFrom
 * @param  float  $latTo
 * @param  float  $lonTo
 * @param  string  $unit
 *
 * @return float
 */
function distance($latFrom, $lonFrom, $latTo, $lonTo, $unit = 'M')
{
    $theta = $lonFrom - $lonTo;
    $dist = sin(deg2rad($latFrom)) * sin(deg2rad($latTo)) + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit);

    if ($unit == "K") {
        return ($miles * 1.609344);
    } else {
        if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
}

/**
 * Create new GuzzleHttp client instance
 *
 * @param  array  $config
 *
 * @return GuzzleHttp\Client
 */
function guzzleClient(array $config = [])
{
    $stack = GuzzleHttp\HandlerStack::create();

    // use custom response class
    $stack->push(GuzzleResponseMiddleware::middleware());

    $config = array_merge([
        'handler' => $stack,
    ], $config);

    return new GuzzleHttp\Client($config);
}

/**
 * Convert html table to php array
 *
 * @param       $html
 * @param  array  $columns
 * @param  int  $fromIndex
 *
 * @return array
 */
function html2array($html, $columns = [], $fromIndex = 0)
{
    $dom = new \DOMDocument;
    $dom->loadHTML($html);

    $data = [];
    $items = $dom->getElementsByTagName('tr');

    foreach ($items as $index => $node) {
        if ($index >= $fromIndex) {
            $row = [];

            $i = 0;
            foreach ($node->childNodes as $element) {
                if (isset($element->tagName) && $element->tagName === 'td') {
                    if (isset($columns[$i])) {
                        $row[$columns[$i]] = trim($element->nodeValue);
                    } else {
                        $row[] = trim($element->nodeValue);
                    }

                    ++$i;
                }
            }

            $data[] = $row;
        }
    }

    return $data;
}

function Zip($source, $destination, $include_dir = false)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    if (file_exists($destination)) {
        unlink($destination);
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        if ($include_dir) {
            $arr = explode("/", $source);
            $maindir = $arr[count($arr) - 1];

            $source = "";
            for ($i = 0; $i < count($arr) - 1; $i++) {
                $source .= '/'.$arr[$i];
            }

            $source = substr($source, 1);

            $zip->addEmptyDir($maindir);
        }

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'])) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source.'/', '', $file.'/'));
            } else {
                if (is_file($file) === true) {
                    $zip->addFromString(str_replace($source.'/', '', $file), file_get_contents($file));
                }
            }
        }
    } else {
        if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
    }

    return $zip->close();
}

/**
 * Determine if a given string contains a given substring.
 *
 * @param  string  $haystack
 * @param  string|array  $needles
 *
 * @return bool
 */
function str_contains_any($haystack, $needles)
{
    return Str::contains($haystack, $needles);
}

/**
 * Post Messages to Discord Channel via Webhooks
 *
 * @param  string  $webhookUrl
 * @param  string  $content
 * @param  string|null  $username
 *
 * @return mixed
 */
function sendNotificationToDiscord(string $webhookUrl, string $content, $username = null)
{
    $data = ['content' => $content];

    if ($username) {
        $data['username'] = $username;
    }

    /** @var Client $client */
    $client = app(Client::class);

    return $client->request('POST', $webhookUrl, ['json' => $data]);
}

function isCrmUser($crmUser)
{
    return config('app.crm_user') === $crmUser;
}
