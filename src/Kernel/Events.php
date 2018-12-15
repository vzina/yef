<?php
namespace Annual\Kernel;

class Events
{
    protected static $listeners = [];

    public static function on($event, callable $listener)
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        self::$listeners[$event][] = $listener;
    }

    public static function once($event, callable $listener)
    {
        $onceListener = function () use (&$onceListener, $event, $listener) {
            self::removeListener($event, $onceListener);
            \call_user_func_array($listener, \func_get_args());
        };
        self::on($event, $onceListener);
    }

    public static function removeListener($event, callable $listener)
    {
        if (isset(self::$listeners[$event])) {
            $index = \array_search($listener, self::$listeners[$event], true);
            if (false !== $index) {
                unset(self::$listeners[$event][$index]);
                if (\count(self::$listeners[$event]) === 0) {
                    unset(self::$listeners[$event]);
                }
            }
        }
    }

    public static function removeAllListeners($event = null)
    {
        if ($event !== null) {
            unset(self::$listeners[$event]);
        } else {
            self::$listeners = [];
        }
    }

    public static function listeners($event)
    {
        return isset(self::$listeners[$event]) ? self::$listeners[$event] : [];
    }

    public static function emit($event, array $arguments = [])
    {
        foreach (self::listeners($event) as $listener) {
            \call_user_func_array($listener, $arguments);
        }
    }
}
