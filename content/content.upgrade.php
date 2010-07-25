<?php
 
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionSubsectionmanagerUpgrade extends AdministrationPage {
 
		public function __construct(&$parent) {
			parent::__construct($parent);
		}

		function view() {
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Subsection Manager Upgrade'))));
			$this->appendSubheading(__('Subsection Manager Upgrade'));		
		}	
	
	}
 
?>