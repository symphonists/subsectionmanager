
(function($) {
	
	/**
	 * This plugin adds an tabbed interface for subsection management.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var field = $('div.field-subsectiontabs').hide(),
			id = field.find('input').val(),
			references = field.find('a'),
			title = $('h2'),
			controls, display, create;
			
		// Set context
		$('body').addClass('subsectiontabs');
		
		// Create interface
		controls = $('<ul />', {
			'id': 'subsectiontabs'
		}).insertAfter(title);
		tabs = $('<div />', {
			'class': 'tabs'
		}).insertAfter(controls);
		
		// Add controls
		references.each(function(count) {
			var reference = $(this),
				tab = $('<li />', {
					'html': '<span>' + $.trim(reference.text()) + '</span>',
					'data-id': reference.attr('data-id'),
					'data-href': reference.attr('href')
				}).appendTo(controls);
								
			// Selection
			if(count == 0) {
				tab.addClass('selected');
			}
		});
		
		// Allow dynamic controls
		if(field.find('label').is('.allow_dynamic_tabs')) {
			create = $('<li />', {
				'class': 'new',
				'html': '<span>+</span>'
			}).appendTo(controls);
		}
		
		// Edit tab name
		controls.delegate('li', 'dblclick.subsectiontabs', function(event) {
			var tab = $(this).addClass('selected'),
				value = tab.text(),
				input = $('<input type="text" />');
				
			// Replace text with input
			tab.html(input.val(value));
			input.focus();
		});
		
		// Stop editing tab names
		$('body').bind('click.subsectiontabs', function(event, tab) {
			if($(event.target).parents('li').size() > 0) return false;
		
			controls.find('li:has(input)').not($(tab)).each(function() {
				var tab = $(this),
					input = tab.find('input').remove(),
					text = $.trim(input.val());
				
				// Save tab name
				tab.html('<span>' + $.trim(input.val()) + '</span>');
				
				// Handle newly created tabs
				if(tab.is('.new')) {
					
					// Empty tab
					if(text == '' || text == '+') {
						tab.html('<span>+</span>');
					}
					
					// Add button
					else {
						tab.removeClass('new');
						tab.after('<li class="new"><span>+</span></li>');
					}
				}
			});
		});
		
		// Load tab
		controls.delegate('li', 'click.subsectiontabs', function(event) {
			var target = $(event.target),
				tab = $(this),
				href = tab.attr('data-href'),
				subsections = $('iframe.subsectiontab'),
				current = subsections.filter('[name=' + $.trim(tab.text()) + ']');

				
			// Close tab editors
			setTimeout(function() {
				$('body').trigger('click', [tab]);		
			}, 200);

			// Don't load tab while editing
			if(target.is('input')) {
				return false;
			}
			else {
				subsections.hide();
			}
				
			// Switch tabs
			controls.find('li').removeClass('selected');
			tab.addClass('selected');
				
			// Tab already loaded
			if(current.size() > 0) {
				current.show();
			}
			
			// Tab not loaded yet
			else {
				subsection = $('<iframe />', {
					'class': 'subsectiontab',
					'src': href,
					'name': $.trim(tab.text())
				}).hide().appendTo(tabs).load(function() {
					var subsection = $(this),
						content = subsection.contents();
	
					// Adjust interface
					content.find('body').addClass('tabbed subsection');
					content.find('h1, h2, #nav, #notice:not(.error):not(.success), #notice a, #footer, div.actions input').remove();
					content.find('fieldset input:first').focus();

					// Set height
					var height = subsection.height();
					height = content.find('#wrapper').outerHeight() || height;
					subsection.height(height).fadeIn('fast');
					tabs.animate({
						'height': height
					});
				});		
			}
		});
		
		// Load first tab by default
		controls.find('li:first').click();
		
	});
	
})(jQuery.noConflict());
