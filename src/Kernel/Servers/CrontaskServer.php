<?php
namespace Annual\Kernel\Servers;

use Annual\Kernel\Conf;
use Annual\Kernel\Contracts\Crontask;
use Annual\Kernel\Contracts\Servers;
use Annual\Kernel\Crontask\TickTable;
use Annual\Kernel\Events;
use Annual\Kernel\HandlerParser;
use Annual\Kernel\Helper;
use Annual\Kernel\Server;
use Symfony\Component\Yaml\Yaml;
use Workerman\Lib\Timer;
use Workerman\Worker;

class CrontaskServer extends Worker implements Servers
{
    private $_config    = [];
    private $uniqueList = [];
    private $tasks      = [];

    public static function start($config)
    {
        $config = array_merge([
            'name'          => 'Crontask-Server',
            'address'       => '127.0.0.1:8883',
            'user'          => '',
            'group'         => '',
            'checkTime'     => false,
            'allowErrCount' => 1,
            'output'        => __LOGPATH__ . '/crontask.log',
            'saveTaskFile'  => __CACHE__ . '/cron/tasks.yml',
        ], $config);
        $server                = new self('http://' . $config['address']);
        $server->name          = $config['name'];
        $server->user          = $config['user'];
        $server->group         = $config['user'];
        $server->onMessage     = [$server, 'onMessage'];
        $server->onWorkerStart = [$server, 'onStart'];
        $server->_config       = $config;
    }

    /**
     * 异步任务处理方法
     *
     * @param  Workerman\Connection\TcpConnection $connection
     * @param  string $data
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function onMessage($connection, $data)
    {
        if (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] == '/') {
            return $connection->send(Helper::error('not found', 400));
        }
        $paths = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        if ($paths[0] != 'cron') {
            return $connection->send(Helper::error('not found', 400));
        }
        if (empty($paths[1]) || $paths[1] == 'list') {
            $ret = $this->tasks;
        } else {
            switch ($paths[1]) {
                case 'stop':
                    foreach ($this->tasks as $name => &$task) {
                        if (!empty($paths[2])) {
                            if ($name != $paths[2]) {
                                continue;
                            }
                        }
                        $task['state'] = Crontask::CRONTASK_STOP;
                    }
                    $ret = "success";
                    break;
                case 'start':
                    foreach ($this->tasks as $name => &$task) {
                        if (!empty($paths[2])) {
                            if ($name != $paths[2]) {
                                continue;
                            }
                        }
                        $task['state']        = Crontask::CRONTASK_STAND;
                        $task['curExecTimes'] = 0;
                        $task['errCount']     = 0;
                    }
                    $ret = 'success';
                    break;
                case 'get':
                    if (!empty($paths[2])) {
                        foreach ($this->tasks as $name => $task) {
                            if ($name == $paths[2]) {
                                $ret = $task;
                                break;
                            }
                        }
                        break;
                    }
                default:
                    return $connection->send(Helper::error('not found', 400));
            }
        }

        $connection->send(Helper::success($ret));
    }

    public function onStart($worker)
    {
        Server::setConf();
        Server::setListeners();
        Conf::setCurrentWorker(Conf::WORKER_CRONTAB_FLAG);
        Events::emit('kernel.WorkerStart');

        if ($this->_config['checkTime']) {
            $run = true;
            print("正在启动...\n");
            while ($run) {
                $s = date("s");
                if ($s == 0) {
                    $this->getCrontasks(true);
                    $this->registerTimer();
                    $run = false;
                } else {
                    print("启动倒计时 " . (60 - $s) . " 秒\n");
                    sleep(1);
                }
            }
        } else {
            $this->getCrontasks(true);
            $this->registerTimer();
        }
    }

    /**
     * 注册定时任务
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-13
     */
    protected function registerTimer()
    {
        Timer::add(60, [$this, 'getCrontasks']);
        Timer::add(1, [$this, 'doCrontable']);
    }

