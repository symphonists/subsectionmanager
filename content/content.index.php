<?php
 
	/**
	 * @package content
	 */
	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionSubsectionmanagerIndex extends AdministrationPage {
		
		/**
		 * Called to build the content for the page.
		 */
		function view() {
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Subsection Manager Upgrade'))));
			$this->addScriptToHead(URL . '/extensions/subsectionmanager/assets/mediathek.upgrade.js', 100, false);
			$this->appendSubheading(__('Subsection Manager Upgrade'));
			$this->Form->setAttribute('action', Administration::instance()->getCurrentPageURL() . 'upgrade/');
			
			// Introduction
			$introduction = new XMLElement(
				'fieldset', 
				'<legend>' . __('Introduction') . '</legend>
				<p>' . __('Subsection Manager is a replacement of the Mediathek field, introducing a new interface and an improved feature set. Subsection Manager requires Symphony 2.1 and should not be used side-by-side with Mediathek. This page will help you upgrading your fields from Mediathek to Subsection Manager.') . '</p>
				<h3>' . __('Before you proceed') . '</h3>
				<ul>
					<li>' . __('Please make sure that you have an up-to-date backup of your site, containing all files and folders, and a copy of your database.') . ' <strong>' . __('If you don&#8217;t have a backup, create one now!') . '</strong> </li>
					<li>' . __('Upgrading your Mediathek fields to Subsection Manager will alter your database.') . ' <strong>' . __('This process cannot be undone!') . '</strong></li>
				</ul>', 
				array(
					'class' => 'settings'
				)
			);
			$this->Form->appendChild($introduction);
			
			// Introduction
			$upgrade = new XMLElement(
				'fieldset', 
				'<legend>' . __('Upgrading your Mediathek') . '</legend>
				<p>' . __('The Subsection Manager Upgrade will automatically perform the following actions:') . '</p>
				<ol>
					<li>' . __('Replace all Mediathek fields with the Subsection Manager, copying all attached information to the new fields.') . '</li>
					<li>' . __('Uninstall the Mediathek extension removing all references in the database.') . '</li>
				</ol>
				<p>' . __('The Mediathek folder will stay intact in your extension folder. You will have to delete it manually.') . '</p>', 
				array(
					'class' => 'settings'
				)
			);
			$this->Form->appendChild($upgrade);
			
			// Actions
			$actions = new XMLElement('div', '<input class="upgrade" type="submit" value="' . __('Upgrade all Mediathek fields') . '" />', array('class' => 'actions'));
			$this->Form->appendChild($actions);
			
		}	
	
	}
 
?>