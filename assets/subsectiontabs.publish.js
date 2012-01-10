
(function($) {

	Symphony.Language.add({
		'Some errors were encountered while attempting to save.': false,
		'Untitled': false,
		'Would you like to clear this tab?': false
	});

	/**
	 * This plugin adds an tabbed interface for subsection management.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {
		var body = $('body'),
			field = $('div.field-subsectiontabs'),
			label = field.find('label'),
			handle = label.attr('data-handle'),
			title = $('h2:not(#documenter-title)').filter(':first'),
			storage = field.find('ul'),
			references = field.find('a'),
			state, controls, tabs,
			fragments, headline, chosen;

		// Set context
		body.addClass('subsectiontabs');

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
			if(current.length > 0) {
				resize(current);
				current.css('visibility', 'visible').show();
			}

			// Tab not loaded yet
			else {
				load(control);
			}
			
			return true;
		});

		// Clear tab
		controls.delegate('a.destructor', 'click', function() {
			var control = $(this).parent();

			clear(control);
		});

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
		var create = function(name, id, link, selected) {
			var control = $('<li />');

			// Set values
			control.addClass('static').append('<span>' + name + '</span><a class="destructor">×</a>').attr({
				'data-id': id,
				'data-link': link
			});

			// Select
			if(selected === true) {
				control.addClass('selected');
			}

			return control;
		};

		// Load tab
		var load = function(control) {
			var id = control.attr('data-id') || control.find('span').text(),
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
					current = subsection.attr('name'),
					selected = controls.find('li.selected').attr('data-id'),
					form = content.find('#contents form'),
					subsectiontab = $('<input />', {
						type: 'hidden',
						name: 'fields[subsectiontab]',
						value: control.find('span').text()
					});
					
				// Add tab name to form
				form.prepend(subsectiontab);

				// Adjust interface
				content.find('body').addClass('tabbed subsection');
				content.find('h1, h2, #nav, #notice:not(.error):not(.success), #notice a, #footer').remove();
				content.find('div.actions').css({
					'height': 0,
					'overflow': 'hidden'
				});

				// Resize frame
				content.find('#contents').resize(function(event) {
					if(!body.is('.resizing, .saving')) {
						var height = $(this).height();
						subsection.height(height);
						tabs.animate({
							'height': height
						}, 'fast');
					}
				});

				// Set height
				if(current == selected) {
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
		};

		// Delete a tab
		var clear = function(control) {
			var name = control.find('span').text(),
				id = control.attr('data-id') || name,
				message = Symphony.Language.get('Would you like to clear this tab?');

			if(confirm(message)) {
				tabs.find('[name="' + id + '"]').fadeOut('fast', function() {
					var	link = field.find('input[name*="[new]"]').val();

					// Mark old tab for deletion on next save
					control.clone().hide().addClass('delete').appendTo(controls);

					// Switch to empty tab
					control.attr('data-id', name);
					control.attr('data-link', link);
					load(control);
				});
			}
		};

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
			}).length;

			if(count > 0) {
				for(i = 1; i < 100; i++) {
					count = controls.find('li:contains(' + name + ')').map(function() {
						return ($(this).text() == name + ' ' + i.toString());
					}).length;

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
				name = $.trim(control.find('span').text()),
				post = tab.contents().find('form').serialize();

			// Set status
			body.addClass('saving');

			// Tab loaded
			if(tab.length > 0 && post != tab.data('post') && !control.is('.delete')) {

				// Callback
				tab.one('load', function(event) {
					var tab = $(this),
						contents = tab.contents(),
						regexp;

					// Get entry id
					regexp = new RegExp(/\bid-(\d*)\b/);
					id = regexp.exec(tab.contents().find('body').attr('class'));
					if(id != null) {
						id = id[1];
					}

					// Store errors
					if(contents.find('#header .error').length > 0) {
						control.addClass('error');
					}
					else if(contents.find('b:contains("Fatal error")').length > 0) {
						control.addClass('error');
						contents.find('body').attr('id', 'fatal').wrapInner('<div />');
						body.parent().find('link[href*="subsectiontabs.publish.css"]').clone().appendTo(contents.find('head'));
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
			if(next.length > 0) {
				save(next);
			}

			// Process parent entry
			else {

				// Errors
				if(controls.find('li.error').length > 0) {
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
			body.addClass('resizing');
			subsection.show();
			height = getHeight(subsection);
			subsection.height(height);
			tabs.animate({
				'height': height
			}, 'fast', function() {
				body.removeClass('resizing');
			});

			// Store current tab
			remember(subsection, height);
		};

		var getHeight = function(subsection) {
			return subsection.contents().find('#wrapper').height();
		};

		// Remember subsection name and height
		var remember = function(subsection, height) {
			if(localStorage) {
				var tab = subsection.attr('name');

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
		};

	/*-----------------------------------------------------------------------*/

		// Get state of new entry
		if(Symphony.Context.get('env').flag == 'created') {
			state = $.parseJSON(localStorage.getItem('subsectiontabs-null'));

			// Fetch id
			active = storage.find('li').map(function() {
				var item = $(this),
					name = item.find('input:eq(1)').val(),
					id = item.find('input:eq(0)').val();

				if(name == state.tab) return id;
			});

			// Store id
			if(active.length) {
				state.tab = active[0];
			}
		}

		// Get state of current entry
		else {
			state = $.parseJSON(localStorage.getItem('subsectiontabs-' + Symphony.Context.get('env').entry_id));

			// No storage yet
			if(state == null) {
				state = {
					tab: -1,
					height: 150
				};
			}
		}

		// Add controls
		storage.find('li').each(function(count) {
			var item = $(this),
				name = item.find('input:eq(1)').val(),
				id = item.find('input:eq(0)').val() || name,
				link = item.find('a').attr('href'),
				selected = false;

			// Set tabs height
			if(state.height) {
				tabs.height(state.height);
			}

			// Selection
			if(id == state.tab) {
				selected = true;
			}

			// Create control
			control = create(name, id, link, selected);
			controls.append(control);

			// Preload tab
			load(control);
		});

		// Select tab
		if(controls.find('.selected').length == 0) {
			controls.find(':first').click();
		}
	});

})(jQuery.noConflict());
