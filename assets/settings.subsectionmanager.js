/*
 * SUBSECTIONMANAGER for Symphony
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/mediathek
 */
	

/*-----------------------------------------------------------------------------
	Settings
-----------------------------------------------------------------------------*/

	jQuery(document).ready(function() {
	
		// Subsection setup on change
		jQuery('select.subsectionmanager').live('change', function(event) {
		
			var select = jQuery(event.target);
			var id = select.val();
			var subsectionmanager = select.parents('li').filter('li');
			var groups = subsectionmanager.find('select.datasource optgroup');
			var filter = subsectionmanager.find('ul.negation.section' + id);
	
			// Reset subsectionmanager height
			subsectionmanager.css('height', 'auto');
	
			// Show and hide filter and filter suggestions
			if(filter.length > 0) {
				subsectionmanager.find('label.filter').show();
				subsectionmanager.find('ul.negation').hide();
				filter.show();
			}
			else {
				subsectionmanager.find('label.filter').hide();
				subsectionmanager.find('ul.negation').hide();
			}
	
			// Show and hide caption suggestions
			subsectionmanager.find('ul.inline').hide().filter('.section' + id).show();
	
			// Show and hide data source sections
			if(groups.length > 0) {
				groups.each(function() {
					subsectionmanager.data(this.label, jQuery(this).children());
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
		jQuery('select.subsectionmanager').each(function() {
			jQuery(this).trigger('change');
		});	

		// Subsection setup on click			
		jQuery('div.controls a').click(function() {
			jQuery('select.subsectionmanager:last').trigger('change');
		});
		
	});

	// Add negation signs for all suggestions while alt key is pressed
    jQuery(window).keydown(function(event) {
		if(event.altKey) {
			jQuery('ul.subsectionmanager.negation li').each(function() {
				var tag = jQuery(this);
				if(tag.text().substr(0, 1) != '-') {
					tag.html('-' + tag.text());
				}
			});
		}
    });

	// Remove negation signs for all suggestions on keyup
	jQuery(window).keyup(function(event) {
		jQuery('ul.subsectionmanager li').each(function() {
			var tag = jQuery(this);
			if(tag.text().substr(0, 1) == '-') {
				tag.html(tag.text().substr(1));
			}
		});
	});
