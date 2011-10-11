<?php

	/**
	 * @package subsectionmanager
	 */
	/**
	 * This field provides inline subsection management. 
	 */
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');
	if(!class_exists('Stage')) {
		require_once(EXTENSIONS . '/subsectionmanager/lib/stage/class.stage.php');
	}

	Class fieldSubsectionmanager extends Field {

		static $sortOrder = NULL;

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#__construct
		 */
		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Manager');
			$this->_required = true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#canFilter
		 */
		function canFilter(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#allowDatasourceParamOutput
		 */
		function allowDatasourceParamOutput(){
			return true;
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displaySettingsPanel
		 */
		function displaySettingsPanel(&$wrapper, $errors=NULL) {

			// Basics
			parent::displaySettingsPanel($wrapper, $errors);

		/*-----------------------------------------------------------------------*/

			// Get current section id
			$section_id = Symphony::Engine()->Page->_context[1];

			// Related section
			$label = new XMLElement('label', __('Subsection'));
			$sectionManager = new SectionManager(Symphony::Engine());
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
			
			// Get settings
			$div = $fieldset->getChildren();

			// Setting: allow multiple
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][allow_multiple]" value="1" type="checkbox"' . ($this->get('allow_multiple') == 0 ? '' : ' checked="checked"') . '/> ' . __('Allow selection of multiple items') . ' <i>' . __('This will switch between single and multiple item lists') . '</i>');
			$div[0]->appendChild($setting);

			// Setting: disallow editing
			$setting = new XMLElement('label', '<input name="fields[' . $this->get('sortorder') . '][lock]" value="1" type="checkbox"' . ($this->get('lock') == 0 ? '' : ' checked="checked"') . '/> ' . __('Disallow item editing') . ' <i>' . __('This will lock items and disable the inline editor') . '</i>');
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
		 * 
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#checkFields
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#commit
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
			$fields['lock'] = ($this->get('lock') ? 1 : 0);
						
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
			$settings = Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());

			// Remove old secion association
			$this->removeSectionAssociation($id);

			// Save new section association
			$association = $this->createSectionAssociation(NULL, $this->get('subsection_id'), $id, $id, false);
			
			if($settings && $association) {
				return true;
			} 
			else {
				return false;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#createSectionAssociation
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

		/**
		 * If you need to fetch the pure data this field returns, please use getDefaultPublishContent()
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayPublishPanel
		 */
		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
		
			// Houston, we have problem: we've been called out of context!
			$callback = Administration::instance()->getPageCallback();
			if($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				$this->getDefaultPublishContent($wrapper);
				return;
			}

			// Append styles
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.css', 'screen', 101, false);
			Symphony::Engine()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.css', 'screen', 102, false);

			// Append scripts
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/stage/stage.publish.js', 103, false);
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectionmanager.publish.js', 104, false);
			Symphony::Engine()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/lib/resize/jquery.ba-resize.js', 105, false);
			
			// Get Subsection
			$subsection = new SubsectionManager;
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
			$page = Symphony::Engine()->getPageCallback();
			$entry_id = $page['context']['entry_id'];
			if(!empty($entry_id)) {
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
			$stage = new XMLElement('div', NULL, array('class' => 'stage' . $settings . ($this->get('show_preview') == 1 ? ' preview' : '') . ($this->get('allow_multiple') == 1 ? ' multiple' : ' single') . ($this->get('lock') == 1 ? ' locked' : '')));
			$content['empty'] = '<li class="empty message"><span>' . __('There are no selected items') . '</span></li>';
			$selected = new XMLElement('ul', $content['empty'] . $content['html'], array('class' => 'selection'));
			$stage->appendChild($selected);
			
			// Append item template
			$thumb = '<img src="' . URL . '/extensions/subsectionmanager/assets/images/new.gif" width="40" height="40" class="thumb" />';
			$item = new XMLElement('li', $thumb . '<span>' . __('New item') . '<br /><em>' . __('Please fill out the form below.') . '</em></span><a class="destructor">&#215;</a>', array('class' => 'template create preview'));
			$selected->appendChild($item);
			
			// Append drawer template
			$subsection_handle = Symphony::Database()->fetchVar('handle', 0,
				"SELECT `handle`
				FROM `tbl_sections`
				WHERE `id` = '" . $this->get('subsection_id') . "'
				LIMIT 1"
			);
			$create_new = SYMPHONY_URL . '/publish/' . $subsection_handle;
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
		 * Get default publish content
		 */
		function getDefaultPublishContent(&$wrapper) {
			
			// Get items
			$subsection = new SubsectionManager;
			$content = $subsection->generate(null, $this->get('id'), $this->get('subsection_id'), NULL, true);
			
			// Append items
			$select = Widget::Select(null, $content['options']);
			$wrapper->appendChild($select);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#processRawFieldData
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#createTable
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#prepareTableValue
		 */
		function prepareTableValue($data, XMLElement $link=NULL) {
			if(empty($data['relation_id'])) return NULL;

			// Single select
			if($this->get('allow_multiple') == 0 || count($data['relation_id']) === 1) {
				$subsection = new SubsectionManager;
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#buildSortingSQL
		 */
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			// Normally there is no need to sort by SubsectionManager field,
			// so we can use it for internal sorting (tough it probably could be 
			// made "public" for DataSources). 
			$entry_id = self::$sortOrder;
			self::$sortOrder = NULL;
			if (empty($entry_id)) return;

			// Get sort order for entries
			$order = Symphony::Database()->fetchVar('order', 0,
				"SELECT `order`
				FROM `tbl_fields_stage_sorting`
				WHERE `entry_id` = " . intval($entry_id) . "
				AND `field_id` = " . $this->get('id') . "
				LIMIT 1"
			);

			if (empty($order)) {
				$sort = 'ORDER BY `e`.`id` ASC';
			}
			else {
				// We could validate $order to be sure all values are integers,
				// but it always is validated before being saved to database.
				// Reverse order because FIELD() returns 0 for "not found".
				// So if order is "A,B,C,D", not found will be before them :(.
				// If sortorder was stored in database in reversed order, we could drop next line.
				$order = implode(',', array_reverse(explode(',', $order)));
				if (!empty($order)) {
					$sort = 'ORDER BY FIELD(`e`.`id`,'.$order.') DESC';
				}
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#buildDSRetrivalSQL
		 */
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false) {

		    // Current field id
		    $field_id = $this->get('id');

		    // Filters connected with AND
		    if($andOperation) {
		        foreach($data as $key => $value) {
		            $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
		            $where .= " AND `t$field_id$key`.relation_id = '" . intval($value) . "' ";
		        }
		    }

		    // Filters connected with OR
		    else {
		        $joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
		        $where .= " AND `t$field_id`.relation_id IN ('" . @implode("', '", array_map('intval', $data)) . "') ";
		    }

		    return true;
		}

		/**
		 *
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#appendFormattedElement
		 * @todo Sorting should be handled via system id
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
			if (!class_exists('EntryManager')) {
				require_once(TOOLKIT . '/class.entrymanager.php');
			}
			$entryManager = new EntryManager(Symphony::Engine());
			$entryManager->setFetchSortingField($this->get('id'));

			// Let our function know, that we are in "internal sorting" mode.
			self::$sortOrder = $wrapper->getAttribute('id');

			// Get sorted entries
			$sorted_entries = $entryManager->fetch($data['relation_id'], $this->get('subsection_id'));

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
								$field->appendFormattedElement($item, $values, $encode, $mode, $item_id);
							}					
						}

						// Append fields without modes
						else {
							$field->appendFormattedElement($item, $values, $encode, NULL, $item_id);
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
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchAssociatedEntrySearchValue
		 */
		public function fetchAssociatedEntrySearchValue($data, $field_id = null, $parent_entry_id = null){
			// $data would contain the related entries, but is usually `null` when called from the frontend
			// (when the field is not included in the DS, and only then "associated entry count" makes sense)
			if(!is_null($parent_entry_id)) {
				return $parent_entry_id;
			}
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#fetchAssociatedEntryCount
		 */
		public function fetchAssociatedEntryCount($value){
			if(isset($value)) {
				return Symphony::Database()->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `entry_id` = '$value'");
			} 
			else {
				return 0;
			}
		}
		
		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#getParameterPoolValue
		 */
		public function getParameterPoolValue($data) {
			return $data['relation_id'];
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#displayDatasourceFilterPanel
		 */
		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);
			$text = new XMLElement('p', __('Use comma separated entry ids for filtering.'), array('class' => 'help') );
			$wrapper->appendChild($text);
		}

		/**
		 * @see http://symphony-cms.com/learn/api/2.2/toolkit/field/#getExampleFormMarkup
		 */
		public function getExampleFormMarkup() {
			return Widget::Select('fields['.$this->get('element_name').']', array(array('...')));
		}

	}
