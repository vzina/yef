<?php
namespace Annual\App\SocketTimers;

use Annual\App\Models\UserModel;
use Annual\Kernel\Contracts\SocketTimer;
use Annual\Kernel\Helper;

class LastOnlineCountTimer implements SocketTimer
{
    public function run($io)
    {
        $io->emit('update_online_count', Helper::success([
            'onlineCountNow'     => UserModel::lastOnlineCount(),
            'onlinePageCountNow' => UserModel::lastOnlinePageCount(),
        ]));
        // echo __CLASS__ . PHP_EOL;
    }

    public function getInterval()
    {
        return 2;
    }

    /**
     * 是否启用
     *
     * @return bool
     * @author Yewj <yeweijian@3k.com> 2018-12-15
     */
    public function enabled()
    {
        return false;
    }
}
