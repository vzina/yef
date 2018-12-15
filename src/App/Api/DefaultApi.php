<?php
namespace Annual\App\Api;

use Annual\Kernel\BaseHandler;
use Annual\Kernel\Conf;
use Annual\Kernel\Helper;

class DefaultApi extends BaseHandler
{
    public function MainAction()
    {
        $fp = stream_socket_client("tcp://127.0.0.1:8882", $errno, $errstr);
        if (!$fp) {
            echo "ERROR: $errno - $errstr<br />\n";
        } else {
            fwrite($fp, '{"ct":"default","data":{"test":123}}' . "\n");
            print_r(json_decode(fread($fp, 1024), true));
            fclose($fp);
        }
        return Helper::success('test #' . Conf::getCurrentWorker());
    }
}
