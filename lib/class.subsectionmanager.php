<?php

	/**
	 * SUBSECTION MANAGER
	 * for Symphony CMS
	 *
	 * Nils Hörrmann, http://www.nilshoerrmann.de
	 */
	class SubsectionManager {

		private $_Parent;
		private $_Items;

		function __construct(&$parent) {
			$this->_Parent = $parent;
		}
		
		function generate($items, $subsection_field, $subsection_id, $entry_id=NULL, $full=false) {
		
			if(!is_array($items)) $items = array($items);
			$this->_Items = $items;
		
			// Fetch subsection meta data
			$meta = Symphony::Database()->fetch(
				"SELECT filter_tags, caption, droptext, show_preview
				FROM tbl_fields_subsectionmanager
				WHERE field_id = '$subsection_field'
				LIMIT 1"
			);
			
			// Get display mode
			if($meta[0]['show_preview'] == 1) {
				$mode = 'preview';
			}
			else {
				$mode = 'plain';			
			}
		
			// Fetch entry data
			$sectionManager = new SectionManager($this->_Parent);
		  	$subsection = $sectionManager->fetch($subsection_id, 'ASC', 'name');
		  	$fields = $subsection->fetchFields();
		  	$entries = $this->__filterEntries($subsection_id, $fields, $meta[0]['filter_tags'], $entry_id);
		  	$droptext = $meta[0]['droptext'];
		  	
		  	// Check caption
		  	$caption = $meta[0]['caption'];
		  	if($caption == '') {
		  		
		  		// Fetch name of primary field in subsection
				$primary = Symphony::Database()->fetch(
					"SELECT element_name
					FROM tbl_fields
					WHERE parent_section = '$subsection_id'
					AND sortorder = '0'
					LIMIT 1"
				);
				$caption = '{$' . $primary[0]['element_name'] . '}';
			  		
		  	}
		  	
		  	// Layout subsection data
		  	$data = $this->__layoutSubsection($entries, $fields, $caption, $droptext, $mode, $full);
		  	return $data;
		  	
		}
		
		function __filterEntries($subsection_id, $fields, $filter, $entry_id) {
		
		  	// Fetch taglist, select and upload fields
		  	$tag_fields = array();
		  	if(is_array($fields)) {
				foreach($fields as $field) {
					if(in_array($field->get('type'), array('taglist', 'select'))) {
						$tag_fields[] = $field->get('id');
					}
				}
			}

			// Fetch entry data
			$entryManager = new EntryManager($this->_Parent);
			$entries = $entryManager->fetch($entry_id, $subsection_id);

			// Setup filter
			$gogoes = array();
			$nonos = array();
			$filters = array();
			if($filter != '') {
				$filters = explode(', ', $filter);
			}
			foreach($filters as $filter) {
				$operator = substr($filter, 0, 1);
				if($operator == '-') {
					$nonos[] = substr($filter, 1);
				}
				else {
					$gogoes[] = $filter;
				}
			}

			// Filter entries and add select options
			$field_data = array();
			if(is_array($entries) && !empty($entries)) {
				foreach($entries as $entry) {
					
					// Collect taglist and select field values
					$tags = array();
					foreach($tag_fields as $field_id) {
						$data = $entry->getData();
						$tag_values = $data[$field_id]['value'];
						if(!is_array($tag_values)) {
							$tag_values = array($tag_values);
						}
						$tags = array_merge($tags, $tag_values);
					}
	
					// Investigate entry exclusion
					$filter_nonos = array_intersect($tags, $nonos);
	
					// Investigate entry inclusion
					$filter_gogoes = array_intersect($tags, $gogoes);
	
					// Filter entries
					if(empty($filter_nonos) && (!empty($filter_gogoes) || empty($gogoes)) ) {
						$entry_data[] = array(
							'data' => $entry->getData(),
							'id' => $entry->get('id')	
						);
					}
	
				}
			}
			
			// Return filtered entry data
			return $entry_data;
		}
		
		function __layoutSubsection($entries, $fields, $caption_template, $droptext_template, $mode, $full) {

			// Templates
			$templates = array(
				'plain' => array(
					'text' => '<li data-value="{$value}" data-drop="{$droptext}"><span>{$caption}</span></li>',
					'image' => '<li data-value="{$value}" data-drop="{$droptext}"><a href="{$href}" class="image file handle">{$caption}</a></li>',
					'file' => '<li data-value="{$value}" data-drop="{$droptext}"><a href="{$href}" class="file handle">{$caption}</a></li>'
				),
				'preview' => array(
					'text' => '<li data-value="{$value}" data-drop="{$droptext}"><span>{$caption}</span></li>',
					'image' => '<li data-value="{$value}" data-drop="{$droptext}" class="preview"><img src="' . URL . '/image/2/40/40/5{$preview}" width="40" height="40" /><a href="{$href}" class="image file handle">{$caption}</a></li>',
					'file' => '<li data-value="{$value}" data-drop="{$droptext}" class="preview"><strong class="file">{$type}</strong><a href="{$href}" class="file handle">{$caption}</a></li>'					
				)
			);
			
			if(is_array($entries)) {
				foreach($entries as $entry) {
				
					// Fetch primary field
					$field_data = $entry['data'][$fields[0]->get('id')]['value'];
					
					// Fetch field value (string)
					if(!empty($field_data)) {
						if(is_array($field_data)) {
							$field_value = implode(', ', $field_data);
						}
						else {
							$field_value = $field_data;
						}
					}
					else {
						$field_value = __('Untitled');
					}
								
					// Generate subsection values
					$caption = $caption_template;
					$droptext = $droptext_template;
					if(in_array($entry['id'], $this->_Items) || $full) {

						// Populate select options
						$options[] = array($entry['id'], in_array($entry['id'], $this->_Items), General::sanitize($field_value));

						// Generate layout
						$thumb = $type = $preview = $template = '';
						foreach($fields as $field) {
							$field_name = $field->get('element_name');
							$field_id = $field->get('id');
							$field_data = $entry['data'][$field_id]['value'];
							
							// Tags
							if(is_array($field_data)) {
								$field_value = implode(', ', $field_data);				
							}
							
							// Files
							elseif(empty($field_data) && $entry['data'][$field_id]['file']) {
								$field_value = $entry['data'][$field_id]['file'];
							}
							
							// Relations
							elseif(empty($field_data) && ($entry['data'][$field_id]['relation_id'] || $entry['data'][$field_id]['related_field_id'])) {
								$field_value = strip_tags($field->prepareTableValue($entry['data'][$field_id]));
							}
							
							// Author
							elseif(empty($field_data) && $entry['data'][$field_id]['author_id']) {
								$field_value = $field->prepareTableValue($entry['data'][$field_id]);
							}
							
							// Default
							else {
								$field_value = $field_data;				
							}
												
							// Caption & Drop text
							$caption = str_replace('{$' . $field_name . '}', $field_value, $caption);
							$droptext = str_replace('{$' . $field_name . '}', $field_value, $droptext);
							
							// Find upload fields
							if(strpos($field->get('type'), 'upload') !== false && !empty($entry['data'][$field->get('id')]['file'])) {
							
								// Image
								if(strpos($entry['data'][$field->get('id')]['mimetype'], 'image') !== false) {
									$type = 'image';
									$preview = $entry['data'][$field->get('id')]['file'];
									$href = URL . '/workspace' . $preview;
								}
								
								// File
								else {
									$type = 'file';
									$preview = pathinfo($entry['data'][$field->get('id')]['file'], PATHINFO_EXTENSION);
									$href = $entry['data'][$field->get('id')]['file'];
								}
								
							}
						}

						// Create stage template
						if($type == 'image') {
							$template = str_replace('{$preview}', $preview, $templates[$mode]['image']);
							$template = str_replace('{$href}', $href, $template);
							$template = str_replace('{$value}', $entry['id'], $template);
							$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						elseif($type == 'file') {
							$template = str_replace('{$type}', $preview, $templates[$mode]['file']);
							$template = str_replace('{$href}', $href, $template);
							$template = str_replace('{$value}', $entry['id'], $template);
							$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						else {
							$template = str_replace('{$preview}', $entry['id'], $templates[$mode]['text']);
							$template = str_replace('{$value}', $entry['id'], $template);
							$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						
						// Create publish index template
						if($type == 'image') {
							$preview = '<img src="' . URL . '/image/2/40/40/5' . $preview . '" width="40" height="40" /><span>' . $caption . '</span></a>';
						}
						else {
							$preview = $caption;
						}
					}
				}
			}
			
			// Remove empty drop texts
			$html = str_replace(' data-drop=""', '', $html);		
						
			// Return options and html
			return array(
				'options' => $options,
				'html' => $html,
				'preview' => $preview
			);		
		}
		
	}
	