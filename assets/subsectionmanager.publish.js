
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
				subsection = manager.attr('data-subsection-id'),
				subsection_link = manager.attr('data-subsection-new'),
				controls, browser, searchfield, counter, existing, controlsWidth;
				
		/*-------------------------------------------------------------------------
			Events
		-------------------------------------------------------------------------*/
		
			// Set item names
			duplicator.on('constructstop.duplicator', 'li', function setName(event) {		
				$(this).find('input:hidden').attr('name', manager_name + '[]');
			});
		
			// Create new item
			duplicator.on('constructshow.duplicator', 'li', function createItem(event) {
				var item = $(this),
					iframe = item.find('iframe');

				// Load subsection
				iframe.attr('src', subsection_link + '/new/').load(function() {
					load(iframe);
					
					// Pre-populate first input with browser content
					if(searchfield.val() != '') {
						iframe.contents().find('input:visible, textarea').eq(0).val(searchfield.val());
					}
				});
			});
			
			// Add existing item
			manager.on('click.subsectionmanager', '.browser li:not(.selected)', function addItem(event) {
				var item = $(this).clone();
					
				// Hide browser
				browser.removeClass('opened');
				
				// Prepare item
				item.find('header').append('<a class="destructor">' + Symphony.Language.get('Remove item') + '</a>');
					
				// Add item
				item.trigger('constructstart.duplicator');
				duplicator.removeClass('empty');
				item.hide().appendTo(selection);
				item.trigger('collapse.collapsible', [0]);
				item.slideDown('fast', function() {
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
				iframe.css('opacity', 0.01).attr('src', subsection_link + '/edit/' + item.attr('data-value') + '/').load(function() {
					load(iframe);
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
				if(existing.children().length == 0) {
					list();
				}			

				// Sync selection with list of existing items
				sync();
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
				
		/*-------------------------------------------------------------------------
			Functions
		-------------------------------------------------------------------------*/
			
			// Load single subsection entry
			var load = function(iframe) {
				var content = iframe.parent(),
					contents = iframe.contents(),
					body = contents.find('body').addClass('inline subsection'),
					form = body.find('form').removeClass('columns'),
					height;

				// Simplify UI
				contents.find('header h1, header ul, header nav, #context').remove();
				
				// Set iframe height
				height = contents.find('#contents').outerHeight();
				iframe.height(height).animate({
					opacity: 1,
					visibility: 'visible'
				}, 'fast');
				
				// Set scroll position
				body[0].scrollTop = 0;
				
				// Set content height
				content.animate({
					height: height
				}, 'fast');
			
				// Fetch saving
				contents.find('div.actions input').on('click.subsectionmanager', function() {
					console.log('save');
					iframe.addClass('saving').animate({
						opacity: 0.01
					}, 'fast', function() {
						iframe.css('visibility', 'hidden');
					});
				});
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
						section: subsection
					},
					success: function(result) {
						existing.removeClass('empty').empty().append(result);
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
							
		/*-------------------------------------------------------------------------
			Initialisation
		-------------------------------------------------------------------------*/
				
			// Initialise Duplicators
			duplicator.symphonyDuplicator({
				headers: 'header',
				collapsible: true,
				save_state: false
			});
			
			// Create search
			// @todo: Check if manager is searchable
			controls = manager.find('fieldset.apply');
			browser = $('<div class="browser" />').insertAfter(duplicator);
			var searchfield = $('<input />', {
				type: 'text',
				placeholder: Symphony.Language.get('Search existing items') + ' …'
			}).appendTo(browser);
			counter = $('<span />').hide().appendTo(browser);
			existing = $('<ol class="empty" />').appendTo(browser);
			
			// Adjust to button width
			if(controls.length > 0) {
				browser.css('margin-right', controls.find('button').outerWidth() + 10);
			}
			
			// Close existing items, if no states are stored
			selection.find('li').trigger('collapse.collapsible', [0]);
		});
		























		// OLD CODE
		$('div.field-subsectionmanagerOLD').each(function() {
			var manager = $(this),
				stage = manager.find('div.stage'),
				selection = stage.find('ul.selection'),
				queue = stage.find('div.queue'),
				queue_loaded = false,
				drawer = $('<div class="drawer"><iframe name="subsection-' + manager.attr('data-subsection-id') + '" src="about:blank" frameborder="0"></iframe></div>'),
				manager_id = manager.attr('data-field-id'),
				manager_name = manager.attr('data-field-name'),
				subsection = manager.attr('data-subsection-id'),
				subsection_link = manager.attr('data-subsection-new'),
				dragger = $('div.dragger'),
				empty = $('<li class="message"><span>' + Symphony.Language.get('There are currently no items available. Perhaps you want create one first?') + '</li>'),
				textarea = $('textarea');

		/*-----------------------------------------------------------------------*/

			// Constructing
			stage.bind('constructstop', function(event, item) {

				// Set name
				item.find('input:hidden').attr('name', manager_name + '[]');

				// New item
				if(item.is('.new')) {
					create(item);
				}
			});

			// Destructing
			stage.bind('destructstart', function(event, item) {

				// Hide drawer
				item.find('div.drawer').slideUp('fast', function() {
					$(this).remove();
				});
			});

			// Queuing
			stage.delegate('li.preview a.file', 'click', function() {

				// Prevent clicks on links in queue
				return false;
			});

			// Editing
			if(!stage.is('.locked')) {
				selection.delegate('li:not(.new, .empty, .message)', 'click', function(event) {
					var item = $(this),
						target = $(event.target),
						editor = item.find('div.drawer');

					// Don't open editor for item that will be removed
					if(target.is('.destructor, input')) {
						return true;
					}

					// Open editor
					if(editor.length == 0) {
						item.addClass('active');
						edit(item);
					}

					// Close editor
					else {
						item.removeClass('active');
						editor.slideUp('fast', function() {
							$(this).remove();
						});
					}

					// Don't follow links
					return false;
				});
			}

			// Updating
			stage.bind('edit', function(event, item, iframe) {
				var id = iframe.contents().find('form').attr('action').match(/\d+/g);

				// Fetch item id
				if($.isArray(id)) {
					id = id[id.length - 1];
				}

				// Update item
				update(id, item, iframe);
			});
			
			// Naming
			stage.bind('update', function(event) {
				selection.find('input:hidden').attr('name', manager_name + '[]');
			});

			// Searching
			stage.bind('browsestart', function(event) {
				browse();
			});

			// Deleting
			stage.bind('erase', function(event, item) {
				erase(item);
			});

			// Dragging
			selection.delegate('.handle', 'mousedown.stage', function(event) {
				var handle = $(this);

				// Set class
				if(handle.parents('li').hasClass('preview')) {
					dragger.addClass('preview');
				}
				else {
					dragger.removeClass('preview');
				}
			});

			// Dropping
			textarea.unbind('drop.stage').bind('drop.stage', function(event, item) {
				var target = $(this);

				// Insert text
				if(target.is('.droptarget')) {
					drop(target, item);
				}
			});

		/*-----------------------------------------------------------------------*/

			// Load subsection
			var load = function(item, editor, iframe) {
				var content = iframe.contents();
				
				// Handle Firefox flickering
				editor.css('overflow', 'hidden');

				// Handle Firefox flickering
				editor.css('overflow', 'hidden');

				// Adjust interface
				content.find('body').addClass('inline subsection');
				content.find('header, #context').remove();
				content.find('fieldset input:first').focus();

				// Frame resizing
				content.find('#contents').resize(function() {
					if(!iframe.is('.saving')) {
						resize(content, editor, iframe);
					}
				});

				// Resize on load
				resize(content, editor, iframe);

				// Delete item
				if(item.is('.delete')) {

					// Remove queue item
					queue.find('li[data-value="' + item.attr('data-value') + '"]').slideUp('fast', function() {
						$(this).remove();

						// Show empty queue message
						if(queue.find('li').length == 0) {
							empty.clone().appendTo(queue.find('ul')).slideDown('fast');
						}
					});

					// Remove item
					item.trigger('destruct');
					stage.trigger('deletestop', [item]);
				}

				// Edit item
				else {

					// Set height
					var height = content.find('#wrapper').outerHeight() || iframe.height();
					iframe.css('visibility', 'visible').height(height).animate({
						opacity: 1
					}, 'fast', function() {

						// Make sure iframe is defenitly visible
						$(this).css('visibility', 'visible');
					});
					editor.animate({
						height: height
					}, 'fast');

					// Handle inline image preview
					if(content.find('body > img').width() > iframe.width()) {
					  content.find('body > img').css({
						'width': iframe.width()
					  });
					}

					// Fetch saving
					content.find('div.actions input').click(function() {
						iframe.addClass('saving').animate({
							opacity: 0.01
						}, 'fast', function() {
							iframe.css('visibility', 'hidden');
						});
					});

					// Trigger update
					if(content.find('#notice.success').length > 0) {
						stage.trigger('edit', [item, iframe]);
					}

					// Trigger delete
					content.find('button.confirm').click(function(event) {
						event.stopPropagation();

						var message = Symphony.Language.get('Are you sure you want to delete {$item}? It will be removed from all entries. This step cannot be undone.', {
							'item': item.find('span:first').text()
						});

						// Prepare deletion
						if(confirm(message)) {
							stage.trigger('deletestart', [item]);
							item.addClass('delete');

							// Hide iframe
							iframe.animate({
								opacity: 0.01
							}, 'fast');

							// Delete item
							return true;
						}

						// Stop deletion
						else {
							return false;
						}
					});
				}
			};

			// Resize editor
			var resize = function(content, editor, iframe) {
				var height = content.find('#contents').height() + content.find('#header .error').height(),
					body = content.find('body');

				if(editor.data('height') != height) {
					iframe.height(height);
					editor.data('height', height).animate({
						'height': height
					}, 'fast');
				}
			};

			// Browse queue
			var browse = function(async) {
				var list = queue.find('ul');

				// Append queue if it's not present yet
				if(queue_loaded == false && !list.is('.loading')) {
					list.addClass('loading');

					// Get queue items
					$.ajax({
						async: (async == true ? true : false),
						type: 'GET',
						dataType: 'html',
						url: Symphony.Context.get('root') + '/symphony/extension/subsectionmanager/get/',
						data: {
							id: manager_id,
							section: subsection
						},
						success: function(result) {

							// Empty queue
							if(!result) {
								empty.clone().appendTo(list);
							}

							// Append queue items
							else {
								$(result).appendTo(list);

								// Highlight selected items
								stage.trigger('update');
							}

							// Save status
							list.removeClass('loading');
							queue_loaded = true;
						}
					});
				}
			};

			// Create item
			var create = function(item) {
				stage.trigger('createstart', [item]);

				var editor = drawer.clone().hide().animate({
					height: 50
				});
				
				// Prepare iframe
				editor.find('iframe').css({
					opacity: 0.01,
					height: 0
				}).attr('src', subsection_link + '/new/').load(function() {
					iframe = $(this);
					load(item, editor, iframe);
				});

				// Show subsection editor
				editor.appendTo(item).slideDown('fast');

				stage.trigger('createstop', [item]);
			};

			// Edit item
			var edit = function(item) {
				stage.trigger('editstart', [item]);

				var editor = drawer.clone().hide();

				// Prepare iframe
				editor.find('iframe').css('opacity', '0.01').attr('src', subsection_link + '/edit/' + item.attr('data-value') + '/').load(function() {
					iframe = $(this);
					load(item, editor, iframe);
				});

				// Show subsection editor
				editor.appendTo(item).slideDown('fast');

				stage.trigger('editstop', [item]);
			};

			// Update item
			var update = function(id, item, iframe) {
				item.addClass('updating');

				// Load item data
				$.ajax({
					type: 'GET',
					url: Symphony.Context.get('root') + '/symphony/extension/subsectionmanager/get/',
					data: {
						id: manager_id,
						section: subsection,
						entry: id
					},
					dataType: 'html',
					success: function(result) {
						var result = $(result);

						// Get queue item
						var queue_item = queue.find('li[data-value="' + item.attr('data-value') + '"]');

						// Add preview class
						if(stage.is('.preview') && result.find('strong.file, img').length > 0) {
							result.addClass('preview');
						}

						// New item
						if(queue_item.length == 0) {
							stage.find('div.queue ul').prepend(result.clone());
							queue.find('li.message').remove();
						}

						// Existing item
						else {
							queue_item.html(result.html()).addClass(result.attr('class')).attr('data-value', result.attr('data-value'));
						}

						// Update item
						item.children().not('.destructor').not('.drawer').remove();
						result.children().prependTo(item);
						item.attr('class', result.attr('class')).attr('data-value', result.attr('data-value')).attr('data-drop', result.attr('data-drop'));
						stage.trigger('update');
					}
				});
			};

			// Dropping items
			var drop = function(target, item) {
				var formatter = target.attr('class').match(/(?:markdown)|(?:textile)/) || ['html'],
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
				var start = target[0].selectionStart || 0;
				var end = target[0].selectionEnd || 0;
				var original = target.val();
				target.val(original.substring(0, start) + text + original.substring(end, original.length));
				target[0].selectionStart = start + text.length;
				target[0].selectionEnd = start + text.length;
			};

		/*-----------------------------------------------------------------------*/

			// Preload queue items
			if(stage.is('.searchable')) {
				browse(true);
			}

			// Name existing items
			stage.trigger('update');
		});

	});

})(jQuery.noConflict());
