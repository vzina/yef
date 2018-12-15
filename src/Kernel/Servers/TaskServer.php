<?php
namespace Annual\Kernel\Servers;

use Annual\Kernel\BaseHandler;
use Annual\Kernel\Conf;
use Annual\Kernel\Contracts\Servers;
use Annual\Kernel\Events;
use Annual\Kernel\HandlerParser;
use Annual\Kernel\Helper;
use Annual\Kernel\Server;
use SuperClosure\Serializer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Workerman\Worker;

class TaskServer extends Worker implements Servers
{
    public static function start($config)
    {
        $config = array_merge([
            'address'   => '127.0.0.1:8882',
            'workerNum' => 2,
            'user'      => '',
            'group'     => '',
            'origins'   => '',
            'name'      => 'Task-Server',
        ], $config);
        $server = new self('text://' . $config['address']);
        // 进程名
        $server->count         = $config['workerNum'];
        $server->name          = $config['name'];
        $server->user          = $config['user'];
        $server->group         = $config['user'];
        $server->onMessage     = [$server, 'onMessage'];
        $server->onWorkerStart = [$server, 'onStart'];
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
        try {
            $ret      = Helper::toResult('', 10000, 'fail');
            $response = JsonResponse::create();
            Events::emit('task.Before', [ & $data]);
            $tData = json_decode($data, true);
            if (isset($tData['type']) && $tData['type'] == 'task') {
                $task = (new Serializer())->unserialize(base64_decode($tData['data']));
                $ret  = $task($response);
            } else {
                $_GET    = $tData;
                $request = Request::createFromGlobals();

                list($handler, $method) = HandlerParser::parseTask($tData);
                if (!class_exists($handler)) {
                    throw new \Exception("Task handler [{$handler}] not found!", 40003);
                } else {
                    $obj = new \ReflectionClass($handler);
                    if ($obj->isSubclassOf(BaseHandler::class) &&
                        $obj->hasMethod($method)
                    ) {
                        Events::emit('task.MethodBefore', [$request, $connection]);
                        $ret = $obj->newInstanceArgs([$request, $response])
                            ->$method();
                    } else {
                        throw new \Exception("Task method [{$handler}::{$method}] not found!", 40004);
                    }
                }
            }
        } catch (\Exception $e) {
            $ret = Server::handlerException($e);
        }
        if (!($ret instanceof JsonResponse)) {
            $response->setData($ret);
        }
        $connection->send($response->getContent());
    }

    public function onStart($worker)
    {
        Server::setConf();
        Server::setListeners();
        Conf::setCurrentWorker(Conf::WORKER_TASK_FLAG);
        Events::emit('kernel.WorkerStart');
    }
}
