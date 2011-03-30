
(function($) {

	Symphony.Language.add({
		'Some errors were encountered while attempting to save.': false
	});
	
	/**
	 * This plugin adds an tabbed interface for subsection management.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var field = $('div.field-subsectiontabs').hide(),
			storage = field.find('ul'),
			references = field.find('a'),
			controls, tabs, create;
			
		// Set context
		$('body').addClass('subsectiontabs');
		
		// Create interface
		controls = $('<ul id="subsectiontabs" />').insertAfter('h2:first');
		tabs = $('<div class="tabs" />').insertAfter(controls);
		
		// Add controls
		storage.find('li').each(function(count) {
			var item = $(this),
				control = $('<li />').appendTo(controls),
				id = item.find('input:eq(0)').val(),
				name = item.find('input:eq(1)').val(),
				link = item.find('a').attr('href');
			
			// Set values
			control.html('<span>' + name + '</span>').attr({
				'data-id': id,
				'data-link': link
			});
						
			// Selection
			if(count == 0) {
				control.addClass('selected');
			}
		});
		
		// Allow dynamic controls
		if(field.find('label').is('.allow_dynamic_tabs')) {
			create = $('<li class="new"><span>+</span></li>').appendTo(controls);
		}

	/*-----------------------------------------------------------------------*/
		
		// Load tab
		controls.delegate('li:not(.new)', 'click.subsectiontabs', function(event) {
			var target = $(event.target),
				control = $(this),
				link = control.attr('data-link'),
				subsections = $('iframe.subsectiontab'),
				current = subsections.filter('[name=' + $.trim(control.text()) + ']');
				
			// Close tab editors:
			// Set timeout to not interfer with doubleclick actions
			setTimeout(function() {
				$('body').trigger('click', [control]);		
			}, 200);

			// Don't load tab while editing
			if(target.is('input')) {
				return false;
			}
			else {
				subsections.hide();
			}
				
			// Switch tabs
			control.siblings().removeClass('selected');
			control.addClass('selected');
				
			// Tab already loaded
			if(current.size() > 0) {
				resize(current);
				current.show();
			}
			
			// Tab not loaded yet
			else {
				load(link, control.text(), true);
			}
		});

		// Dynamic tabs
		if(field.find('label').is('.allow_dynamic_tabs')) {
			
			// Create tab
			controls.delegate('li.new', 'click.subsectiontabs', function(event) {
				var control = $(this),
					new_tab = $('<li data-id="" data-link="' + field.find('input[name="field[subsection-tabs][new]"]').val() + '"></li>').insertBefore(control).click().dblclick();
			});

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
				if($(event.target).parents('#subsectiontabs li').size() > 0) return false;
			
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
		}
		
		// Save tab
		$('body.subsectiontabs form').click(function(event, validated) {
			var first = controls.find('li:first');

			// Don't send parent form until tabs have been validated and saved
			if(validated != true) {
				event.preventDefault();
			
				// Clear storage
				storage.empty();
			
				// Save tabs
				save(first);
			}
		});
		
	/*-----------------------------------------------------------------------*/
	
		// Load tab
		var load = function(link, name, show) {
			var subsection = $('<iframe />', {
				'class': 'subsectiontab',
				'src': link,
				'name': $.trim(name)
			}).hide().appendTo(tabs);
			
			// Prepare subsection display
			subsection.load(function() {
				var content = $(this).contents();

				// Adjust interface
				content.find('body').addClass('tabbed subsection');
				content.find('h1, h2, #nav, #notice:not(.error):not(.success), #notice a, #footer').remove();
				content.find('div.actions').css({
					'height': 0,
					'overflow': 'hidden'
				});

				// Set height
				if(show == true) {
					resize(subsection);
				}
			});
		};
		
		// Save tab
		var save = function(control) {
			var tab = tabs.find('iframe[name=' + $.trim(control.text()) + ']'),
				next = control.next('li:not(.new)'),
				button = tab.contents().find('div.actions input'),
				name = $.trim(control.text()),
				id = control.attr('data-id');
			
			// Tab loaded
			if(tab.size() > 0) {
			
				// Callback
				tab.one('load', function(event) {
					var tab = $(this),
						regexp;
						
					// Get entry id
					regexp = new RegExp(/\bid-(\d*)\b/);
					id = regexp.exec(tab.contents().find('body').attr('class'));
					if(id != null) {
						id = id[1]
					}
					
					// Store errors
					console.log(tab.contents().find('#header .error'), tab.contents().find('#header .error').size());
					if(tab.contents().find('#header .error').size() > 0) {
						control.addClass('error');
					}
					else {
						control.removeClass('error');
					}
				
					// Store data
					store(id, name, next);
				});
				
				// Post
				button.click();		
			}
			
			// Tab not loaded
			else {
				store(id, name, next);
			}
		};
		
		// Store data
		var store = function(id, name, next) {
			var item = $('<li />').appendTo(storage);
			
			item.append('<input name="fields[subsection-tabs][relation_id][]" value="' + id + '" />');
			item.append('<input name="fields[subsection-tabs][tab][]" value="' + name + '" />');
			
			// Process next tab
			if(next.size() > 0) {
				save(next);
			}
			
			// Process parent entry
			else {
			
				// Errors
				if(controls.find('li.error').size() > 0) {
					Symphony.Message.post(Symphony.Language.get('Some errors were encountered while attempting to save.'), 'error');
					controls.find('li.error:first').click();
				}
				
				else {
					$('body.subsectiontabs form div.actions input').trigger('click', [true]);				
				}
			}
		};
		
		var resize = function(subsection) {
			var content = subsection.contents(),
				height = subsection.height();
				
				
			height = content.find('#wrapper').outerHeight() || height;
			subsection.height(height).show();
			tabs.animate({
				'height': height
			});
		};
		
	/*-----------------------------------------------------------------------*/
	
		// Preload tab
		controls.find('li').each(function(position) {
			var control = $(this);
			load(control.attr('data-link'), control.text(), (position == 0));
		});
		
	});
	
})(jQuery.noConflict());
