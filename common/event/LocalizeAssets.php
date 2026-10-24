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

namespace tpext\cms\common\event;

use tpext\common\ExtLoader;
use tpext\cms\common\RemoteAssets;

/**
 * 远程资源本地化：文章保存前（before_write）处理内容中的远程图片/文件。
 * 挂在 before_write 上，本地化后的内容会随本次保存一并落库，
 * 其后的 after_insert/after_update（静态化等）拿到的已是本地地址，且不会二次触发。
 */
class LocalizeAssets
{
    public function watch()
    {
        ExtLoader::watch('cms_content_on_before_write', function ($data) {
            $this->contentChange($data);
        });
    }

    /**
     * @param mixed $data CmsContent模型实例
     * @return void
     */
    protected function contentChange($data)
    {
        $arrayData = $data->toArray();
        if (!isset($arrayData['content'])) {
            return;
        }
        $content = $data->getData('content');
        if (empty($content) || !is_string($content)) {
            return;
        }

        $processed = RemoteAssets::localize($content);
        if ($processed !== $content) {
            $data['content'] = $processed;
        }
    }
}
