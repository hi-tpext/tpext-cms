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

use tpext\think\App;

/**
 * 远程资源本地化：扫描富文本内容中的远程图片/文件，下载到本地并替换地址。
 * 重写后的地址是相对路径，再次处理时自然跳过（幂等）。
 */
class RemoteAssets
{
    protected static $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    protected static $fileExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'txt', 'mp3', 'mp4', 'webm'];

    protected static $maxSize = 20971520; //20M

    //测试钩子：默认拒绝内网/回环地址(SSRF防护)，本机自测时可放开
    protected static $allowPrivateIp = false;

    public static function setAllowPrivateIp($allow)
    {
        static::$allowPrivateIp = (bool) $allow;
    }

    /**
     * 处理内容
     *
     * @param string $html
     * @return string
     */
    public static function localize($html)
    {
        if (empty($html) || !is_string($html) || stripos($html, '://') === false) {
            return $html;
        }

        $pattern = '/<(img|source|video|audio|a)\b[^>]*?\b(src|href)\s*=\s*([\'"])(https?:\/\/[^\'"]+)\3/is';

        $selfHost = strtolower((string) request()->host());
        $selfHost = preg_replace('/:\d+$/', '', $selfHost);

        return preg_replace_callback($pattern, function ($m) use ($selfHost) {
            list(, $tag, $attr, $quote, $url) = $m;

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '' || $host === $selfHost) {
                return $m[0]; //本站地址或无host不处理
            }

            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (!preg_match('/^[a-z0-9]{1,5}$/', $ext)) {
                $ext = '';
            }

            $dirType = 'files';
            if ($attr == 'href') {
                //a链接只处理指向可下载文件的，页面链接跳过
                if (!in_array($ext, static::$fileExts)) {
                    return $m[0];
                }
            } else {
                if ($ext !== '') {
                    if (in_array($ext, static::$imageExts)) {
                        $dirType = 'images';
                    } elseif (!in_array($ext, static::$fileExts)) {
                        return $m[0]; //未知后缀的src不处理
                    }
                } else {
                    $dirType = 'images'; //无后缀按图片处理，下载后嗅探类型
                }
            }

            $local = static::download($url, $dirType, $ext);
            if (!$local) {
                return $m[0]; //下载失败保留原地址
            }

            return str_replace($quote . $url . $quote, $quote . $local . $quote, $m[0]);
        }, $html);
    }

    /**
     * 下载远程资源到本地
     *
     * @param string $url
     * @param string $dirType images/files
     * @param string $ext 已解析的后缀，可为空
     * @return string|false 本地相对路径
     */
    protected static function download($url, $dirType, $ext)
    {
        //SSRF防护：拒绝内网/回环/保留地址(含域名解析后命中内网的，防DNS指向内网)。
        //内容来源是文章富文本，虽由管理员触发，仍不应让服务器端随意请求内网资源
        if (!static::$allowPrivateIp) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
            if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        $context = stream_context_create([
            'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0', 'max_redirects' => 3],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $content = @file_get_contents($url, false, $context, 0, static::$maxSize);
        if ($content === false || strlen($content) < 10) {
            return false;
        }

        if ($ext === '') {
            //从内容嗅探图片类型，嗅不出放弃
            $info = @getimagesizefromstring($content);
            $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'];
            $ext = $info ? ($map[$info[2]] ?? '') : '';
            if ($ext === '') {
                return false;
            }
        }

        $sub = date('Ym');
        $dir = App::getPublicPath() . '/uploads/' . $dirType . '/' . $sub . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            return false;
        }

        $name = md5($url) . '.' . $ext;
        if (!is_file($dir . $name) && @file_put_contents($dir . $name, $content) === false) {
            return false;
        }

        return '/uploads/' . $dirType . '/' . $sub . '/' . $name;
    }
}
