<?php
namespace Annual\Kernel;

use Annual\Kernel\Contracts\Listeners;
use Symfony\Component\Yaml\Yaml;
use Workerman\Worker;

class Server
{
    protected static $configFile = '';
    /**
     * 配置初始化
     *
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public static function setConf()
    {
        $config = [];
        if (file_exists(self::$configFile)) {
            $config = Yaml::parseFile(self::$configFile, Yaml::PARSE_CONSTANT);
            // 全局配置
            $globalsConf = empty($config['Globals']) ? [] : (array) $config['Globals'];
            // 环境配置名称
            $environment = empty($globalsConf['Environment']) ? 'Develop' : $globalsConf['Environment'];
            // 环境配置
            $envConf = empty($config[$environment]) ? [] : $config[$environment];
            Conf::setup(array_merge($config['Globals'], (array) $envConf));
        }
    }

    public static function setListeners()
    {
        HandlerParser::getFileRecursive(__APP__ . '/Listeners', $listenerFiles);
        if (empty($listenerFiles)) {
            return;
        }
        $suffix = 'Listener.php';
        foreach ($listenerFiles as $file) {
            if (empty(strpos($file, $suffix))) {
                continue;
            }
            $e = 'Annual\App\Listeners\\' . str_replace('.php', '', basename($file));
            try {
                $ref = new \ReflectionClass($e);
                if (!$ref->implementsInterface(Listeners::class)) {
                    throw new \Exception("Class [{$e}] must implement Listeners interface");
                }
                $lObj  = $ref->newInstance();
                // 接口启用状态
                if (!$lObj->enabled()) {
                    continue;
                }
                $event = $lObj->getName();
                if (is_array($event)) {
                    foreach ($event as $v) {
                        Events::on($v, [$lObj, 'run']);
                    }
                    continue;
                }
                Events::on($event, [$lObj, 'run']);
            } catch (\Exception $e) {
                Helper::log($e->getMessage(), 'warn', $logFile);
                continue;
            }
        }
    }

    public static function handlerException($e)
    {
        $code = $e->getCode();
        if ($e instanceof EndException && $code == 0) {
            return Helper::toResult($e->getMessage());
        }
        Helper::logException($e, 'error');
        return Helper::error($e->getMessage(), $code ?: 10000);
    }

    public static function run($file = null)
    {
        // 项目目录
        defined('__ROOT__') or define('__ROOT__', dirname(__DIR__));
        defined('__APP__') or define('__APP__', __ROOT__ . '/App');
        defined('__CONF__') or define('__CONF__', __ROOT__ . '/Conf');
        defined('__RUNTIME__') or define('__RUNTIME__', __ROOT__ . '/Runtime');
        defined('__CACHE__') or define('__CACHE__', __RUNTIME__ . '/cache');
        defined('__LOGPATH__') or define('__LOGPATH__', __RUNTIME__ . '/logs');

        self::$configFile = $file ?: __CONF__ . '/conf.yml';
        self::setConf();
        $servers = Conf::getServerList();
        if (empty($servers)) {
            exit('未配置启动服务' . PHP_EOL);
        }
        // 启动服务
        foreach ($servers as $server) {
            $config = [];
            if (is_array($server)) {
                $config = current($server);
                $server = key($server);
            }
            $ref = new \ReflectionClass($server);
            if (!$ref->implementsInterface('Annual\Kernel\Contracts\Servers')) {
                continue;
            }
            call_user_func_array([$server, 'start'], [$config]);
        }
        // 进程设置配置
        Worker::$pidFile    = __RUNTIME__ . '/worker-annual.pid';
        Worker::$logFile    = Conf::getLogPath() . '/socket-annual-' . date('Ymd') . '.log';
        Worker::$stdoutFile = Worker::$logFile;
        Worker::runAll();
    }
}
