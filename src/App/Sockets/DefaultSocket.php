<?php
namespace Annual\App\Sockets;

use Annual\Kernel\SocketsHandler;

/**
 * @alias default
 */
class DefaultSocket extends SocketsHandler
{
    /**
     * @alias connect
     */
    public function MainAction()
    {
        return $this->with('test', "hello")->toRet();
    }
}
