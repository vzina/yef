<?php
namespace Annual\Kernel;

use Annual\Kernel\Helper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseHandler
{
    /**
     * 请求对象
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $_request;
    /**
     * 响应对象
     * @var Symfony\Component\HttpFoundation\Response
     */
    protected $_response;

    private $_data;

    final public function __construct(Request $request, Response $response)
    {
        $this->_request  = $request;
        $this->_response = $response;
        $this->init();
    }

    protected function init()
    {
        # code...
    }

    final public function end($msg, $code = 0)
    {
        Helper::end($msg, $code);
    }

    /**
     * 任务发送
     *
     * @param  \Closure $callback
     * @return $this
     * @author Yewj <yeweijian@3k.com> 2018-12-11
     */
    final protected function task(\Closure $callback)
    {
        Helper::task($callback, [$this, 'onFinish']);
        return $this;
    }

    /**
     * 数据追加方法
     *
     * @param  [type] $name  [description]
     * @param  [type] $value [description]
     * @return self
     */
    final protected function with($name, $value = null)
    {
        if (is_object($name)) {
            throw new \Exception("Assign key is object!");
        } elseif (is_array($name)) {
            $this->_data += $name;
        } elseif (is_string($name)) {
            $this->_data[$name] = $value;
        }
        return $this;
    }

    public function __set($name, $value)
    {
        $this->with($name, $value);
    }

    public function __get($name)
    {
        if (property_exists($this->_request, $name)) {
            return $this->_request->$name;
        }
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    public function __call($method, $argv)
    {
        if (strpos($method, 'with') === 0) {
            if (!empty($argv)) {
                $name = strtolower(preg_replace('/((?<=[a-z])(?=[A-Z]))/', '_', ltrim($method, 'with')));
                $this->with($name, $argv[0]);
            }
            return $this;
        }
    }

    protected function toRet($code = 0, $msg = "")
    {
        $this->_response->setData(Helper::toResult(
            $this->_data, $code, $msg
        ));
        $this->_data = [];
        return $this->_response;
    }

    protected function onFinish($taskResult)
    {

    }
}
