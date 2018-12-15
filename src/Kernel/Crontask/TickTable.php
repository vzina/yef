<?php
namespace Annual\Kernel\Crontask;

class TickTable extends \SplHeap
{
    private static $Instance;
    public static function getInstance()
    {
        if (!self::$Instance) {
            self::$Instance = new self();
        }
        return self::$Instance;
    }

    protected function compare($v1, $v2)
    {
        if ($v1["tick"] === $v2["tick"]) {
            return 0;
        }
        return $v1["tick"] < $v2["tick"] ? 1 : -1;
    }

    public static function setTask($secList, $task)
    {
        $time = time();
        foreach ($secList as $sec) {
            if ($sec > 60) {
                self::getInstance()->insert(["tick" => $sec, "task" => $task]);
            } else {
                self::getInstance()->insert(["tick" => $time + $sec, "task" => $task]);
            }
        }
    }

    public static function getTask()
    {
        $time  = time();
        $ticks = [];
        while (self::getInstance()->valid()) {
            $data = self::getInstance()->extract();
            if ($data["tick"] > $time) {
                self::getInstance()->insert($data);
                break;
            } else {
                $ticks[] = $data["task"];
            }
        }
        return $ticks;
    }
}
