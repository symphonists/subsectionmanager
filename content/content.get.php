<?php
 
	/**
	 * @package content
	 */
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	require_once(EXTENSIONS . '/subsectionmanager/lib/class.subsectionmanager.php');
	
	class contentExtensionSubsectionmanagerGet extends AdministrationPage {

		/**
		 * Used to fetch subsection items via an AJAX request.
		 */
		public function __viewIndex() {
			$subsection = new SubsectionManager();
			
			// Set flags
			$flags = SubsectionManager::GETHTML;
			if(!isset($_GET['entry'])) {
				$flags |= SubsectionManager::GETALLITEMS;
			}

			// Get items
			$content = $subsection->generate(intval($_GET['id']), intval($_GET['section']), (intval($_GET['entry']) ? intval($_GET['entry']) : NULL), 0, $flags);
			echo $content['html'];
			exit;
		}

	}
 
?>