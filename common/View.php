<?php

namespace tpext\cms\common;

use think\Template;
use tpext\think\App;
use tpext\common\ExtLoader;

class View
{
    protected static $shareVars = [];
    protected $vars = [];
    protected $content = null;
    protected $isContent = false;
    /**
     * 原始数据
     * @var mixed
     */
    protected $data = [];

    protected $app;

    /**
     * Undocumented variable
     *
     * @var Template
     */
    protected $engine;

    public function __construct($data = '', $vars = [], $config = [])
    {
        $this->data = $data;
        $this->vars = $vars;

        $config = array_merge([
            'taglib_build_in' => '\\tpext\\cms\\common\\taglib\\Cms,cx',
            'taglib_pre_load' => '\\tpext\\cms\\common\\taglib\\Cms',
            'tpl_deny_func_list' => 'eval,echo,exit,exec,shell_exec',
            'tpl_deny_php' => true,
            'cache_prefix' => 'tpex.tcms',
            'tpl_begin' => '{',
            'tpl_end' => '}',
            'taglib_begin' => '{',
            'taglib_end' => '}',
            'cache_path' => App::getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR,
        ], $config);


        if (ExtLoader::isTP51()) {
            $this->app = app();
            $this->engine = new Template($this->app, $config);
        } else {
            $this->engine = new Template($config);
        }
    }

    /**
     * 获取输出数据
     * @access public
     * @return string
     */
    public function getContent()
    {
        if (null == $this->content) {
            $this->content = $this->fetch($this->data) ?: '';
        }

        return $this->content;
    }

    public function isContent($content = true)
    {
        $this->isContent = $content;
        return $this;
    }

    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->vars = array_merge($this->vars, $name);
        } else {
            $this->vars[$name] = $value;
        }

        return $this;
    }

    public static function share($name, $value = '')
    {
        if (is_array($name)) {
            self::$shareVars = array_merge(self::$shareVars, $name);
        } else {
            self::$shareVars[$name] = $value;
        }

        if (class_exists('\\think\\facade\\View')) {
            \think\facade\View::assign($name, $value);
        }
    }

    public function clear()
    {
        self::$shareVars  = [];
        $this->data = [];
        $this->vars = [];

        return $this;
    }

    protected function fetch($template = '')
    {
        ob_start();

        if (PHP_VERSION > 8.0) {
            ob_implicit_flush(false);
        } else {
            ob_implicit_flush(0);
        }

        $vars = array_merge(self::$shareVars, $this->vars);

        try {
            if ($this->isContent) {
                $this->engine->display($template, $vars);
            } else {
                $this->engine->fetch($template, $vars);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $content = ob_get_clean();

        return $content;
    }

    public function __toString()
    {
        return $this->getContent();
    }
}
