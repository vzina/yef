<?php
namespace Annual\App\Models;

use Annual\Kernel\Conf;
use Annual\Kernel\Helper;
use Firebase\JWT\JWT;

class LoginModel
{

    public static function setLogin($uid)
    {
        try {
            $newtime      = time();
            $key          = Conf::getJwtSecretKey();
            $token        = Conf::getJwtToken();
            $token['iat'] = 1538323200; // strtotime('2018-10-01'),
            $token['nbf'] = $newtime;
            $token['exp'] = $newtime + 7200;
            $token['uid'] = $uid;
            return JWT::encode($token, $key);
        } catch (\Exception $e) {
            Helper::logException($e, 'warn');
            return false;
        }
    }

    public static function check($str)
    {
        try {
            $key = Conf::getJwtSecretKey();
            $d   = JWT::decode($str, $key, ['HS256']);
            return $d->uid;
        } catch (\Exception $e) {
            Helper::logException($e, 'error');
            return false;
        }
    }
}
