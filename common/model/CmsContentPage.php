<?php

namespace tpext\cms\common\model;

use think\Model;

class CmsContentPage extends Model
{
    protected $autoWriteTimestamp = 'datetime';

    public function template()
    {
        return $this->belongsTo(CmsTemplate::class, 'template_id', 'id');
    }
}
