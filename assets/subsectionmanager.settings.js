
(function($) {
	
	/**
	 * Subsection Manager settings
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
	
		// Subsection setup on change
		$('select.subsectionmanager').live('change', function(event) {
		
			var select = $(event.target);
			var id = select.val();
			var subsectionmanager = select.parents('li').filter('li');
			var groups = subsectionmanager.find('select.datasource optgroup');
			var filter = subsectionmanager.find('ul.negation li[rel~=section' + id + ']');
	
			// Reset subsectionmanager height
			subsectionmanager.css('height', 'auto');
	
			// Show and hide filter and filter suggestions
			if(filter.length > 0) {
				subsectionmanager.find('label.filter').show();
				subsectionmanager.find('ul.negation li').hide();
				filter.show();
			}
			else {
				subsectionmanager.find('label.filter').hide();
				subsectionmanager.find('ul.negation li').hide();
			}
	
			// Show and hide caption suggestions
			subsectionmanager.find('ul.inline li').hide().filter('[rel~=section' + id + ']').show();
	
			// Show and hide data source sections
			if(groups.length > 0) {
				groups.each(function() {
					subsectionmanager.data(this.label, $(this).children());
				});
				groups.remove();
			}
			subsectionmanager.find('select.datasource option').remove();
			if(subsectionmanager.data(id)) {
				subsectionmanager.find('select.datasource').length = subsectionmanager.data(id).length;
				subsectionmanager.data(id).appendTo(subsectionmanager.find('select.datasource'));
			}
		});
		
		// Subsection setup on start up
		$('select.subsectionmanager').each(function() {
			$(this).trigger('change');
		});	

		// Subsection setup on click			
		$('div.controls a').click(function() {
			$('select.subsectionmanager:last').trigger('change');
		});
		
	});

	// Add negation signs for all suggestions while alt key is pressed
    $(window).keydown(function(event) {
		if(event.altKey) {
			$('ul.subsectionmanager.negation li').each(function() {
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
