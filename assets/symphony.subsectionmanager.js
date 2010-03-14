/*
 * SUBSECTION MANAGER
 * for Symphony
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/subsection
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
		
				object.trigger('editStart');
							
				var template = object.find(settings.drawer).clone().removeClass('template');
				var iframe = template.find('iframe').css('opacity', '0.01');
				var source = iframe.attr('src');
				var id = item.attr('value');
				
				if(!item.next('li').hasClass('drawer')) {
						
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
					
						// Set frame and drawer height
						var height = jQuery(this.contentDocument.body).find('form').outerHeight();
						iframe.height(height).animate({
							opacity: 1
						}, 'fast');
						template.animate({
							height: height
						}, settings.speed);
						
						// Fetch saving
						iframe.contents().find('div.actions input').click(function() {
							iframe.animate({
								opacity: 0.01
							}, 'fast');
						})
						
						// Update item 
						if(iframe.contents().find('#notice.success').size() > 0) {
							update(item.attr('value'), item, iframe, create);
						}
						
						// Delete item
						var remove = iframe.contents().find('button.confirm');
						remove.die('click').unbind();
						remove.click(function(event) {
							erase(event, id);
						});
						
					});
				
					// Automatically hide drawer later
					if(!create) {
						jQuery('body').bind('click', function(event) {
							if(jQuery(event.target).parents().filter('div.stage li.active, div.stage li.drawer').size() == 0) {
								object.find('div.stage li.active').removeClass('active');
								object.find('div.stage li.drawer:not(.template):not(.create)').slideUp('normal', function(element) {
									jQuery(this).remove();
								});
								jQuery('body').unbind('click');
							}
						});
					}

				}
		
				object.trigger('editEnd');
				
			};
			
			// Update item
			var update = function(id, item, iframe, create) {
			
				object.trigger('updateStart');
				
				var meta = object.find('input[name*=subsection_id]');
				var field = meta.attr('name').match(/\[subsection_id\]\[(.*)\]/)[1];
				var section = meta.val();
				
				// Get id of newly created items
				if(create) id = iframe.contents().find('form').attr('action').match(/\d+/g)[0];

				// Load item data
				jQuery.ajax({
					type: 'GET',
					url: Symphony.WEBSITE + '/symphony/extension/subsectionmanager',
					data: { 
						id: field, 
						section: section,
						entry: id
					},
					dataType: 'html',
					success: function(result) {
					
						// Find destructor
						var destructor = item.find('.destructor').clone();
						result = jQuery(result).append(destructor);
					
						// Remove old data and replace it
						item.replaceWith(result).fadeIn('fast');
						
						// Store new item
						if(create) {
							
							// Selectbox
							var option = jQuery('<option value="' + id + '" selected="selected">New item</option>');
							object.find('select').append(option);
							
							// Queue
							object.find('div.queue ul').append(result);
							
							// Close editor
							object.find('li.create').slideUp(settings.speed, function() {
								jQuery(this).remove();
							})
							
						}
						
					}
				});

				object.trigger('updateEnd');
						
			};
			
			// Remove item
			var erase = function(event, id) {

				object.trigger('removeStart');
				event.stopPropagation();
				
				if(confirm(Symphony.Language.get('Are you sure you want to delete this item? It will be remove from all entries. This step cannot be undone.'))) {
					object.find('li[value=' + id + '], li.drawer').slideUp(settings.speed, function() {
						jQuery(this).remove();
					});
					object.find('select option[value=' + id + ']').removeAttr('selected');
					return true;
				}
				else {
					event.preventDefault();
					return false;
				}
				
				object.trigger('removeEnd');
			
			};
			
			// Create item
			var create = function(event) {

				object.trigger('createStart');
				event.preventDefault();
				event.stopPropagation();
				
				var stage = object.find('div.stage ul.selection');
				var item = object.find(settings.template).clone().removeClass('template').appendTo(stage).slideDown(settings.speed);
				
				// Enable destructor
				item.find('.destructor').click(function(event) {
					item.next('li').andSelf().slideUp(settings.speed, function() {
						jQuery(this).remove();
					})
				});				
				
				// Open editor
				edit(item, true);
				
				object.trigger('createEnd');
			
			};

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
						object.find('div.stage').symphonyStage({
							source: object.find('select'),
							draggable: true,
							dragclick: function(item) {
								edit(item);
							},
							queue_ajax: {
								url: Symphony.WEBSITE + '/symphony/extension/subsectionmanager',
								data: { 
									id: id, 
									section: section 
								}
							}
						});
					});

					// Attach events
					object.find('.create').click(create);
					object.find('div.stage').bind('dragstop', object.subsection.getSortOrder);
					object.find('div.stage').bind('dragstart', object.subsection.close);

				},

				
				close: function() {
								
					// Handle drawers
					var active = object.find('ul.selection li.active');
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
					object.find(settings.items).each(function(index, item) {
						value = jQuery(item).attr('value');
						if(value != undefined) {
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
