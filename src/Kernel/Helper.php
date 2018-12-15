<?php
namespace Annual\Kernel;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SuperClosure\Serializer;
use Symfony\Component\Cache\Simple\FilesystemCache;
use Symfony\Component\Cache\Simple\MemcachedCache;
use Symfony\Component\Cache\Simple\RedisCache;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\MySQL\Connection;

class Helper
{
    private static $_maps = [];

    /**
     * 异步任务方法
     *
     * @param  \Closure $callback    回调方法
     * @param  callable $retCallBack 结果回调方法
     * @param  array $args 结果回传参数
     * @return null
     */
    public static function task(
        \Closure $callback, $retCallBack = null, array $args = []
    ) {
        if (Conf::getCurrentWorker() === Conf::WORKER_TASK_FLAG) {
            throw new \Exception('task_worker does not call async task!');
        }
        $address   = Conf::getTaskClientAddress();
        $hasFinish = false;
        if (empty($address)) {
            $address   = Conf::getTaskAddress();
            $hasFinish = true;
        }
        $serializer = new Serializer();
        $serialized = $serializer->serialize($callback);
        // 与远程task服务建立异步连接，ip为远程task服务的ip，如果是本机就是127.0.0.1，如果是集群就是lvs的ip
        $taskConnection = new AsyncTcpConnection('text://' . $address);
        $data           = ['type' => 'task', 'data' => base64_encode($serialized)];
        // 发送数据
        $taskConnection->send(json_encode($data));
        // 异步获得结果
        $taskConnection->onMessage = function (
            $taskConnection, $taskResult
        ) use ($hasFinish, $retCallBack, $args) {
            try {
                if (is_callable($retCallBack)) {
                    \array_unshift($args, $taskResult);
                    \call_user_func_array($retCallBack, $args);
                }
            } finally {
                // 获得结果后记得关闭异步连接
                $taskConnection->close();
            }
        };
        // 执行异步连接
        $taskConnection->connect();
    }

    /**
     * redis 对象
     * @param  string $prefix 配置前缀
     * @return \Redis
     */
    public static function redis($prefix = 'default')
    {
        $prefix = ucfirst($prefix);
        if (isset(self::$_maps['redis'][$prefix])) {
            return self::$_maps['redis'][$prefix];
        }
        // 创建 redis 实例
        $redis    = new \Redis();
        $hostName = "get{$prefix}RedisHost";
        $portName = "get{$prefix}RedisPort";
        $authName = "get{$prefix}RedisAuth";
        $dbName   = "get{$prefix}RedisDb";
        $redis->connect(Conf::$hostName(), Conf::$portName());
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
        if ($auth = Conf::$authName()) {
            $redis->auth($auth);
        }
        $redis->select(Conf::$dbName() ?: 0);
        // 创建缓存实例
        return self::$_maps['redis'][$prefix] = $redis;
    }

    /**
     * memcache 对象
     * @param  string $prefix 配置前缀
     * @return \Memcached
     */
    public static function memcached($prefix = 'default')
    {
        $prefix = ucfirst($prefix);
        if (isset(self::$_maps['memcached'][$prefix])) {
            return self::$_maps['memcached'][$prefix];
        }
        $mc = new \Memcached($prefix);
        $mc->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
        $servers = "get{$prefix}MemcachedServers";
        $mc->addServers(Conf::$servers());
        // 创建缓存实例
        return self::$_maps['memcached'][$prefix] = $mc;
    }

    /**
     * 策略缓存对象
     *
     * @param  string $prefix 缓存对象类型
     * @return Symfony\Component\Cache\Simple\AbstractCache
     */
    public static function cache($prefix = 'Memcached')
    {
        $prefix = ucfirst($prefix);
        if (isset(self::$_maps['cache'][$prefix])) {
            return self::$_maps['cache'][$prefix];
        }
        $namespace       = Conf::getCacheNamespace();
        $defaultLifetime = Conf::getCacheLifetime();
        // 创建缓存实例
        switch ($prefix) {
            case 'Memcached':
                $driveConfName = Conf::getCacheDriveConfName();
                $cache         = new MemcachedCache(self::memcached($driveConfName), $namespace, $defaultLifetime);
                break;
            case 'Redis':
                $driveConfName = Conf::getCacheDriveConfName();
                $cache         = new RedisCache(self::redis($driveConfName), $namespace, $defaultLifetime);
                break;
            default:
                $directory = Conf::getCacheDirectory();
                $cache     = new FilesystemCache($namespace, $defaultLifetime, $directory);
                break;
        }
        return self::$_maps['cache'][$prefix] = $cache;
    }

    /**
     * 日志对象
     * @param  string $message 日志内容
     * @param  string $level   日志级别 debug|info|notice|warn|error
     * @return bool Whether the record has been processed
     */
    public static function log($message, $level = 'info', $filename = '')
    {
        $filename = $filename ?: 'annual-' . date('Ymd') . '.log';
        $key      = md5($filename);
        if (empty(self::$_maps['log'][$key])) {
            $log = new Logger('annual');
            $log->pushHandler(new StreamHandler(
                Conf::getLogPath() . '/' . $filename,
                Logger::DEBUG
            ));
            self::$_maps['log'][$key] = $log;
        }
        return call_user_func([self::$_maps['log'][$key], $level], $message);
    }

    /**
     * db操作对象
     *
     * @param  string $prefix 配置前缀
     * @return Workerman\MySQL\Connection
     */
    public static function db($prefix = 'default')
    {
        $prefix = ucfirst($prefix);
        if (isset(self::$_maps['db'][$prefix])) {
            return self::$_maps['db'][$prefix];
        }

        $hostName = "get{$prefix}MysqlHost";
        $portName = "get{$prefix}MysqlPort";
        $userName = "get{$prefix}MysqlUser";
        $passName = "get{$prefix}MysqlPass";
        $dbName   = "get{$prefix}MysqlName";
        $charset  = "get{$prefix}MysqlCharset";

        return self::$_maps['db'][$prefix] = new Connection(
            Conf::$hostName(),
            Conf::$portName(),
            Conf::$userName(),
            Conf::$passName(),
            Conf::$dbName(),
            Conf::$charset()
        );
    }

    public static function end($msg = '', $code = 0)
    {
        throw new EndException($msg, $code);
    }

    public static function success($data, $code = 0, $toJson = false)
    {
        return self::toResult($data, $code, '', $toJson);
    }

    public static function error($msg, $code = 0, $toJson = false)
    {
        return self::toResult('', $code, $msg, $toJson);
    }

    public static function toResult($data, $code = 0, $msg = '', $toJson = false)
    {
        $data = [
            Conf::getErrCodeKey() => $code,
            Conf::getErrMsgKey()  => $msg,
            Conf::getDataKey()    => $data,
        ];
        if ($toJson) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    public static function logException(\Exception $e, $level = 'info')
    {
        return self::log(json_encode([
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'type'    => $e->getCode(),
        ], JSON_UNESCAPED_UNICODE), $level);
    }
}
