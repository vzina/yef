<?php
namespace Annual\Kernel\Contracts;

interface Crontask
{
    const CRONTASK_STAND  = 0;
    const CRONTASK_RUNING = 1;
    const CRONTASK_STOP   = 2;
    const CRONTASK_ERROE  = 3;

    const CRONTASK_TYPE_CLOSURE = 0; // 闭包任务
    const CRONTASK_TYPE_SHELL   = 1; // shell命令任务

    /**
     * 执行入口
     *
     * @param  PHPSocketIO\SocketIO $io [description]
     * @return \Closure
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public static function run();

    /**
     * 任务执行时间格式
     *
     * @return string
     * @author Yewj <yeweijian@3k.com> 2018-12-13
     */
    public static function getFormat();

    /**
     * 排他数量，如果已经有这么多任务在执行，即使到了下一次执行时间，也不执行
     *
     * @return int
     * @author Yewj <yeweijian@3k.com> 2018-12-13
     */
    public static function getExecTimes();

    /**
     * 是否启用
     *
     * @return bool
     * @author Yewj <yeweijian@3k.com> 2018-12-15
     */
    public static function enabled();
}
