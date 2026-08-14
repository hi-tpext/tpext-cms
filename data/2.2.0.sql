ALTER TABLE `__PREFIX__cms_channel`
	ADD COLUMN `extend_table` VARCHAR(55) NOT NULL DEFAULT '' COMMENT '附加表' AFTER `path`;