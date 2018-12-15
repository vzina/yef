<?php
namespace Annual\Kernel\Contracts;

interface SocketTimer
{
    /**
     * 设定定时器间隔时间
     *
     * @return int 定时器时间，单位秒
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function getInterval();

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
     * @param  PHPSocketIO\SocketIO $io [description]
     * @return null
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public function run($io);
}