    public function getCrontasks($loadFlie = false)
    {
        HandlerParser::getFileRecursive(__APP__ . '/Crontasks', $cronFiles);
        if (empty($cronFiles)) {
            return [];
        }
        $suffix  = 'Crontask.php';
        $logFile = $this->_config['output'] ? basename($this->_config['output']) : 'crontask.log';
        // 加载已保存的任务
        if ($loadFlie && file_exists($this->_config['saveTaskFile'])) {
            $this->tasks = (array) Yaml::parseFile($this->_config['saveTaskFile']);
        }
        $uniq = [];
        foreach ($cronFiles as $file) {
            if (empty(strpos($file, $suffix))) {
                continue;
            }
            $e = 'Annual\App\Crontasks\\' . str_replace('.php', '', basename($file));
            try {
                $ref = new \ReflectionClass($e);
                if (!$ref->implementsInterface(Crontask::class)) {
                    throw new \Exception("Class [{$e}] must implement Crontask interface");
                }
                // 接口启用状态
                if (!$e::enabled()) {
                    continue;
                }
                // 任务id
                $id = md5($file);
                // 记录任务更新的任务列表，用于过滤无效任务
                $uniq[] = $id;
                // 已经停止的任务，不再重新解析
                if (isset($this->tasks[$id]) &&
                    (
                        $this->tasks[$id]['state'] == Crontask::CRONTASK_STOP ||
                        ($this->tasks[$id]['state'] == Crontask::CRONTASK_ERROE &&
                            $this->tasks[$id]['errCount'] >= $this->_config['allowErrCount'])
                    )
                ) {
                    continue;
                }
                // 任务时间格式
                $format = $e::getFormat();
                $ret    = HandlerParser::parseCrontab($format);
                if (empty($ret)) {
                    throw new \Exception(HandlerParser::$error);
                }
                // 获取任务类型
                $task = $e::run();
                switch (gettype($task)) {
                    case 'string':
                        $execType = Crontask::CRONTASK_TYPE_SHELL;
                        break;
                    case 'object':
                        if ($task instanceof \Closure) {
                            $execType = Crontask::CRONTASK_TYPE_CLOSURE;
                            break;
                        }
                    default:
                        throw new \Exception("Task [{$e}::run] must be an executable shell or \Closure!");
                }
                // 任务执行次数
                $execTimes = (int) $e::getExecTimes();
                // 添加任务
                TickTable::setTask($ret, [
                    'id'       => $id,
                    'task'     => $task,
                    'execType' => $execType, // 任务类型
                ]);

                if (array_key_exists($id, $this->tasks)) {
                    $this->tasks[$id]['execType']  = $execType;
                    $this->tasks[$id]['format']    = $format;
                    $this->tasks[$id]['execTimes'] = $execTimes;
                    continue;
                }
                // 添加新任务
                $this->tasks[$id] = [
                    'name'                 => $e,
                    'format'               => $format,
                    'execTimes'            => $execTimes,
                    'addTime'              => time(),
                    'execType'             => $execType,
                    'totalExecTimes'       => 0,
                    'curExecTimes'         => 0,
                    'lastExecTimeDuration' => 0,
                    'lastStartTime'        => 0,
                    'lastEndTime'          => 0,
                    'errCount'             => 0,
                    'state'                => Crontask::CRONTASK_STAND,
                ];
            } catch (\Exception $e) {
                Helper::log($e->getMessage(), 'warn', $logFile);
                continue;
            }
        }

        // 过滤无效的任务
        if (!empty($this->tasks) && count($uniq) != count($this->tasks)) {
            foreach ($this->tasks as $id => $task) {
                if (!in_array($id, $uniq)) {
                    unset($this->tasks[$id]);
                }
            }
        }
        // 数据持久化
        $this->saveCrontasks();
    }

    private function saveCrontasks()
    {
        $dir = dirname($this->_config['saveTaskFile']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents(
            $this->_config['saveTaskFile'],
            Yaml::dump($this->tasks)
        );
    }

    public function doCrontable()
    {
        $tasks = TickTable::getTask();
        if (empty($tasks)) {
            return false;
        }

        foreach ($tasks as $task) {
            if (!isset($this->tasks[$task['id']])) {
                continue;
            }
            $curTask = &$this->tasks[$task['id']];
            if ($curTask['state'] == Crontask::CRONTASK_STOP ||
                ($curTask['state'] == Crontask::CRONTASK_ERROE &&
                    $curTask['errCount'] >= $this->_config['allowErrCount'])
            ) {
                continue;
            }

            if ($curTask['execTimes'] &&
                $curTask['curExecTimes'] >= $curTask['execTimes']
            ) {
                continue;
            }
            ++$curTask['totalExecTimes'];
            ++$curTask['curExecTimes'];

            $curTask['lastStartTime'] = microtime(true);
            $curTask['state']         = Crontask::CRONTASK_RUNING;
            if ($task['execType']) {
                $this->execInBackground($task['task'], $task['id']);
            } else {
                Helper::task($task['task'], [$this, 'onFinish'], [$task['id']]);
            }
        }
    }

    private function execInBackground($cmd, $id)
    {
        $output = $this->_config['output'] ? ' >> ' . $this->_config['output'] : '';
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B " . $cmd . $output, "r"));
        } else {
            exec($cmd . ($output ?: '> /dev/null') . " &");
        }
        $this->onFinish('', $id);
    }

    public function onFinish($result, $id)
    {
        if (!isset($this->tasks[$id])) {
            return;
        }
        $now     = microtime(true);
        $curTask = &$this->tasks[$id];
        // 更新最后执行时间
        $curTask['lastEndTime'] = $now;
        // 计算任务执行时长
        $curTask['lastExecTimeDuration'] = $now - $curTask['lastStartTime'];
        // 任务有返回值，则标记为执行错误
        if ($result !== "null") {
            $curTask['state'] = Crontask::CRONTASK_ERROE;
            ++$curTask['errCount'];
            return;
        }
        // 更新任务状态
        if ($curTask['execTimes'] &&
            $curTask['curExecTimes'] >= $curTask['execTimes']
        ) {
            $curTask['state'] = Crontask::CRONTASK_STOP;
        } else {
            $curTask['state'] = Crontask::CRONTASK_STAND;
        }
    }
}
