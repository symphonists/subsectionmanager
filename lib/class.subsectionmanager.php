<?php

	/**
	 * SUBSECTION MANAGER
	 * for Symphony CMS
	 *
	 * Nils Hörrmann, http://www.nilshoerrmann.de
	 */
	class SubsectionManager {

		public function generate($subsection_field, $subsection_id, $items=NULL, $full=false, $recurse=0) {
			static $done = array();
			if ($done[$subsection_field] >= $recurse + 1) return array('options' => array(), 'html' => '', 'preview' => '');	
			$done[$subsection_field] += 1;

			// Fetch subsection meta data
			$meta = Symphony::Database()->fetch(
				"SELECT filter_tags, caption, droptext, show_preview
				FROM tbl_fields_subsectionmanager
				WHERE field_id = '" . intval($subsection_field) . "'
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
			$sectionManager = new SectionManager(Symphony::Engine());
		  	$subsection = $sectionManager->fetch($subsection_id, 'ASC', 'name');
		  	$fields = $subsection->fetchFields();
		  	$entries = $this->__filterEntries($subsection_id, $fields, $meta[0]['filter_tags'], (!empty($full) ? NULL : $items));
		  	$droptext = $meta[0]['droptext'];
		  	
		  	// Check caption
		  	$caption = $meta[0]['caption'];
		  	if($caption == '') {
		  		
		  		// Fetch name of primary field in subsection
				$primary = Symphony::Database()->fetch(
					"SELECT element_name
					FROM tbl_fields
					WHERE parent_section = '" . intval($subsection_id) . "'
					AND sortorder = '0'
					LIMIT 1"
				);
				$caption = '{$' . $primary[0]['element_name'] . '}';
			  		
		  	}
		  	
		  	// Layout subsection data
		  	$data = $this->__layoutSubsection($entries, $fields, $caption, $droptext, $mode, ($full ? $items : NULL));

			$done[$subsection_field] -= 1;
		  	return $data;
		  	
		}
		
		private function __filterEntries($subsection_id, $fields, $filter, $items) {

			// Fetch entry data
			$entryManager = new EntryManager(Symphony::Engine());
			$entries = $entryManager->fetch($items, $subsection_id);

			// Return early if there were no $entries found
			if(empty($entries) || !is_array($entries)) {
				return array();
			}

			// Setup filter
		  	$tag_fields = array();
			$gogoes = array();
			$nonos = array();
			$filters = (empty($filter) ? array() : explode(', ', $filter));
			if(!empty($filters)) {
			  	// Fetch taglist, select and upload fields
			  	if(is_array($fields)) {
					foreach($fields as $field) {
						if(in_array($field->get('type'), array('taglist', 'select'))) {
							$tag_fields[] = $field->get('id');
						}
					}
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
			}

			// Filter entries and add select options
			$entry_data = array();
			foreach($entries as $entry) {
				$data = $entry->getData();

				if(!empty($filters)) {
					// Collect taglist and select field values
					$tags = array();
					foreach($tag_fields as $field_id) {
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
					if(!empty($filter_nonos) || (empty($filter_gogoes) && !empty($gogoes))) {
						continue;
					}
				}
	
				// Keep sort order of selected items
				$index = (is_array($items) ? array_search($entry->get('id'), $items) : $index+1);

				// array_search may return false. It should not happen, because all $items should
				// exist in $entries, but... check that, just to be sure.
				if($index === false) continue;

				$entry_data[$index] = array(
					'data' => $data,
					'id' => $entry->get('id')	
				);
			}

			// Keep sort order of selected items
			if(is_array($items)) ksort($entry_data, SORT_NUMERIC);

			// Return filtered entry data
			return $entry_data;
		}
		
		private function __layoutSubsection($entries, $fields, $caption_template, $droptext_template, $mode, $selected = NULL) {
			$html = array();

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

			$options = array();
			if(is_array($entries)) {
				foreach($entries as $index => $entry) {
				
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

					// Generate layout
					$thumb = $type = $preview = $template = '';
					foreach($fields as $field) {
						$field_name = $field->get('element_name');
						$field_id = $field->get('id');

						// Get value
						if(is_callable(array($field, 'preparePlainTextValue'))) {
							$field_value = $field->preparePlainTextValue($entry['data'][$field_id], $entry['id']);
						}
						else {
							$field_value = strip_tags($field->prepareTableValue($entry['data'][$field_id]));
						}

						// Caption & Drop text
						if(empty($field_value) || $field_value == __('None')) {
							$caption = preg_replace('/{\$' . $field_name . '(:([^}]*))?}/U', '$2', $caption);
							$droptext = preg_replace('/{\$' . $field_name . '(:([^}]*))?}/U', '$2', $droptext);
						}
						else {
							$caption = preg_replace('/{\$' . $field_name . '(:[^}]*)?}/', $field_value, $caption);
							$droptext = preg_replace('/{\$' . $field_name . '(:[^}]*)?}/', $field_value, $droptext);
						}
							
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

					// Populate select options
					$options[] = array($entry['id'], (is_array($selected) ? in_array($entry['id'], $selected) : true), strip_tags($caption));

					// Create stage template
					if($type == 'image') {
						$template = str_replace('{$preview}', $preview, $templates[$mode]['image']);
						$template = str_replace('{$href}', $href, $template);
						$template = str_replace('{$value}', $entry['id'], $template);
						$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
						$tmp = str_replace('{$caption}', $caption, $template);
					}
					elseif($type == 'file') {
						$template = str_replace('{$type}', $preview, $templates[$mode]['file']);
						$template = str_replace('{$href}', $href, $template);
						$template = str_replace('{$value}', $entry['id'], $template);
						$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
						$tmp = str_replace('{$caption}', $caption, $template);
					}
					else {
						$template = str_replace('{$preview}', $entry['id'], $templates[$mode]['text']);
						$template = str_replace('{$value}', $entry['id'], $template);
						$template = str_replace('{$droptext}', htmlspecialchars($droptext), $template);
						$tmp = str_replace('{$caption}', $caption, $template);
					}
						
					// Remove empty drop texts
					$html[strip_tags($tmp)] = str_replace(' data-drop=""', '', $tmp);
						
					// Create publish index template
					if($type == 'image') {
						$preview = '<img src="' . URL . '/image/2/40/40/5' . $preview . '" width="40" height="40" /><span>' . $caption . '</span></a>';
					}
					else {
						$preview = $caption;
					}
				}
			}
			
			// Sort html
			ksort($html);

			// Return options and html
			return array(
				'options' => $options,
				'html' => implode('', $html),
				'preview' => $preview
			);		
		}
		
	}
