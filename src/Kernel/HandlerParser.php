<?php
namespace Annual\Kernel;

use zpt\anno\Annotations;

class HandlerParser
{
    public static function parseController($uri, $nsp = 'Annual\App\Api\\')
    {
        $paths   = array_filter(explode('/', $uri));
        $method  = 'Main';
        $handler = 'Default';
        switch (count($paths)) {
            case 0:
                $paths = [$handler, $method];
                break;
            case 1:
                array_push($paths, $method);
            default:
                array_walk($paths, function (&$path) {
                    if (strpos($path, '.') !== false) {
                        $path = strstr($path, '.', true);
                    }
                    $path = ucfirst($path);
                });
        }
        $method  = array_pop($paths) . 'Action';
        $handler = $nsp . join($paths, '\\') . 'Api';
        return [$handler, $method];
    }

    public static function parseTask($data, $nsp = '\Annual\App\Tasks\\')
    {
        return [
            $nsp . (empty($data['ct']) ? 'Default' : $data['ct']) . 'Task',
            (empty($data['ac']) ? 'Main' : $data['ac']) . 'Action',
        ];
    }

    public static function parseEvent($eventPath, $nsp = 'Annual\App\Sockets\\')
    {
        self::getFileRecursive($eventPath, $eventFiles);
        if (empty($eventFiles)) {
            return [];
        }
        $suffix = 'Socket.php';
        $result = [];
        $uniq   = [];
        foreach ($eventFiles as $file) {
            if (empty(strpos($file, $suffix))) {
                continue;
            }
            $eName = str_replace($suffix, '', basename($file));
            $e     = $nsp . $eName . 'Socket';
            try {
                $reflector = new \ReflectionClass($e);
                if (!$reflector->isSubclassOf(SocketsHandler::class)) {
                    continue;
                }
                $cAs = new Annotations($reflector);
                if ($cAs->hasAnnotation('alias')) {
                    $eName = $cAs['alias'];
                }
                $methods = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    $m = $method->getName();
                    if (strpos($m, 'Action') === false) {
                        continue;
                    }
                    $mAs = new Annotations($method);
                    if ($mAs->hasAnnotation('alias')) {
                        $mName = $mAs['alias'];
                    } else {
                        // 默认方法名
                        $mName = rtrim($m, 'Action');
                    }
                    // 事件命名空间
                    $path = "/{$eName}/{$mName}";
                    // 设置独立事件，默认覆盖别名事件
                    if ($mAs->hasAnnotation('path') && strlen($mAs['path']) > 0) {
                        $path = $mAs['path'];
                    }
                    // 重名路径不处理
                    if (!array_key_exists($path, $uniq)) {
                        $result[$e][$m] = $path;
                        $uniq[$path]    = true;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return $result;
    }

    public static function getFileRecursive($path, &$files)
    {
        if (is_dir($path)) {
            $dp = dir($path);
            while ($file = $dp->read()) {
                if ($file != "." && $file != "..") {
                    self::getFileRecursive($path . "/" . $file, $files);
                }
            }
            $dp->close();
        }

        if (is_file($path)) {
            $files[] = $path;
        }
    }

    public static $error;
    /**
     *  解析crontab的定时格式，linux只支持到分钟/，这个类支持到秒
     * @param string $crontab_string :
     *
     *      0     1    2    3    4    5
     *      *     *    *    *    *    *
     *      -     -    -    -    -    -
     *      |     |    |    |    |    |
     *      |     |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     *      |     |    |    |    +----- month (1 - 12)
     *      |     |    |    +------- day of month (1 - 31)
     *      |     |    +--------- hour (0 - 23)
     *      |     +----------- min (0 - 59)
     *      +------------- sec (0-59)
     * @param int $start_time timestamp [default=current timestamp]
     * @return int unix timestamp - 下一分钟内执行是否需要执行任务，如果需要，则把需要在那几秒执行返回
     * @throws \InvalidArgumentException 错误信息
     */
    public static function parseCrontab($crontab_string, $start_time = null)
    {
        if (is_array($crontab_string)) {
            return self::_parse_array($crontab_string, $start_time);
        }
        if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
            if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
                self::$error = "Invalid cron string: " . $crontab_string;
                return false;
            }
        }
        if ($start_time && !is_numeric($start_time)) {
            self::$error = "\$start_time must be a valid unix timestamp ($start_time given)";
            return false;
        }
        $cron  = preg_split("/[\s]+/i", trim($crontab_string));
        $start = empty($start_time) ? time() : $start_time;
        if (count($cron) == 6) {
            $date = array(
                'second'  => self::_parse_cron_number($cron[0], 0, 59),
                'minutes' => self::_parse_cron_number($cron[1], 0, 59),
                'hours'   => self::_parse_cron_number($cron[2], 0, 23),
                'day'     => self::_parse_cron_number($cron[3], 1, 31),
                'month'   => self::_parse_cron_number($cron[4], 1, 12),
                'week'    => self::_parse_cron_number($cron[5], 0, 6),
            );
        } elseif (count($cron) == 5) {
            $date = array(
                'second'  => array(1 => 1),
                'minutes' => self::_parse_cron_number($cron[0], 0, 59),
                'hours'   => self::_parse_cron_number($cron[1], 0, 23),
                'day'     => self::_parse_cron_number($cron[2], 1, 31),
                'month'   => self::_parse_cron_number($cron[3], 1, 12),
                'week'    => self::_parse_cron_number($cron[4], 0, 6),
            );
        }
        if (
            in_array(intval(date('i', $start)), $date['minutes']) &&
            in_array(intval(date('G', $start)), $date['hours']) &&
            in_array(intval(date('j', $start)), $date['day']) &&
            in_array(intval(date('w', $start)), $date['week']) &&
            in_array(intval(date('n', $start)), $date['month'])
        ) {
            return $date['second'];
        }
        return null;
    }
    /**
     * 解析单个配置的含义
     * @param $s
     * @param $min
     * @param $max
     * @return array
     */
    protected static function _parse_cron_number($s, $min, $max)
    {
        $result = array();
        $v1     = explode(",", $s);
        foreach ($v1 as $v2) {
            $v3   = explode("/", $v2);
            $step = empty($v3[1]) ? 1 : $v3[1];
            $v4   = explode("-", $v3[0]);
            $_min = count($v4) == 2 ? $v4[0] : ($v3[0] == "*" ? $min : $v3[0]);
            $_max = count($v4) == 2 ? $v4[1] : ($v3[0] == "*" ? $max : $v3[0]);
            for ($i = $_min; $i <= $_max; $i += $step) {
                $result[$i] = intval($i);
            }
        }
        ksort($result);
        return $result;
    }
    protected static function _parse_array($crontab_array, $start_time)
    {
        $result = array();
        foreach ($crontab_array as $val) {
            if (count(explode(":", $val)) == 2) {
                $val = $val . ":01";
            }
            $time = strtotime($val);
            if ($time >= $start_time && $time < $start_time + 60) {
                $result[$time] = $time;
            }
        }
        return $result;
    }
}
