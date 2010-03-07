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
		
		function generate($items, $subsection_field, $subsection_id, $full=false) {
		
			if($items == null) $items = array();
			$this->_Items = $items;
		
			// Fetch subsection meta data
			$meta = Administration::instance()->Database->fetch(
				"SELECT filter_tags, caption
				FROM tbl_fields_subsectionmanager
				WHERE field_id = '$subsection_field'
				LIMIT 1"
			);
		
			// Fetch entry data
			$sectionManager = new SectionManager($this->_Parent);
		  	$subsection = $sectionManager->fetch($subsection_id, 'ASC', 'name');
		  	$fields = $subsection->fetchFields();
		  	$entries = $this->__filterEntries($subsection_id, $fields, $meta[0]['filter_tags']);
		  	
		  	// Layout subsection data
		  	$data = $this->__layoutSubsection($entries, $fields, $meta[0]['caption'], $full);
		  	return $data;
		  	
		}
		
		function __filterEntries($subsection_id, $fields, $filter) {
		
		  	// Fetch taglist, select and upload fields
		  	$tag_fields = array();
			foreach($fields as $field) {
				if(in_array($field->get('type'), array('taglist', 'select'))) {
					$tag_fields[] = $field->get('id');
				}
			}

			// Fetch entry data
			$entryManager = new EntryManager($this->_Parent);
			$entries = $entryManager->fetch(NULL, $subsection_id);

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
						$tag_values = $entry_data[$field_id]['value'];
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
		
		function __layoutSubsection($entries, $fields, $caption_template, $full) {
		
			// Templates
			$templates = array(
				'text' => '<li value="{$value}"><span>{$caption}</span></li>',
				'image' => '<li value="{$value}" class="image preview"><img src="' . URL . '/image/2/40/40/5{$preview}" width="40" height="40" /><span>{$caption}</span></li>',
				'file' => '<li value="{$value}" class="file preview"><span class="file">{$type}</span><span>{$caption}</span></li>'
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
					
					// Populate select options
					$options[] = array($entry['id'], in_array($entry['id'], $this->_Items), $field_value);
					
					// Generate subsection values
					$caption = $caption_template;
					if(in_array($entry['id'], $this->_Items) || $full) {
						$thumb = $type = $preview = $template = '';
						foreach($fields as $field) {
							$field_name = $field->get('element_name');
							$field_id = $field->get('id');
							$field_data = $entry['data'][$field_id]['value'];
							
							if(is_array($field_data)) {
								$field_value = implode(', ', $field_data);				
							}
							elseif(empty($field_data) && $entry['data'][$field_id]['file']) {
								$field_value = $entry['data'][$field_id]['file'];
							}
							else {
								$field_value = $field_data;				
							}
												
							// Caption
							$caption = str_replace('{$' . $field_name . '}', $field_value, $caption);
							
							// Find upload fields
							if(strpos($field->get('type'), 'upload') !== false && !empty($entry['data'][$field->get('id')]['file'])) {
							
								// Image
								if(strpos($entry['data'][$field->get('id')]['mimetype'], 'image') !== false) {
									$type = 'image';
									$preview = $entry['data'][$field->get('id')]['file'];
								}
								
								// File
								else {
									$type = 'file';
									$preview = pathinfo($entry['data'][$field->get('id')]['file'], PATHINFO_EXTENSION);
								}
								
							}
						}
						
						// Apply template
						if($type == 'image') {
							$template = str_replace('{$preview}', $preview, $templates['image']);
							$template = str_replace('{$value}', $entry['id'], $template);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						elseif($type == 'file') {
							$template = str_replace('{$type}', $preview, $templates['file']);
							$template = str_replace('{$value}', $entry['id'], $template);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						else {
							$template = str_replace('{$preview}', $entry['id'], $templates['text']);
							$html .= str_replace('{$caption}', $caption, $template);
						}
						
					}
				}
			}
						
			// Return options and html
			return array(
				'options' => $options,
				'html' => $html
			);		
		}
		
	}
	