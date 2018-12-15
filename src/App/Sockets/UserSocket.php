<?php
namespace Annual\App\Sockets;

use Annual\App\Models\LoginModel;
use Annual\App\Models\UserModel;
use Annual\Kernel\Helper;
use Annual\Kernel\SocketsHandler;

/**
 * @alias user
 */
class UserSocket extends SocketsHandler
{
    /**
     * @alias add
     */
    public function MainAction($data)
    {
        if(isset($this->socket->uid)){
            return Helper::end('您已登录过了！', 401);
        }
        if (empty($data['token'])) {
            return Helper::end('参数缺失，请求Token不存在！', 402);
        }
        try {
            $uid = (string) LoginModel::check($data['token']);
        } catch (\Exception $e) {
            Helper::logException($e, 'error');
            return Helper::end("Token异常", 403);
        }
        UserModel::incrUidConnectionMap($uid);
        $this->socket->join($uid);
        $this->socket->uid = $uid;
        $this->event('update_online_count');
        return Helper::success([
            'onlineCountNow'     => UserModel::lastOnlineCount(),
            'onlinePageCountNow' => UserModel::lastOnlinePageCount(),
        ]);
    }

    /**
     * @path disconnect
     *
     * @return array
     * @author Yewj <yeweijian@3k.com> 2018-12-12
     */
    public function DisconnectAction()
    {
        if(!isset($this->socket->uid)){
            return Helper::end('您未登录！', 404);
        }

        $ret = UserModel::decrUidConnectionMap($this->socket->uid);
        if ($ret <= 0) {
            UserModel::unsetUidConnectionMap($this->socket->uid);
        }
    }
}
