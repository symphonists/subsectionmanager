
(function($) {
	
	/**
	 * Subsection Manager Settings
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var manager = $('li[data-type="subsectionmanager"]');
	
		// Subsection setup on change
		manager.delegate('select.subsectionmanager', 'change', function(event) {
			var select = $(event.target),
				id = select.val(),
				parent = select.parents('li').filter('li'),
				groups = parent.find('select.datasource optgroup'),
				filter = parent.find('ul.negation li[rel~=section' + id + ']');
	
			// Reset subsectionmanager height
			parent.css('height', 'auto');
	
			// Show and hide filter and filter suggestions
			if(filter.length > 0) {
				parent.find('label.filter').show();
				parent.find('ul.negation li').hide();
				filter.show();
			}
			else {
				parent.find('label.filter').hide();
				parent.find('ul.negation li').hide();
			}
	
			// Show and hide caption suggestions
			parent.find('ul.inline li').hide().filter('[rel~=section' + id + ']').show();
	
			// Show and hide data source sections
			if(groups.length > 0) {
				groups.each(function() {
					parent.data(this.label, $(this).children());
				});
				groups.remove();
			}
			parent.find('select.datasource option').remove();
			if(parent.data(id)) {
				parent.find('select.datasource').length = parent.data(id).length;
				parent.data(id).appendTo(parent.find('select.datasource'));
			}
		});
		
		// Subsection setup on start up
		manager.find('select.subsectionmanager').trigger('change');

		// Subsection setup on click			
		$('div.controls a').click(function() {
			manager.find('select.subsectionmanager').trigger('change');
		});

		// Setup dependencies
		manager.delegate('input[name*="allow_multiple"]:checkbox', 'change', function() {
			var multiple = $(this),
				parent = multiple.parents('li:first'),
				related = parent.find('input[name*="draggable"], input[name*="allow_quantities"]');
				
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
		manager.find('input[name*="allow_multiple"]:checkbox').change();
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
