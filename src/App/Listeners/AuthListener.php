<?php
namespace Annual\App\Listeners;

use Annual\App\Models\LoginModel;
use Annual\Kernel\Contracts\Listeners as BaseListeners;
use Annual\Kernel\Helper;

class AuthListener implements BaseListeners
{
    private $httpWhiteList = [
        'Annual\App\Api\LoginApi' => ['MainAction'],
    ];
    private $socketWhiteList = [
        '/user/add',
    ];

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

    public function getName()
    {
        return [
            BaseListeners::EVENT_HTTP_METHOD_BEFORE,
            BaseListeners::EVENT_SOCKETIO_METHOD_BEFORE,
        ];
    }

    public function run($request = null, $data = null)
    {
        if (isset($request->socket)) {
            $this->doSocketIOBefore($request, $data);
        } else {
            $this->doHttpBefore($request, $data);
        }
    }

    protected function doHttpBefore($request, $data)
    {
        list($api, $method) = $request->handlers;
        if (isset($this->httpWhiteList[$api]) &&
            in_array($method, $this->httpWhiteList[$api])
        ) {
            return;
        }
        $token = $request->query->get('token');
        if (!empty($token) && LoginModel::check($token)) {
            return;
        }
        Helper::end("unauthorized", 400);
    }

    protected function doSocketIOBefore($request, $data)
    {
        if ((isset($request->event) &&
            in_array($request->event, $this->socketWhiteList)) ||
            isset($request->socket->uid)
        ) {
            return;
        }
        Helper::end("unauthorized", 400);
    }
}
