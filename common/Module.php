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

namespace tpext\cms\common;

use tpext\common\Module as baseModule;
use tpext\cms\common\model\CmsTemplate;
use tpext\think\App;

class Module extends baseModule
{
    protected $version = '1.0.1';

    protected $name = 'tpext.cms';

    protected $title = 'tpext cms框架';

    protected $description = '内容管理功能';

    protected $root = __DIR__ . '/../';

    /**
     * 后台菜单
     *
     * @var array
     */
    protected $menus = [
        [
            'title' => '内容管理',
            'sort' => 1,
            'url' => '#',
            'icon' => 'mdi mdi-library-books',
            'children' => [
                [
                    'title' => '内容管理',
                    'sort' => 1,
                    'url' => '/admin/cmscontent/index',
                    'icon' => 'mdi mdi-book-open'
                ],
                [
                    'title' => '栏目管理',
                    'sort' => 2,
                    'url' => '/admin/cmschannel/index',
                    'icon' => 'mdi mdi-file-tree'
                ],
                [
                    'title' => '模板管理',
                    'sort' => 3,
                    'url' => '/admin/cmstemplate/index',
                    'icon' => 'mdi mdi-json'
                ],
                [
                    'title' => '广告位置',
                    'sort' => 4,
                    'url' => '/admin/cmsposition/index',
                    'icon' => 'mdi mdi-bullhorn'
                ],
                [
                    'title' => '广告管理',
                    'sort' => 5,
                    'url' => '/admin/cmsbanner/index',
                    'icon' => 'mdi mdi-image-multiple'
                ],
                [
                    'title' => '标签管理',
                    'sort' => 6,
                    'url' => '/admin/cmstag/index',
                    'icon' => 'mdi mdi-tag-outline'
                ]
            ]
        ]
    ];

    protected $modules = [
        'admin' => ['cmschannel', 'cmscontent', 'cmsposition', 'cmsbanner', 'cmstag', 'cmstemplate', 'cmstemplatehtml', 'cmstemplatestatic', 'cmstemplatemake'],
    ];

    public function install()
    {
        $success = parent::install();

        if ($success) {
            $view_path = App::getRootPath();

            try {
                CmsTemplate::initPath($view_path . 'theme/default');
                CmsTemplate::initPath($view_path . 'theme/mobile');
            } catch (\Throwable $e) {
                trace($e->__toString());
            }
        }

        return $success;
    }
}
