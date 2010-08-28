<?php

	Class extension_subsectionmanager extends Extension {

		/**
		 * Extension information
		 */
		public function about() {
			return array(
				'name' => 'Subsection Manager',
				'type' => 'Field, Interface',
				'version' => '1.0.1',
				'release-date' => '2010-08-28',
				'author' => array(
					'name' => 'Nils Hörrmann',
					'website' => 'http://nilshoerrmann.de',
					'email' => 'post@nilshoerrmann.de'
				),
				'description' => 'Subsection Management for Symphony.',
				'compatibility' => array(
					'2.0' => false,
					'2.1.0' => true
				)
			);
		}

		/**
		 * Add callback functions to backend delegates
		 */
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/administration/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => '__appendAssets'
				),
				array(
					'page' => '/publish/new/',
					'delegate' => 'EntryPostNew',
					'callback' => '__saveSortOrder'
				),
				array(
					'page' => '/publish/edit/',
					'delegate' => 'EntryPostEdit',
					'callback' => '__saveSortOrder'
				),
				array(
					'page' => '/publish/',
					'delegate' => 'Delete',
					'callback' => '__deleteSortOrder'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'AppendPageAlert', 
					'callback' => '__upgradeMediathek'
				)
			);
		}

		/**
		 * Append assets to the page head
		 *
		 * @param object $context
 		 */
 		public function __appendAssets($context) {

			// Do not use Administration::instance() in this context, see:
			// http://github.com/nilshoerrmann/subsectionmanager/issues#issue/27
			$callback = $this->_Parent->getPageCallback();

			// Append javascript for field settings pane
			if($callback['driver'] == 'blueprintssections' && is_array($callback['context'])) {
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/settings.subsectionmanager.js', 100, false);
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/settings.subsectionmanager.css', 'screen', 101, false);
			}

			// Append styles and javascript for mediasection display
			if($callback['driver'] == 'publish' && ($callback['context']['page'] == 'edit' || $callback['context']['page'] == 'new')) {
					Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/symphony.subsection.css', 'screen', 101, false);
			}
			
		}

		/**
		 * Save sort order
		 *
		 * @param object $context
		 */
		public function __saveSortOrder($context) {
		
			if(!is_null($context['fields']['sort_order'])) {
			
				// Delete current sort order
				$entry_id = $context['entry']->_fields['id'];
				Administration::instance()->Database->query(
					"DELETE FROM `tbl_fields_subsectionmanager_sorting` WHERE `entry_id` = '$entry_id'"
				);
				
				// Add new sort order
				foreach($context['fields']['sort_order'] as $field_id => $value) {
					$entries = explode(',', $value);
					$order = array();
					foreach($entries as $entry) {
						$order[] = intval($entry);
					}
					Administration::instance()->Database->query(
						"INSERT INTO `tbl_fields_subsectionmanager_sorting` (`entry_id`, `field_id`, `order`) VALUES ('$entry_id', '$field_id', '" . implode(',', $order) . "')"
					);
				}
				
			}
			
		}

		/**
		 * Delete sort order of the field
		 *
		 * @param object $context
		 */
		public function __deleteSortOrder($context) {
			// DELEGATE NOT WORKING:
			// http://github.com/symphony/symphony-2/issues#issue/108
		}
		
		/**
		 * Upgrade Mediathek fields to make use of this extension
		 */
		public function __upgradeMediathek() {

			// Do not use Administration::instance() in this context, see:
			// http://github.com/nilshoerrmann/subsectionmanager/issues#issue/27
			$callback = $this->_Parent->getPageCallback();

			// Append upgrade notice
			if($callback['driver'] == 'systemextensions') {
			
				require_once(TOOLKIT . '/class.extensionmanager.php');
				$ExtensionManager = new ExtensionManager(Administration::instance());

				// Check if Mediathek field is installed
				$mediathek = $ExtensionManager->fetchStatus('mediathek');
				if($mediathek == EXTENSION_ENABLED || $mediathek == EXTENSION_DISABLED) {
				
					// Append upgrade notice to page
					Administration::instance()->Page->Alert = new Alert(
						__('You are using Mediathek and Subsection Manager simultaneously.') . ' <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/">' . __('Upgrade') . '?</a> <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/uninstall/mediathek">' . __('Uninstall Mediathek') . '</a> <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/uninstall/subsectionmanager">' . __('Uninstall Subsection Manager') . '</a>', 
						Alert::ERROR
					);
					
				}
				
			}
			
		}

		/**
		 * Function to be executed on uninstallation
		 */
		public function uninstall() {
		
			// Drop related entries from stage table
			Administration::instance()->Database->query("DELETE FROM `tbl_fields_stage` WHERE `context` = 'subsectionmanager'");
		
			// Drop database table
			Administration::instance()->Database->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager`");
			Administration::instance()->Database->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");
			
		}

		/**
		 * Function to be executed if the extension has been updated
		 *
		 * @param string $previousVersion - version number of the currently installed extension build
		 * @return boolean - true on success, false otherwise
		 */
		public function update($previousVersion) {
		
			// Update beta installs
			if(version_compare($previousVersion, '1.0.0', '<')) {
				
				// Install missing tables
				$this->install();
				
				// Check if context column exists
				$columns = Administration::instance()->Database->fetch("SHOW COLUMNS FROM `tbl_fields_stage`");
				$context = false;
				foreach($columns as $column) {
					if($column['Field'] == 'context') {
						$context = true;
					}
				}

				// Add context row and return status
				if(!$context) {
					Administration::instance()->Database->query(
						"ALTER TABLE `tbl_fields_stage` ADD `context` varchar(255) default NULL"
					);
				}
				
				return true;
				
			}
			
		}

		/**
		 * Function to be executed on installation.
		 *
		 * @return boolean - true on success, false otherwise
		 */
		public function install() {
		
			// Create database field table
			$fields = Administration::instance()->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectionmanager` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`field_id` int(11) unsigned NOT NULL,
					`subsection_id` VARCHAR(255) NOT NULL,
					`filter_tags` text,
					`caption` text,
					`included_fields` text,
					`allow_multiple` tinyint(1) default '0',
					`show_preview` tinyint(1) default '0',
			  		PRIMARY KEY  (`id`),
			  		KEY `field_id` (`field_id`)
				)"
			);
			
			// Create database sorting table
			$sorting = Administration::instance()->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectionmanager_sorting` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`entry_id` int(11) NOT NULL,
					`field_id` int(11) NOT NULL,
					`order` text,
					PRIMARY KEY (`id`)
				)"
			);
			
			// Create database stage table
			$stage = Administration::instance()->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_stage` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `field_id` int(11) unsigned NOT NULL default '0',
				  `constructable` smallint(1) default '0',
				  `destructable` smallint(1) default '0',
				  `draggable` smallint(1) default '0',
				  `droppable` smallint(1) default '0',
				  `searchable` smallint(1) default '0',
				  `context` varchar(255) default NULL,
				  PRIMARY KEY  (`id`)
				) TYPE=MyISAM;"
			);
			
			// Return status
			if($fields && $sorting && $stage) {
				return true;
			}
			else {
				return false;
			}
			
		}

	}