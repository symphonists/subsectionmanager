<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * This field provides inline subsection management.
	 */
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');

	Class fieldSubsectionmanager extends Field {

		static $sortOrder = null;

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#__construct
		 */
		function __construct() {
			parent::__construct();
			$this->_name = __('Subsection Manager');
			$this->_required = true;
		}

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#canFilter
		 */
		function canFilter(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#isSortable
		 */
		public function isSortable(){
			return false;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#allowDatasourceOutputGrouping
		 */
		public function allowDatasourceOutputGrouping(){
			return ($this->get('allow_multiple') == 0 ? true : false);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#allowDatasourceParamOutput
		 */
		function allowDatasourceParamOutput(){
			return true;
		}

		/**
		 * Test whether this field requires grouping. If this function returns true
		 * SQL statements generated in the `EntryManager` will include the `DISTINCT` keyword
		 * to only return a single row for an entry regardless of how many 'matches' it
		 * might have. This default implementation returns false.
		 */
		public function requiresSQLGrouping(){
			return ($this->get('allow_multiple') == 1 ? true : false);
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#createTable
		 */
		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT null auto_increment,
				  `entry_id` int(11) unsigned NOT null,
				  `relation_id` int(11) unsigned DEFAULT null,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
			);
		}


	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#fetchAssociatedEntrySearchValue
		 *
		 * `$data` would contain the related entries, but is usually `null` when called from the frontend
		 * (when the field is not included in the DS, and only then "associated entry count" makes sense)
		 */
		public function fetchAssociatedEntrySearchValue($data, $field_id = null, $parent_entry_id = null){
			if(!is_null($parent_entry_id)) {
				return $parent_entry_id;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#fetchAssociatedEntryCount
		 */
		public function fetchAssociatedEntryCount($value){
			if(isset($value)) {
				return Symphony::Database()->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '".intval($value)."'");
			}
			else {
				return 0;
			}
		}


	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#displaySettingsPanel
		 */
		function displaySettingsPanel(&$wrapper, $errors=null) {

			// Basics
			parent::displaySettingsPanel($wrapper, $errors);

		/*-----------------------------------------------------------------------*/
		
			$div = new XMLElement('div', null, array('class' => 'two columns'));

			// Get current section id
			$section_id = Symphony::Engine()->Page->_context[1];

			// Related section
			$label = new XMLElement('label', __('Subsection'), array('class' => 'column'));
			$sections = SectionManager::fetch(null, 'ASC', 'name');
			$options = array(
				array('', false, __('None Selected')),
			);
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$options[] = array($section->get('id'), ($section->get('id') == $this->get('subsection_id')), $section->get('name'));
				}
			}
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][subsection_id]', $options, array('class' => 'subsectionmanager')));
			if(isset($errors['subsection_id'])) {
				$div->appendChild(Widget::Error($label, $errors['subsection_id']));
			}
			else {
				$div->appendChild($label);
			}

			// Recursion levels
			$label = new XMLElement('label', null, array('class' => 'column'));
			$recursion_levels = $this->get('recursion_levels');
			$select = Widget::Select('fields[' . $this->get('sortorder') . '][recursion_levels]', array(
				array('0', ($recursion_levels == 0), __('Exclude from XML output')),
				array('1', ($recursion_levels == 1), __('Include 1 child level in XML output')),
				array('2', ($recursion_levels == 2), __('Include 2 child levels in XML output')),
				array('3', ($recursion_levels == 3), __('Include 3 child levels in XML output')),
			));
			$label->setValue(__('Nested subsections %s', array($select->generate())));
			$div->appendChild($label);
			
			$wrapper->appendChild($div);

		/*-----------------------------------------------------------------------*/

			// Filter input
			$label = new XMLElement('label', __('Filter items by tags or categories') . '<i>' . __('Comma separated, alt+click for negation') . '</i>', array('class' => 'filter', 'style' => 'display: none;'));
			$label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][filter_tags]', $this->get('filter_tags')));
			$wrapper->appendChild($label);

			// Filter suggestions
			if(is_array($sections) && !empty($sections)) {

				// Get values
				$values = array();
				foreach($sections as $section) {

					// Don't include the current section
					if($section->get('id') != $section_id) {
						$fields = $section->fetchFields();

						// Continue if fields exist
						if(is_array($fields)) {
							foreach($fields as $field) {

								// Fetch only taglist or select boxes
								if($field->get('type') == 'taglist' || $field->get('type') == 'select' ) {

									// Fetch dynamic filter values
									$dynamic = Symphony::Database()->fetchCol(
										'value',
										"SELECT DISTINCT `value` FROM `tbl_entries_data_" . $field->get('id') . "` LIMIT 100"
									);

									// Fetch static filter values
									$static = explode(', ', $field->get('static_options'));

									// Merge dynamic and static values
									$filters = array_unique(array_merge($dynamic, $static));

									$relation = 'section' . $section->get('id');
									foreach($filters as $value) {
										if(!empty($value)) {
											$values[$value][] = $relation;
										}
									}

								}
							}

						}

					}
				}

				// Generate list
				if(!empty($values)) {
					$filter = new XMLElement('ul', null, array('class' => 'tags negation subsectionmanager'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', $handle, array('rel' => implode(' ', $fields))));
					}
					$wrapper->appendChild($filter);
				}

			}

		/*-----------------------------------------------------------------------*/

			// Behaviour
			$fieldset = new XMLElement('fieldset');
			$div = new XMLElement('div', null, array('class' => 'three columns'));
			
			// Create
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][create]" value="1" type="checkbox"' . ($this->get('create') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow creation of new items'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Remove
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][remove]" value="1" type="checkbox"' . ($this->get('remove') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow removal of existing items'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Multiple
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_multiple]" value="1" type="checkbox"' . ($this->get('allow_multiple') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow multiple items'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Edit
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][edit]" value="1" type="checkbox"' . ($this->get('edit') == 0 ? '' : ' checked="checked"') . '/> ' . __('Enable editing'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Sort
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][sort]" value="1" type="checkbox"' . ($this->get('sort') == 0 ? '' : ' checked="checked"') . '/> ' . __('Enable sorting'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Drop
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][drop]" value="1" type="checkbox"' . ($this->get('drop') == 0 ? '' : ' checked="checked"') . '/> ' . __('Enable dropping'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Search
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][show_search]" value="1" type="checkbox"' . ($this->get('show_search') == 0 ? '' : ' checked="checked"') . '/> ' . __('Show search'), array('class' => 'column'));
			$div->appendChild($label);
			
			// Search
			$label = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][show_preview]" value="1" type="checkbox"' . ($this->get('show_preview') == 0 ? '' : ' checked="checked"') . '/> ' . __('Show image and file previews'), array('class' => 'column'));
			$div->appendChild($label);

			// Append behaviour settings
			$fieldset->appendChild($div);
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// Display
			$fieldset = new XMLElement('fieldset');
			$div = new XMLElement('div', null, array('class' => 'two columns'));

			// Caption input
			$div->appendChild($this->__groupContentGenerator('caption', __('Caption'), $sections, $errors));

			// Custom drop text
			$div->appendChild($this->__groupContentGenerator('droptext', __('Drop text'), $sections, $errors));

			$fieldset->appendChild($div);
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// General
			$fieldset = new XMLElement('fieldset');
			$div = new XMLElement('div', null, array('class' => 'two columns'));
			$this->appendShowColumnCheckbox($div);
			$this->appendRequiredCheckbox($div);
			$fieldset->appendChild($div);
			$wrapper->appendChild($fieldset);

			// Compatibility with 1.x
			$wrapper->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][included_fields]', $this->get('included_fields'), 'hidden'));
		}

		/**
		 * Create caption and droptext settings
		 */
		private function __groupContentGenerator($name, $title, $sections, $errors) {
			$container = new XMLElement('div', null, array('class' => 'column'));
			$label = new XMLElement('label', $title);
			$label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][' . $name . ']', htmlspecialchars($this->get($name))));

			// Append Caption
			if(isset($errors[$name])) {
				$container->appendChild(Widget::wrapFormElementWithError($label, $errors[$name]));
			}
			else {
				$container->appendChild($label);
			}

			// Caption suggestions
			if(is_array($sections) && !empty($sections) && !isset($errors[$name])) {

				// Get values
				$values = array();
				foreach($sections as $section) {

					// Don't include the current section
					if($section->get('id') != $section_id) {
						$fields = $section->fetchFields();

						// Continue if fields exist
						if(is_array($fields)) {
							foreach($fields as $field) {
								$values[$field->get('element_name')][] = 'section' . $section->get('id');
							}
						}

					}
				}

				// Generate list
				if(is_array($values)) {
					$filter = new XMLElement('ul', null, array('class' => 'tags inline'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', '{$' . $handle . '}', array('rel' => implode(' ', $fields))));
					}
					$container->appendChild($filter);
				}

			}

			return $container;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#checkFields
		 */
		function checkFields(&$errors, $checkForDuplicates=true) {

			if(!is_array($errors)) $errors = array();

			// Check if a related section has been selected
			if($this->get('subsection_id') == '') {
				$errors['subsection_id'] = __('This is a required field.');
			}

			// Check if caption content is well formed
			if($this->get('caption')) {
				try {
					simplexml_load_string('<li>' . $this->get('caption') . '</li>');
				}
				catch(Exception $e) {
					$errors['caption'] = __('%s has to be well-formed. Please check opening and closing tags.', array(__('Caption')));
				}
			}

			// Check if droptext content is well formed
			if($this->get('droptext')) {
				try {
					simplexml_load_string('<li>' . $this->get('droptext') . '</li>');
				}
				catch(Exception $e) {
					$errors['droptext'] = __('%s has to be well-formed. Please check opening and closing tags.', array(__('Drop text')));
				}
			}

			parent::checkFields($errors, $checkForDuplicates);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#commit
		 */
		function commit() {

			// Prepare commit
			if(!parent::commit()) return false;
			$id = $this->get('id');
			if($id === false) return false;

			// Set up fields
			$fields = array();
			$fields['field_id'] = $id;
			$fields['subsection_id'] = $this->get('subsection_id');
			
			$fields['create'] = ($this->get('create') ? 1 : 0);
			$fields['remove'] = ($this->get('remove') ? 1 : 0);
			$fields['allow_multiple'] = ($this->get('allow_multiple') ? 1 : 0);

			$fields['edit'] = ($this->get('edit') ? 1 : 0);
			$fields['sort'] = ($this->get('sort') ? 1 : 0);
			$fields['drop'] = ($this->get('drop') ? 1 : 0);
			
			$fields['show_search'] = ($this->get('show_search') ? 1 : 0);
			$fields['show_preview'] = ($this->get('show_preview') ? 1 : 0);

			// Clean up filter values
			if($this->get('filter_tags') != '') {
				$tags = explode(",", $this->get('filter_tags'));
				foreach($tags as &$tag) {
					$tag = trim($this->cleanValue($tag));
					$list[] = $tag;
				}
				$fields['filter_tags'] = implode(', ', $list);
			}

			// Item caption
			$fields['caption'] = $this->get('caption');
			if($this->get('caption') == '') {

				// Fetch fields in subsection
				$subsection_fields = Symphony::Database()->fetch(
					"SELECT element_name, type
					FROM tbl_fields
					WHERE parent_section = '" . $this->get('subsection_id') . "'
					ORDER BY sortorder ASC
					LIMIT 10"
				);

				// Generate default caption
				$text = $file = '';
				foreach($subsection_fields as $subfield) {
					if($text != '' && $file != '') break;
					if(strpos($subfield['type'], 'upload') === false) {
						if($text == '') $text = '{$' . $subfield['element_name'] . '}';
					}
					else {
						if($file == '') $file = '{$' . $subfield['element_name'] . '}';
					}
				}

				// Caption markup
				if($text != '' && $file != '') {
					$fields['caption'] = $text . '<br /> <em>' . $file . '</em>';
				}
				else {
					$fields['caption'] = $text . $file;
				}

			}

			// Drop text
			$fields['droptext'] = $this->get('droptext');

			// XML Output
			$fields['recursion_levels'] = intval($this->get('recursion_levels'));
			if(empty($fields['recursion_levels'])) $fields['recursion_levels'] = 0; // Make sure it is 0, not null

			// Delete old field settings
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1"
			);

			// Save new field setting
			$settings = Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

			// Remove old secion association
			SectionManager::removeSectionAssociation($id);

			// Save new section association
			$association = $this->createSectionAssociation(null, $this->get('subsection_id'), $id, $id, false);

			if($settings && $association) {
				return true;
			}
			else {
				return false;
			}

		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#createSectionAssociation
		 */
		public function createSectionAssociation($parent_section_id = null, $child_section_id = null, $child_field_id = null, $parent_field_id = null, $show_association = false){

			if(is_null($parent_section_id) && is_null($child_section_id) && (is_null($parent_field_id) || !$parent_field_id)) return false;

			if(is_null($parent_section_id )) {
				$parent_section_id = Symphony::Database()->fetchVar('parent_section', 0,
					"SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '$parent_field_id' LIMIT 1"
				);
			}

			$fields = array(
				'parent_section_id' => $parent_section_id,
				'parent_section_field_id' => $parent_field_id,
				'child_section_id' => $child_section_id,
				'child_section_field_id' => $child_field_id,
				'hide_association' => ($show_association ? 'no' : 'yes')
			);

			return Symphony::Database()->insert($fields, 'tbl_sections_association');
		}


	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		/**
		 * If you need to fetch the pure data this field returns, please use getDefaultPublishContent()
		 *
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#displayPublishPanel
		 */
		function displayPublishPanel(&$wrapper, $data=null, $flagWithError=null, $fieldnamePrefix=null, $fieldnamePostfix=null, $entry_id=null) {
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			// Houston, we have problem: we've been called out of context!
			$callback = Administration::instance()->getPageCallback();
			if($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				$this->getDefaultPublishContent($wrapper, $data);
				return;
			}

			// Append styles
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.css', 'screen', 102, false);

			// Append scripts
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.js', 104, false);
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/resize/jquery.ba-resize.js', 105, false);

			// Setup field name
			$fieldname = 'fields' . $fieldnamePrefix . '['. $this->get('element_name') . ']' . $fieldnamePostfix;

			// Label
			$label = Widget::Label($this->get('label'));
			if($this->get('required') == 'no') {
				$optional = new XMLElement('i', __('Optional'));
				$label->appendChild($optional);
			}
			$wrapper->appendChild($label);

			// Get Subsection
			$subsection = new SubsectionManager();
			$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data, $this->get('recursion_levels'), SubsectionManager::GETHTML);

			// Create interface
			$settings = array('dark', 'frame');
			if($this->get('create') == 1) {
				$settings[] = 'constructable';
			}
			if($this->get('remove') == 1) {
				$settings[] = 'destructable';
			}
			if($this->get('allow_multiple') == 1) {
				$settings[] = 'multiple';
			}
			if($this->get('edit') == 1) {
				$settings[] = 'editable';
			}
			if($this->get('sort') == 1) {
				$settings[] = 'sortable';
			}
			if($this->get('drop') == 1) {
				$settings[] = 'droppable';
			}
			if($this->get('show_search') == 1) {
				$settings[] = 'searchable';
			}
			
			$duplicator = new XMLElement('div', null, array('class' => implode(' ', $settings)));
			$selected = new XMLElement('ol', $content['html']);
			$duplicator->appendChild($selected);

			// Append item template
			$item = new XMLElement('li',
				'<header>
					<strong>' . __('New item') . '</strong>
					<span>' . __('Please fill out the form below') . '</span>
				</header>
				<div class="content">
					<iframe></iframe>
				</div>',
				array(
					'class' => 'template',
					'data-type' => 'subsection'
				)
			);
			$selected->appendChild($item);

			// Append subsection information
			$subsection = SectionManager::fetch($this->get('subsection_id'));
			$wrapper->setAttribute('data-field-id', $this->get('id'));
			$wrapper->setAttribute('data-field-name', $fieldname);
			$wrapper->setAttribute('data-subsection-id', $this->get('subsection_id'));
			$wrapper->setAttribute('data-subsection-new', SYMPHONY_URL . '/publish/' . $subsection->get('handle'));

			// Error handling
			if($flagWithError != null) {
				$wrapper->appendChild(Widget::Error($duplicator, $flagWithError));
			}
			else {
				$wrapper->appendChild($duplicator);
			}
		}

		/**
		 * Get default publish content
		 */
		function getDefaultPublishContent(&$wrapper, $data = null) {

			// Get items
			$subsection = new SubsectionManager();
			$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data, $this->get('recursion_levels'), SubsectionManager::GETOPTIONS | SubsectionManager::GETALLITEMS);

			// Append items
			$select = Widget::Select('', $content['options'], ($this->get('allow_multiple') == 0 ? array() : array('multiple' => 'multiple')));
			$wrapper->appendChild($select);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#checkPostFieldData
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			if(!is_array($data)) $data = array($data);
			$data = array_filter($data);

			$status = parent::checkPostFieldData($data, $message, $entry_id);
			if($status != self::__OK__) return $status;

			if(empty($data) || !is_array($data)) return self::__OK__;

			if($this->get('allow_multiple') == 0 && count($data) > 1) {
				$message = __("'%s' allows single item only.", array($this->get('label')));
				return self::__INVALID_FIELDS__;
			}

			return self::__OK__;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#processRawFieldData
		 */
		public function processRawFieldData($data, &$status, &$message=null, $simulate=false, $entry_id=null) {
			$status = self::__OK__;
			if(!is_array($data)) $data = array($data);
			if(empty($data)) return null;

			return array('relation_id' => $data);
		}


	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		/**
		 * Subsection entries are pre-processed in the extension driver and stored in
		 * extension_subsectionmanager::$storage with other helpful data. If you are building
		 * custom data sources, please use extension_subsectionmanager::storeSubsectionFields()
		 * to store subsection fields and extension_subsectionmanager::preloadSubsectionEntries()
		 * to preload subsection entries.
		 *
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#appendFormattedElement
		 */
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {
			static $done = array();

			// Unify data
			if(empty($data) || empty($data['relation_id'])) {
				$data['relation_id'] = array();
			}
			else if(!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
			}

			// Create subsection element
			$subsection = new XMLElement($this->get('element_name'));
			$subsection->setAttribute('field-id', $this->get('id'));
			$subsection->setAttribute('subsection-id', $this->get('subsection_id'));

			// Generate output
			foreach($data['relation_id'] as $index => $entry_id) {

				// Populate entry element
				$entry = extension_subsectionmanager::$storage['entries'][$entry_id];

				// Fetch missing entries
				if(empty($entry)) {
					$entry = EntryManager::fetch($entry_id, $this->get('subsection_id'));

					// Store entry
					$entry = $entry[0];
					extension_subsectionmanager::$storage['entries'][$entry_id] = $entry;
				}

				if(empty($entry)) {
					// TODO: Looks like related entry was deleted and data was not removed.
					//		 Should SubsectionManager gather deleted IDs and remove them from DB at exit?
					//		 Or register delegate to always delete them when entry is deleted?
					continue;
				}

				// Create item
				$item = new XMLElement('item', null, array('id' => $entry_id, 'creation-date' => $entry->get('creation_date')));
				$subsection->appendChild($item);

				// Process entry for Data Source
				if(!empty(extension_subsectionmanager::$storage['fields'][$mode][$this->get('id')])) {
					foreach(extension_subsectionmanager::$storage['fields'][$mode][$this->get('id')] as $field_id => $modes) {
						$field = FieldManager::fetch($field_id);

						// Omit fields that were removed in meantime
						if(empty($field)) continue;

						$entry_data = $entry->getData($field_id);

						// No modes
						if(empty($modes)) {
							$field->appendFormattedElement($item, $entry_data, $encode, $mode, $entry_id);
						}

						// With modes
						else {
							foreach($modes as $m) {
								$field->appendFormattedElement($item, $entry_data, $encode, $m, $entry_id);
							}
						}
					}
				}

				// Process entry for anyone else
				else {
					$engine = Symphony::Engine();
					if($engine instanceof Administration) {
						// Check for recursion first
						$id = $this->get('parent_section');
						if($done[$id] >= $this->get('recursion_levels') + 1) return array();
						$done[$id] += 1;

						// Now output data
						$callback = Administration::instance()->getPageCallback();
						if(!empty($callback['context']['page'])) {
							$data = $entry->getData();

							// Add fields:
							foreach($data as $field_id => $values) {
								if(empty($field_id)) continue;

								$field = FieldManager::fetch($field_id);
								if(empty($field)) continue;

								$field->appendFormattedElement($item, $values, $encode, null, $entry_id);
							}
						}

						$done[$id] -= 1;
					}
				}
			}

			// Append subsection
			$subsection->setAttribute('items', count($data['relation_id']));
			$wrapper->appendChild($subsection);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#prepareTableValue
		 */
		function prepareTableValue($data, XMLElement $link=null) {
			if(empty($data['relation_id'])) return null;

			// Single select
			if($this->get('allow_multiple') == 0 || count($data['relation_id']) === 1) {
				$subsection = new SubsectionManager();
				$content = $subsection->generate($this->get('id'), $this->get('subsection_id'), $data, $this->get('recursion_levels'), SubsectionManager::GETPREVIEW);

				// Link?
				if($link) {
					$href = $link->getAttribute('href');
					$item = '<a href="' . $href . '">' . $content['preview'] . '</a>';
				}
				else {
					$item = $content['preview'];
				}

				return '<div class="subsectionmanager">' . $item . '</div>';
			}

			// Multiple select
			else {
				$count = count($data['relation_id']);
				return parent::prepareTableValue(array('value' => ($count > 1) ? $count . ' ' . __('items') : $count . ' ' . __('item')), $link);
			}
		}

		/**
		 * Used by the XML Importer when importing
		 *
		 * @param mixed $value
		 * @param integer $entry_id
		 * @return array
		 */
		public function prepareImportValue($value, $entry_id = null) {
			if($this->get('allow_multiple_selection') == 'no') {
				$value = array(implode('', $value));
			}

			return $value;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#getParameterPoolValue
		 */
		public function getParameterPoolValue($data) {
			return $data['relation_id'];
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#fetchIncludableElements
		 */
		public function fetchIncludableElements($break = false) {
			static $done = array();

			$id = $this->get('parent_section');
			if($done[$id] >= $this->get('recursion_levels') + 1) return array();
			$done[$id] += 1;

			$includable = array();

			// Fetch subsection fields
			$section = SectionManager::fetch($this->get('subsection_id'));
			$fields = $section->fetchFields();

			foreach($fields as $field) {
				$field_id = $field->get('id');
				$elements = $field->fetchIncludableElements(true);

				if (is_array($elements)) {
					foreach($elements as $element) {
						$includable[] = $this->get('element_name') . ': ' . $element;
					}
				}
			}

			$done[$id] -= 1;
			return $includable;
		}


	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#displayDatasourceFilterPanel
		 */
		function displayDatasourceFilterPanel(&$wrapper, $data=null, $errors=null, $fieldnamePrefix=null, $fieldnamePostfix=null) {
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);

			$text = new XMLElement('p', __('Use comma separated list of entry ids that has to be associated with filtered entries, e.g., "23, 45, 691" or "not: 23, 45, 691".'), array('class' => 'help') );
			$wrapper->appendChild($text);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#buildDSRetrievalSQL
		 */
		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false) {

			// Current field id
			$field_id = $this->get('id');

			// Filters connected with AND
			if($andOperation) {
				$op = '=';
				if(preg_match('/^not:\s*/i', $data[0], $m)) {
					$data[0] = str_replace($m[0], '', $data[0]);
					$op = '!=';
				}

				foreach($data as $value) {
					$this->_key++;
					$joins .= " LEFT JOIN `tbl_entries_data_{$field_id}` AS `t{$field_id}_{$this->_key}` ON (`e`.`id` = `t{$field_id}_{$this->_key}`.entry_id) ";
					$where .= " AND `t{$field_id}_{$this->_key}`.relation_id {$op} '". intval($value) ."' ";
				}
			}

			// Filters connected with OR
			else {
				$op = 'IN';
				if(preg_match('/^not:\s*/i', $data[0], $m)) {
					$data[0] = str_replace($m[0], '', $data[0]);
					$op = 'NOT IN';
				}

				$this->_key++;
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t{$field_id}_{$this->_key}` ON (`e`.`id` = `t{$field_id}_{$this->_key}`.entry_id) ";
				$where .= " AND `t{$field_id}_{$this->_key}`.relation_id {$op} ('" . @implode("', '", array_map('intval', $data)) . "') ";
			}

			return true;
		}


	/*-------------------------------------------------------------------------
		Grouping:
	-------------------------------------------------------------------------*/

		public function groupRecords($records){
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			$field_id = $this->get('id');
			foreach($records as $r){
				$data = $r->getData($field_id);
				$entry_id = $data['relation_id'];

				if(!isset($groups[$this->get('element_name')][$entry_id])){
					$groups[$this->get('element_name')][$entry_id] = array('attr' => array('id' => $entry_id),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->get('element_name')][$entry_id]['records'][] = $r;
			}

			return $groups;
		}


	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		/**
		 * @see http://symphony-cms.com/learn/api/2.3/toolkit/field/#getExampleFormMarkup
		 */
		public function getExampleFormMarkup() {
			return Widget::Select('fields['.$this->get('element_name').']', array(array('...')));
		}


	/*-------------------------------------------------------------------------
		Compatibility:
	-------------------------------------------------------------------------*/

		/**
		 * Keep compatibility with Symphony pre 2.2.1 for a little longer.
		 * @see http://symphony-cms.com/learn/api/2.2.1/toolkit/field/#buildDSRetrivalSQL
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false) {
			return $this->buildDSRetrievalSQL($data, $joins, $where, $andOperation);
		}
	}
