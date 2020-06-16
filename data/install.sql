CREATE TABLE IF NOT EXISTS `__PREFIX__cms_category`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '栏目ID',
  `name` varchar(55) NOT NULL DEFAULT '' COMMENT '类目名称',
  `parent_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '上级ID',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '栏目类型',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接',
  `deep` tinyint(2) unsigned NOT NULL DEFAULT 0 COMMENT '链接',
  `path` varchar(55) NOT NULL DEFAULT '' COMMENT '链接',
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
  `category_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '所属分类',
  `author` varchar(32) NOT NULL DEFAULT '' COMMENT '作者',
  `source` varchar(32) NOT NULL DEFAULT '' COMMENT '来源',
  `is_recommend` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '推荐',
  `is_hot` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '热门',
  `is_top` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '置顶',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '文章标签',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` text DEFAULT NULL COMMENT '文章内容',
  `publish_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '发布时间',
  `sort` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `is_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示',
  `create_user` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '添加人',
  `create_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '添加时间',
  `update_time` datetime NOT NULL DEFAULT '2020-01-01 00:00:00' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='文章内容';

CREATE TABLE IF NOT EXISTS `__PREFIX__cms_position`  (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '位置ID',
  `name` varchar(55) NOT NULL COMMENT '类目名称',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型',
  `is_show` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示',
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
