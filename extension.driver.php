<?php

	class Extension_PathedSelectBoxLinkField extends Extension{
		public function about(){
			return array(
				'name'			=> 'Field: Pathed Select Box Link',
				'version'		=> '1.0.1',
				'release-date'	=> '2010-01-14',
				'author'		=> array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://www.symphony-cms.com',
					'email'			=> 'team@symphony-cms.com'
				)
			);
		}

		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_pathedselectboxlink`");
		}

		public function install() {
			try {
				Symphony::Database()->query("
					CREATE TABLE IF NOT EXISTS `tbl_fields_pathedselectboxlink` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`field_id` int(11) unsigned NOT NULL,
						`allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
						`related_field_id` VARCHAR(255) NOT NULL,
						`limit` int(4) unsigned NOT NULL default '20',
						PRIMARY KEY  (`id`),
						KEY `field_id` (`field_id`)
					)
				");
			}
			
			catch (Exception $e) {
				return false;
			}
			
			return true;
		}
	}
	
?>