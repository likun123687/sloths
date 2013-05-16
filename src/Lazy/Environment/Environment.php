<?php

namespace Lazy\Environment;

use Lazy\Environment\Exception\Exception;

class Environment
{
    protected $data;

    public function __construct()
    {
        $this->data = [
            'serverVars'    => $_SERVER,
            'paramsGet'     => $_GET,
            'paramsPost'    => $_POST,
            'paramsFile'    => $_FILES,
            'paramsCookie'  => $_COOKIE,
            'params'        => $_REQUEST,
        ];
    }

    public function __call($method, $args)
    {
        if (!array_key_exists($method, $this->data)) {
            throw new Exception("Call undefined method $method");
        }

        $name = isset($args[0])? $args[0] : null;
        $value = isset($args[1])? $args[1] : null;
        $data = $this->data[$method];

        switch (count($args)) {
            case 0: return $data;
            case 1:
                if (is_array($name)) {
                    $this->data[$method] = array_merge($data, $name);
                    return $this;
                }
                if ('params' == $method) {
                    if (isset($data[$name])) {
                        return $data[$name];
                    }

                    foreach (['paramsGet', 'paramsPost', 'paramsCookie'] as $type) {
                        if (isset($this->data[$type][$name])) {
                            return $this->data[$type][$name];
                        }
                    }

                    return null;

                } else {
                    return isset($data[$name])? $data[$name] : null;
                }

            default:
                if (true === $name) {
                    $this->data[$method] = (array) $value;
                } else {
                    $this->data[$method][$name] = $value;
                }
        }
        return $this;
    }

    public function serverVar()
    {
        return call_user_func_array([$this, 'serverVars'], func_get_args());
    }

    public function paramGet()
    {
        return call_user_func_array([$this, 'paramsGet'], func_get_args());
    }

    public function paramPost()
    {
        return call_user_func_array([$this, 'paramsPost'], func_get_args());
    }

    public function paramCookie()
    {
        return call_user_func_array([$this, 'paramsCookie'], func_get_args());
    }

    public function paramFile()
    {
        return call_user_func_array([$this, 'paramsFile'], func_get_args());
    }

    public function param()
    {
        return call_user_func_array([$this, 'params'], func_get_args());
    }
}