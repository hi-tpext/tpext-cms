CREATE TABLE IF NOT EXISTS `__PREFIX__cms_channel`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '栏目ID',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '类目名称',
  `full_name` varchar(55) NOT NULL DEFAULT '' COMMENT '完整类目名称',
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '上级ID',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `dir` varchar(255) NOT NULL DEFAULT '' COMMENT 'url目录',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '栏目类型',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接',
  `deep` tinyint(2) unsigned NOT NULL DEFAULT 0 COMMENT '层级',
  `path` varchar(55) NOT NULL DEFAULT '' COMMENT '层级路径',
  `is_show` tinyint(1) unsigned DEFAULT 1 COMMENT '是否显示',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='文章栏目';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_content`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '文章ID',
  `title` varchar(55) NOT NULL DEFAULT '' COMMENT '文章名称',
  `channel_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '所属分类',
  `author` varchar(32) NOT NULL DEFAULT '' COMMENT '作者',
  `source` varchar(32) NOT NULL DEFAULT '' COMMENT '来源',
  `is_recommend` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '推荐',
  `is_hot` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '热门',
  `is_top` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '置顶',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '文章标签',
  `keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '关键字',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `attachment` varchar(255) NOT NULL DEFAULT '' COMMENT '附件',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '摘要',
  `publish_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '发布时间',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `is_show` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否显示',
  `click` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '点击量',
  `create_user` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '添加人',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='文章';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_content_detail`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '内容ID',
  `main_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '文章ID',
  `content` text DEFAULT NULL COMMENT '文章内容',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='文章详情';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_position`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '位置ID',
  `name` varchar(55) NOT NULL COMMENT '类目名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '类型',
  `is_show` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否显示',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `start_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '开始时间',
  `end_time` datetime NOT NULL DEFAULT '2030-01-01 00:00:00' COMMENT '结束时间',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE = InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8 COMMENT = '文章栏目';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '轮播ID',
  `title` varchar(55) NOT NULL DEFAULT '' COMMENT '轮播名称',
  `position_id` mediumint(8) NOT NULL DEFAULT 0 COMMENT '位置',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '摘要',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '轮播图片',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `is_show` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否显示',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CMS轮播';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_tag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '标签ID',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '标签名称',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '摘要',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `is_show` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否显示',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='内容标签';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_template` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '模板名称',
  `platform` varchar(25) NOT NULL DEFAULT '' COMMENT '平台类型',
  `view_path` varchar(25) NOT NULL DEFAULT '' COMMENT '模板基础路径',
  `prefix` varchar(25) NOT NULL DEFAULT '' COMMENT '生成路径前缀',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `is_open` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否启用',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='模板';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_content_page` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `to_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '栏目/内容ID',
  `template_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '模板ID',
  `html_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '页面ID',
  `html_type` varchar(25) NOT NULL DEFAULT '' COMMENT '页面类型',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  KEY `cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='内容模板页';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_template_html` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '页面ID',
  `template_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '模板ID',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '页面名称',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '页面路径',
  `key` varchar(55) NOT NULL DEFAULT '' COMMENT 'key',
  `type` varchar(25) NOT NULL DEFAULT '' COMMENT '页面类型',
  `ext` varchar(25) NOT NULL DEFAULT '' COMMENT '后缀',
  `size` varchar(25) NOT NULL DEFAULT '' COMMENT '大小',
  `is_default` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否为默认模板',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `filectime` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '创建时间',
  `filemtime` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '编辑时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='模板页面';

INSERT INTO `tp_cms_template` (`id`, `name`, `platform`, `view_path`, `prefix`, `tepm_prefix`, `description`, `sort`, `is_open`, `create_time`, `update_time`) VALUES
(1, 'default', 'pc', 'default', '/', '', 'pc端默认模板', 5, 1, '2022-09-16 14:53:08', '2022-09-16 15:03:24'),
(2, 'mobile', 'mobile', 'mobile', '/m/', '', '手机端默认模板', 10, 1, '2022-09-16 14:53:29', '2022-09-16 15:03:45');