<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');

	Class fieldSubsectionmanager extends Field {

		/**
		 * Initialize Subsection Manager as unrequired field
		 */
		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Manager');
			$this->_required = true;
		}

		/**
		 * Allow data source filtering
		 */
		function canFilter(){
			return true;
		}

		/**
		 * Allow data source parameter output
		 */
		function allowDatasourceParamOutput(){
			return true;
		}

		/**
		 * Displays setting panel in section editor.
		 *
		 * @param XMLElement $wrapper - parent element wrapping the field
		 * @param array $errors - array with field errors, $errors['name-of-field-element']
		 */
		function displaySettingsPanel(&$wrapper, $errors=NULL) {

			// Initialize field settings based on class defaults (name, placement)
			parent::displaySettingsPanel($wrapper, $errors);

			// Get current section id
			$section_id = Administration::instance()->Page->_context[1];

			// Related section
			$label = new XMLElement('label', __('Subsection'));
			$sectionManager = new SectionManager($this->_engine);
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array(
				array('', false, __('None Selected')),
			);
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					if($section->get('id') != $section_id) {
						$options[] = array($section->get('id'), ($section->get('id') == $this->get('subsection_id')), $section->get('name'));
					}
				}
			}
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][subsection_id]', $options, array('class' => 'subsectionmanager')));
			if(isset($errors['subsection_id'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['subsection_id']));
			}
			else {
				$wrapper->appendChild($label);
			}
			
			
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
									$dynamic = $this->Database->fetchCol(
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
					$filter = new XMLElement('ul', NULL, array('class' => 'tags negation subsectionmanager'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', $handle, array('rel' => implode(' ', $fields))));
					}
					$wrapper->appendChild($filter);
				}
					
			}
			
			
			// BEHAVIOUR
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Behaviour') . '</legend>', array('class' => 'settings group compact'));
			
			// Get stage settings
			$stage = Administration::instance()->Database->fetchRow(0, 
				"SELECT * FROM tbl_fields_stage WHERE field_id = '" . $this->get('id') . "' LIMIT 1"
			);
			
			// Handle missing stage settings
			if(empty($stage)) {
				$stage = array(
					'constructable' => 1,
					'destructable' => 1,
					'searchable' => 1,
					'droppable' => 0,
					'draggable' => 1
				);
				$this->set('allow_multiple', 1);
				$this->set('show_preview', 1);
			}
			
			// Setting: constructable
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][stage][constructable]" value="1" type="checkbox"' . ($stage['constructable'] == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow creation of new items') . ' <i>' . __('This will add a <code>Create New</code> button to the interface') . '</i>');
			$fieldset->appendChild($setting);
			
			// Setting: destructable		
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][stage][destructable]" value="1" type="checkbox"' . ($stage['destructable'] == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow deselection of items') . ' <i>' . __('This will add a <code>Remove</code> button to the interface') . '</i>');
			$fieldset->appendChild($setting);
			
			// Setting: searchable
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][stage][searchable]" value="1" type="checkbox"' . ($stage['searchable'] == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow selection of items from a list of existing items') . ' <i>' . __('This will add a search field to the interface') . '</i>');
			$fieldset->appendChild($setting);
			
			// Setting: droppable
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][stage][droppable]" value="1" type="checkbox"' . ($stage['droppable'] == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow dropping of items') . ' <i>' . __('This will enable item dropping on textareas') . '</i>');
			$fieldset->appendChild($setting);
			
			// Setting: allow multiple
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_multiple]" value="1" type="checkbox"' . ($this->get('allow_multiple') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow selection of multiple items') . ' <i>' . __('This will switch between single and multiple item lists') . '</i>');
			$fieldset->appendChild($setting);
			
			// Setting: draggable
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][stage][draggable]" value="1" type="checkbox"' . ($stage['draggable'] == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow sorting of items') . ' <i>' . __('This will enable item dragging and reordering') . '</i>');
			$fieldset->appendChild($setting);
			
			// Append behaviour settings
			$wrapper->appendChild($fieldset);
			
			
			// DISPLAY
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Display') . '</legend>', array('class' => 'settings group'));
			$container = new XMLElement('div');
			
			// Caption input
			$label = new XMLElement('label', __('Caption'));
			$label->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][caption]', htmlspecialchars($this->get('caption'))));
			
			// Append Caption
			if(isset($errors['caption'])) {
				$container->appendChild(Widget::wrapFormElementWithError($label, $errors['caption']));
			}
			else {
				$container->appendChild($label);
			}
			
			// Caption suggestions		
			if(is_array($sections) && !empty($sections) && !isset($errors['caption'])) {
				
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
					$filter = new XMLElement('ul', NULL, array('class' => 'tags inline'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', '{$' . $handle . '}', array('rel' => implode(' ', $fields))));
					}
					$container->appendChild($filter);
				}
				
			}
			$fieldset->appendChild($container);

			// Preview options
			$label = new XMLElement('label', NULL, array('class' => 'thumbnails'));
			$input = Widget::Input('fields[' . $this->get('sortorder') . '][show_preview]', 1, 'checkbox');
			if($this->get('show_preview') != 0) {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Show thumbnail images', array($input->generate())));
			$fieldset->appendChild($label);			
			$wrapper->appendChild($fieldset);
		
			
			// DATA SOURCE
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Data Source XML') . '</legend>', array('class' => 'settings'));

			$label = new XMLElement('label', __('Included elements') . '<i>' . __('Don&#8217;t forget to include the Subsection Manager field in your Data Source') . '</i>');
			$field_groups = array();
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
				}
			}
			$options = array();
			foreach($field_groups as $group) {
				if(!is_array($group['fields'])) continue;
				$fields = array();
				foreach($group['fields'] as $field){
					if($field->get('id') != $this->get('id')) {
						$fields[] = array($field->get('id'), (in_array($field->get('id'), explode(',', $this->get('included_fields')))), $field->get('label'));
					}
				}
				if(is_array($fields) && !empty($fields)) {
					$options[] = array('label' => $group['section']->get('id'), 'options' => $fields);
				}
			}
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][included_fields][]', $options, array('multiple' => 'multiple', 'class' => 'datasource')));
			$fieldset->appendChild($label);
			
			$wrapper->appendChild($fieldset);


			// GENERAL
			$fieldset = new XMLElement('fieldset', NULL, array('class' => 'settings group'));
			$this->appendShowColumnCheckbox($fieldset);
			$this->appendRequiredCheckbox($fieldset);
			$wrapper->appendChild($fieldset);

		}

		/**
		 * Check fields for errors in section editor.
		 *
		 * @param array $errors
		 * @param boolean $checkForDuplicates
		 */
		function checkFields(&$errors, $checkForDuplicates=true) {

			if(!is_array($errors)) $errors = array();

			// Check if a related section has been selected
			if($this->get('subsection_id') == '') {
				$errors['subsection_id'] = __('This is a required field.');
			}

			// Check if caption content is well formed
			if($this->get('caption')) {
				$validate = @simplexml_load_string('<li>' . $this->get('caption') . '</li>');
				if(!$validate) {
					$errors['caption'] = __('Caption has to be well-formed. Please check opening and closing tags.');
				}
			}

			parent::checkFields($errors, $checkForDuplicates);

		}

		/**
		 * Save field settings in section editor.
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
			$fields['allow_multiple'] = ($this->get('allow_multiple') ? 1 : 0);
			$fields['show_preview'] = ($this->get('show_preview') ? 1 : 0);
			
			// Delete old stage settings for this field
			Administration::instance()->Database->query(
				"DELETE FROM `tbl_fields_stage` WHERE `field_id` = '$id' LIMIT 1"
			);
					
			// Save new stage settings for this field
			if(is_array($this->get('stage'))) {
				Administration::instance()->Database->query(
					"INSERT INTO `tbl_fields_stage` (`field_id`, " . implode(', ', array_keys($this->get('stage'))) . ", `context`) VALUES ($id, " . implode(', ', $this->get('stage')) . ", 'subsectionmanager')"
				);
			}
			else {
				Administration::instance()->Database->query(
					"INSERT INTO `tbl_fields_stage` (`field_id`, `context`) VALUES ($id, 'subsectionmanager')"
				);
			}

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
				$subsection_fields = Administration::instance()->Database->fetch(
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

			// Data source fields
			$fields['included_fields'] = (is_null($this->get('included_fields')) ? NULL : implode(',', $this->get('included_fields')));

			// Delete old field settings
			Administration::instance()->Database->query(
				"DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1"
			);

			// Save new field setting
			return Administration::instance()->Database->insert($fields, 'tbl_fields_' . $this->handle());

		}

		/**
		 * Display publish panel in content area.
		 *
		 * @param XMLElement $wrapper
		 * @param $data
		 * @param $flagWithError
		 * @param $fieldnamePrefix
		 * @param $fieldnamePostfix
		 */
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
		
			// Get version number
			$about = Administration::instance()->ExtensionManager->about('subsectionmanager');
			$version = strtolower($about['version']);	

			// Append assets
			$this->_engine->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/draggable/symphony.draggable.js?v=' . $version, 101, false);
			$this->_engine->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/stage/symphony.stage.js?v=' . $version, 101, false);
			$this->_engine->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/lib/stage/symphony.stage.css?v=' . $version, 'screen', 103, false);
			$this->_engine->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/symphony.subsectionmanager.js?v=' . $version, 102, false);
			$this->_engine->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/symphony.subsectionmanager.css?v=' . $version, 'screen', 104, false);

			// Get Subsection
			$subsection = new SubsectionManager($this->_Parent);
			$content = $subsection->generate($data['relation_id'], $this->get('id'), $this->get('subsection_id'), NULL, false);

			// Prepare select options
			$options = $content['options'];
			
			if($this->get('allow_multiple') == 0) {
				$options[] = array(-1, false, __('None Selected'));
			}
			if(!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
			}

			// Setup field name
			$fieldname = 'fields' . $fieldnamePrefix . '['. $this->get('element_name') . ']' . $fieldnamePostfix . '[]';

			// Setup select
			$label = Widget::Label($this->get('label'), $links);
			$select = Widget::Select($fieldname, $options, ($this->get('allow_multiple') == 1 ? array('multiple' => 'multiple') : NULL));
			$label->appendChild($select);

			// Setup sorting
			$currentPageURL = Administration::instance()->getCurrentPageURL();
			preg_match_all('/\d+/', $currentPageURL, $entry_id, PREG_PATTERN_ORDER);
			$entry_id = $entry_id[0][count($entry_id[0])-1];
			if($entry_id) {
				$order = Administration::instance()->Database->fetchVar('order', 0,
					"SELECT `order`
					FROM `tbl_fields_subsectionmanager_sorting`
					WHERE `entry_id` = " . $entry_id . "
					AND `field_id` = " . $this->get('id') . "
					LIMIT 1"
				);
			}
			$input = Widget::Input('fields[sort_order][' . $this->get('id') . ']', $order, 'hidden');
			$label->appendChild($input);

			// Setup relation id
			$input = Widget::Input('fields[subsection_id][' . $this->get('id') . ']', $this->get('subsection_id'), 'hidden');
			$label->appendChild($input);
			$wrapper->appendChild($label);
			
			// Check if all needed components are available
			$flagAsMissing['draggable'] = !file_exists(EXTENSIONS. '/subsectionmanager/lib/draggable/symphony.draggable.js');
			$flagAsMissing['stage'] = !file_exists(EXTENSIONS. '/subsectionmanager/lib/stage/symphony.stage.js');
			
			if($flagAsMissing['draggable'] || $flagAsMissing['stage']) {
				$error = new XMLElement('ul');
				
				// Draggable missing
				if($flagAsMissing['draggable']) {
					$message = new XMLElement('li', __('Submodule %s is missing.', array('<code>Draggable</code>')));
					$error->appendChild($message);
				}
				
				// Stage missing
				if($flagAsMissing['stage']) {
					$message = new XMLElement('li', __('Submodule %s is missing.', array('<code>Stage</code>')));
					$error->appendChild($message);
				}
				
				// Display error
				if($flagAsMissing['draggable'] && $flagAsMissing['stage']) {
					$addition = __('Please add the missing submodules to %s. ', array('<code>' . URL . '/extensions/subsectionmanager/lib/</code>'));
				}
				else {
					$addition = __('Please add the missing submodule to %s. ', array('<code>' . URL . '/extensions/subsectionmanager/lib/</code>'));
				}
				$wrapper->appendChild(Widget::wrapFormElementWithError($error, $addition . __('For further assistence have a look at the documentation available on %s.', array('<a href="http://github.com/nilshoerrmann/subsectionmanager/">GitHub</a>'))));
				
				return;
			}

			// Get stage settings
			$settings = Administration::instance()->Database->fetchRow(0,
				"SELECT `constructable`, `destructable`, `draggable`, `droppable`, `searchable` FROM `tbl_fields_stage` WHERE `field_id` = '" . $this->get('id') . "' LIMIT 1"
			);
			foreach($settings as $key => $value) {
				if($value == 0) unset($settings[$key]);
			}
			$settings = ' ' . implode(' ', array_keys($settings));
			
			// Create stage
			$stage = new XMLElement('div', NULL, array('class' => 'stage' . $settings . ($this->get('show_preview') == 1 ? ' preview' : '')));
			$content['empty'] = '<li class="empty message"><span>' . __('There are no selected items') . '</span></li>';
			$selected = new XMLElement('ul', $content['empty'] . $content['html'], array('class' => 'selection'));
			$stage->appendChild($selected);
			
			// Append item template
			$thumb = '<img src="' . URL . '/extensions/subsectionmanager/assets/images/new.gif" width="40" height="40" class="thumb" />';
			$item = new XMLElement('li', $thumb . '<span>' . __('New item') . '<br /><em>' . __('Please fill out the form below.') . '</em></span><a class="destructor">' . __('Remove Item') . '</a>', array('class' => 'item template preview'));
			$selected->appendChild($item);
			
			// Append drawer template
			$subsection_handle = Administration::instance()->Database->fetchVar('handle', 0,
				"SELECT `handle`
				FROM `tbl_sections`
				WHERE `id` = '" . $this->get('subsection_id') . "'
				LIMIT 1"
			);
			$create_new = URL . '/symphony/publish/' . $subsection_handle;
			$item = new XMLElement('li', '<iframe name="subsection-' . $this->get('element_name') . '" src="about:blank" target="' . $create_new . '"  frameborder="0"></iframe>', array('class' => 'drawer template'));
			$selected->appendChild($item);

			// Error handling
			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($stage, $flagWithError));
			}
			else {
				$wrapper->appendChild($stage);
			}

		}

 		/**
		 * Prepare field values for database.
		 */
		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
		
			$status = self::__OK__;
			if(!is_array($data) && !is_null($data)) return array('relation_id' => $data);
			if(empty($data)) return NULL;

			$result = array();

			foreach($data as $a => $value) {
			  $result['relation_id'][] = $data[$a];
			}

			return $result;
		}

 		/**
		 * Create database field table.
		 */
		function createTable(){

			return Administration::instance()->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);

		}

 		/**
		 * Prepare value for the content overview table.
		 *
		 * @param array $data
		 * @param XMLElement $link
		 */
		function prepareTableValue($data, XMLElement $link=NULL) {
		
			if(empty($data['relation_id'])) return NULL;
			$count = count($data['relation_id']);
			return parent::prepareTableValue(array('value' => ($count > 1) ? $count . ' ' . __('items') : $count . ' ' . __('item')), $link);

		}

 		/**
		 * Generate data source output.
		 *
		 * @param XMLElement $wrapper
		 * @param array $data
		 * @param boolean $encode
		 * @param string $mode
		 */
		public function appendFormattedElement(&$wrapper, $data, $encode = false) {

			// Unify data
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);

			// Create Subsection Manager element
			$subsectionmanager = new XMLElement($this->get('element_name'));

			// Check for included fields
			if($this->get('included_fields') == '') {
				$error = new XMLElement('error', 'No fields for output defined.');
				$subsectionmanager->appendChild($error);
				$wrapper->appendChild($subsectionmanager);
				return;
			}

			// Fetch field data
			$entryManager = new EntryManager($this->_engine);
			$entries = $entryManager->fetch($data['relation_id'], $this->get('subsection_id'));

			// Sort entries
			$order = $this->_Parent->_Parent->Database->fetchVar('order', 0,
				"SELECT `order`
				FROM `tbl_fields_subsectionmanager_sorting`
				WHERE `entry_id` = " . $wrapper->getAttribute('id') . "
				AND `field_id` = " . $this->get('id') . "
				LIMIT 1"
			);
			$sorted_ids = explode(',', $order);
			$sorted_entries = array();
			if(!empty($sorted_ids) && $sorted_ids[0] != 0 && !empty($sorted_ids[0])) {
				foreach($sorted_ids as $id) {
					foreach($entries as $entry) {
						if($entry->get('id') == $id) {
							$sorted_entries[] = $entry;
						}
					}
				}
			}
			else {
				$sorted_entries = $entries;
			}

			// Build XML
			$count = 1;
			foreach($sorted_entries as $entry) {
			
				// Fetch entry data
				$entry_data = $entry->getData();

				// Create entry element
				$item = new XMLElement('item');
				
				// Populate entry element
				$included_fields = explode(',', $this->get('included_fields'));
				foreach ($entry_data as $field_id => $values) {
				
					// Only append if field is listed or if list empty
					if(in_array($field_id, $included_fields) || empty($included_fields[0])) {
						$item_id = $entry->get('id');
						$item->setAttribute('id', $item_id);
						$field =& $entryManager->fieldManager->fetch($field_id);
						$field->appendFormattedElement($item, $values, false);
					}
				}
				
				// Append entry element
				$subsectionmanager->appendChild($item);
				$subsectionmanager->setAttribute('items', $count);
				$count++;
			}

			// Append Subsection Manager to data source
			$wrapper->appendChild($subsectionmanager);

		}

 		/**
		 * Generate parameter pool values.
		 *
		 * @param array $data
		 */
		public function getParameterPoolValue($data) {

			if(is_array($data['relation_id'])) return implode(", ", $data['relation_id']);
			return $data['relation_id'];

		}

 		/**
		 * Generate data source filter panel.
		 *
		 * @param XMLElement $wrapper
		 * @param array $data
		 * @param $errors
		 * @param $fieldnamePrefix
		 * @param $fieldnamePostfix
		 */
		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			$text = new XMLElement('p', __('Use comma separated entry ids for filtering.'), array('class' => 'help') );
			$wrapper->appendChild($text);

		}

 		/**
		 * Return sample markup for the event editor.
		 */
		public function getExampleFormMarkup() {
		
			// Nothing to show here yet
			return Widget::Select('fields['.$this->get('element_name').']', array(array('...')));
			
		}

	}
