<?php
 
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionSubsectionmanagerIndex extends AdministrationPage {
 
		public function __construct(&$parent) {
			parent::__construct($parent);
		}

		function view() {
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Subsection Manager Upgrade'))));
			$this->appendSubheading(__('Subsection Manager Upgrade'));
			$this->Form->setAttribute('action', $this->_Parent->getCurrentPageURL() . 'upgrade/');
			
			// Introduction
			$introduction = new XMLElement(
				'fieldset', 
				'<legend>Introduction</legend>
				<p>Subsection Manager is a replacement of the Mediathek field introducing a new interface and an improved feature set. Subsection Manager requires Symphony 2.1 and should not be used side-by-side with Mediathek. This page will help you upgrading your fields from Mediathek to Subsection Manager.</p>
				<h3>Before you proceed</h3>
				<ul>
					<li>Please make sure that you have an up-to-date backup of your site containing all files and folders and a copy of your database. <strong>If you don&#8217;t have a backup, create one now!</strong></li>
					<li>Upgrading your Mediathek fields to Subsection Manager will alter your database. <strong>This process cannot be undone!</strong></li>
				</ul>', 
				array(
					'class' => 'settings'
				)
			);
			$this->Form->appendChild($introduction);
			
			// Introduction
			$upgrade = new XMLElement(
				'fieldset', 
				'<legend>Upgrading your Mediathek</legend>
				<p>The Subsection Manager Upgrade will automatically perform the following actions:</p>
				<ol>
					<li>Replace all Mediathek fields with the Subsection Manager copying all attached information to the new fields.</li>
					<li>Uninstall the Mediathek extension removing all references in the database.</li>
				</ol>
				<p>The Mediathek folder will stay intact in your extension folder. You will have to delete it manually.</p>', 
				array(
					'class' => 'settings'
				)
			);
			$this->Form->appendChild($upgrade);
			
			// Actions
			$actions = new XMLElement('div', '<input type="submit" value="Upgrade all Mediathek fields" />', array('class' => 'actions'));
			$this->Form->appendChild($actions);
			
		}	
	
	}
 
?>