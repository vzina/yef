<?php
namespace Annual\App\SocketTimers;

use Annual\Kernel\Contracts\SocketTimer;
use Annual\Kernel\Helper;

class AutoSayHiTimer implements SocketTimer
{
    public function run($io)
    {
        return;
        $io->emit('chat message from server', Helper::success('hello'));
    }

    public function getInterval()
    {
        return 10;
    }

    public function enabled()
    {
        return false;
    }

}
