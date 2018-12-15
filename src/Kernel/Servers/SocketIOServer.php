<?php
namespace Annual\Kernel\Servers;

use Annual\Kernel\BaseHandler;
use Annual\Kernel\Conf;
use Annual\Kernel\Contracts\Servers;
use Annual\Kernel\Contracts\SocketTimer;
use Annual\Kernel\Events;
use Annual\Kernel\HandlerParser;
use Annual\Kernel\Helper;
use Annual\Kernel\Server;
use PHPSocketIO\SocketIO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Workerman\Lib\Timer;
use Workerman\Protocols\Http;
use Workerman\Worker;

class SocketIOServer extends Worker implements Servers
{
    private $io;
    private $events;
    private $_config = [];

    public static function start($config)
    {
        $config = array_merge([
            'address'       => '127.0.0.1:8880',
            'httpEnable'    => true,
            'httpAddress'   => '127.0.0.1:8881',
            'user'          => '',
            'group'         => '',
            'origins'       => '',
            'name'          => 'SocketIO',
            'jsonpCallback' => 'callback',
        ], $config);
        $server          = new self("SocketIO://" . $config['address']);
        $server->name    = $config['name'];
        $server->user    = $config['user'];
        $server->group   = $config['group'];
        $server->_config = $config;
        $io              = new SocketIO();
        $io->attach($server);
        $io->on('connection', [$server, 'onConnection']);
        $io->on('workerStart', [$server, 'onStart']);
        if ($config['origins']) {
            $io->origins($config['origins']);
        }
        $server->io = $io;
    }

    public function onStart($worker)
    {
        Server::setConf();
        Server::setListeners();
        Conf::setCurrentWorker(Conf::WORKER_SOCKETIO_FLAG);
        Events::emit('kernel.WorkerStart');
        if ($this->_config['httpEnable']) {
            $http            = new self("http://" . $this->_config['httpAddress']);
            $http->onMessage = [$this, 'onMessage'];
            $http->listen();
        }
        $this->setSocketTimers();

        $this->events = HandlerParser::parseEvent(__APP__ . '/Sockets');
    }

    private function setSocketTimers()
    {
        HandlerParser::getFileRecursive(__APP__ . '/SocketTimers', $timerFiles);
        if (empty($timerFiles)) {
            return;
        }
        $suffix = 'Timer.php';
        $uniq   = [];
        foreach ($timerFiles as $file) {
            if (empty(strpos($file, $suffix))) {
                continue;
            }
            $e = 'Annual\App\SocketTimers\\' . str_replace('.php', '', basename($file));
            try {
                $ref = new \ReflectionClass($e);
                if (!$ref->implementsInterface(SocketTimer::class)) {
                    throw new \Exception("Class [{$e}] must implement SocketTimer interface");
                }
                $tObj     = $ref->newInstance();
                // 接口启用状态
                if (!$tObj->enabled()) {
                    continue;
                }
                $interval = (int) $tObj->getInterval();
                if (in_array($interval, $uniq)) {
                    throw new \Exception("Class [{$e}] repeatedly defining the timer!");
                }
                // 执行定时推送任务
                Timer::add($interval,
                    [$tObj, 'run'],
                    [$this->io]
                );
                $uniq[] = $interval;
            } catch (\Exception $e) {
                Helper::log($e->getMessage(), 'warn', $logFile);
                continue;
            }
        }
    }

    /**
     * SocketIO回调方法
     *
     * @param  PHPSocketIO\SocketIO $socket
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function onConnection($socket)
    {
        $request         = Request::createFromGlobals();
        $request->socket = $socket;
        $response        = JsonResponse::create();
        try {
            Events::emit('socketio.Before', [$request, $socket]);
            foreach ($this->events as $e => $ms) {
                // 执行回调方法
                $eObj = new $e($request, $response);
                foreach ($ms as $m => $event) {
                    $socket->on($event, function ($msg) use (
                        $eObj, $m, $response, $socket, $request, $event
                    ) {
                        try {
                            $response->event = $request->event = $event;
                            Events::emit('socketio.BeforeDecode', [$request, &$msg]);
                            $msgArr = json_decode($msg, true);
                            if (JSON_ERROR_NONE != json_last_error()) {
                                throw new \Exception('data decode err!', 40000);
                            }
                            if (!empty($msgArr['event_name'])) {
                                // 回调前端事件
                                $response->event = $msgArr['event_name'];
                            }
                            Events::emit('socketio.MethodBefore', [$request, &$msgArr]);
                            $ret = $eObj->$m(empty($msgArr['data']) ? null : $msgArr['data']);
                            if (empty($ret)) {
                                return;
                            }
                        } catch (\Exception $e) {
                            $ret = Server::handlerException($e);
                        }

                        if (!($ret instanceof JsonResponse)) {
                            $response->setData($ret);
                        }
                        $socket->emit($response->event, $response->getContent());
                    });
                }
            }
        } catch (\Exception $e) {
            Helper::logException($e, 'error');
            return;
        }
    }

    /**
     * http 回调方法
     * @param  Workerman\Connection\TcpConnection $connection
     * @param  string $data
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function onMessage($connection, $data)
    {
        try {
            $response = JsonResponse::create();
            Events::emit('http.Before', [$connection]);
            $request = Request::createFromGlobals();
            // 设置请求连接对象
            $request->io = $this->io;
            // 设置jsonp回调
            if ($jsonp = $request->query->get($this->_config['jsonpCallback'])) {
                $response->setCallback($jsonp);
            }

            $uri = $request->getPathInfo();
            $ext = pathinfo($uri, PATHINFO_EXTENSION);
            if (!empty($ext) && !in_array(strtolower($ext), ['php', 'do'])) {
                $connection->send("");
                return;
            }
            if (empty($ext) || $ext == 'do') {
                list($handler, $method) = HandlerParser::parseController($uri);
                if (!class_exists($handler)) {
                    throw new \Exception("API handler [{$handler}] not found!", 40003);
                } else {
                    $obj = new \ReflectionClass($handler);
                    if ($obj->isSubclassOf(BaseHandler::class) &&
                        $obj->hasMethod($method)
                    ) {
                        $request->handlers = [$handler, $method];
                        Events::emit('http.MethodBefore', [$request, $connection]);
                        $ret = $obj->newInstanceArgs([$request, $response])
                            ->$method();
                    } else {
                        throw new \Exception("API method [{$handler}::{$method}] not found!", 40004);
                    }
                }
            } else {
                $handlersPath = __ROOT__ . '/Public';
                if (file_exists($handlersPath . $uri)) {
                    ob_start();
                    include $handlersPath . $uri;
                    $ret = ob_get_clean();
                    $ret = Helper::toResult($ret);
                } else {
                    throw new \Exception("URI [{$uri}] not found!", 40005);
                }
            }
            Events::emit('http.After', [ & $ret]);
        } catch (\Exception $e) {
            $ret = Server::handlerException($e);
        }

        if (!($ret instanceof JsonResponse)) {
            $response->setData($ret);
        }

        Http::header("Content-Type:application/json");
        $connection->send($response->getContent());
    }
}
