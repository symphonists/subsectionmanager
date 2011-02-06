<?php

	/**
	 * @package fields
	 */
	/**
	 * This field provides inline subsection management. 
	 */
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');
	require_once(EXTENSIONS . '/subsectionmanager/lib/stage/class.stage.php');

	Class fieldSubsectionmanager extends Field {

		/**
		 * Construct a new instance of this field.
		 *
		 * @param mixed $parent
		 *  The class that created this Field object, usually the FieldManager,
		 *  passed by reference.
		 */
		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Manager');
			$this->_required = true;
		}

		/**
		 * Test whether this field can be filtered. Filtering allows the 
		 * xml output results to be limited according to an input parameter. 
		 *
		 * @return boolean
		 *	true if this can be filtered, false otherwise.
		 */
		function canFilter(){
			return true;
		}

		/**
		 * Test whether this field supports data-source output grouping. 
		 * Data-source grouping allows clients of this field to group the 
		 * xml output according to this field.
		 *
		 * @return boolean
		 *	true if this field does support data-source grouping, false otherwise.
		 */
		function allowDatasourceParamOutput(){
			return true;
		}

		/**
		 * Display the default settings panel, calls the buildSummaryBlock
		 * function after basic field settings are added to the wrapper.
		 *
		 * @see buildSummaryBlock()
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 */
		function displaySettingsPanel(&$wrapper, $errors=NULL) {

			// Initialize field settings based on class defaults (name, placement)
			parent::displaySettingsPanel($wrapper, $errors);

		/*-----------------------------------------------------------------------*/

			// Get current section id
			$section_id = Symphony::Engine()->Page->_context[1];

			// Related section
			$label = new XMLElement('label', __('Subsection'));
			$sectionManager = new SectionManager($this->_engine);
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
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
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['subsection_id']));
			}
			else {
				$wrapper->appendChild($label);
			}

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
					$filter = new XMLElement('ul', NULL, array('class' => 'tags negation subsectionmanager'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', $handle, array('rel' => implode(' ', $fields))));
					}
					$wrapper->appendChild($filter);
				}
					
			}

		/*-----------------------------------------------------------------------*/

			// Behaviour
			$fieldset = Stage::displaySettings(
				$this->get('id'), 
				$this->get('sortorder'), 
				__('Behaviour')
			);

			// Handle missing settings
			if(!$this->get('id') && $errors == NULL) {
				$this->set('allow_multiple', 1);
				$this->set('show_preview', 1);
			}
			
			// Setting: allow multiple
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_multiple]" value="1" type="checkbox"' . ($this->get('allow_multiple') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow selection of multiple items') . ' <i>' . __('This will switch between single and multiple item lists') . '</i>');
			$div = $fieldset->getChildren();
			$div[0]->appendChild($setting);
			
			// Append behaviour settings
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// Display
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Display') . '</legend>');

			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			// Caption input
			$div->appendChild($this->__groupContentGenerator('caption', __('Caption'), $sections, $errors));
			
			// Custom drop text
			$div->appendChild($this->__groupContentGenerator('droptext', __('Drop text'), $sections, $errors));

			$fieldset->appendChild($div);

			// Preview options
			$label = new XMLElement('label');
			$input = Widget::Input('fields[' . $this->get('sortorder') . '][show_preview]', 1, 'checkbox');
			if($this->get('show_preview') != 0) {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue(__('%s Show thumbnail images', array($input->generate())));
			$fieldset->appendChild($label);			
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// Data Source
			$fieldset = new XMLElement('fieldset', '<legend>' . __('Data Source XML') . '</legend>');

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
				foreach($group['fields'] as $field) {
					if($field->get('id') != $this->get('id')) {

						// Fetch includable elements (formatted/unformatted)
						$elements = $field->fetchIncludableElements();

						// Loop through elements
						if(is_array($elements) && !empty($elements)) {
							foreach($elements as $name) {

								// Get mode
								$element_mode = '';
								if(strpos($name, ': ') !== false) {
									$element_mode = explode(': ', $name);
									$element_mode = ':' . $element_mode[1];
								}

								// Generate ID
								$element_id = $field->get('id') . $element_mode;

								// Selection status
								$element_status = false;
								if(in_array($field->get('id') . $element_mode, explode(',', $this->get('included_fields')))) {
									$element_status = true;
								}

								// Generate field list
								$fields[] = array($element_id, $element_status, $name);

							}
						}

					}

				}

				// Generate includable field list options
				if(is_array($fields) && !empty($fields)) {
					$options[] = array('label' => $group['section']->get('id'), 'options' => $fields);
				}
			}			
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][included_fields][]', $options, array('multiple' => 'multiple', 'class' => 'datasource')));
			$fieldset->appendChild($label);
			
			$wrapper->appendChild($fieldset);

		/*-----------------------------------------------------------------------*/

			// General
			$fieldset = new XMLElement('fieldset');
			$this->appendShowColumnCheckbox($fieldset);
			$this->appendRequiredCheckbox($fieldset);
			$wrapper->appendChild($fieldset);

		}
		
		/**
		 * Generate a content generator consisting of a text input field and 
		 * an inline tag list with field name.
		 *
		 * @param string $name
		 *  handle of the group
		 * @param string $title
		 *  title used for the group label
		 * @param SectionManager $sections
		 *  section object
		 * @return XMLElement
		 *  returns the content generator element
		 */
		private function __groupContentGenerator($name, $title, $sections, $errors) {
			$container = new XMLElement('div');
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
					$filter = new XMLElement('ul', NULL, array('class' => 'tags inline'));
					foreach($values as $handle => $fields) {
						$filter->appendChild(new XMLElement('li', '{$' . $handle . '}', array('rel' => implode(' ', $fields))));
					}
					$container->appendChild($filter);
				}
				
			}
			
			return $container;
		}

		/**
		 * Check the field's settings to ensure they are valid on the section
		 * editor
		 *
		 * @param array $errors
		 *	the array to populate with the errors found.
		 * @param boolean $checkFoeDuplicates (optional)
		 *	if set to true, duplicate field entries will be flagged as errors.
		 *	this defaults to true.
		 * @return number
		 *	returns the status of the checking. if errors has been populated with
		 *	any errors self::__ERROR__, self__OK__ otherwise.
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
					$errors['droptext'] = __('%s has to be well-formed. Please check opening and closing tags.', array(__('Droptext')));
				}
			}

			parent::checkFields($errors, $checkForDuplicates);
		}

		/**
		 * Commit the settings of this field from the section editor to
		 * create an instance of this field in a section.
		 *
		 * @return boolean
		 *	true if the commit was successful, false otherwise.
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
			
			// Save new stage settings for this field
			Stage::saveSettings($this->get('id'), $this->get('stage'), 'subsectionmanager');

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

			// Data source fields
			$fields['included_fields'] = (is_null($this->get('included_fields')) ? NULL : implode(',', $this->get('included_fields')));

			// Delete old field settings
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1"
			);

			// Save new field setting
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

		}

		/**
		 * Display the publish panel for this field. The display panel is the
		 * interface to create the data in instances of this field once added
		 * to a section.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the html defined user interface to this
		 *	field.
		 * @param array $data (optional)
		 *	any existing data that has been supplied for this field instance.
		 *	this is encoded as an array of columns, each column maps to an
		 *	array of row indexes to the contents of that column. this defaults
		 *	to null.
		 * @param mixed $flagWithError (optional)
		 *	flag with error defaults to null.
		 * @param string $fieldnamePrefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $fieldnameSuffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param number $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
		
			// Get version number
			$about = Symphony::ExtensionManager()->about('subsectionmanager');
			$version = strtolower($about['version']);	

			// Append assets
			if(Administration::instance() instanceof Symphony && !is_null(Administration::instance()->Page)) {
				Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.js?v=' . $version, 101, false);
				Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.css?v=' . $version, 'screen', 103, false);
				Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.js?v=' . $version, 102, false);
				Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.css?v=' . $version, 'screen', 104, false);
			}

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
			$currentPageURL = Symphony::Engine()->getCurrentPageURL();
			preg_match_all('/\d+/', $currentPageURL, $entry_id, PREG_PATTERN_ORDER);
			$entry_id = $entry_id[0][count($entry_id[0])-1];
			if($entry_id) {
				$order = Symphony::Database()->fetchVar('order', 0,
					"SELECT `order`
					FROM `tbl_fields_stage_sorting`
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

			// Get stage settings
			$settings = ' ' . implode(' ', Stage::getComponents($this->get('id')));
			
			// Create stage
			$stage = new XMLElement('div', NULL, array('class' => 'stage' . $settings . ($this->get('show_preview') == 1 ? ' preview' : '') . ($this->get('allow_multiple') == 1 ? ' multiple' : ' single')));
			$content['empty'] = '<li class="empty message"><span>' . __('There are no selected items') . '</span></li>';
			$selected = new XMLElement('ul', $content['empty'] . $content['html'], array('class' => 'selection'));
			$stage->appendChild($selected);
			
			// Append item template
			$thumb = '<img src="' . URL . '/extensions/subsectionmanager/assets/images/new.gif" width="40" height="40" class="thumb" />';
			$item = new XMLElement('li', $thumb . '<span>' . __('New item') . '<br /><em>' . __('Please fill out the form below.') . '</em></span><a class="destructor">' . __('Remove Item') . '</a>', array('class' => 'template create preview'));
			$selected->appendChild($item);
			
			// Append drawer template
			$subsection_handle = Symphony::Database()->fetchVar('handle', 0,
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
		 * Process the raw field data.
		 *
		 * @param mixed $data
		 *	post data from the entry form
		 * @param reference $status
		 *	the status code resultant from processing the data.
		 * @param boolean $simulate (optional)
		 *	true if this will tell the CF's to simulate data creation, false
		 *	otherwise. this defaults to false. this is important if clients
		 *	will be deleting or adding data outside of the main entry object
		 *	commit function.
		 * @param mixed $entry_id (optional)
		 *	the current entry. defaults to null.
		 * @return array[string]mixed
		 *	the processed field data.
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

			return Symphony::Database()->query(
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
		 * Format this field value for display in the administration pages summary tables.
		 *
		 * @param array $data
		 *	the data to use to generate the summary string.
		 * @param XMLElement $link (optional)
		 *	an xml link structure to append the content of this to provided it is not
		 *	null. it defaults to null.
		 * @return string
		 *	the formatted string summary of the values of this field instance.
		 */
		function prepareTableValue($data, XMLElement $link=NULL) {
			if(empty($data['relation_id'])) return NULL;

			// Single select
			if($this->get('allow_multiple') == 0) {
				$subsection = new SubsectionManager($this->_Parent);
				$content = $subsection->generate(null, $this->get('id'), $this->get('subsection_id'), $data['relation_id'], true);
				
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
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the xml representation of this to.
		 * @param array $data
		 *	the current set of values for this field. the values are structured as
		 *	for displayPublishPanel.
		 * @param boolean $encode (optional)
		 *	flag as to whether this should be html encoded prior to output. this
		 *	defaults to false.
		 * @param string $mode
		 *	 A field can provide ways to output this field's data. For instance a mode
		 *  could be 'items' or 'full' and then the function would display the data
		 *  in a different way depending on what was selected in the datasource
		 *  included elements.
		 * @param number $entry_id (optional)
		 *	the identifier of this field entry instance. defaults to null.
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
			$order = Symphony::Database()->fetchVar('order', 0,
				"SELECT `order`
				FROM `tbl_fields_stage_sorting`
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

				// Get included elements
				$included = array();
				$included_fields = explode(',', $this->get('included_fields'));
				foreach($included_fields as $included_field) {

					// Get fields with modes
					if(strpos($included_field, ':') !== false) {
						$component = explode(':', $included_field);
						$included[$component[0]][] = $component[1];
					}

					// Get fields without modes
					else {
						$included[$included_field] = NULL;
					}
				}

				// Populate entry element
				foreach ($entry_data as $field_id => $values) {

					// Only append if field is listed or if list empty
					if(array_key_exists($field_id, $included) || empty($included_fields[0])) {
						$item_id = $entry->get('id');
						$item->setAttribute('id', $item_id);
						$field =& $entryManager->fieldManager->fetch($field_id);

						// Append fields with modes
						if($included[$field_id] !== NULL) {
							foreach($included[$field_id] as $mode) {
								$field->appendFormattedElement($item, $values, false, $mode);
							}					
						}

						// Append fields without modes
						else {
							$field->appendFormattedElement($item, $values, false, NULL);
						}
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
		 * Function to format this field if it chosen in a data-source to be
		 * output as a parameter in the XML
		 *
		 * @param array $data
		 *	 The data for this field from it's tbl_entry_data_{id} table
		 * @return string
		 *	 The formatted value to be used as the parameter
		 */
		public function getParameterPoolValue($data) {

			if(is_array($data['relation_id'])) return implode(", ", $data['relation_id']);
			return $data['relation_id'];

		}

		/**
		 * Display the default data-source filter panel.
		 *
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed $data (optional)
		 *	the input data. this defaults to null.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 * @param string $fieldNamePrefix
		 *	the prefix to apply to the display of this.
		 * @param string $fieldNameSuffix
		 *	the suffix to apply to the display of this.
		 */
		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			$text = new XMLElement('p', __('Use comma separated entry ids for filtering.'), array('class' => 'help') );
			$wrapper->appendChild($text);

		}

 		/**
		 * Return sample markup for the event editor.
		 *
		 * @return XMLElement
		 *	a label widget containing the formatted field element name of this.
		 */
		public function getExampleFormMarkup() {
		
			// Nothing to show here yet
			return Widget::Select('fields['.$this->get('element_name').']', array(array('...')));
			
		}

	}
