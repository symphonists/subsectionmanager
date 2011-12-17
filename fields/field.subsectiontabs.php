<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * This field provides a tabbed subsection management.
	 */
	Class fieldSubsectiontabs extends Field {

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#__construct
		 */
		public function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Tabs');
			$this->_required = true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#mustBeUnique
		 */
		public function mustBeUnique(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#canFilter
		 */
		public function canFilter(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#canPrePopulate
		 */
		public function canPrePopulate() {
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displaySettingsPanel
		 */
		public function displaySettingsPanel(&$wrapper, $errors=NULL) {

			// Basics
			parent::displaySettingsPanel($wrapper, $errors);
			$div = new XMLElement('div', NULL, array('class' => 'group'));

			// Subsection
			$sectionManager = new SectionManager(Symphony::Engine());
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();

			// Options
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$options[] = array(
						$section->get('id'),
						($section->get('id') == $this->get('subsection_id')),
						$section->get('name')
					);
				}
			}

			$label = new XMLElement('label', __('Subsection'));
			$selection = Widget::Select('fields[' . $this->get('sortorder') . '][subsection_id]', $options);
			$label->appendChild($selection);
			$div->appendChild($label);

			// Tab names
			$label = new XMLElement('label', __('Tab names') . '<i>' . __('List of comma-separated tabs') . '</i>');
			$tabs = Widget::Input('fields['.$this->get('sortorder').'][static_tabs]', $this->get('static_tabs'));
			$label->appendChild($tabs);
			$div->appendChild($label);

			// General
			$fieldset = new XMLElement('fieldset');
			$this->appendShowColumnCheckbox($fieldset);
			$wrapper->appendChild($div);
			$wrapper->appendChild($fieldset);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#commit
		 */
		public function commit(){

			// Prepare commit
			if(!parent::commit()) return false;
			if($this->get('id') === false) return false;

			// Set up fields
			$fields = array();
			$fields['field_id'] = $this->get('id');
			$fields['subsection_id'] = $this->get('subsection_id');
			$fields['static_tabs'] = $this->get('static_tabs');

			// Delete old field settings
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '" . $this->get('id') . "' LIMIT 1"
			);

			// Save new field setting
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayPublishPanel
		 */
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL, $entry_id=NULL) {

			// Houston, we have problem: we've been called out of context!
			$callback = Administration::instance()->getPageCallback();
			if($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				return;
			}

			// Append assets
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.js', 101, false);
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.css', 'screen', 102, false);
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/resize/jquery.ba-resize.js', 105, false);

			// Store settings
			if($this->get('allow_dynamic_tabs') == 1) {
				$settings = 'allow_dynamic_tabs';
			}

			// Label
			$label = Widget::Label($this->get('label'), NULL, $settings, NULL, array('data-handle' => $this->get('element_name')));
			$wrapper->appendChild($label);

			// Container
			$container = new XMLElement('span', NULL, array('class' => 'frame'));
			$list = new XMLElement('ul');
			$container->appendChild($list);
			$label->appendChild($container);

			// Get entry ID
			$page = Symphony::Engine()->getPageCallback();
			$entry_id = $page['context']['entry_id'];

			// Get subsection name
			$subsection = Symphony::Database()->fetchVar('handle', 0,
				"SELECT `handle`
				FROM `tbl_sections`
				WHERE `id`= " . $this->get('subsection_id') . "
				LIMIT 1"
			);

			// Fetch existing tabs
			$existing_tabs = $this->__getExistingTabs($entry_id, $data);

			// Static tabs
			if($this->get('static_tabs') != '') {
				$static_tabs = preg_split('/,( )?/', $this->get('static_tabs'));

				// Create tab
				foreach($static_tabs as $tab) {

					// Existing tab
					if(array_key_exists($tab, $existing_tabs) && $existing_tabs[$tab] !== NULL) {
						$list->appendChild($this->__createTab(
							$tab,
							SYMPHONY_URL . '/publish/' . $subsection . '/edit/' . $existing_tabs[$tab],
							$existing_tabs[$tab],
							true
						));
					}

					// New tab
					else {
						$list->appendChild($this->__createTab(
							$tab,
							SYMPHONY_URL . '/publish/' . $subsection . '/new/',
							NULL,
							true
						));
					}

					// Unset
					unset($existing_tabs[$tab]);
				}
			}

			// No tabs yet
			if($this->get('static_tabs') == '' && empty($existing_tabs)) {
				$list->appendChild($this->__createTab(
					__('Untitled'),
					SYMPHONY_URL . '/publish/' . $subsection . '/new/'
				));
			}

			// Field ID
			$input = Widget::Input('field[' . $this->get('element_name') . '][new]', SYMPHONY_URL . '/publish/' . $subsection . '/new/', 'hidden');
			$wrapper->appendChild($input);

			return $wrapper;
		}

		/**
		 * Fetch names and relation ids from all existing tabs in the selected entry
		 *
		 * @param number $entry_id
		 *	The identifier of this field entry instance
		 * @return array
		 *	Returns an array with tab names and their relation ids
		 */
		private function __getExistingTabs($entry_id, $data) {
			$tabs = array();

			// Use given data
			if(is_array($data) && !empty($data)) {

				// Create relations
				for($i = 0; $i < count($data['relation_id']); $i++) {
					if(is_array($data['relation_id'])) {
						$tabs[$data['name'][$i]] = $data['relation_id'][$i];
					}
					else {
						$tabs[$data['name']] = $data['relation_id'];
					}
				}
			}

			// Load data
			elseif(isset($entry_id)) {
				$existing = Symphony::Database()->fetch(
					"SELECT `relation_id`, `name`
					FROM `tbl_entries_data_" . $this->get('id') . "`
					WHERE `entry_id` = " . $entry_id . "
					ORDER BY `id`
					LIMIT 100"
				);

				// Create relations
				foreach($existing as $tab) {
					$tabs[$tab['name']] = $tab['relation_id'];
				}
			}

			return $tabs;
		}

		/**
		 * Create the markup that is used as storage for each tab's data.
		 *
		 * @param string $name
		 *	Name of the tab
		 * @param string $link
		 *	Link to the subsection entry
		 * @param number $id
		 *	Relation id of the subsection entry
		 * @return XMLElement
		 *	Returns a list item with all attached data
		 */
		private function __createTab($name, $link, $id=NULL, $static=false) {
			$item = new XMLElement('li');

			// Static tabs
			if($static) {
				$item->setAttribute('class', 'static');
			}

			// Relation ID
			$storage = Widget::Input('fields[' . $this->get('element_name') . '][relation_id][]', $id, 'hidden');
			$item->appendChild($storage);

			// Relation ID
			$storage = Widget::Input('fields[' . $this->get('element_name') . '][name][]', trim($name), 'hidden');
			$item->appendChild($storage);

			// Link to subentry
			$link = new XMLElement('a', trim($name), array('href' => $link));
			$item->appendChild($link);

			// Return tab
			return $item;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#processRawFieldData
		 */
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
			$status = self::__OK__;
			if(empty($data)) return NULL;

			// Create handles
			if(isset($data['name'])) {
				foreach($data['name'] as $name) {
					$data['handle'][] = Lang::createHandle($name);
				}
			}

			// Delete removed tab entries
			if(is_array($data['delete'])) {
				$entryManager = new EntryManager(Symphony::Engine());
				$entryManager->delete($data['delete']);
				unset($data['delete']);
			}

			// Return processed data
			return $data;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchIncludableElements
		 */
		public function fetchIncludableElements() {
			$includable = array();

			// Fetch subsection fields
			$sectionManager = new SectionManager(Symphony::Engine());
			$section = $sectionManager->fetch($this->get('subsection_id'));
			$fields = $section->fetchFields();

			foreach($fields as $field) {
				$elements = $field->fetchIncludableElements();

				foreach($elements as $element) {
					$includable[] = $this->get('element_name') . ': ' . $element;
				}
			}

			return $includable;
		}

		/**
		 * Subsection entries are pre-processed in the extension driver and stored in
		 * extension_subsectionmanager::$storage with other helpful data. If you are building
		 * custom data sources, please use extension_subsectionmanager::storeSubsectionFields()
		 * to store subsection fields and extension_subsectionmanager::preloadSubsectionEntries()
		 * to preload subsection entries.
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#appendFormattedElement
		 */
		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $context) {

			// Prepare data
			if(!is_array($data['name'])) $data['name'] = array($data['name']);
			if(!is_array($data['handle'])) $data['handle'] = array($data['handle']);
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			// Create tabs
			$entryManager = new EntryManager(Symphony::Engine());
			$subsection = new XMLElement($this->get('element_name'));

			for($i = 0; $i < count($data['name']); $i++) {
				$name = $data['name'][$i];
				$handle= $data['handle'][$i];
				$entry_id = $data['relation_id'][$i];

				// Create item
				$item = new XMLElement('item', NULL, array('name' => $name, 'handle' => $handle));
				$subsection->appendChild($item);

				// Populate entry element
				$entry = extension_subsectionmanager::$storage['entries'][$entry_id];
				$item->setAttribute('id', $entry_id);

				// Fetch missing entries
				if(empty($entry)) {
					$entry = $entryManager->fetch($entry_id, $this->get('subsection_id'));

					// Store entry
					$entry = $entry[0];
					extension_subsectionmanager::$storage['entries'][$entry_id] = $entry;
				}

				// Process entry for Data Source
				if(!empty($entry) && !empty(extension_subsectionmanager::$storage['fields'][$context][$this->get('id')])) {
					foreach(extension_subsectionmanager::$storage['fields'][$context][$this->get('id')] as $field_id => $modes) {
						$entry_data = $entry->getData($field_id);
						$field = $entryManager->fieldManager->fetch($field_id);

						// No modes
						if(empty($modes) || empty($modes[0])) {
							$field->appendFormattedElement($item, $entry_data, $encode, $context, $entry_id);
						}

						// With modes
						else {
							foreach($modes as $mode) {
								$field->appendFormattedElement($item, $entry_data, $encode, $mode, $entry_id);
							}
						}
					}
				}
			}
			$wrapper->appendChild($subsection);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#prepareTableValue
		 */
		public function prepareTableValue($data, XMLElement $link = null) {
			$entryManager = new EntryManager(Symphony::Engine());

			// Prepare data
			if(!is_array($data['name'])) $data['name'] = array($data['name']);
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			// Get tabs
			$tabs = '';
			for($i = 0; $i < count($data['name']); $i++) {
				if($i > 0) $tabs .= ', ';
				$tabs .= $data['name'][$i];
			}

			if(!empty($tabs)) {
				$tabs =	 ' <span class="inactive">(' . $tabs . ')</span>';
			}

			// Get first title
			if(isset($data['relation_id'])) {
				$field_id = Symphony::Database()->fetchVar('id', 0,
					"SELECT `id`
					FROM `tbl_fields`
					WHERE `parent_section` = '" . $this->get('subsection_id') . "'
					ORDER BY `sortorder`
					LIMIT 1"
				);
				$entry = $entryManager->fetch($data['relation_id'][0], $this->get('subsection_id'));
				if(is_object($entry[0])){
					$title = $entry[0]->getData($field_id);
				}
			}

			// Handle empty titles
			if(empty($title['value'])) {
				$title['value'] = __('Untitled');
			}

			// Link or plain text?
			if($link) {
				$link->setValue($title['value']);
				return $link->generate() . $tabs;
			}
			else {
				return $title['value'] . $tabs;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayDatasourceFilterPanel
		 */
		public function displayDatasourceFilterPanel(XMLElement &$wrapper, $data = null, $errors = null, $fieldnamePrefix = null, $fieldnamePostfix = null) {
			$wrapper->appendChild(new XMLElement('h4', $this->get('label') . ' <i>' . $this->Name() . '</i>'));
			$label = Widget::Label(__('Name'));
			$label->appendChild(Widget::Input('fields[filter]' . ($fieldnamePrefix ? '['.$fieldnamePrefix.']' : '') . '[' . $this->get('id') . ']' . ($fieldnamePostfix ? '['.$fieldnamePostfix.']' : ''), ($data ? General::sanitize($data) : null)));
			$wrapper->appendChild($label);

			// Existing tab names
			$suggestions = Symphony::Database()->fetchCol('name',
				"SELECT DISTINCT `name`
				FROM `tbl_entries_data_" . $this->get('id') . "`
				LIMIT 100"
			);

			// Create suggestions
			if(!empty($suggestions)) {
				$tabs = new XMLElement('ul', NULL, array('class' => 'tags'));
				foreach($suggestions as $suggestion) {
					$tabs->appendChild(
						new XMLElement('li', $suggestion)
					);
				}
				$wrapper->appendChild($tabs);
			}

			// Append help for title or handle filtering
			$help = new XMLElement('p', __('Use <code>title:</code> to filter by title or handle of an attached subsection entry.'), array('class' => 'help'));
			$wrapper->appendChild($help);
		}

		/**
		 * Keep compatibility with Symphony pre 2.2.1 for a little longer.
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#buildDSRetrivalSQL
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false) {
			return $this->buildDSRetrievalSQL($data, $joins, $where, $andOperation);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#buildDSRetrievalSQL
		 */
		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->get('id');

			// Filter by regular expression
			if(self::isFilterRegex($data[0])) {

				// Get pattern and type
				if(preg_match('/^regexp:/i', $data[0])) {
					$pattern = preg_replace('/regexp:/i', null, $this->cleanValue($data[0]));
					$regex = 'REGEXP';
				}
				else {
					$pattern = preg_replace('/not-?regexp:/i', null, $this->cleanValue($data[0]));
					$regex = 'NOT REGEXP';
				}

				// Get first field
				$subfield_id = Symphony::Database()->fetchVar('id', 0,
					"SELECT `id`
					FROM `tbl_fields`
					WHERE `parent_section` = '" . $this->get('subsection_id') . "'
					AND `sortorder` = '0'
					LIMIT 1"
				);

				// Get entry ids
				$entry_id = Symphony::Database()->fetchCol('entry_id',
					"SELECT `entry_id`
					FROM `tbl_entries_data_" . $subfield_id . "`
					WHERE `handle` {$regex} '{$pattern}'"
				);

				// Query
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
				$where .= " AND `t$field_id`.relation_id IN ('" . @implode("', '", $entry_id) . "') OR `t$field_id`.name {$regex} '{$pattern}' ";
			}

			// Filter by subsection entry title or handle
			elseif(preg_match('/^title:\s*/', $data[0], $matches)) {
				$data = Lang::createHandle(trim(array_pop(explode(':', $data[0], 2))));

				// Get first field
				$subfield_id = Symphony::Database()->fetchVar('id', 0,
					"SELECT `id`
					FROM `tbl_fields`
					WHERE `parent_section` = '" . $this->get('subsection_id') . "'
					AND `sortorder` = '0'
					LIMIT 1"
				);

				// Get entry ids
				$entry_id = Symphony::Database()->fetchCol('entry_id',
					"SELECT `entry_id`
					FROM `tbl_entries_data_" . $subfield_id . "`
					WHERE `handle` = '" . $data . "'"
				);

				// Query
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
				$where .= " AND `t$field_id`.relation_id IN ('" . @implode("', '", $entry_id) . "') ";
			}

			// Filter by tab name or handle
			else {

				// Get handles
				for($i = 0; $i < count($data); $i++) {
					$data[$i] = Lang::createHandle($data[$i]);
				}

				// And
				if($andOperation) {
					foreach($data as $key => $value) {
						$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
						$where .= " AND `t$field_id$key`.handle = '". $this->cleanValue($value) ."' ";
					}
				}

				// Or
				else {
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
					$where .= " AND `t$field_id`.handle IN ('" . @implode("', '", array_map(array($this, 'cleanValue'), $data)) . "') ";
				}
			}

			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#createTable
		 */
		public function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned DEFAULT NULL,
				  `name` varchar(255) NOT NULL,
				  `handle` varchar(255) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}

	}
