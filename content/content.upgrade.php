<?php
 
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionSubsectionmanagerUpgrade extends AdministrationPage {
 
		public function __construct(&$parent) {
			parent::__construct($parent);
		}

		function view() {
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Subsection Manager Upgrade'))));

			// Start upgrade
			$this->upgrade();

			// Display success message
			$this->appendSubheading(__('Subsection Manager Upgrade'));
			$introduction = new XMLElement(
				'fieldset', 
				'<legend>' . __('Upgrade successful') . '</legend>
				<p>' . __('Your Mediathek fields have successfully been upgrade to Subsection Manager.') . ' <br />' . __('Please delete the old Mediathek folder from your <code>extensions</code> folder.') . ' <br /><a href="' . URL . '/symphony/system/extensions/">' . __('Go back to the extension overview.') . '</a></p>', 
				array(
					'class' => 'settings'
				)
			);
			$this->Form->appendChild($introduction);

		}
		
		function upgrade() {
		
			// Fetch Mediathek fields
			$mediathek = Administration::instance()->Database->fetch("SELECT * FROM tbl_fields_mediathek LIMIT 100");
			
			// Create Subsection Manager instances
			foreach($mediathek as $subsection) {
			
				// Add data
				Administration::instance()->Database->query(
					"INSERT INTO tbl_fields_subsectionmanager (`field_id`, `subsection_id`, `filter_tags`, `caption`, `included_fields`, `allow_multiple`, `show_preview`) VALUES (" . $subsection['field_id'] . ", " . $subsection['related_section_id'] . ", '" . $subsection['filter_tags'] . "', '" . $subsection['caption'] . "', '" . $subsection['included_fields'] . "', " . ($subsection['allow_multiple_selection'] == 'yes' ? 1 : 0) . ", 1)"
				);

				// Add stage settings			
				Administration::instance()->Database->query(
					"INSERT INTO tbl_fields_stage (`field_id`, `constructable`, `destructable`, `draggable`, `droppable`, `searchable`, `context`) VALUES (" . $subsection['field_id'] . ", 1, 1, 1, 1, 1, 'subsectionmanager')"
				);

			}

			// Fetch sort orders
			$sortings = Administration::instance()->Database->fetch("SELECT * FROM tbl_fields_mediathek_sorting LIMIT 1000");
			
			// Store sort orders
			foreach($sortings as $sorting) {
				Administration::instance()->Database->query(
					"INSERT INTO tbl_fields_subsectionmanager_sorting (`entry_id`, `field_id`, `order`) VALUES (" . $sorting['entry_id'] . ", " . $sorting['field_id'] . ", '" . $sorting['order'] . "')"
				);
			}

			// Replace Mediathek by Subsection Manager fields
			Administration::instance()->Database->query(
				"UPDATE tbl_fields SET `type` = 'subsectionmanager' WHERE `type` = 'mediathek'"
			);
			
			// Uninstall Mediathek
			Administration::instance()->ExtensionManager->uninstall('mediathek');
				
		}
	
	}
 
?>