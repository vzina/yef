<?php
namespace Annual\App\Models;

class UserModel
{
    // 全局数组保存uid在线数据
    private static $uidConnectionMap   = [];
    public static $LastOnlineCount     = 0;
    public static $LastOnlinePageCount = 0;

    public static function issetUidConnectionMap($uid)
    {
        return isset(self::$uidConnectionMap[$uid]);
    }

    public static function incrUidConnectionMap($uid)
    {
        if (!isset(self::$uidConnectionMap[$uid])) {
            self::$uidConnectionMap[$uid] = 0;
        }
        return ++self::$uidConnectionMap[$uid];
    }

    public static function decrUidConnectionMap($uid)
    {
        if (!isset(self::$uidConnectionMap[$uid])) {
            return 0;
        }
        return --self::$uidConnectionMap[$uid];
    }

    public static function unsetUidConnectionMap($uid)
    {
        unset(self::$uidConnectionMap[$uid]);
    }

    public static function lastOnlineCount()
    {
        return self::$LastOnlineCount = count(self::$uidConnectionMap);
    }

    public static function lastOnlinePageCount()
    {
        return self::$LastOnlinePageCount = array_sum(self::$uidConnectionMap);
    }
}
