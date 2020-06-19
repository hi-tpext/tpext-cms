<?php

namespace tpext\cms\common;

use tpext\common\Module as baseModule;

class Module extends baseModule
{
    protected $version = '1.0.1';

    protected $name = 'tpext.cms';

    protected $title = 'tpext cms框架';

    protected $description = '内容管理功能';

    protected $root = __DIR__ . '/../../';

    /**
     * 后台菜单
     *
     * @var array
     */
    protected $menus = [
        [
            'title' => '内容管理',
            'sort' => 1,
            'url' => '＃',
            'icon' => 'mdi mdi-library-books',
            'children' => [
                [
                    'title' => '栏目管理',
                    'sort' => 1,
                    'url' => '/admin/cmscategory/index',
                    'icon' => 'mdi mdi-file-tree'
                ],
                [
                    'title' => '内容管理',
                    'sort' => 2,
                    'url' => '/admin/cmscontent/index',
                    'icon' => 'mdi mdi-book-open'
                ],
                [
                    'title' => '广告位置',
                    'sort' => 3,
                    'url' => '/admin/cmsposition/index',
                    'icon' => 'mdi mdi-bullhorn'
                ],
                [
                    'title' => '广告管理',
                    'sort' => 4,
                    'url' => '/admin/cmsbanner/index',
                    'icon' => 'mdi mdi-image-multiple'
                ],
                [
                    'title' => '标签管理',
                    'sort' => 5,
                    'url' => '/admin/cmstag/index',
                    'icon' => 'mdi mdi-tag-outline'
                ]
            ]
        ]
    ];

    protected $modules = [
        'admin' => ['cmscategory', 'cmscontent', 'cmsposition', 'cmsbanner', 'cmstag'],
    ];
}
