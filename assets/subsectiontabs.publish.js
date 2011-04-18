
(function($) {

	Symphony.Language.add({
		'Some errors were encountered while attempting to save.': false,
		'Untitled': false,
		'You just removed the tab "{$tab}". It will be deleted when you save this entry.': false,
		'Undo?': false,
		'An error occured while restoring the tab.': false
	});
	
	/**
	 * This plugin adds an tabbed interface for subsection management.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var field = $('div.field-subsectiontabs'),
			label = field.find('label'),
			handle = label.attr('data-handle'),
			title = $('h2:first'),
			storage = field.find('ul'),
			references = field.find('a'),
			state = $.parseJSON(localStorage.getItem('subsectiontabs-' + Symphony.Context.get('env').entry_id)) || { tab: -1 },
			controls, tabs,
			fragments, headline;
			
		// Set context
		$('body').addClass('subsectiontabs');
			
		// Switch display modes
		if(location.search != '?debug') {
			field.hide();
		}
		
		// Create interface
		controls = $('<ul id="subsectiontabs" />').insertAfter(title);
		tabs = $('<div class="tabs" />').insertAfter(controls);
		
		// Set height
		if(state) {
			tabs.height(state.height);
		}
		
		// Clean up page and document title
		if(title.text() != Symphony.Language.get('Untitled')) {
			fragments = title.text().split(' (');
			fragments.splice(fragments.length - 1, 1);
			
			// Get headline
			headline = fragments.join(' (');
			if(headline == '') {
				headline = Symphony.Language.get('Untitled');
			}
			
			// Set page title
			title.text(headline);
			
			// Set document title
			fragments = document.title.split(' – ');
			fragments.splice(fragments.length - 1, 1);
			document.title = fragments.join(' – ') + ' – ' + headline;
		}

	/*-----------------------------------------------------------------------*/
		
		// Load tab
		controls.delegate('li:not(.new)', 'click.subsectiontabs', function(event) {
			var target = $(event.target),
				control = $(this),
				link = control.attr('data-link'),
				subsections = tabs.find('iframe'),
				id = control.attr('data-id') || control.find('span').text(),			
				current = subsections.filter('[name=' + id + ']');
				
			// Don't process, is tab should be removed
			if(target.is('a')) {
				return false;
			}
				
			// Close tab editors:
			// Set timeout to not interfer with doubleclick actions
			setTimeout(function() {
				$('body').trigger('click', [control]);		
			}, 200);

			// Don't load tab while editing
			if(target.is('input')) {
				return false;
			}
				
			// Switch tabs
			control.siblings().removeClass('selected');
			control.addClass('selected');
				
			// Hide subsections
			subsections.hide();

			// Tab already loaded
			if(current.size() > 0) {
				resize(current);
				current.show();
			}
			
			// Tab not loaded yet
			else {
				load(control);
			}
		});

		// Dynamic tabs
		if(field.find('label').is('.allow_dynamic_tabs')) {
			
			// Create tab
			controls.delegate('li.new', 'click.subsectiontabs', function(event) {
				var creator = $(this),
					name = getName(Symphony.Language.get('Untitled')),
					control = create(name, 'new', field.find('input[name="field[' + handle + '][new]"]').val(), false, true),
					subsection = tabs.find('iframe');

				// Deselect other tabs
				controls.find('li').removeClass('selected');
				subsection.hide();

				// Load new tab
				control.hide().insertBefore(creator);
				insert(control);
				load(control);
			});

			// Edit tab name
			controls.delegate('li', 'dblclick.subsectiontabs', function(event) {
				var tab = $(this).addClass('selected'),
					label = tab.find('span'),
					value = label.text(),
					input = $('<input type="text" />').val(value);
					
				// Replace text with input
				label.replaceWith(input);
				
				// Store name and focus
				input.attr('data-name', value).focus();
			});
			
			// Fetch keys
			controls.delegate('input', 'keyup.subsectiontabs', function(event) {
				var input = $(this);

				// Enter = apply
				if(event.which == 13) {
					$('body').click();
				}
				
				// Escape = undo
				if(event.which == 27) {
				
					// Restore old name
					input.val(input.attr('data-name'));
					$('body').click();
				}				
			});
			
			// Stop editing tab names
			$('body').bind('click.subsectiontabs', function(event, tab) {
				if($(event.target).parents('#subsectiontabs li').size() > 0) return false;
			
				controls.find('li:has(input)').not($(tab)).each(function() {
					var control = $(this),
						subsection = tabs.find('iframe:visible'),
						input = control.find('input'),
						value = $.trim(input.val()),
						name = getName(value);
															
					// Save tab name
					input.replaceWith('<span>' + name + '</span>');
					
					// Rename iframe if no identifier has been set
					if(!control.attr('data-id') || control.attr('data-id') == 'new') {
						subsection.attr('name', name);
						remember(subsection);
					}
				});
			});
			
			// Remove tab
			controls.delegate('a', 'click', function() {
				var control = $(this).parent();
				
				remove(control);
			});
			
			// Undo removal
			$('#header').delegate('a.undo', 'click', function() {
				var undo = $(this),
					id = undo.attr('name'),
					control = controls.find('li[data-id="' + id + '"]');
					
				// Restore				
				if(control.size() > 0) {
					Symphony.Message.clear('notice');
					insert(control);
				}
				
				// Error
				else {
					Symphony.Message.post(
						Symphony.Language.get('An error occured while restoring the tab.'),
						'error'
					);
				}				
			});
		}
		
		// Save tab
		$('body.subsectiontabs form div.actions input').click(function(event, validated) {
			var first = controls.find('li:first');

			// Don't send parent form until tabs have been validated and saved
			if(validated != true) {
				event.preventDefault();
			
				// Clear storage
				storage.empty();
			
				// Don't use hide(): tabs must be displayed to appease Firefox
				tabs.find('iframe').show().css('visibility', 'hidden');
				
				// Scroll to top
				$('body, html').animate({
					'scrollTop': 0
				}, 'fast');

				// Save tabs
				save(first);
			}
		});
		
	/*-----------------------------------------------------------------------*/
	
		// Create control
		var create = function(name, id, link, static, selected) {
			var control = $('<li />');
		
			// Set values
			control.append('<span>' + name + '</span>').attr({
				'data-id': id,
				'data-link': link
			});
			
			// Set mode
			if(static === true) {
				control.addClass('static');
			}
						
			// Add destructor
			else {
				control.append('<a class="destructor">×</a>');
			}
			
			// Select
			if(selected === true) {
				control.addClass('selected');
			}
			
			return control;
		}
	
		// Load tab
		var load = function(control) {
			var id = control.attr('data-id') || control.find('span').text(),
				link = control.attr('data-link'),
				subsection;
			
			// Append iframe
			subsection = $('<iframe />', {
				'class': 'subsectiontab',
				'src': link,
				'name': id
			}).hide().appendTo(tabs);
			
			// Prepare subsection display
			subsection.load(function() {
				var content = subsection.contents(),
					current = subsection.attr('name');

				// Adjust interface
				content.find('body').addClass('tabbed subsection');
				content.find('h1, h2, #nav, #notice:not(.error):not(.success), #notice a, #footer').remove();
				content.find('div.actions').css({
					'height': 0,
					'overflow': 'hidden'
				});

				// Set height
				if(current == id) {
					resize(subsection);
				}
				
				// Store current form values to check for changes before saving
				subsection.data('post', content.find('form').serialize());
			});
		};
		
		// Insert a tab
		var insert = function(control) {
			var width = control.attr('data-width');
			
			control.fadeIn('fast', function() {
				control.removeClass('delete').animate({
					'width': width
				}, 'fast', function() {
					control.find('span, a').fadeIn('fast');
					control.click();
				});
			});
		}
		
		// Delete a tab
		var remove = function(control) {
			var width = control.width(),
				name = control.find('span').text(),
				id = control.attr('data-id') || name,
				prev = control.prev('li:visible').filter(':not(.delete)'),
				next = control.next('li:visible').filter(':not(.new, .delete)');
			
			// Switch tab
			if(control.is('.selected')) {
				if(prev.size() > 0) {
					prev.click();
				}
				else if(next.size() > 0) {
					next.click();
				}
			}

			// Hide tab
			control.animate({
				'opacity': 0
			}, 'fast', function() {
			
				// Create empty tab if needed
				if(prev.size() == 0 && next.size() == 0) {
					controls.find('li.new').click();
				}
					
				// Shrink tab
				control.find('span, a').hide();
				control.addClass('delete').css('width', control.outerWidth()).attr('data-width', width).animate({
					'width': 0,
				}, 'fast', function() {
				
					// Reset styles
					control.removeAttr('style').hide();
										
					// Show undo message
					Symphony.Message.post(
						Symphony.Language.get('You just removed the tab "{$tab}". It will be deleted when you save this entry.', { 'tab': name }) + 
						' <a class="undo" name="' + id + '">' + Symphony.Language.get('Undo?') + '</a>',
						'notice'
					);
				});
			});
		}
		
		// Get name, making sure there are no duplicates
		var getName = function(name) {
			var counter = '',
				count;
		
			// Handle empty names
			if(name == '') {
				name = Symphony.Language.get('Untitled');
			}
			
			// Get clones
			count = controls.find('li:contains(' + name + ')').map(function() {
				return ($(this).text() == name);
			}).size();
			
			if(count > 0) {
				for(i = 1; i < 100; i++) {
					count = controls.find('li:contains(' + name + ')').map(function() {
						return ($(this).text() == name + ' ' + i.toString());
					}).size();
				
					if(count == 0) {
						count = i;
						break;
					};
				}
			}
			
			// Set counter
			if(count > 0) {
				counter = ' ' + (count + 1).toString();
			}
			
			return name + counter;	
		};
		
		// Save tab
		var save = function(control) {
			var id = control.attr('data-id'),
				tab = tabs.find('iframe[name=' + id + ']'),
				next = control.next('li:not(.new)'),
				button = tab.contents().find('div.actions input'),
				name = $.trim(control.find('span').text())
				post = tab.contents().find('form').serialize();
			
			// Tab loaded
			if(tab.size() > 0 && post != tab.data('post') && !control.is('.delete')) {
			
				// Callback
				tab.one('load', function(event) {
					var tab = $(this),
						regexp;
					
					// Get entry id
					regexp = new RegExp(/\bid-(\d*)\b/);
					id = regexp.exec(tab.contents().find('body').attr('class'));
					if(id != null) {
						id = id[1];
					}
					
					// Store errors
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
				
			// Delete
			if(controls.find('li[data-id="' + id + '"]').is('.delete')) {
				item.append('<input name="fields[' + handle + '][delete][]" value="' + id + '" />');
			}
			
			// Store
			else if(!isNaN(id)) {
				item.append('<input name="fields[' + handle + '][relation_id][]" value="' + id + '" />');
				item.append('<input name="fields[' + handle + '][name][]" value="' + name + '" />');
			}
			
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
		
		// Resize tab
		var resize = function(subsection) {
			var height, storage;
			
			// Resize tab
			subsection.show();
			height = getHeight(subsection);
			subsection.height(height);
			tabs.animate({
				'height': height
			}, 'fast');
			
			// Store current tab
			remember(subsection, height);
		};
		
		var getHeight = function(subsection) {
			return subsection.contents().find('#wrapper').height();
		}
		
		// Remember subsection name and height
		var remember = function(subsection, height) {
			if(localStorage) {
				var tab = subsection.attr('name');
				
				// New entry
				if(Symphony.Context.get('env').entry_id == null) {
					tab = '';
				}

				// Get height
				if(!height) {
					height = getHeight(subsection);
				}
				
				// Store state
				localStorage.setItem('subsectiontabs-' + Symphony.Context.get('env').entry_id, JSON.stringify({
					'tab': tab,
					'height': height
				}));
			}
		}
		
	/*-----------------------------------------------------------------------*/
	
		// Add controls
		storage.find('li').each(function(count) {
			var item = $(this),
				name = item.find('input:eq(1)').val(),
				id = item.find('input:eq(0)').val(),
				link = item.find('a').attr('href'),
				static = false;
				selected = false;
				
			// Fallback id
			if(!id) {
				id = name;
			}
			
			// Static tabs
			if(item.is('.static')) {
				static = true;
			}

			// Selection
			if(id == state.tab || ((state.tab <= 0 || state.tab == '' || state.tab == 'new') && count == 0)) {
				selected = true;
			}

			// Create control
			control = create(name, id, link, static, selected);
			controls.append(control);
			
			// Preload tab
			load(control);
		});
		
		// Allow dynamic controls
		if(label.is('.allow_dynamic_tabs')) {
			controls.addClass('destructable');
			$('<li class="new"><span>+</span></li>').appendTo(controls);
		}

	});
	
})(jQuery.noConflict());
