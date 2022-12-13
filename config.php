<?php

return [
    'name' => 'Tpext CMS系统',
    'description' => 'Tpext CMS系统',
    'copyright' => 'Copyright &copy; 2020. <a target="_blank" href="#">Tpext CMS系统</a> All rights reserved.',
    'assets_ver' => '1.0',
    'editor' => 'wangEditor',
    //配置描述
    '__config__' => [
        'name' => ['type' => 'text', 'label' => '名称'],
        'description' => ['type' => 'textarea', 'label' => '描述'],
        'copyright' => ['type' => 'textarea', 'label' => '版权'],
        'assets_ver' => ['type' => 'text', 'label' => '静态资源版本号', 'size' => [2, 4]],
        'editor' => ['type' => 'radio', 'label' => '编辑器', 'options' => ['wangEditor' => 'wangEditor', 'ueditor' => 'UEditor', 'ckeditor' => 'CKEditor', 'mdeditor' => 'MDEditor', 'tinymce' => 'Tinymce']],
    ],
];
