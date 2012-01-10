(function($) {

	/**
	 * Stage is a jQuery plugin for Symphony
	 * that adds a multiselect interface to the backend.
	 *
	 * @author: Nils Hörrmann, post@nilshoerrmann.de
	 * @source: http://github.com/nilshoerrmann/stage
	 */
	$.fn.symphonyStage = function() {
		var objects = $(this);

	/*---------------------------------------------------------------------------*/

		// Language strings
		Symphony.Language.add({
			'Browse': false,
			'Create New': false,
			'No items found.': false,
			'no results': false,
			'1 result': false,
			'{$count} results': false
		});

	/*---------------------------------------------------------------------------*/

		// Initialize Stage
		return objects.each(function() {
			var page = $(document),
				stage = $(this),
				field = stage.parent(),
				selection = stage.find('ul.selection'),
				templates = stage.find('li.template').remove(),
				empty = stage.find('li.empty').remove(),
				items = stage.find('li'),
				queue = $('<div class="queue"><ul></ul></div>'),
				placeholder = Symphony.Language.get('Browse') + ' …',
				browser = $('<div class="browser"><input type="text" class="placeholder" value="' + placeholder + '" /><div class="counter">' + Symphony.Language.get('{$count} results', { count: '0' }) + '</div></div>'),
				searchfield = browser.find('input'),
				counter = browser.find('.counter').hide(),
				dragger = $('<div class="dragger"></div>'),
				dropper = $('<div class="dropper"></div>'),
				index;

			// Handle empty stage
			if(empty.size() == 0) {
				empty = templates.filter('.create');
			}
			if(items.size() == 0) {
				selection.append(empty);
			}

			// Set sort order
			if(stage.is('.draggable')) {
				var sortorder = field.find('input[name*=sort_order]').val();

				if(sortorder && sortorder != 0) {
					sortorder = sortorder.split(',').reverse();
					$.each(sortorder, function(index, id) {
						items.filter('[data-value=' + id + ']').prependTo(selection);
					});
				}
			}

			// Add destructors
			if(stage.is('.destructable')) {
				var destructor = $('<a class="destructor">&#215;</a>');
				items.append(destructor.clone());

				// It's possible that the empty message is a create template
				if(empty.is('.template.create')) {
					empty.append(destructor.clone());
				}
			}

			// Add search field
			if(stage.is('.searchable')) {
				browser.prependTo(queue);
			}

			// Add constructor
			if(stage.is('.constructable')) {
				$('<a class="create">' + Symphony.Language.get('Create New') + '</a>').prependTo(queue);
			}

			// Add queue
			// Note: The queue list is always available
			if(queue.children().size() > 1) {
				queue.find('ul').hide();
				selection.after(queue);
			}

			// Make draggable
			if(stage.is('.draggable')) {
				selection.symphonyOrderable({
					items: 'li',
					handles: 'span, .handle'
				});
			}

			// Add drag helper
			if(page.find('div.dragger').size() == 0) {
				$('body').append(dragger.hide());
			}
			else {
				dragger = page.find('div.dragger');
			}

			// Add drop helper
			if(page.find('div.dropper').size() == 0) {
				$('body').append(dropper.hide());
			}
			else {
				dropper = page.find('div.dropper');
			}

			// Store templates:
			// This is needed for other script that interact with Stage
			stage.data('templates.stage', {
				templates: templates,
				empty: empty
			});

		/*-----------------------------------------------------------------------*/

			// Clicking
			stage.bind('click.stage', function(event) {

				// Prevent click-trough
				event.stopPropagation();
			});

			// Constructing
			stage.delegate('a.create', 'click.stage', function(event) {
				event.preventDefault();

				// Create new item
				construct();

				// Close browser
				stage.trigger('browsestop');
			});
			queue.delegate('li', 'construct.stage', function() {
				var item = $(this);
				construct(item);
			});

			// Destructing
			stage.delegate('a.destructor', 'click.stage', function(event) {
				var item = $(this).parents('li');
				item.trigger('destruct');
			});
			stage.delegate('li', 'destruct.stage', function() {
				var item = $(this);
				destruct(item);
			});

			// Selecting
			queue.delegate('li', 'click.stage', function() {
				var item = $(this);
				if(!item.is('.message')) {
					choose(item);
				}
			});

			// Browsing
			stage.delegate('.browser input', 'click.stage search.stage', function() {
				stage.trigger('browsestart');
				queue.find('ul').slideDown('fast');

				// Clear placeholder
				if(searchfield.val() == placeholder) {
					searchfield.val('').removeClass('placeholder');
				}

				// Close queue on body click
				$('body').one('click.stage', function() {
					stage.trigger('browsestop');
				});
			})
			stage.bind('browsestop.stage', function() {
				searchfield.val(placeholder).addClass('placeholder');
				queue.find('ul').slideUp('fast');
				queue.find('ul li').show();
				counter.hide();
			});
			stage.delegate('.browser input', 'focus.stage blur.stage', function(event) {
				if(event.type == 'click' || event.type == 'focus' || event.type == 'focusin') {
					browser.addClass('searching');
				}
				else {
					browser.removeClass('searching');
				}
			});
			stage.delegate('.browser .counter', 'click.stage', function() {
				counter.hide();
				searchfield.val('').focus().keyup();

				return false;
			});

			// Updating
			stage.bind('update.stage', function() {
				sync();
			});

			// Searching
			stage.delegate('.browser input', 'keyup.stage', function(event) {
				var strings = $.trim($(event.target).val()).toLowerCase().split(' ');

				// Searching
				if(strings.length > 0 && strings[0] != '') {
					stage.trigger('searchstart', [strings]);
				}

				// Not searching
				else {
					queue.find('li').removeClass('found').show();
					stage.trigger('searchstop');
					stage.trigger('browsestart');

					// Show item count
					count();
				}
			});
			stage.bind('searchstart.stage', function(event, strings) {
				search(strings);
			});

			// Sorting
			selection.bind('orderstop.stage', function() {

				// Get new item order
				var sortorder = selection.find('li').map(function() {
					return $(this).attr('data-value');
				}).get().join(',');

				// Save sortorder
				field.find('input[name*="sort_order"]').val(sortorder);
			});

			// Dragging & dropping
			if(stage.is('.droppable')) {
				selection.bind('orderstart', function(event, item) {
					$('textarea').removeClass('droptarget');
					move(item, event);
				});
				selection.delegate('span, .handle', 'mousedown.stage', function(event) {
					var item = $(event.target).parent('li');

					event.preventDefault();
					event.stopPropagation();

					move(item, event);
				});
			};

		/*-----------------------------------------------------------------------*/

			// Construct an item
			var construct = function(item) {
				stage.trigger('constructstart', [item]);
				selection.addClass('constructing');

				// Remove empty selection message
				empty.slideUp('fast', function() {
					empty.remove();
				});

				// Existing item
				if(item) {
					item = item.clone(true).hide().appendTo(selection);
					items = items.add(item);
				}

				// New item
				else {
					item = templates.filter('.create').clone().removeClass('template create empty').addClass('new').hide().appendTo(selection);
					items = items.add(item);
				}

				// Add destructor
				if(stage.is('.destructable') && item.has('a.destructor').size() == 0) {
					item.append(destructor.clone());
				}

				// Destruct other items in single mode
				if(stage.is('.single')) {
					items.not(item).trigger('destruct');
				}

				// Sync queue
				queue.find('li[data-value="' + item.attr('data-value') + '"]').trigger('choose');

				// Show item
				item.appendTo(selection);
				stage.trigger('constructanim', [item]);
				item.slideDown('fast', function() {
					selection.removeClass('constructing');
					stage.trigger('constructstop', [item]);
				});
			};

			// Destruct an item
			var destruct = function(item) {
				stage.trigger('destructstart', [item]);
				selection.addClass('destructing');

				// Update queue
				queue.find('li[data-value=' + item.attr('data-value') + ']').removeClass('selected');

				// Check selection size
				if(items.not(item).size() == 0 && !selection.is('.constructing') && !selection.is('.choosing')) {

					// It's possible that the empty message is a create template
					if(empty.is('.template.create')) {
						stage.trigger('constructstart', [empty]);
						var empty_item = empty.clone().addClass('new').hide().appendTo(selection);
						stage.trigger('constructanim', [empty_item]);
						empty_item.slideDown('fast', function() {
							stage.trigger('constructstop', [empty_item]);
						}).removeClass('template create empty');
						items = items.add(empty_item);
					}
					else {
						empty.appendTo(selection).slideDown('fast');
					}
				}

				// Sync queue
				queue.find('li[data-value="' + item.attr('data-value') + '"]').trigger('choose');

				// Remove item
				stage.trigger('destructanim', [item]);
				item.addClass('destructing').slideUp('fast', function() {
					item.remove();
					items = items.not(item);
					stage.trigger('destructstop', [item]);
				});

				selection.removeClass('destructing');
			};

			// Choose an item in the queue
			var choose = function(item) {
				stage.trigger('choosestart', [item]);
				selection.addClass('choosing');

				// Deselect
				if(item.is('.selected')) {

					// Destruct item
					if(stage.is('.destructable')) {
						item.removeClass('selected');
						selection.removeClass('choosing').find('li[data-value="' + item.attr('data-value') + '"]').trigger('destruct');
					}
				}

				// Select
				else {

					// Single selects
					if(stage.is('.single')) {
						items.trigger('destruct');
					}

					// Construct item
					if(stage.is('.searchable')) {
						item.addClass('selected');
						item.trigger('construct');
					}

					selection.removeClass('choosing');
				}

				stage.trigger('choosestop', [item]);
			};

			// Search the queue
			var search = function(strings) {
				var queue_items = queue.find('li'),
					size = 0;

				// Search
				queue_items.hide().removeClass('found odd').each(function(position) {
					var found = true,
						current = $(this),
						text = current.text();

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
						current.addClass('found').show();

						// Restore zebra
						if(size % 2 == 0) {
							current.addClass('odd');
						}
					}
				});

				// Show count
				count(size);

				// Found
				if(size > 0) {
					stage.trigger('searchfound');
				}

				// None found
				else {
					stage.trigger('searchnonfound');
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
					counter.fadeIn('fast');

					// No items
					if(size == 0) {
						counter.html(Symphony.Language.get('no results') + '<span>&#215;</span>');
					}

					// Single item
					else if(size == 1) {
						counter.html(Symphony.Language.get('1 result', { count: 1 }) + '<span>&#215;</span>');
					}

					// Multiple items
					else{
						counter.html(Symphony.Language.get('{$count} results', { count: size }) + '<span>&#215;</span>');
					}
				}
			};

			// Synchronize lists
			var sync = function() {
				queue.find('li').removeClass('selected');
				selection.find('li').each(function(index, item) {
					queue.find('li[data-value="' + $(item).attr('data-value') + '"]').addClass('selected');
				});
			};

			// Drag and drop items
			var move = function(item, event) {

				// Don't move messages
				if(item.is('.message')) {
					return true;
				}

				// Start dragging
				selection.addClass('dragging');
				dragger.empty().append(item.html()).attr('data-value', item.attr('data-value')).find('.destructor').remove();

				// Context
				if(stage.is('.draggable')) {
					context = selection;
				}
				else {
					context = item;
				}

				// Dragging
				page.bind('mousemove.stage', function(event) {
					var target = $(event.target);

					// Drag item
					drag(context, item, event);

					// Highlight drop target
					if(target.is('textarea')) {
						drop(target);
					}
					else if(!target.is('.dropper') && !target.is('.dragger') && target.parent('.dragger').size() == 0) {
						$('textarea').removeClass('droptarget');
						dropper.fadeOut('fast');
					}

				});

				// Stop dragging
				page.unbind('mouseup.stage').one('mouseup.stage', function(event) {

					// Remove helpers
					dropper.fadeOut('fast');
					dragger.fadeOut('fast');
					page.unbind('mousemove.stage');

					// Drop content
					$('textarea').trigger('drop.stage', [item]);
					selection.removeClass('dragging');
				});
			};

			// Drag items
			var drag = function(context, item, event) {
				var offset = context.offset(),
					area = {
						top: offset.top - 10,
						left: offset.left - 10,
						bottom: offset.top + context.height() + 10,
						right: offset.left + context.width() + 10
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

					// Stop ordering
					$(document).unbind('.orderable');
					item.removeClass('ordering');
					selection.removeClass('ordering').trigger('ordercancel', [item]);
				}

				// Hide drag helper
				else {
					dragger.fadeOut('fast');
				}
			};

			var drop = function(textarea) {
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

		});

	};

	// Initialise Stage
	$(document).ready(function() {
		$('div.stage').symphonyStage();
	});

})(jQuery.noConflict());
