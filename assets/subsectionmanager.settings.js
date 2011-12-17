
(function($) {

	/**
	 * Subsection Manager Settings
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var duplicator = $('#fields-duplicator');

		// Subsection setup on change
		duplicator.delegate('select.subsectionmanager', 'change', function(event) {
			var select = $(event.target),
				id = select.val(),
				manager = select.parents('li').filter('li'),
				groups = manager.find('select.datasource optgroup'),
				filter = manager.find('ul.negation li[rel~=section' + id + ']');

			// Reset subsectionmanager height
			manager.css('height', 'auto');

			// Show and hide filter and filter suggestions
			if(filter.length > 0) {
				manager.find('label.filter').show();
				manager.find('ul.negation li').hide();
				filter.show();
			}
			else {
				manager.find('label.filter').hide();
				manager.find('ul.negation li').hide();
			}

			// Show and hide caption suggestions
			manager.find('ul.inline li').hide().filter('[rel~=section' + id + ']').show();

			// Show and hide data source sections
			if(groups.length > 0) {
				groups.each(function() {
					manager.data(this.label, $(this).children());
				});
				groups.remove();
			}
			manager.find('select.datasource option').remove();
			if(manager.data(id)) {
				manager.find('select.datasource').length = manager.data(id).length;
				manager.data(id).appendTo(manager.find('select.datasource'));
			}
		});

		// Subsection setup on start up
		duplicator.find('select.subsectionmanager').trigger('change');

		// Subsection setup on click
		$('div.controls a').click(function() {
			duplicator.find('select.subsectionmanager').trigger('change');
		});

		// Setup dependencies
		duplicator.delegate('li[data-type="subsectionmanager"] input[name*="allow_multiple"]:checkbox', 'change', function() {
			var multiple = $(this),
				manager = multiple.parents('li:first'),
				related = manager.find('input[name*="draggable"]');

			// Activate multiple selection
			if(multiple.is(':checked')) {
				related.parent().removeClass('disabled');
				related.removeAttr('disabled');

				// Restore state
				related.each(function() {
					var input = $(this);
					if(input.data('selected') === true) {
						input.attr('checked', true);
					}
				});
			}

			// Deactivate multiple selection
			else {
				related.parent().addClass('disabled');
				related.attr('disabled', 'disabled');

				// Store state
				related.each(function() {
					var input = $(this);
					input.data('selected', input.is(':checked')).attr('checked', false);
				});
			}
		});

		// Initialise dependencies
		$('li[data-type="subsectionmanager"] input[name*="allow_multiple"]:checkbox').change();
	});

	// Add negation signs for all suggestions while alt key is pressed
	$(window).keydown(function(event) {
		if(event.altKey) {
			$('ul.parentnegation li').each(function() {
				var tag = $(this);
				if(tag.text().substr(0, 1) != '-') {
					tag.html('-' + tag.text());
				}
			});
		}
	});

	// Remove negation signs for all suggestions on keyup
	$(window).keyup(function(event) {
		$('ul.subsectionmanager li').each(function() {
			var tag = $(this);
			if(tag.text().substr(0, 1) == '-') {
				tag.html(tag.text().substr(1));
			}
		});
	});

})(jQuery.noConflict());
