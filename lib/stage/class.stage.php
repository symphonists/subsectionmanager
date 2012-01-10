<?php

	/**
	 * @package stage
	 */
	/**
	 * The Stage class offers function to display and save
	 * Stage settings in the section editor.
	 */
	class Stage {

		/**
		 * Install Stage by creating tables for settings and sortings if needed.
		 *
		 * @return boolean
		 *  Returns true, if installation was completed successfully, false otherwise
		 */
		public static function install() {
			$status = array();

			// Create database stage table
			$status[] = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_stage` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL default '0',
					`constructable` smallint(1) default '0',
					`destructable` smallint(1) default '0',
					`draggable` smallint(1) default '0',
					`droppable` smallint(1) default '0',
					`searchable` smallint(1) default '0',
					`context` varchar(255) default NULL,
					PRIMARY KEY  (`id`)
				) TYPE=MyISAM;"
			);

			// Create database sorting table
			$status[] = Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_fields_stage_sorting` (
					`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
					`entry_id` int(11) NOT NULL,
					`field_id` int(11) NOT NULL,
					`order` text,
					`context` varchar(255) default NULL,
					PRIMARY KEY (`id`)
				) TYPE=MyISAM;"
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
		 * Display settings in the section editor.
		 *
		 * @param number $field_id
		 *  ID of the field linked to the Stage instance
		 * @param number $position
		 *  Field position in section editor
		 * @param string $title
		 *  Title of the settings fieldset
		 * @param array $order
		 *  Optional array to sort and limit the setting display
		 * @return XMLElement
		 *  Returns the settings fieldset
		 */
		public static function displaySettings($field_id, $position, $title, $order=NULL) {

			// Create settings fieldset
			$fieldset = new XMLElement('fieldset', '<legend>' . $title . '</legend>');
			$group = new XMLElement('div', NULL, array('class' => 'compact'));
			$fieldset->appendChild($group);

			// Get stage settings
			$stage = Symphony::Database()->fetchRow(0,
				"SELECT * FROM tbl_fields_stage WHERE field_id = '" . $field_id . "' LIMIT 1"
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
			}

			// Setting order
			if(empty($order)) {
				$order = array('constructable', 'destructable', 'searchable', 'droppable', 'draggable');
			}

			// Create settings
			foreach($order as $setting) {

				// Get copy
				if($setting == 'constructable') {
					$option = __('Allow creation of new items');
					$description = __('This will add a <code>Create New</code> button to the interface');
				}
				elseif($setting == 'destructable') {
					$option = __('Allow deselection of items');
					$description = __('This will add a <code>Remove</code> button to the interface');
				}
				elseif($setting == 'searchable') {
					$option = __('Allow selection of items from a list of existing items');
					$description = __('This will add a search field to the interface');
				}
				elseif($setting == 'droppable') {
					$option = __('Allow dropping of items');
					$description = __('This will enable item dropping on textareas');
				}
				elseif($setting == 'draggable') {
					$option = __('Allow sorting of items');
					$description = __('This will enable item dragging and reordering');
				}

				// Layout
				$label = new XMLElement('label', '<input name="fields[' . $position . '][stage][' . $setting . ']" value="1" type="checkbox"' . ($stage[$setting] == 0 ? '' : ' checked="checked"') . '/> ' . $option . ' <i>' . $description . '</i>');
				$group->appendChild($label);
			}

			// Return stage settings
			return $fieldset;
		}

		/**
		 * Save setting in the section editor.
		 *
		 * @param number $field_id
		 *  ID of the field linked to this Stage instance
		 * @param array $data
		 *  Data to be stored
		 * @param string $context
		 *  Context of the Stage instance
		 */
		public static function saveSettings($field_id, $data, $context) {
			Symphony::Database()->query(
				"DELETE FROM `tbl_fields_stage` WHERE `field_id` = '$field_id' LIMIT 1"
			);

			// Save new stage settings for this field
			if(is_array($data)) {
				Symphony::Database()->query(
					"INSERT INTO `tbl_fields_stage` (`field_id`, " . implode(', ', array_keys($data)) . ", `context`) VALUES ($field_id, " . implode(', ', $data) . ", '$context')"
				);
			}
			else {
				Symphony::Database()->query(
					"INSERT INTO `tbl_fields_stage` (`field_id`, `context`) VALUES ($field_id, '$context')"
				);
			}
		}

		/**
		 * Create stage interface.
		 *
		 * @param string $handle
		 *  Handle of the parent extension
		 * @param integer $id
		 *  ID of the parent field
		 * @param string $custom_settings
		 *  Custom stage settings separated with spaces
		 * @param array $content
		 *  An array of XMLElements that should be appended to the stage selection
		 * @return XMLElement
		 *  Return stage interface
		 */
		public static function create($handle, $id, $custom_settings, $content=array()) {

			// Get stage settings
			$settings = 'stage ' . implode(' ', Stage::getComponents($id)) . ' ' . $custom_settings;

			// Create stage
			$stage = new XMLElement('div', NULL, array('class' => $settings));
			$selection = new XMLElement('ul', NULL, array('class' => 'selection'));
			$selection->appendChildArray($content);
			$stage->appendChild($selection);
			return $stage;
		}

		/**
		 * Get components
		 *
		 * @param number $field_id
		 *  ID of the field linked to this Stage instance
		 * @return array
		 *  Array of active components
		 */
		public static function getComponents($field_id) {

			// Fetch settings
			$settings = Symphony::Database()->fetchRow(0,
				"SELECT `constructable`, `destructable`, `draggable`, `droppable`, `searchable` FROM `tbl_fields_stage` WHERE `field_id` = '" . $field_id . "' LIMIT 1"
			);

			// Remove disabled components
			foreach($settings as $key => $value) {
				if($value == 0) unset($settings[$key]);
			}

			// Return active components
			return array_keys($settings);
		}

	}
