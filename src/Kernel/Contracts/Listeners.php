<?php
namespace Annual\Kernel\Contracts;

interface Listeners
{
    // 进程启动
    const EVENT_KERNEL_WORKERSTART = 'kernel.WorkerStart';
    // 请求开始
    const EVENT_HTTP_BEFORE = 'http.Before';
    // 执行方法前
    const EVENT_HTTP_METHOD_BEFORE = 'http.MethodBefore';
    // 响应数据前
    const EVENT_HTTP_AFTER = 'http.After';

    // 请求开始
    const EVENT_SOCKETIO_BEFORE = 'socketio.Before';
    // 解析数据前
    const EVENT_SOCKETIO_BEFORE_DECODE = 'socketio.BeforeDecode';
    // 执行方法前
    const EVENT_SOCKETIO_METHOD_BEFORE = 'socketio.MethodBefore';

    // 请求开始
    const EVENT_TASK_BEFORE = 'task.Before';
    // 执行方法前
    const EVENT_TASK_METHOD_BEFORE = 'task.MethodBefore';

    /**
     * 获取监听事件
     *
     * @return string|array 监听事件名
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function getName();

    /**
     * 是否启用
     *
     * @return bool
     * @author Yewj <yeweijian@3k.com> 2018-12-15
     */
    public function enabled();


    /**
     * 执行入口
     *
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function run();
}
