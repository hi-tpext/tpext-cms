<?php

namespace tpext\cms\common\model;

use think\Model;

class EmptyData extends Model
{
    public function __get($name)
    {
        if ($name == '__not_found__') {
            return true;
        }
        return '';
    }

    public function offsetExists($name)
    {
        return true;
    }

    public function offsetGet($name)
    {
        if ($name == '__not_found__') {
            return true;
        }
        return '';
    }
}
