
(function($) {

	/**
	 * This plugin add an interface for subsection management.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/subsectionmanager
	 */
	$(document).ready(function() {

		// Language strings
		Symphony.Language.add({
			'There are no selected items': false,
			'Are you sure you want to delete {$item}? It will be removed from all entries. This step cannot be undone.': false,
			'There are currently no items available. Perhaps you want create one first?': false,
			'New item': false,
			'Search existing items': false,
			'no matches': false,
			'1 match': false,
			'{$count} matches': false,
			'Remove item': false
		});
		
		// Subsection Manager
		$('div.field-subsectionmanager').each(function subsectionmanager() {
			var manager = $(this),
				duplicator = manager.find('div.frame'),
				selection = manager.find('ol'),
				manager_id = manager.attr('data-field-id'),
				manager_name = manager.attr('data-field-name'),
				subsection_id = manager.attr('data-subsection-id'),
				subsection_link = manager.attr('data-subsection-new'),
				dragger = $('<div class="dragger"></div>'),
				dropper = $('<div class="dropper"></div>'),
				textareas = $('textarea'),
				searchfield = $('<input type="text" />'),
				controls, browser, counter, existing,
				controlsWidth;
				
		/*-------------------------------------------------------------------------
			Events
		-------------------------------------------------------------------------*/
		
			// Set item names
			duplicator.on('constructstop.duplicator update.subsectionmanager', 'li', function setName(event) {
				$(this).find('input:hidden').attr('name', manager_name + '[]');
			});
		
			// Create new item
			duplicator.on('constructshow.duplicator', 'li', function createItem(event) {
				var item = $(this),
					iframe = item.find('iframe');

				// Load subsection
				iframe.addClass('initialise loading new').attr('src', subsection_link + '/new/').load(function() {
					load(item);
				});
			});
			
			// Add existing item
			manager.on('click.subsectionmanager', '.browser li:not(.selected)', function addItem(event) {
				var item = $(this).clone(),
					height, header, top;
					
				// Hide browser
				browser.removeClass('opened');
				searchfield.trigger('blur.subsectionmanager');
				
				// Prepare item
				addDestructor(item);
					
				// Add item
				item.trigger('constructstart.duplicator');
				duplicator.removeClass('empty');
				item.find('.content').hide();
				item.hide().addClass('collapsed').appendTo(selection);
				
				// Prepare animation
				height = item.height();
				header = item.find('header');
				top = header.css('padding-top');
			
				// Reveal item
				header.css('padding-top', 0).animate({
					paddingTop: top
				}, 'fast');
				item.css('height', 0).show().animate({
					height: height
				}, 'fast', function() {
					item.removeAttr('style');
					item.trigger('constructstop.duplicator');
				});
			});
			
			// Edit items
			duplicator.on('expandstart.collapsible', 'li', function loadItem() {
				var item = $(this),
					content = item.find('.content'),
					iframe = content.find('iframe');
					
				// Check if iframe exists
				if(iframe.length == 0) {
					iframe = $('<iframe />').appendTo(content);
				}
					
				// Load iframe
				iframe.addClass('initialise loading').attr('src', subsection_link + '/edit/' + item.attr('data-value') + '/').load(function() {
					load(item);
				});
			});
			
			// Toggle search
			manager.on('focus.subsectionmanager', '.browser input', function toggleSearch(event) {

				// Check if browser should open upwards or downwards
				if($(window).height() - browser.offset().top < 240) {
					browser.removeClass('downwards').addClass('upwards');
				}
				else {
					browser.removeClass('upwards').addClass('downwards');
				}

				// Open browser
				browser.addClass('opened');

				// List existing items
				if(existing.children().length == 0) {
					list();
				}

				// Sync selection with list of existing items
				else {
					sync();
				}
			});
			
			manager.on('blur.subsectionmanager', '.browser input', function toggleSearch(event) {
				setTimeout(function() {
					browser.removeClass('opened');
				}, 100);
			});
			
			manager.on('click.subsectionmanager', '.browser > span', function clearSearch(event) {
				counter.hide();
				searchfield.val('').trigger('focus').trigger('input');
			});
		
			// Find items
			manager.on('input.subsectionmanager keyup.subsectionmanager', '.browser input', function findMatches(event) {
				var strings = $.trim($(event.target).val()).toLowerCase().split(' ');
				
				// Show filtered items
				if(strings.length > 0 && strings[0] != '') {
					search(strings);
				}
				
				// Show all items
				else {
					existing.find('li').show();
					counter.hide();
				}
			});
			
			// Drag items
			manager.on('mousedown.subsectionmanager', 'li > header', function(event) {
				var item = $(event.target).closest('.instance');
				
				// Don't highlight text
				event.preventDefault();
				event.stopPropagation();
				
				move(item, event);
			});
			
			// Drop items
			textareas.off('drop.subsectionmanager').on('drop.subsectionmanager', function(event, item) {
				var textarea = $(this);
				drop(textarea, item);
			});
			
			// Stop dragging
			manager.on('mouseup.subsectionmanager click.subsectionmanager', 'li', function(event) {
				$(document).off('mousemove.subsectionmanager mouseup.subsectionmanager');
			});
								
		/*-------------------------------------------------------------------------
			Functions
		-------------------------------------------------------------------------*/
			
			// Load single subsection entry
			var load = function(item) {
				var header = item.find('> header'),
					content = item.find('> .content'),
					iframe = item.find('iframe'),
					subsection = iframe.contents(),
					body = subsection.find('body').addClass('inline subsection'),
					form = body.find('form').removeAttr('style').removeClass('columns'),
					init = true;

				// Simplify UI
				subsection.find('#header, #context, .drawer').remove();
				subsection.find('.drawer-vertical-right').removeClass('drawer-vertical-right');
				
				// Pre-populate first input with browser content
				if(iframe.is('.new') && searchfield.val() != '') {
					subsection.find('input:visible, textarea').eq(0).val(searchfield.val());
				}
				
				// Lock item
				if(!duplicator.is('.editable') && !item.is('.new')) {
					subsection.find('input:visible, textarea').attr('readonly', 'readonly');
					subsection.find('select').attr('disabled', 'disabled');
					subsection.find('div.actions, .field-upload .frame em').remove();
				}
				
				// Delete item
				if(iframe.is('.deleting')) {
					init = false;
					item.slideUp('fast', function() {
						item.remove();
						clear();
					});
				}
				
				// Update item
				if(!iframe.is('.initialise')) {
					update(item);
				}
				
				// Resize item
				subsection.find('#contents > form').on('resize.subsectionmanager', function(event, init) {
					var height = $(this).outerHeight();

					if(init == true || (!iframe.is('.loading') && content.data('height') !== height && height !== 0)) {
						resize(content, iframe, body, height);
					}
				}).trigger('resize.subsectionmanager', [init]);
			
				// Save item
				subsection.find('div.actions input').on('click.subsectionmanager', function(event) {
					iframe.addClass('loading');
				});
				
				// Delete item
				subsection.find('div.actions button').on('click.subsectionmanager', function(event) {
					iframe.addClass('deleting');
				});
				
				// Remove markers
				iframe.removeClass('new').removeClass('initialise');
			};

			// Update item
			var update = function(item) {
				item.addClass('updating');

				// Get entry
				var entry = item.find('iframe').contents().find('form').attr('action').match(/(\d+)(?!.*\d)/);
				if(entry != null) {
					entry = entry[0];
				}

				// Load item data
				$.ajax({
					type: 'GET',
					url: Symphony.Context.get('root') + '/symphony/extension/subsectionmanager/get/',
					data: {
						id: manager_id,
						section: subsection_id,
						entry: entry
					},
					dataType: 'html',
					success: function(result) {
						var result = $(result),
							header = result.find('> header'),
							id = result.find('input:first');

						if(header.length > 0) {
							
							// Update header
							item.find('> header').replaceWith(header);
							addDestructor(item);
		
							// Set id for new items
							if(item.attr('data-value') == undefined) {
								item.append(id).attr('data-value', id.val()).trigger('update.subsectionmanager');
							}
	
							// Clear browser list
							clear();
						}

						item.removeClass('updating');
					}
				});
			};
						
			// Resize editor
			var resize = function(content, iframe, body, height) {
			
				// Set iframe height
				iframe.height(height).removeClass('loading');
				
				// Set scroll position
				body[0].scrollTop = 0;
				body[0].querySelector('#wrapper').scrollTop = 0;
				
				// Set content height
				content.data('height', height).animate({
					height: height
				}, 'fast');
			};
						
			// List all subsection entries
			var list = function() {
				$.ajax({
					async: true,
					type: 'GET',
					dataType: 'html',
					url: Symphony.Context.get('root') + '/symphony/extension/subsectionmanager/get/',
					data: {
						id: manager_id,
						section: subsection_id
					},
					success: function(result) {
						var result = $(result).hide();

						// Append existing items
						existing.removeClass('empty').empty().append(result);

						// Sync selection with list of existing items
						sync();

						// Reveal items
						result.fadeIn('fast');
					}
				});
			};
			
			// Clear list of subsection entries
			var clear = function() {
				existing.addClass('empty').empty();
			};
			
			// Sync selection and list
			var sync = function() {
				var items = existing.find('li').removeClass('selected');
				selection.find('li').each(function checkSelected() {
					items.filter('[data-value="' + $(this).attr('data-value') + '"]').addClass('selected');
				});
			};
			
			// Search the queue
			var search = function(strings) {
				var items = existing.find('li'),
					size = 0;

				// Search
				items.hide().addClass('hidden').each(function find(position) {
					var item = $(this),
						text = item.text(),
						found = true;

					// Items have to match all search strings
					$.each(strings, function(count, string) {
						var expression = new RegExp(string, 'i');
						if(text.search(expression) == -1) {
							found = false;
						}
					});

					// Show matching items
					if(found) {
						size++;
						item.removeClass('hidden').show();
					}
				});

				// Show count
				count(size);
				
				// Hide list
				if(size == 0) {
					existing.hide();
				}

				// Show list
				else {
					existing.show();
				}
			};

			// Count items
			var count = function(size) {

				// No size
				if(!size && size !== 0) {
					counter.hide();
				}

				// Show counter
				else {
					counter.show();

					// No items
					if(size == 0) {
						counter.text(Symphony.Language.get('no matches'));
					}

					// Single item
					else if(size == 1) {
						counter.text(Symphony.Language.get('1 match', { count: 1 }));
					}

					// Multiple items
					else{
						counter.text(Symphony.Language.get('{$count} matches', { count: size }));
					}
				}
			};
			
			// Add destructor
			var addDestructor = function(item) {
				if(duplicator.is('.destructable')) {
					item.find('header').append('<a class="destructor">' + Symphony.Language.get('Remove item') + '</a>');
				}
			};

			// Drag and drop items
			var move = function(item, event) {
				selection.addClass('dragging');
				dragger.empty().append(item.html()).attr('data-value', item.attr('data-value')).find('.destructor').remove();

				// Dragging
				$(document).on('mousemove.subsectionmanager', function(event) {
					var target = $(event.target);

					// Drag item
					drag(item, event);

					// Highlight drop target
					if(target.is('textarea')) {
						hover(target);
					}
					else if(!target.is('.dropper') && !target.is('.dragger') && target.parent('.dragger').size() == 0) {
						textareas.removeClass('droptarget');
						dropper.fadeOut('fast');
					}

				});
			};

			// Drag items
			var drag = function(item, event) {
				var offset = selection.offset(),
					area = {
						top: offset.top - 10,
						left: offset.left - 10,
						bottom: offset.top + selection.height() + 10,
						right: offset.left + selection.width() + 10
					},
					x = event.pageX,
					y = event.pageY;

				// Move drag helper
				dragger.css({
					position: 'absolute',
					top: y - 15,
					left: x + 15
				});

				// Show drag helper
				if(x < area.left || x > area.right || y < area.top || y > area.bottom) {
					dragger.fadeIn('fast');
	
					// Stop dragging
					$(document).off('mouseup.subsectionmanager').one('mouseup.subsectionmanager', function(event) {

						// Remove helpers
						dropper.fadeOut('fast');
						dragger.fadeOut('fast');
						$(document).off('mousemove.subsectionmanager');

						// Drop content
						textareas.filter('.droptarget').trigger('drop.subsectionmanager', [item]);
						selection.removeClass('dragging');
					});
				}

				// Hide drag helper
				else {
					dragger.fadeOut('fast');
		
					// Stop dragging
					$(document).off('mouseup.subsectionmanager')
				}
			};

			// Hover over textarea
			var hover = function(textarea) {
				var offset = textarea.offset();

				// Show drop helper
				textarea.addClass('droptarget');
				dropper.css({
					width: textarea.outerWidth(),
					height: textarea.outerHeight(),
					top: offset.top - 4,
					left: offset.left - 4
				}).fadeIn('fast');
			}

			// Dropping items
			var drop = function(textarea, item) {
				var formatter = textarea.attr('class').match(/(?:markdown)|(?:textile)/) || ['html'],
					syntax = {
						markdown: {
							image: '![{@text}]({@path})',
							file: '[{@text}]({@path})'
						},
						textile: {
							image: '!{@path}({@text})!',
							file: '"{@text}":({@path})'
						},
						html: {
							image: '<img src="{@path}" alt="{@text}" />',
							file: '<a href="{@path}">{@text}</a>'
						}
					},
					text = item.attr('data-drop'),
					file, type, match, matches;

				// No custom drop text available
				if(!text) {
					text = $.trim(item.clone().find('a.destructor').remove().end().text());

					// Image or file
					if(item.find('a.file').length != 0) {

						//
						file = item.find('a.file');
						matches = {
							text: text,
							path: file.attr('href')
						};

						// Get type
						type = 'file';
						if(file.hasClass('image')) {
							type = 'image';
						}

						// Prepare text
						text = syntax[formatter.join()][type];
						for(match in matches) {
							text = text.replace('{@' + match + '}', matches[match]);
						}
					}
				}

				// Replace text
				var start = textarea[0].selectionStart || 0;
				var end = textarea[0].selectionEnd || 0;
				var original = textarea.val();
				textarea.val(original.substring(0, start) + text + original.substring(end, original.length));
				textarea[0].selectionStart = start + text.length;
				textarea[0].selectionEnd = start + text.length;
			};
							
		/*-------------------------------------------------------------------------
			Initialisation
		-------------------------------------------------------------------------*/
				
			// Initialise Duplicators
			duplicator.symphonyDuplicator({
				headers: 'header',
				constructable: duplicator.is('.constructable'),
				destructable: duplicator.is('.destructable'),
				collapsible: true,
				orderable: duplicator.is('.sortable'),
				maximum: (duplicator.is('.multiple') ? 1000 : 1),
				save_state: false
			});
			
			// Remove templates
			if(!duplicator.is('.constructable')) {
				duplicator.find('li.template').remove();
			}
			
			// Enable searching
			if(duplicator.is('.searchable')) {
				controls = manager.find('fieldset.apply');
				browser = $('<div class="browser" />').insertAfter(duplicator);
				searchfield.attr('placeholder', Symphony.Language.get('Search existing items') + ' …').appendTo(browser);
				counter = $('<span />').hide().appendTo(browser);
				existing = $('<ol class="empty" />').appendTo(browser);
				
				// Adjust to button width
				if(controls.length > 0) {
					browser.css('margin-right', controls.find('button').outerWidth() + 10);
				}
			}
			
			// Close existing items, if no states are stored
			selection.find('li').trigger('collapse.collapsible', [0]);
			
			// Enable dropping
			if(duplicator.is('.droppable')) {
				var body = $('body');
				
				// Add drag helper
				if(body.find('div.dragger').length == 0) {
					body.append(dragger.hide());
				}
				else {
					dragger = body.find('div.dragger');
				}
	
				// Add drop helper
				if(body.find('div.dropper').length == 0) {
					body.append(dropper.hide());
				}
				else {
					dropper = body.find('div.dropper');
				}
			}
		});
		
	});
	
})(jQuery.noConflict());
