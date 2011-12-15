<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * Subsection Manager Extension
	 */

	Class extension_subsectionmanager extends Extension {

		/**
		 * Public instance of EntryManager
		 */
		public static $entryManager = null;

		/**
		 * Storage for subsection entries
		 */
		public static $storage = array(
			'fields' => array(),
			'entries' => array(),
			'elements' => array()
		);

		/**
		 * Private flag set when cache is to be updated
		 */
		private static $updateCache;

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#__construct
		 */
		public function __construct(Array $args){
			parent::__construct($args);

			// Include Stage
			if(!class_exists('Stage')) {
				try {
					if((include_once(EXTENSIONS . '/subsectionmanager/lib/stage/class.stage.php')) === FALSE) {
						throw new Exception();
					}
				}
				catch(Exception $e) {
					throw new SymphonyErrorPage(__('Please make sure that the Stage submodule is initialised and available at %s.', array('<code>' . EXTENSIONS . '/subsectionmanager/lib/stage/</code>')) . '<br/><br/>' . __('It\'s available at %s.', array('<a href="https://github.com/nilshoerrmann/stage">github.com/nilshoerrmann/stage</a>')), __('Stage not found'));
				}
			}

			self::$updateCache = false;
			if(!isset(self::$storage['cache']) && file_exists(MANIFEST . '/subsectionmanager-storage')) {
				self::$storage = unserialize(file_get_contents(MANIFEST . '/subsectionmanager-storage'));
			}

			// Initialise Entry Manager
			if(empty(self::$entryManager)) {
				require_once(TOOLKIT . '/class.entrymanager.php');
				self::$entryManager = new EntryManager(Symphony::Engine());
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#about
		 */
		public function about() {
			return array(
				'name' => 'Subsection Manager',
				'type' => 'Field, Interface',
				'version' => '2.0alpha',
				'release-date' => false,
				'author' => array(
					'name' => 'Nils Hörrmann',
					'website' => 'http://nilshoerrmann.de',
					'email' => 'post@nilshoerrmann.de'
				),
				'description' => 'Subsection Management for Symphony.'
			);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#getSubscribedDelegates
		 */
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => '__appendAssets'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'AppendPageAlert',
					'callback' => '__upgradeMediathek'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'DataSourceEntriesBuilt',
					'callback' => '__prepareSubsection'
				),
				array(
					'page' => '/blueprints/datasources/',
					'delegate' => 'DatasourcePreDelete',
					'callback' => '__clearSubsectionCache'
				),
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

			// Update cache if Data Source was saved/created.
			if($callback['driver'] == 'blueprintsdatasources' && is_array($callback['context']) && $callback['context'][0] == 'edit') {
				if($callback['context'][2] == 'saved' || $callback['context'][2] == 'created') {
					$classname = $callback['context'][1];
					$this->__prepareSubsectionCache($classname);
				}
			}
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
		 * Fetch all subsection elements included in a data source and
		 * join modes into a single call to `appendFormattedElement()`.
		 * Cache results, so `__prepareSubsection` can use it later.
		 *
		 * We cannot use DatasourcePostCreate and DatasourcePostEdit because when they are called,
		 * Data Source class was not updated yet and Data Source Manager
		 * will be using "old" version of Data Source class (with old list of elements, rootname, etc...).
		 * @see http://symphony-cms.com/learn/api/2.2/delegates/#DatasourcePostCreate
		 * @see http://symphony-cms.com/learn/api/2.2/delegates/#DatasourcePostEdit
		 *
		 * That is why we call this function from __appendAssets().
		 */
		public function __prepareSubsectionCache($classname) {
			$datasourceManager = new DatasourceManager(Symphony::Engine());
			$handle = $datasourceManager->__getHandleFromFilename($classname);
			$existing =& $datasourceManager->create($handle, NULL, false);

			$ctx = array('datasource' => $existing);

			self::$updateCache = true;
			$this->__clearSubsectionCache($existing);
			$this->__prepareSubsection($ctx);
			self::$updateCache = false;

			file_put_contents(MANIFEST . '/subsectionmanager-storage', serialize(self::$storage));
		}

		/**
		 * Clear cache.
		 * TODO: clear cache after field is removed from section.
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/delegates/#DatasourcePreDelete
		 */
		public function __clearSubsectionCache(&$context) {
			$existing = NULL;

			// When called by Symphony
			if(is_array($context)) {
				$datasourceManager = new DatasourceManager(Symphony::Engine());
				$handle = $datasourceManager->__getHandleFromFilename(basename($context['file']));
				$existing =& $datasourceManager->create($handle, NULL, false);
			}

			// When called from __prepareSubsectionCache()
			else if(!empty(self::$updateCache)) {
				$existing = &$context;
			}

			$parent = get_parent_class($existing);

			// Default Data Source
			if($parent == 'DataSource') {
				unset(self::$storage['elements'][$existing->dsParamROOTELEMENT]);
				$regex = '%^'.preg_quote($existing->dsParamROOTELEMENT).'(/|$)%';
				foreach(array_keys(self::$storage['fields']) as $ctx) {
					if(preg_match($regex, $ctx)) unset(self::$storage['fields'][$ctx]);
				}
			}

			// Union Data Source
			elseif($parent == 'UnionDatasource') {
				foreach($existing->datasources as $datasource) {
					unset(self::$storage['elements'][$datasource['datasource']->dsParamROOTELEMENT]);
					$regex = '%^'.preg_quote($datasource['datasource']->dsParamROOTELEMENT).'(/|$)%';
					foreach(array_keys(self::$storage['fields']) as $ctx) {
						if(preg_match($regex, $ctx)) unset(self::$storage['fields'][$ctx]);
					}
				}
			}

			if(empty(self::$updateCache)) {
				file_put_contents(MANIFEST . '/subsectionmanager-storage', serialize(self::$storage));
			}
		}

		/**
		 * Fetch all subsection elements included in a data source and
		 * join modes into a single call to `appendFormattedElement()`.
		 * Preprocess all subsection entry for performance reasons.
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/delegates/#DataSourceEntriesBuilt
		 */
		public function __prepareSubsection(&$context) {
			$parent = get_parent_class($context['datasource']);

			// Initialise Entry Manager
			if(empty(self::$entryManager)) {
				self::$entryManager = new EntryManager(Symphony::Engine());
			}

			// Default Data Source
			if($parent == 'DataSource') {
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
			$updateCache = false;
			if(isset($datasource)) {
				if(is_numeric($datasource)) {
					$section = $datasource;
				}
				else if(is_object($datasource)) {
					// Try cached version first
					$updateCache = self::$updateCache;
					if(empty($updateCache) && isset(self::$storage['elements'][$context])) {
						$datasource->dsParamINCLUDEDELEMENTS = self::$storage['elements'][$context];
						return;
					}

					if(method_exists($datasource, 'getSource')) {
						$section = $datasource->getSource();
					}
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
						if($remainder == 'formatted' || $remainder == 'unformatted' || $reminder == 'increment' || empty($remainder)) {
							$this->__fetchFields($section, $context, $subsection, $field, $remainder);
						}
						else {
							$subsection_id = $this->__fetchFields($section, $context, $subsection, $field, "{$context}/{$subsection}");
							$this->__parseSubsectionFields(array($field . ': ' . $remainder), "{$context}/{$subsection}", $subsection_id);
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

			// Update cache
			if($updateCache) {
				if(is_array($datasource->dsParamINCLUDEDELEMENTS)) {
					self::$storage['elements'][$context] = $datasource->dsParamINCLUDEDELEMENTS;
				}
				else {
					unset(self::$storage['elements'][$context]);
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
					LIMIT 1)
				UNION
				(SELECT t1.`subsection_id`, t1.field_id
					FROM `tbl_fields_subsectiontabs` AS t1
					INNER JOIN `tbl_fields` AS t2
					WHERE t2.`element_name` = '{$subsection}'
					{$section}
					AND t1.`field_id` = t2.`id`
					LIMIT 1)
				LIMIT 1"
			);

			// Get subfield id
			$subfield_id = self::$entryManager->fieldManager->fetchFieldIDFromElementName($field, $id[0]['subsection_id']);

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
					$subsection_id = self::$entryManager->fetchEntrySectionID($entry_id[0]);

					// Fetch entries
					$entries = self::$entryManager->fetch($entry_id, $subsection_id);

					if(!empty($entries)) {
						foreach($entries as $entry) {
							self::$storage['entries'][$entry->get('id')] = $entry;
						}
					}
				}
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#install
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
					`included_fields` text,
					`allow_multiple` tinyint(1) default '0',
					`show_preview` tinyint(1) default '0',
					`lock` tinyint(1) DEFAULT '0',
					`recursion_levels` tinyint DEFAULT '0',
					PRIMARY KEY	 (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);

			// Create Subsection Tabs
			$status[] = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectiontabs` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`field_id` int(11) unsigned NOT NULL,
					`subsection_id` varchar(255) NOT NULL,
					`static_tabs` varchar(255) DEFAULT NULL,
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#update
		 */
		public function update($previousVersion) {
			$status = array();

			if(version_compare($previousVersion, '1.0', '<')) {

				// Install missing tables
				$this->install();

				// Add context row and return status
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'context'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_stage` ADD `context` varchar(255) default NULL"
					);
				}

			}

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '1.1', '<')) {

				// Add droptext column
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'droptext'") == false) {
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
							$status[] = Symphony::Database()->query("
								INSERT INTO tbl_fields_stage_sorting (`entry_id`, `field_id`, `order`, `context`) 
								VALUES (" . $sorting['entry_id'] . ", " . $sorting['field_id'] . ", '" . $sorting['order'] . "', 'subsectionmanager')
							");
						}
					}

					// Drop old sorting table
					$status[] = Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");
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
						$associations[] = array(
							'parent_section_id' => $parent_section_id,
							'parent_section_field_id' => $id,
							'child_section_id' => $child_section_id,
							'child_section_field_id' => $id,
							'hide_association' => 'yes',
						);
					}
				}
				if(!empty($associations)) {
					foreach ($associations as $association) {
						$status[] = Symphony::Database()->insert($association, 'tbl_sections_association');
					}
				}
			}

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '1.2', '<')) {

				// Add lock column
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'lock'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD `lock` tinyint(1) DEFAULT '0'"
					);
				}
			}

		/*-----------------------------------------------------------------------*/

			if(version_compare($previousVersion, '2.0', '<')) {

				// Add subsection tabs
				$status[] = Symphony::Database()->query(
					"CREATE TABLE IF NOT EXISTS `tbl_fields_subsectiontabs` (
						`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						`field_id` int(11) unsigned NOT NULL,
						`subsection_id` varchar(255) NOT NULL,
						`static_tabs` varchar(255) DEFAULT NULL,
						PRIMARY KEY (`id`),
						KEY `field_id` (`field_id`)
					)"
				);

				// Remove dynamic tabs available in early development versions
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectiontabs` LIKE 'allow_dynamic_tabs'") == true) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectiontabs` DROP `allow_dynamic_tabs`"
					);
				}

			/*-------------------------------------------------------------------*/

				// Add recursion levels
				if((boolean)Symphony::Database()->fetchVar('Field', 0, "SHOW COLUMNS FROM `tbl_fields_subsectionmanager` LIKE 'recursion_levels'") == false) {
					$status[] = Symphony::Database()->query(
						"ALTER TABLE `tbl_fields_subsectionmanager` ADD COLUMN `recursion_levels` tinyint DEFAULT '0'"
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

			// Report status
			if(in_array(false, $status, true)) {
				return false;
			}
			else {
				return true;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/extension/#uninstall
		 */
		public function uninstall() {

			// Drop related entries from stage tables
			Symphony::Database()->query("DELETE FROM `tbl_fields_stage` WHERE `context` = 'subsectionmanager'");

			// Drop tables
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager`");
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectiontabs`");

			// Maintenance
			Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_fields_subsectionmanager_sorting`");
			Symphony::Database()->query("DELETE FROM `tbl_fields_stage_sorting` WHERE `context` = 'subsectionmanager'");
		}

	}
