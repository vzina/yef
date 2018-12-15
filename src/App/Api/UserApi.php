<?php
namespace Annual\App\Api;

use Annual\App\Models\LoginModel;
use Annual\App\Models\UserModel;
use Annual\Kernel\BaseHandler;
use Annual\Kernel\Helper;

class UserApi extends BaseHandler
{
    /**
     * @author Yewj <yeweijian@3k.com> 2018-12-12
     */
    public function PublishAction()
    {
        $msg = $this->query->get('content');
        $uid = $this->query->get('to');
        if (empty($uid)) {
            $this->io->emit('new_msg', $msg);
        } else{
            $this->io->to($uid)->emit('new_msg', $msg);
        }

        if ($uid && !UserModel::issetUidConnectionMap((string) $uid)) {
            return Helper::success('offline');
        } else {
            return Helper::success('success');
        }
        return Helper::success('fail');
    }
}
