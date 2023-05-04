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
use tpext\think\App;
use tpext\cms\common\DirFilter;

class CmsTemplateHtml extends Model
{
    protected $name = 'cms_template_html';
    protected $autoWriteTimestamp = 'datetime';

    protected static function init()
    {
        /**是否为tp5**/
        if (method_exists(static::class, 'event')) {

            self::afterDelete(function ($data) {
                return self::onAfterDelete($data);
            });
        }
    }

    public static function onAfterDelete($data)
    {
        $file = App::getRootPath() . $data['path'];
        if (is_file($file)) {
            @copy($file, $file .  date('YmdHis') . '.del');
            @unlink($file);
        }
    }

    /**
     * 扫描模板页面
     *
     * @param int $templateId
     * @param string $templatePath
     * @param array $exts
     * @return void
     */
    public static function scanPageFiles($templateId, $templatePath)
    {
        if (!is_dir($templatePath)) {
            return;
        }

        $templatePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $templatePath);

        //排除静态资源文件夹
        $excludDirs = ['uploads', 'upload', 'static', 'assets', 'css', 'js', 'images', 'fonts', 'font', 'img', 'lib', 'node_modules', 'components', 'dist', 'release', 'cache', 'runtime'];

        $dirIterator = new \RecursiveDirectoryIterator($templatePath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
        $filterIterator = new DirFilter($dirIterator, $excludDirs);
        $iterator = new \RecursiveIteratorIterator($filterIterator);

        foreach ($iterator as $file) {

            $ext = $file->getExtension();

            if (is_dir($file) || !in_array($ext, ['html'])) {
                continue;
            }

            $path = str_replace('\\', '/', str_replace(App::getRootPath(), '', $file->getPathname()));

            if ($ext == 'html') {
                $key = md5(strtolower($path));

                $page = static::where('template_id', $templateId)->where('key', $key)->find();

                $isDefault = preg_match('/theme\/[\w\-]+?\/(content|channel)\/default\.html$/i', $path) || preg_match('/theme\/[\w\-]+?\/index.html$/i', $path);

                if (!$page) {
                    $page = new static;
                    $type = 'single';
                    $description = '单页';
                    if (stripos($path, 'channel') !== false) {
                        $type = 'channel';
                        $description = '栏目页' . ($isDefault ? '[默认模板]' : '');
                    } else if (stripos($path, 'content') !== false) {
                        $type = 'content';
                        $description = '详情页' . ($isDefault ? '[默认模板]' : '');
                    } else if (stripos($path, 'common') !== false) {
                        $type = 'common';
                        $description = '公共模板';
                    } else if (preg_match('/theme\/[\w\-]+?\/index.html$/i', $path)) {
                        $type = 'index';
                        $description = '首页';
                    }

                    $page->save([
                        'name' => pathinfo($path, PATHINFO_FILENAME),
                        'path' => $path,
                        'key' => $key,
                        'template_id' => $templateId,
                        'description' => $description,
                        'type' => $type,
                        'ext' => $ext,
                        'filectime' => date('Y-m-d H:i:s', filectime($file->getPathname())),
                        'filemtime' => date('Y-m-d H:i:s', filemtime($file->getPathname())),
                        'size' => round(filesize($file->getPathname()) / 1024, 2),
                        'is_default' => $isDefault,
                    ]);
                } else {
                    $page->save([
                        'path' => $path,
                        'size' => round(filesize($file->getPathname()) / 1024, 2),
                        'update_time' => date('Y-m-d H:i:s'),
                        'filectime' => date('Y-m-d H:i:s', filectime($file->getPathname())),
                        'filemtime' => date('Y-m-d H:i:s', filemtime($file->getPathname())),
                        'is_default' => $isDefault,
                    ]);
                }
            }
        }
    }

    /**
     * 扫描静态资源
     *
     * @param int $templateId
     * @param string $templatePath
     * @param array $exts
     * @return array
     */
    public static function scanStaticFiles($templateId, $templatePath)
    {
        if (!is_dir($templatePath)) {
            return [];
        }

        $templatePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $templatePath);

        //排除静态资源文件夹
        $excludDirs = ['images', 'fonts', 'font', 'img', 'lib', 'node_modules', 'components', 'dist', 'release', 'cache', 'runtime'];

        $dirIterator = new \RecursiveDirectoryIterator($templatePath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS);
        $filterIterator = new DirFilter($dirIterator, $excludDirs);
        $iterator = new \RecursiveIteratorIterator($filterIterator);

        $files = [];

        foreach ($iterator as $file) {

            $ext = $file->getExtension();

            if (is_dir($file) || !in_array($ext, ['js', 'css'])) {
                continue;
            }

            $path = str_replace('\\', '/', str_replace(App::getRootPath(), '', $file->getPathname()));

            $files[] = [
                'name' => pathinfo($path, PATHINFO_FILENAME),
                'path' => $path,
                'template_id' => $templateId,
                'type' => $ext,
                'ext' => $ext,
                'filectime' => date('Y-m-d H:i:s', filectime($file->getPathname())),
                'filemtime' => date('Y-m-d H:i:s', filemtime($file->getPathname())),
                'size' => round(filesize($file->getPathname()) / 1024, 2),
            ];
        }

        return $files;
    }
}
