<?php
namespace Annual\App\Api;

use Annual\App\Models\LoginModel;
use Annual\Kernel\BaseHandler;
use Annual\Kernel\Helper;

class LoginApi extends BaseHandler
{
    /**
     * @author Yewj <yeweijian@3k.com> 2018-12-12
     */
    public function MainAction()
    {
        return Helper::success(
            LoginModel::setLogin($this->query->get('uid')));
    }
}
