<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * Subsection Manager Extension
	 */

	Class extension_subsectionmanager extends Extension {

		public function __construct(Array $args){
			parent::__construct($args);
			
			// Include Stage
			try {
				if((include_once(EXTENSIONS . '/subsectionmanager/lib/stage/class.stage.php')) === FALSE) {
					throw new Exception();
				}
			}
			catch(Exception $e) {
			    throw new SymphonyErrorPage(__('Please make sure that the Stage submodule is initialised and available at %s.', array('<code>' . EXTENSIONS . '/subsectionmanager/lib/stage/</code>')) . '<br/><br/>' . __('It\'s available at %s.', array('<a href="https://github.com/nilshoerrmann/stage">github.com/nilshoerrmann/stage</a>')), __('Stage not found'));
			}
		}

		public function about() {
			return array(
				'name' => 'Subsection Manager',
				'type' => 'Field, Interface',
				'version' => '1.1beta4',
				'release-date' => '2011-01-27',
				'author' => array(
					'name' => 'Nils Hörrmann',
					'website' => 'http://nilshoerrmann.de',
					'email' => 'post@nilshoerrmann.de'
				),
				'description' => 'Subsection Management for Symphony.'
			);
		}

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
			$callback = Symphony::Engine()->getPageCallback();
			
			// Append skripts and styles for field settings pane
			if($callback['driver'] == 'blueprintssections' && is_array($callback['context'])) {
				Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.settings.js', 100, false);
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.settings.css', 'screen', 101, false);
			}

			// Append styles for publish area
			if($callback['driver'] == 'publish' && $callback['context']['page'] == 'index') {
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.index.publish.css', 'screen', 100, false);
			}

			// Append styles for subsection display
			if($callback['driver'] == 'publish' && $callback['context']['page'] != 'index') {
				Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsection.publish.css', 'screen', 101, false);
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
				$entry_id = $context['entry']->get('id');
				Symphony::Database()->query(
					"DELETE FROM `tbl_fields_stage_sorting` WHERE `entry_id` = '$entry_id'"
				);
				
				// Add new sort order
				foreach($context['fields']['sort_order'] as $field_id => $value) {
					$entries = explode(',', $value);
					$order = array();
					foreach($entries as $entry) {
						$order[] = intval($entry);
					}
					Symphony::Database()->query(
						"INSERT INTO `tbl_fields_stage_sorting` (`entry_id`, `field_id`, `order`) VALUES ('$entry_id', '$field_id', '" . implode(',', $order) . "')"
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
					Symphony::Engine()->Page->Alert = new Alert(
						__('You are using Mediathek and Subsection Manager simultaneously.') . ' <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/">' . __('Upgrade') . '?</a> <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/uninstall/mediathek">' . __('Uninstall Mediathek') . '</a> <a href="http://' . DOMAIN . '/symphony/extension/subsectionmanager/uninstall/subsectionmanager">' . __('Uninstall Subsection Manager') . '</a>', 
						Alert::ERROR
					);
				}
			}
		}

		/**
		 * Any logic that assists this extension in being installed such as
		 * table creation, checking for dependancies etc.
		 *
		 * @see toolkit.ExtensionManager#install
		 * @return boolean
		 *  True if the install completely successfully, false otherwise
		 */
		public function install() {
			$status = array();
		
			// Create database field table
			$status[] = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectionmanager` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`field_id` int(11) unsigned NOT NULL,
					`subsection_id` VARCHAR(255) NOT NULL,
					`filter_tags` text,
					`caption` text,
					`droptext` text,
					`included_fields` text,
					`allow_multiple` tinyint(1) default '0',
					`show_preview` tinyint(1) default '0',
			  		PRIMARY KEY  (`id`),
			  		KEY `field_id` (`field_id`)
				)"
			);
			
			// Create stage
			$status[] = Stage::install();
			
			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

		/**
		 * Logic that should take place when an extension is to be been updated
		 * when a user runs the 'Enable' action from the backend. The currently
		 * installed version of this extension is provided so that it can be
		 * compared to the current version of the extension in the file system.
		 * This is commonly done using PHP's version_compare function. Common
		 * logic done by this method is to update differences between extension
		 * tables.
		 *
		 * @see toolkit.ExtensionManager#update
		 * @param string $previousVersion
		 *  The currently installed version of this extension from the
		 *  tbl_extensions table. The current version of this extension is
		 *  provided by the about() method.
		 * @return boolean
		 */
		public function update($previousVersion) {
			$status = array();
		
			// Update beta installs
			if(version_compare($previousVersion, '1.0', '<')) {
				
				// Install missing tables
				$this->install();
				
				// Add context row and return status
				$context = Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'context'");
				if(!$context) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_stage` ADD `context` varchar(255) default NULL"
					);
				}
				
			}

			// Update 1.0 installs
			if(version_compare($previousVersion, '1.1', '<')) {
			
				// Add droptext column
				$droptext = Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'droptext'");
				if(!$droptext) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD `droptext` text default NULL"
					);
				}
				
				// Create stage tables
				$status[] = Stage::install();
				
				// Fetch sort orders
				$table = Symphony::Database()->fetch("SHOW TABLES LIKE 'tbl_fields_subsectionmanager_sorting'");
				if(!empty($table)) {
					$sortings = Symphony::Database()->fetch("SELECT * FROM tbl_fields_subsectionmanager_sorting LIMIT 1000");
					
					// Move sort orders to stage table
					if(is_array($sortings)) {
						foreach($sortings as $sorting) {
							$status[] = Symphony::Database()->query(
								"INSERT INTO tbl_fields_stage_sorting (`entry_id`, `field_id`, `order`, `context`) VALUES (" . $sorting['entry_id'] . ", " . $sorting['field_id'] . ", '" . $sorting['order'] . "', 'subsectionmanager')"
							);
						}
					}
	
					// Drop old sorting table
					$status[] = Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");			
				}
			}
			
			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

		/**
		 * Any logic that should be run when an extension is to be uninstalled
		 * such as the removal of database tables.
		 *
		 * @see toolkit.ExtensionManager#uninstall
		 * @return boolean
		 */
		public function uninstall() {
		
			// Drop related entries from stage tables
			Symphony::Database()->query("DELETE FROM `tbl_fields_stage` WHERE `context` = 'subsectionmanager'");
			Symphony::Database()->query("DELETE FROM `tbl_fields_stage_sorting` WHERE `context` = 'subsectionmanager'");

			// Drop tables
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager`");
			
			// Maintenance		
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");			
		}
		
	}