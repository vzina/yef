<?php
namespace Annual\App\Crontasks;

use Annual\Kernel\Contracts\Crontask;

class TestCrontask implements Crontask
{
    /**
     * 执行入口
     *
     * @param  PHPSocketIO\SocketIO $io [description]
     * @return \Closure
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    public static function run()
    {
        return function () {
            echo 'test1 #@'. date('Y-m-d H:i:s') . PHP_EOL;
        };
    }

    /**
     * 任务执行时间格式
     *
     * @return string
     * @author Yewj <yeweijian@3k.com> 2018-12-13
     */
    public static function getFormat()
    {
        return '*/5 * * * * *';
    }

    /**
     * 排他数量，如果已经有这么多任务在执行，即使到了下一次执行时间，也不执行
     *
     * @return int
     * @author Yewj <yeweijian@3k.com> 2018-12-13
     */
    public static function getExecTimes()
    {
        return 2;
    }

    public static function enabled()
    {
        return false;
    }
}
