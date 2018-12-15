<?php
namespace Annual\App\Tasks;

use Annual\Kernel\BaseHandler;
use Annual\Kernel\Conf;
use Annual\Kernel\Helper;

class DefaultTask extends BaseHandler
{
    public function MainAction()
    {
        var_dump($this->query->all());
        return Helper::toResult('hello #' . Conf::getCurrentWorker());
    }
}
