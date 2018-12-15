<?php
namespace Annual\Kernel\Servers;

use Annual\Kernel\Contracts\Servers;
use Workerman\Lib\Timer;
use Workerman\Worker;

class FileMonitorServer extends Worker implements Servers
{
    private $_config;
    private $lastMtime;

    public static function start($config)
    {
        $config = array_merge([
            'monitorDir' => [
                __APP__,
                __CONF__,
            ],
        ], $config);
        $server             = new self();
        $server->_config    = $config;
        $server->name       = empty($config['name']) ? 'File-Monitor' : $config['name'];
        $server->reloadable = false;
        $server->lastMtime  = time();

        $server->onWorkerStart = [$server, 'onStart'];
    }

    public function onStart($worker)
    {
        if (self::$daemonize || empty($this->_config['monitorDir'])) {
            return;
        }
        // 定时器监控
        Timer::add(1, function () {
            foreach ((array) $this->_config['monitorDir'] as $path) {
                $this->checkFilesChange($path);
            }
        });
    }

    /**
     * 文件热更回调方法
     *
     * @param  string $monitor_dir 文件更新监控路径
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function checkFilesChange($path)
    {
        // recursive traversal directory
        $dirIterator = new \RecursiveDirectoryIterator($path);
        $iterator    = new \RecursiveIteratorIterator($dirIterator);
        foreach ($iterator as $file) {
            // only check php files
            if (\pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            clearstatcache();
            // check mtime
            if ($this->lastMtime < $file->getMTime()) {
                echo $file . " update and reload\n";
                // send SIGUSR1 signal to master process for reload
                \posix_kill(\posix_getppid(), SIGUSR1);
                $this->lastMtime = $file->getMTime();
                break;
            }
        }
    }
}
