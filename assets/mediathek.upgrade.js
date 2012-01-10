
(function($) {

	// Language strings
	Symphony.Language.add({
		'I have a working backup of my site including files and database and like to upgrade all my Mediathek fields to Subsection Manager fields.': false
	});

	/**
	 * Upgrade Mediathek to Subsection Manager
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/stage
	 */
	$(document).ready(function() {
		$('input.upgrade').click(function(event) {
			return confirm(Symphony.Language.get('I have a working backup of my site including files and database and like to upgrade all my Mediathek fields to Subsection Manager fields.'));
		});
	});

})(jQuery.noConflict());
