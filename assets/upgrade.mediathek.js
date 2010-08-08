/*
 * SUBSECTION MANAGER UPGRADER
 * for Symphony
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/subsectionmanager
 */


/*-----------------------------------------------------------------------------
	Language strings
-----------------------------------------------------------------------------*/	 

	Symphony.Language.add({
		'I have a working backup of my site including files and database and like to upgrade all my Mediathek fields to Subsection Manager fields.': false,
	}); 
	

/*-----------------------------------------------------------------------------
	Upgrade
-----------------------------------------------------------------------------*/	

	jQuery(document).ready(function() {
		jQuery('input.upgrade').click(function(event) {
			event.preventDefault();
			return confirm(Symphony.Language.get('I have a working backup of my site including files and database and like to upgrade all my Mediathek fields to Subsection Manager fields.'));
		});
	});
