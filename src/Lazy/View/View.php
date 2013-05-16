<?php
namespace Lazy\View;

use Lazy\View\Exception\Exception;
use Lazy\Util\String;


class View
{
    protected $path;
    protected $extension = 'php';
    protected $layout = false;
    protected $template;
    protected $variables = [];
    protected $helpers = [];
    protected $config = [];

    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            if (method_exists($this, $name)) {
                $this->{$name}($value);
            } else {
                $this->config[$name] = $value;
            }
        }
    }

    public function __call($method, $args)
    {
        if (!isset($this->helpers[$method])) {
            $className = String::camelize(true, $method);

            # find from helper class
            $helperClassName = __NAMESPACE__ . '\\Helper\\' . $className;

            if (!class_exists($helperClassName)) { // find from custom helper
                $helperFile = $this->path . '/helpers/' . $className . '.php';
                if (file_exists($helperFile)) {
                    require_once $helperFile;
                }
            }

            if (class_exists($helperClassName)) {
                $helperInstance = new $helperClassName($this);

                if (isset($this->config['helpers'][$method])) {
                    foreach ($this->config['helpers'][$method] as $k => $v) {
                        if (method_exists($helperInstance, $k)) {
                            $helperInstance->{$k}($v);
                        }
                    }
                }

                $this->helpers[$method] = $helperInstance;
            }
        }

        if (isset($this->helpers[$method])) {
            $helper = $this->helpers[$method];
            if ($helper instanceof \Closure) {
                $callable = $helper;
            } else {
                $callable = [$helper, $method];
            }
            return call_user_func_array($callable, $args);
        }

        throw new Exception("Call undefined method $method");
    }

    public function path($path = null)
    {
        if (!func_num_args()) {
            return $this->path;
        }
        $this->path = $path;
        return $this;
    }

    public function layout($layout = null)
    {
        if (!func_num_args()) {
            return $this->layout;
        }

        $this->layout = $layout;
        return $this;
    }

    public function template($template = null)
    {
        if (!func_num_args()) {
            return $this->template;
        }

        $this->template = $template;
        return $this;
    }

    public function variables($name = null, $value = null)
    {
        switch (func_num_args()) {
            case 0: return $this->variables;

            case 1:
                if (is_array($name)) {
                    $this->variables = array_merge($this->variables, $name);
                    return $this;
                }

                return $this->variables[$name];

            default:
                $this->variables[$name] = $value;
                return $this;
        }
    }

    protected function _render($templateFile)
    {
        if (!file_exists($templateFile)) {
            throw new Exception("View file not found: $templateFile");
        }

        $errorReporting = error_reporting();
        error_reporting(E_ALL);

        extract($this->variables);
        ob_start();
        include $templateFile;
        $result = ob_get_clean();

        error_reporting($errorReporting);

        return $result;
    }

    public function render($template = null, $variables = null)
    {
        if (is_array($template)) {
            $variables = $template;
            $template = null;
        }

        !$variables || $this->variables($variables);
        !$template || $this->template($template);

        $templateFile = $this->template;
        !$this->path || $templateFile = $this->path . '/' . $templateFile;

        if (!pathinfo($templateFile, PATHINFO_EXTENSION)) {
            $templateFile .= '.' . $this->extension;
        }

        $content = $this->_render($templateFile);

        if ($this->layout) {
            $this->helpers['content'] = function() use ($content) {
                return $content;
            };

            $layoutFile = $this->layout;
            if (!pathinfo($layoutFile, PATHINFO_EXTENSION)) {
                $layoutFile .= '.' . $this->extension;
            }

            $content = $this->_render($layoutFile);
        }
        return $content;
    }

    public function display($template = null, $variables = null)
    {
        echo $this->render($template, $variables);
    }
}