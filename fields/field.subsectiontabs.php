<?php

	/**
	 * @package fields
	 */
	/**
	 * This field provides a tabbed subsection management. 
	 */
	Class fieldSubsectiontabs extends Field {

		function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = __('Subsection Tabs');
			$this->_required = true;
		}

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
		
			// Append assets
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.js', 101, false);
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/subsectionmanager/assets/subsectiontabs.publish.css', 'screen', 102, false);
			
			// Label
			$label = Widget::Label(__('Subsection Tabs'));
			$wrapper->appendChild($label);
			
			// Container
			$container = new XMLElement('span', NULL, array('class' => 'frame'));		
			$names = array('Deutsch' => '123', 'English' => '124');
			foreach($names as $name => $id) {
				$link = new XMLElement('a', $name, array('href' => URL . '/symphony/publish/subsection/edit/' . $id, 'data-id' => $id));
				$container->appendChild($link);
			}
			$label->appendChild($container);

			// Field ID
			$input = Widget::Input('field[subsectiontabs][id]', $this->get('id'), 'hidden');
			$wrapper->appendChild($input);

			return $wrapper;
		}

		function createTable(){
			return Symphony::Database()->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `relation_id` int(11) unsigned DEFAULT NULL,
				  `tabname` varchar(255) NOT NULL,
				  PRIMARY KEY (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);
		}

	}
