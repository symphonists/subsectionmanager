/**
 * SUBSECTION MANAGER
 * for Symphony
 *
 * @author: Nils Hörrmann, http://nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/subsectionmanager
 */


/*-----------------------------------------------------------------------------
	Language strings
-----------------------------------------------------------------------------*/	 

	Symphony.Language.add({
		'There are no selected items': false,
		'Are you sure you want to delete this item? It will be remove from all entries. This step cannot be undone.': false
	}); 
	

/*-----------------------------------------------------------------------------
	Subsection plugin
-----------------------------------------------------------------------------*/

	jQuery.fn.symphonySubsectionmanager = function(custom_settings) {

		// Get objects
		var objects = this;
		
		// Get settings
		var settings = {
			items:				'li:not(.template):not(.empty)',
			drawer:				'li.drawer.template',
			template:			'li.item.template',
			autodiscover:		false,
			speed:				'fast',
			draggable:			true,
			dragtarget:			'textarea',
			formatter: {
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
			delay_initialize:	false
		};
		jQuery.extend(settings, custom_settings);


	/*-------------------------------------------------------------------------
		Subsection
	-------------------------------------------------------------------------*/

		objects = objects.map(function() {
		
			// Get elements
			var object = this;
			
			// Edit an item
			var edit = function(item, create) {
		
				object.trigger('editstart');
							
				var template = object.find(settings.drawer).clone().removeClass('template');
				var iframe = template.find('iframe').css('opacity', '0.01');
				var source = iframe.attr('target') + '/{$action}/{$id}' ;
				var id = item.attr('value');
				
				if(!item.next('li:not(.template)').hasClass('drawer')) {
						
					// Setup source
					if(create) {
						template.addClass('create');
						source = source.replace('{$action}', 'new').replace('{$id}', '');
					}
					else {
						source = source.replace('{$action}', 'edit').replace('{$id}', id);
					}
					iframe.attr('src', source);
					
					// Close other drawers
					jQuery('body').click();

					// Insert drawer
					item.addClass('active');
					template.insertAfter(item).slideUp(0).slideDown(settings.speed);
					
					// Handle iframe
					iframe.load(function(event) {
						
						var contents = iframe.contents();
						
						// Remove unneeded elements
						contents.find('body').addClass('subsection');
						contents.find('h1').remove();
						contents.find('h2').remove();
						contents.find('#nav').remove();
						contents.find('#usr').remove();
						contents.find('#notice:not(.error):not(.success)').remove();
						contents.find('#notice a').remove();
						
						// Focus first input field
						contents.find('input:first').focus();
						
						// Set frame and drawer height
						var height = contents.find('form').outerHeight();
						iframe.height(height).animate({
							opacity: 1
						}, 'fast');
						template.animate({
							height: height
						}, settings.speed);
						
						// Fetch saving
						contents.find('div.actions input').click(function() {
							iframe.animate({
								opacity: 0.01
							}, 'fast');
						})
						
						// Update item 
						if(contents.find('#notice.success').size() > 0) {
							update(item.attr('value'), item, iframe, create);
						}
											
						// Delete item
						var remove = contents.find('button.confirm');
						remove.die('click').unbind();
						remove.click(function(event) {
							erase(event, id);
						});
						
						// Focus first input
						contents.find('fieldset input:first').focus();
						
					});
				
					// Automatically hide drawer later
					if(!create) {
						jQuery('body').bind('click', function(event) {
							if(jQuery(event.target).parents().filter('li.active, li.drawer, li.new, ul.selection').size() == 0) {						
								object.find('div.stage li.active').removeClass('active');
								object.find('div.stage li.drawer:not(.create):not(.template)').slideUp('normal', function(element) {
									jQuery(this).remove();
								});
								jQuery('body').unbind('click');
							}
						});
					}

				}
		
				object.trigger('editstop');
				
			};
			
			// Update item
			var update = function(id, item, iframe, create) {
			
				object.trigger('updatestart');
				
				var meta = object.find('input[name*=subsection_id]');
				var field = meta.attr('name').match(/\[subsection_id\]\[(.*)\]/)[1];
				var section = meta.val();
				
				// Get id of newly created items
				if(create) id = iframe.contents().find('form').attr('action').match(/\d+/g);
				if(jQuery.isArray(id)) {
					id = id[id.length - 1];
				}

				// Load item data
				jQuery.ajax({
					type: 'GET',
					url: Symphony.WEBSITE + '/symphony/extension/subsectionmanager/get/',
					data: { 
						id: field, 
						section: section,
						entry: id
					},
					dataType: 'html',
					success: function(result) {
					
						result = jQuery(result);
					
						// Find destructor
						var destructor = item.find('.destructor').clone().click(function(event) {
							var item = jQuery(event.target).parent('li');
							object.find('div.stage').trigger('destruct', [item]);
						});

						// Remove old data and replace it
						result.clone().append(destructor).insertAfter(item).fadeIn('fast');
						item.remove();
						
						// Prevent clicks on layout anchors
						object.find('a.file, a.image').click(function(event) {
							event.preventDefault();
						});
											
						// Store new item
						if(create) {
							
							// Synchronize Stage
							item = object.find('li[value=' + result.attr('value') + ']');
							object.find('div.stage').trigger('sync', [item]);
							
							// Queue
							result.addClass('selected');
							object.find('div.queue ul').append(result);
							
							// Close editor
							object.find('li.create').slideUp(settings.speed, function() {
								jQuery(this).remove();
							})
							
							object.trigger('createstop');
							
						}
						
					}
				});

				object.trigger('updatestop');
						
			};
			
			// Remove item
			var erase = function(event, id) {

				object.trigger('removestart');
				event.stopPropagation();
				
				if(confirm(Symphony.Language.get('Are you sure you want to delete this item? It will be remove from all entries. This step cannot be undone.'))) {
					object.find('li[value=' + id + '], li.drawer:not(.template)').slideUp(settings.speed, function() {
						jQuery(this).remove();

						// Add empty selection message
						var selection = object.find('ul.selection').find(settings.items);
						if(selection.filter(':not(.new)').size() < 1) {
							object.find('ul.selection li.empty').slideDown(settings.speed);
						}

					});
					object.find('select option[value=' + id + ']').removeAttr('selected');
					return true;
				}
				else {
					event.preventDefault();
					return false;
				}
				
				object.trigger('removestop');
			
			};
			
			// Create item
			var create = function(event) {
	
				event.preventDefault();
				event.stopPropagation();

				// Do only create one item at once
				if(object.find('div.stage ul.selection li.new').size() == 0) {

					object.trigger('createstart');
					
					var stage = object.find('div.stage ul.selection');
					var empty = stage.find('li.empty');
					var item = object.find(settings.template).clone().removeClass('template').addClass('new').insertBefore(empty).slideDown(settings.speed);
									
					// Enable destructor
					item.find('.destructor').click(function(event) {
						item.next('li').andSelf().slideUp(settings.speed, function() {
							jQuery(this).remove();
						});
						// Add empty selection message
						var selection = object.find('ul.selection').find(settings.items);
						if(selection.filter(':not(.new)').size() <= 1) {
							object.find('ul.selection li.empty').slideDown(settings.speed);
						}
					});
					
					// Hide messages
					stage.find('li.empty:visible').slideUp(settings.speed);		
					
					// Open editor
					edit(item, true);
					
				}
							
			};

			var drop = function(event, helper) {
			
				var target = jQuery(event.target);
				var item = jQuery(helper);
				var text;

				// Remove dropper
				jQuery('.dropper').mouseout();
				
				// Remove destructor
				item.find('a.destructor').remove();
		
				// Formatter
				formatter = target.attr('class').match(/(?:markdown)|(?:textile)/) || ['html'];
				
				// Image or file
				if(item.find('.file').size() != 0) {
								
					var file = item.find('a.file');
					var matches = {
						text: file.text(),
						path: file.attr('href')
					}

					// Get type
					var type = 'file';
					if(file.hasClass('image')) type = 'image';
					
					// Prepare text
					text = object.subsection.substitute(settings.formatter[formatter.join()][type], matches);
				
				}
				
				// Text 
				else {
					text = item.text();
				}
				
				// Replace text
				var start = target[0].selectionStart || 0;
				var end = target[0].selectionEnd || 0;
				var original = target.val();
				target.val(original.substring(0, start) + text + original.substring(end, original.length));
				target[0].selectionStart = start + text.length;
				target[0].selectionEnd = start + text.length;

			}

		/*-------------------------------------------------------------------*/
			
			if (object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			object.subsection = {
			
				initialize: function() {
				
					var meta = object.find('input[name*=subsection_id]');
					var id = meta.attr('name').match(/\[subsection_id\]\[(.*)\]/)[1];
					var section = meta.val();
					
					// Set sortorder
					object.subsection.setSortOrder();
				
					// Initialize stage for subsections
					jQuery(document).ready(function() {
						var stage = object.find('div.stage');
						stage.symphonyStage({
							source: object.find('select'),
							draggable: stage.hasClass('draggable'),
							droppable: stage.hasClass('droppable'),
							constructable: stage.hasClass('constructable'),
							destructable: stage.hasClass('destructable'),
							searchable: stage.hasClass('searchable'),
							dragclick: function(item) {
								if(!item.hasClass('message')) {
									edit(item);
								}
							},
							queue_ajax: {
								url: Symphony.WEBSITE + '/symphony/extension/subsectionmanager/get/',
								data: { 
									id: id, 
									section: section 
								}
							}						
						});
					});

					// Attach events
					object.find('.create').live('click', create);
					object.find('div.stage').bind('dragstop', object.subsection.getSortOrder);
					object.find('div.stage').bind('constructstop', object.subsection.getSortOrder);
					object.bind('createstop', object.subsection.getSortOrder);
					object.find('div.stage').bind('dragstart', object.subsection.close);
					object.find('.destructor').bind('click', function(event) {
						jQuery('ul.selection li.drawer:not(.template)').slideUp(settings.speed, function() {
							jQuery(this).remove();
						});
					})
					
					// Handle drop events
					if(settings.draggable) {
						jQuery(settings.dragtarget).unbind('drop').bind('drop', function(event, item) {
							drop(event, item);
						});
					}

				},

				
				close: function() {
											
					// Handle drawers
					var active = object.find('ul.selection li.active:not(.new)');
					if(active.size() > 0) {	
								
						// Remove active state
						active.removeClass('active');
						
						// Close all drawers
						object.find('li.drawer:not(.template)').slideUp(settings.speed, function() {
							jQuery(this).remove();
						});
						
					}
					
				},
				
				getSortOrder: function() {
								
					// Get new item order
					var sorting = '';
					object.find('div.stage ul.selection').find(settings.items).each(function(index, item) {
						value = jQuery(item).attr('value');
						if(value != undefined && value != -1) {
							if(index != 0) sorting += ',';
							sorting += value;
						}
					});
					
					// Save sortorder				
					object.find('input[name*=sort_order]').val(sorting);

				},
				
				setSortOrder: function() {
					var sorting = object.find('input[name*=sort_order]').val().split(',').reverse();
					var items = object.find(settings.items);
					var selection = object.find('ul.selection');

					// Sort
					jQuery.each(sorting, function(index, value) {
						items.filter('[value=' + value + ']').prependTo(selection);
					});
					
				},
				
				substitute: function(template, matches) {
					var match;
					for(match in matches) {
						template = template.replace('{@' + match + '}', matches[match]);
					}
					return template;
				}
							
			}
			
			if (settings.delay_initialize !== true) {
				object.subsection.initialize();
			}
			
			return object;
		});
		
		return objects;

	}
	

/*-----------------------------------------------------------------------------
	Apply Subsection plugin
-----------------------------------------------------------------------------*/

	jQuery(document).ready(function() {
		jQuery('div.field-subsectionmanager').symphonySubsectionmanager();
	});
