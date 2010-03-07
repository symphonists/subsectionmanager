/*
 * SUBSECTION MANAGER for Symphony CMS
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/mediathek
 */

(function($) {

	// on page load
	$(function() {
		$('select.mediathek').each(function() {
			fieldToggle(this);
		});		
		$('select.mediathek').live('change', function() {
			fieldToggle(this);
		});
		$('div.controls a').click(function() {
			fieldToggle($('select.mediathek:last'));
		});
	});

	// show and hide suggestion lists
	function fieldToggle(select) {
		var $select = $(select),
			id = $select.val(),
			mediathek = $select.parents('li').filter('li'),
			groups = mediathek.find('select.datasource optgroup'),
			filter = mediathek.find('ul.negation.section' + id);

		// reset mediathek height
		mediathek.css('height', 'auto');

		// show and hide filter and filter suggestions
		if(filter.length > 0) {
			mediathek.find('label.filter').show();
			mediathek.find('ul.negation').hide();
			filter.show();
		}
		else {
			mediathek.find('label.filter').hide();
			mediathek.find('ul.negation').hide();
		}

		// show and hide caption suggestions
		mediathek.find('ul.inline').hide().filter('.section' + id).show();

		// show and hide data source sections
		if(groups.length > 0) {
			groups.each(function() {
				mediathek.data(this.label, $(this).children());
			});
			groups.remove();
		}
		mediathek.find('select.datasource option').remove();
		if(mediathek.data(id)) {
			mediathek.find('select.datasource').length = mediathek.data(id).length;
			mediathek.data(id).appendTo(mediathek.find('select.datasource'));
		}
	}

	// add negation signs for all suggestions while alt key is pressed
    $(window).keydown(function(event) {
		if(event.altKey) {
			$('ul.mediathek.negation li').each(function() {
				var tag = $(this);
				if(tag.text().substr(0, 1) != '-') {
					tag.html('-' + tag.text());
				}
			});
		}
    });

	// remove negation signs for all suggestions on keyup
	$(window).keyup(function(event) {
		$('ul.mediathek li').each(function() {
			var tag = $(this);
			if(tag.text().substr(0, 1) == '-') {
				tag.html(tag.text().substr(1));
			}
		});
	});

})(jQuery.noConflict());
