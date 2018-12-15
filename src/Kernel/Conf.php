<?php
namespace Annual\Kernel;

use Exception;

class Conf
{
    private $Environment = '';
    private $Name        = '';
    private $Debug       = true;
    private $App         = [];

    // 分布式异步任务
    private $TaskClientAddress = '';

    // 日志配置
    private $LogPath = __LOGPATH__;

    // redis 配置
    private $DefaultRedisHost = '127.0.0.1';
    private $DefaultRedisPort = 6379;
    private $DefaultRedisAuth = '';
    private $DefaultRedisDb   = 0;

    // memcached 配置
    private $DefaultMemcachedServers = [
        ['127.0.0.1', 11211],
    ];

    // cache 配置
    private $CacheNamespace = 'Annual';
    private $CacheLifetime  = 3600;
    // redis或memcached配置前缀
    private $CacheDriveConfName = 'Default';
    // 文件缓存配置
    private $CacheDirectory = __CACHE__;

    // 数据库配置
    private $DefaultMysqlHost    = '127.0.0.1';
    private $DefaultMysqlPort    = 3306;
    private $DefaultMysqlUser    = 'root';
    private $DefaultMysqlPass    = 'root';
    private $DefaultMysqlName    = 'test';
    private $DefaultMysqlCharset = 'utf8';

    // 接口模板key
    private $ErrCodeKey = 'code';
    private $ErrMsgKey  = 'error';
    private $DataKey    = 'data';

    private $ServerList = [];
    // 事件监听
    private $Listeners = [];

    const WORKER_SOCKETIO_FLAG = 1;
    const WORKER_TASK_FLAG     = 2;
    const WORKER_CRONTAB_FLAG  = 3;

    // 当前进程
    private $CurrentWorker = 1;

    // 微信配置
    private $WechatConf = [];

    // Jwt配置
    private $JwtSecretKey = '5c10c6d27758eghgiyttr5646e75';
    private $JwtToken     = [
        "iss" => "https://vaina.github.com",
        "aud" => "https://vaina.github.com",
    ];

    // jsonp
    private $JsonpCallback = 'callback';

    private static $_instance;

    public static function __callStatic($name, $arguemnts)
    {
        // $class = get_class();
        if (strpos($name, 'get') === 0) {
            $key = preg_replace('/^get/', '', $name);
            if (property_exists(self::$_instance, $key)) {
                return self::$_instance->$key;
            } else {
                $key = ucfirst($key);
                return self::$_instance->$key;
            }
        }
        if (strpos($name, 'set') === 0) {
            $key   = preg_replace('/^set/', '', $name);
            $value = isset($arguemnts[0]) ? $arguemnts[0] : null;
            if (property_exists(self::$_instance, $key)) {
                if (gettype($value) === gettype(self::$_instance->$key)) {
                    self::$_instance->$key = $value;
                } else {
                    throw new Exception("Call to method " . __CLASS__ . "::{$name}() with invalid arguements", 1);
                }
                return;
            } else {
                $key = ucfirst($key);
                self::$_instance->$key = $value;
            }
        }
        throw new Exception("Call to undefined method " . __CLASS__ . "::{$name}()", 1);
    }

    public static function setup($config = null)
    {
        if (!is_array($config)) {
            // 初始化时缺少配置项
            throw new Exception("E_INIT_LOST_CONFIG");
        }
        $class = new self();
        foreach ($config as $key => $value) {
            $key = ucfirst($key);
            if (property_exists($class, $key)) {
                if (gettype($value) === gettype($class->$key)) {
                    $class->$key = $value;
                } else {
                    // 初始化时配置类型错误
                    throw new Exception("E_INIT_CONFIG_TYPE" . ': ' . $key);
                }
            } else {
                $class->$key = $value;
            }
        }
        self::$_instance = $class;
    }
}
