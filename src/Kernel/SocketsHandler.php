<?php
namespace Annual\Kernel;

class SocketsHandler extends BaseHandler
{
    protected function event($e)
    {
        $this->_response->event = $e;
        return $this;
    }
}
