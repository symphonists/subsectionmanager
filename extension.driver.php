<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * Subsection Manager Extension
	 */
	Class extension_subsectionmanager extends Extension {

		/**
		 * Storage for subsection entries
		 */
		public static $storage = array(
			'fields' => array(),
			'entries' => array()
		);

		/**
		 * Private flag set when cache is to be updated
		 */
		private static $updateCache = true;

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/extension/#__construct
		 */
		public function __construct(Array $args){
			parent::__construct($args);

			// Prepare cache
			if(file_exists(CACHE . '/subsectionmanager-storage')) {

				// If Data Source files have not changed, get cache
				if(filemtime(DATASOURCES) < filemtime(CACHE . '/subsectionmanager-storage')) {
					$cache = unserialize(file_get_contents(CACHE . '/subsectionmanager-storage'));
				}

				// Check store cache
				if(!empty($cache)) {
					self::$storage['fields'] = $cache['fields'];
					self::$updateCache = false;
				}
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/extension/#getSubscribedDelegates
		 */
		public function getSubscribedDelegates(){
			return array(

				// Subsection Manager
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => '__appendAssets'
				),
				array(
					'page' => '/blueprints/datasources/',
					'delegate' => 'DatasourcePostCreate',
					'callback' => '__clearSubsectionCache'
				),
				array(
					'page' => '/blueprints/datasources/',
					'delegate' => 'DatasourcePostEdit',
					'callback' => '__clearSubsectionCache'
				),
				array(
					'page' => '/blueprints/datasources/',
					'delegate' => 'DatasourcePreDelete',
					'callback' => '__clearSubsectionCache'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'DataSourceEntriesBuilt',
					'callback' => '__prepareSubsection'
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
		 * Clear cache.
		 *
		 * @see http://symphony-cms.com/learn/api/2.3/delegates/#DatasourcePreDelete
		 */
		public function __clearSubsectionCache() {
			if(file_exists(CACHE . '/subsectionmanager-storage')) {
				General::deleteFile(CACHE . '/subsectionmanager-storage');
			}
			self::$updateCache = true;
		}

		/**
		 * Fetch all subsection elements included in a data source and
		 * join modes into a single call to `appendFormattedElement()`.
		 * Preprocess all subsection entry for performance reasons.
		 *
		 * @see http://symphony-cms.com/learn/api/2.3/delegates/#DataSourceEntriesBuilt
		 */
		public function __prepareSubsection(&$context) {
			$parent = get_parent_class($context['datasource']);

			// Default Data Source
			if($parent == 'DataSource' || $parent == 'SectionDatasource') {
				$this->__parseSubsectionFields(
					$context['datasource']->dsParamINCLUDEDELEMENTS,
					$context['datasource']->dsParamROOTELEMENT,
					$context['datasource']
				);
			}

			// Union Data Source
			elseif($parent == 'UnionDatasource') {
				foreach($context['datasource']->datasources as $datasource) {
					$this->__parseSubsectionFields(
						$datasource['datasource']->dsParamINCLUDEDELEMENTS,
						$datasource['datasource']->dsParamROOTELEMENT,
						$datasource['datasource']
					);
				}
			}

			// Update field cache
			if(self::$updateCache == true) {
				$cache = self::$storage;
				unset($cache['entries']);
				General::writeFile(CACHE . '/subsectionmanager-storage', serialize($cache), Symphony::Configuration()->get('write_mode', 'file'));
			}

			// Preload entries
			self::preloadSubsectionEntries($context['entries']['records']);
		}

		/**
		 * Parse data source and extract subsection fields
		 *
		 * @param DataSource $datasource
		 *	The data source class to parse
		 */
		private function __parseSubsectionFields($fields, $context, $datasource = null) {

			// Get source
			$section = 0;
			if(is_numeric($datasource)) {
				$section = $datasource;
			}
			elseif(is_object($datasource)) {
				if(method_exists($datasource, 'getSource')) {
					$section = $datasource->getSource();
				}
			}

			// Parse included elements
			if(!empty($fields)) {
				foreach($fields as $index => $included) {
					list($subsection, $field, $remainder) = explode(': ', $included, 3);

					// TODO: depending on hardcoded mode names is highly error-prone. We should check each field type and
					//       work only on fields that we know about, i.e., SubsectionManager and SubsectionTabs.

					// Fetch fields
					if($field != 'formatted' && $field != 'unformatted' && $field != 'increment' && !empty($field)) {

						// Get field id and mode
						if(!isset(self::$storage['fields'][$context]) || self::$updateCache == true) {
							if($remainder == 'formatted' || $remainder == 'unformatted' || $reminder == 'increment' || empty($remainder)) {
								$this->__fetchFields($section, $context, $subsection, $field, $remainder);
							}
							else {
								$subsection_id = $this->__fetchFields($section, $context, $subsection, $field, "{$context}/{$subsection}");
								$this->__parseSubsectionFields(array($field . ': ' . $remainder), "{$context}/{$subsection}", $subsection_id);
							}

							// Make sure fields will be stored
							self::$updateCache = true;
						}

						// Set a single field call for subsection fields
						if(is_object($datasource)) {
							unset($datasource->dsParamINCLUDEDELEMENTS[$index]);

							$storage = $subsection . ': ' . $context;
							if(!in_array($storage, $datasource->dsParamINCLUDEDELEMENTS)) {
								$datasource->dsParamINCLUDEDELEMENTS[$index] = $storage;
							}
						}
					}
				}
			}
		}

		private function __fetchFields($section, $context, $subsection, $field, $mode = '') {

			// Section context
			if($section !== 0) {
				$section = " AND t2.`parent_section` = '".intval($section)."' ";
			}
			else {
				$section = '';
			}

			$subsection = Symphony::Database()->cleanValue($subsection);

			// Get id
			$id = Symphony::Database()->fetch(
				"(SELECT t1.`subsection_id`, t1.field_id
					FROM `tbl_fields_subsectionmanager` AS t1
					INNER JOIN `tbl_fields` AS t2
					WHERE t2.`element_name` = '{$subsection}'
					{$section}
					AND t1.`field_id` = t2.`id`
					LIMIT 1)"
			);

			// Get subfield id
			$subfield_id = FieldManager::fetchFieldIDFromElementName($field, $id[0]['subsection_id']);

			// Store field data
			$field_id = $id[0]['field_id'];
			if(!is_array(self::$storage['fields'][$context][$field_id][$subfield_id])) {
				self::storeSubsectionFields($context, $field_id, $subfield_id, $mode);
			}

			return $id[0]['subsection_id'];
		}

		/**
		 * Store subsection fields
		 *
		 * @param integer $field_id
		 *	The subsection field id
		 * @param integer $subfield_id
		 *	The subsection field subfield id
		 * @param string $mode
		 *	Subfield mode, e. g. 'formatted' or 'unformatted'
		 */
		public static function storeSubsectionFields($context, $field_id, $subfield_id, $mode) {
			if(!empty($context) && !empty($field_id) && !empty($subfield_id)) {
				self::$storage['fields'][$context][$field_id][$subfield_id][] = $mode;
			}
		}

		/**
		 * Preload subsection entries
		 *
		 * @param Array $parents
		 *	Array of entry objects
		 */
		public static function preloadSubsectionEntries($parents) {
			if(empty($parents) || !is_array($parents)) return;

			// Get parent data
			$fields = array();
			foreach($parents as $entry) {
				$data = $entry->getData();

				// Get relation id
				foreach($data as $field => $settings) {
					if(isset($settings['relation_id'])) {
						if(!is_array($settings['relation_id'])) $settings['relation_id'] = array($settings['relation_id']);

						foreach($settings['relation_id'] as $relation_id) {
							if(empty($relation_id)) continue;
							$fields[$field][] = $relation_id;
						}
					}
				}
			}

			// Store entries
			foreach($fields as $field => $relation_id) {

				// Check for already loaded entries
				$entry_id = array_diff($relation_id, array_keys(self::$storage['entries']));

				// Load new entries
				if(!empty($entry_id)) {

					// Get subsection id
					$subsection_id = EntryManager::fetchEntrySectionID($entry_id[0]);

					// Fetch entries
					$entries = EntryManager::fetch($entry_id, $subsection_id);

					if(!empty($entries)) {
						foreach($entries as $entry) {
							self::$storage['entries'][$entry->get('id')] = $entry;
						}
					}
				}
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/extension/#install
		 */
		public function install() {
			$status = array();

			// Create Subsection Manager
			$status[] = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectionmanager` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`field_id` int(11) unsigned NOT NULL,
					`subsection_id` VARCHAR(255) NOT NULL,
					`filter_tags` text,
					`caption` text,
					`droptext` text,
					`create` tinyint(1) default '1',
					`remove` tinyint(1) default '1',
					`allow_multiple` tinyint(1) default '1',
					`edit` tinyint(1) default '1',
					`sort` tinyint(1) default '1',
					`drop` tinyint(1) default '1',
					`show_search` tinyint(1) default '1',
					`show_preview` tinyint(1) default '0',
					`recursion_levels` tinyint DEFAULT '0',
					PRIMARY KEY	 (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);

			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/extension/#update
		 */
		public function update($previousVersion) {
			$status = array();

			// Install missing tables
			$status[] = $this->install();

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '1.1', '<')) {

				// Rename allow multiple column
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'allow_multiple_selection'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` CHANGE COLUMN `allow_multiple_selection` `allow_multiple` tinyint(1) default '1'"
					);
				}

				// Add section associations data to sections_association table
				$field_ids = array();
				$associations = array();
				$field_ids = Symphony::Database()->fetchCol('field_id', "
					SELECT f.field_id
					FROM `tbl_fields_subsectionmanager` AS f
				");
				if(!empty($field_ids)) {
					foreach($field_ids as $id) {
						$parent_section_id = Symphony::Database()->fetchVar('parent_section', 0, "
							SELECT f.parent_section
							FROM `tbl_fields` AS f
							WHERE f.id = '{$id}'
							LIMIT 1
						");
						$child_section_id = Symphony::Database()->fetchVar('subsection_id', 0, "
							SELECT f.subsection_id
							FROM `tbl_fields_subsectionmanager` AS f
							WHERE f.field_id = '{$id}'
							LIMIT 1
						");
						if(!empty($parent_section_id) && !empty($child_section_id)) {
							$associations[] = array(
								'parent_section_id' => $parent_section_id,
								'parent_section_field_id' => $id,
								'child_section_id' => $child_section_id,
								'child_section_field_id' => $id,
								'hide_association' => 'yes',
							);
						}
					}
				}
				if(!empty($associations)) {
					foreach($associations as $association) {
						$status[] = Symphony::Database()->insert($association, 'tbl_sections_association');
					}
				}
			}

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '2.0', '<')) {

				// Add droptext column
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'droptext'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD `droptext` text default NULL"
					);
				}

				// Add recursion levels
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'recursion_levels'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `recursion_levels` tinyint DEFAULT '0'"
					);
				}

			/*-------------------------------------------------------------------*/

				// Remove dynamic tabs available in early development versions
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectiontabs` LIKE 'allow_dynamic_tabs'") == true) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectiontabs` DROP `allow_dynamic_tabs`"
					);
				}

				// Remove nonunique setting available in early development versions
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'allow_nonunique'") == true) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` DROP `allow_nonunique`"
					);
				}

				// Remove quantities setting available in early development versions
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'allow_quantities'") == true) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` DROP `allow_quantities`"
					);
				}
			}

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '3.0', '<')) {
			
				// Remove included fields setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'included_fields'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` DROP `included_fields`"
					);
				}
				
				// Add create setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'create'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `create` tinyint DEFAULT '1'"
					);
				}
				
				// Add remove setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'remove'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `remove` tinyint DEFAULT '1'"
					);
				}
				
				// Add edit setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'edit'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `edit` tinyint DEFAULT '1'"
					);
				}
				
				// Add sort setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'sort'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `sort` tinyint DEFAULT '1'"
					);
				}
				
				// Add drop setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'drop'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `drop` tinyint DEFAULT '1'"
					);
				}
				
				// Add search setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'show_search'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `show_search` tinyint DEFAULT '1'"
					);
				}
				
				// Adjust allow multiple setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'allow_multiple'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` MODIFY COLUMN `allow_multiple` tinyint DEFAULT '1'"
					);
				}
			
				// Drop look setting
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'lock'") == true) {
					$status[] = Symphony::Database()->query(
						"UPDATE `tbl_fields_subsectionmanager` SET `edit` = 1 WHERE `lock` = 0"
					);
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` DROP `lock"
					);
				}

			/*-------------------------------------------------------------------*/

				// Transfer old stage settings: constructable
				$settings = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM  `tbl_fields_stage` WHERE  `constructable` = 0");
				if(!empty($settings) && is_array($settings)) {
					Symphony::Database()->query("UPDATE `tbl_fields_subsectionmanager` SET `create` = 0 WHERE `field_id` IN (" . implode(',', $settings) . ")");
				}

				// Transfer old stage settings: destructable
				$settings = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM  `tbl_fields_stage` WHERE  `destructable` = 0");
				if(!empty($settings) && is_array($settings)) {
					Symphony::Database()->query("UPDATE `tbl_fields_subsectionmanager` SET `remove` = 0 WHERE `field_id` IN (" . implode(',', $settings) . ")");
				}

				// Transfer old stage settings: draggable
				$settings = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM  `tbl_fields_stage` WHERE  `draggable` = 0");
				if(!empty($settings) && is_array($settings)) {
					Symphony::Database()->query("UPDATE `tbl_fields_subsectionmanager` SET `sort` = 0 WHERE `field_id` IN (" . implode(',', $settings) . ")");
				}

				// Transfer old stage settings: droppable
				$settings = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM  `tbl_fields_stage` WHERE  `droppable` = 0");
				if(!empty($settings) && is_array($settings)) {
					Symphony::Database()->query("UPDATE `tbl_fields_subsectionmanager` SET `drop` = 0 WHERE `field_id` IN (" . implode(',', $settings) . ")");
				}

				// Transfer old stage settings: droppable
				$settings = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM  `tbl_fields_stage` WHERE  `searchable` = 0");
				if(!empty($settings) && is_array($settings)) {
					Symphony::Database()->query("UPDATE `tbl_fields_subsectionmanager` SET `show_search` = 0 WHERE `field_id` IN (" . implode(',', $settings) . ")");
				}
				
				// Remove old Stage instances
				Symphony::Database()->query("DELETE FROM `tbl_fields_stage` WHERE `context` = 'subsectionmanager'");
				Symphony::Database()->query("DELETE FROM `tbl_fields_stage_sorting` WHERE `context` = 'subsectionmanager'");
			}
			
		/*-----------------------------------------------------------------------*/

			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/extension/#uninstall
		 */
		public function uninstall() {

			// Remove old Stage tables if they are empty
			$exists = Symphony::Database()->fetch("SHOW TABLES LIKE 'tbl_fields_stage'");
			if(!empty($exists)) {
				Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_stage`");
				Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_stage_sorting`");
			}

			// Drop tables
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager`");
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectiontabs`");

			// Maintenance
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");
		}

	}
