<?php
 
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.extensionmanager.php');
	
	class contentExtensionSubsectionmanagerDeactivate extends AdministrationPage {
 
		public function __construct(&$parent){
			parent::__construct($parent);
		}

		/**
		 * Mediathek and Subsection Manager cannot be used simultaneously: 
		 * This page deactivates one of these two extensions based on the context and returns to the extension overview.
		 */
		public function build($context) {
		
			$ExtensionManager = new ExtensionManager(Administration::instance());
			
			// Deactivate extension
			if($context[0] == 'mediathek' || $context[0] == 'subsectionmanager') {
				$ExtensionManager->disable($context[0]);
			}
			
			// Return to extension overview
			redirect(URL . '/symphony/system/extensions/');
			
		}

	}
 