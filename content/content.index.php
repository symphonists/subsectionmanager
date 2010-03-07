<?php
 
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
//	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');
	
	class contentExtensionSubsectionmanagerIndex extends AdministrationPage {
 
		public function __construct(&$parent){
			parent::__construct($parent);
		}

		public function __viewIndex() {
			$subsection = new SubsectionManager($this->_Parent);
			$content = $subsection->generate(null, $_GET['id'], $_GET['section'], true);
			echo $content['html'];
			exit;
		}

	}
 
?>