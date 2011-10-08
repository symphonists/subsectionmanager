<?php
 
	/**
	 * @package content
	 */
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.extensionmanager.php');
	
	class contentExtensionSubsectionmanagerUninstall extends AdministrationPage {

		/**
		 * Mediathek and Subsection Manager cannot be used simultaneously: 
		 * This page uninstalls one of these two extensions based on the context and returns to the extension overview.
		 *
		 * @param array $context
		 *  An associative array describing this pages context. This
		 *  can include the section handle, the current entry_id, the page
		 *  name and any flags such as 'saved' or 'created'. This list is not exhaustive
		 *  and extensions can add their own keys to the array.
		 */
		public function build($context) {
			
			// Deactivate extension
			if($context[0] == 'mediathek' || $context[0] == 'subsectionmanager') {
				Symphony::ExtensionManager()->uninstall($context[0]);
			}
			
			// Return to extension overview
			redirect(SYMPHONY_URL . '/system/extensions/');
			
		}

	}
 