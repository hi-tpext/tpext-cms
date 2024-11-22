<?php
// +----------------------------------------------------------------------
// | tpext.cms
// +----------------------------------------------------------------------
// | Copyright (c) tpext.cms All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: lhy <ichynul@163.com>
// +----------------------------------------------------------------------

namespace tpext\cms\common\model;

use think\Model;

class CmsContentPage extends Model
{
    protected $name = 'cms_content_page';
    protected $autoWriteTimestamp = 'datetime';

    public function template()
    {
        return $this->belongsTo(CmsTemplate::class, 'template_id', 'id');
    }

    public function templateHtml()
    {
        return $this->belongsTo(CmsTemplateHtml::class, 'html_id', 'id');
    }
}
