/*
 * STAGE for Symphony
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/stage
 */


/*-----------------------------------------------------------------------------
	Language strings
-----------------------------------------------------------------------------*/
 
	Symphony.Language.add({
		'Browse': false,
		'Create New': false,
		'Remove Item': false,
		'There are currently no items available. Perhaps you want create one first?': false,
		'Click here to create a new item.': false,
		'Load items': false
	});
 

/*-----------------------------------------------------------------------------
	Stage plugin
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyStage = function(custom_settings) {
	
		// Get objects
		var objects = this;
		
		// Get settings
		var settings = {
			items:				'li:not(.template):not(.empty)',	//
			source:				false,					// A stage source, e. g. a select box 
			queue:				'div.queue input',		// Handle for queue
			queue_ajax:			false,					// AJAX options for queue
			orderable:			true,					// Can instances be ordered?
			constructable:		true,					// Allow construction of new instances?
			destructable:		true,					// Allow destruction of instances?
			searchable:			true,					// Allow searching of queue?
			minimum:			0,						// Do not allow instances to be removed below this limit.
			maximum:			1000,					// Do not allow instances to be added above this limit.
			speed:				'fast',					// Control the speed of any animations
			queue_speed:		'normal',
			delay_initialize:	false
		};
		jQuery.extend(settings, custom_settings);

	
	/*-------------------------------------------------------------------------
		Orderable
	-------------------------------------------------------------------------*/
		
		if(settings.orderable) objects = objects.symphonyOrderable();

		
	/*-------------------------------------------------------------------------
		Stage
	-------------------------------------------------------------------------*/
		
		objects = objects.map(function() {

			// This object
			var object = this;
			
			// Construct a new item
			var construct = function(item) {

				var value = item.attr('value');
				
				// Remove empty selection message
				object.find('ul.selection li.empty').slideUp('fast');
				
				// Add queue selection
				var queue_item = object.find('div.queue li[value=' + value + ']');
				queue_item.addClass('selected');
				
				// Add stage selection
				var stage_item = queue_item.clone().hide();
				stage_item.appendTo(object.find('ul:first')).slideDown(settings.speed);
				object.stage.addDestructor(stage_item);
				
				// Add source selection
				var source = jQuery(settings.source);
				if(source.size() > 0) {
					source.find('option[value=' + value + ']').attr('selected', 'selected');
				}
				
			};
			
			// Destruct an item
			var destruct = function(item) {
				
				var value = item.attr('value');

				// Remove stage selection
				object.find('ul.selection li[value=' + value + ']').slideUp(settings.speed, function() {
					jQuery(this).remove();
				});
				
				// Remove queue selection
				object.find('div.queue li[value=' + value + ']').removeClass('selected');
				
				// Remove source selection
				var source = jQuery(settings.source);
				if(source.size() > 0) {
					source.find('option[value=' + value + ']').removeAttr('selected');
				}
				
				// Add empty selection message
				var selection = object.find('ul.selection').find(settings.items);
				if(selection.size() <= 1) {
					object.find('ul.selection li.empty').slideDown(settings.speed);
				}

			};
			
		/*-------------------------------------------------------------------*/
			
			if(object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			object.stage = {

				initialize: function() {
				
					var items = object.find(settings.items);
					
					if(items.size() > 0) {
						object.find('ul.selection li.empty').hide();
					}
					
					// Add queue
					if(settings.searchable || settings.constructable) {
						var queue = jQuery('<div class="queue" />');
						if(settings.searchable) jQuery('<input type="search" placeholder="' + Symphony.Language.get('Browse') + ' &#8230;" class="browser" value="" />').appendTo(queue);
						if(settings.constructable) jQuery('<button class="create">' + Symphony.Language.get('Create New') + '</button>').appendTo(queue);
						object.find('ul:first').after(queue);
					}
					
					// Add destructors
					object.stage.addDestructor(items);

					// Open queue on click
					object.find(settings.queue).bind('click', this.showQueue);
					
					// Search
					if(settings.searchable) {
						object.find('div.queue .browser').bind('click keyup change', object.stage.search);
					}
					
				},
				
				addDestructor: function(items) {
					if(settings.destructable) {
						jQuery('<a class="destructor">' + Symphony.Language.get('Remove Item') + '</a>').appendTo(items).click(function(event) {
							var item = jQuery(event.target).parent('li');
							destruct(item);
						});
					}
				},
				
				showQueue: function(event) {
					var queue = object.find('div.queue');
					
					// Append queue if it's not present yet
					if(queue.find('ul').size() == 0) {
						
						// Append queue
						var list = jQuery('<ul class="queue"></ul>').appendTo(queue).hide();
						
						// Get queue content
						if(settings.queue_ajax) {
							list.append(jQuery('<li class="message loading"><span>' + Symphony.Language.get('Load items') + ' &#8230;</span></li>')).slideDown(settings.speed);
							jQuery.ajax(jQuery.extend({
								async: false,
								type: 'GET',
								dataType: 'html',
								success: function(result) {

									list.find('li.loading').remove();
									
									if(result != '') {	
										list.append(jQuery(result));
										
										// Highlight items, add events
										list.find('li').each(function(index, element) {
											element = jQuery(element);
																				
											// Odd
											if(index % 2 != 0) element.addClass('odd')
											
											// Selected
											var value = jQuery(element).attr('value');
											if(object.find('ul:first li[value=' + value + ']').size() > 0) element.addClass('selected');
											
											// Events
											element.click(function(event) {
												var item = jQuery(event.currentTarget);
												
												// Deselect
												if(item.hasClass('selected')) {
													destruct(item);
												}
												
												// Select
												else {
													construct(item);
												}
												
											});
											
										});
										
										// Slide queue
										list.slideDown(settings.queue_speed);
									}
								}
							}, settings.queue_ajax));
						}
						
						// Empty queue information
						if(queue.find('li').size() == 0) {
							list.append(jQuery('<li class="message"><span>' + Symphony.Language.get('There are currently no items available. Perhaps you want create one first?') + ' <a class="create">' + Symphony.Language.get('Click here to create a new item.') + '</a></span></li>')).slideDown(settings.queue_speed);
						}
						
					}

					// Slide queue
					else {
						queue.find('ul').slideDown(settings.queue_speed);
					}					

					// Automatically hide queue later
					jQuery('body').bind('click', function(event) {
						var target = jQuery(event.target);
						if(target.parents().filter('div.queue').size() == 0) {
							object.stage.hideQueue();
							jQuery('body').unbind('click');
						}
					});
				},
				
				hideQueue: function() {
					var queue = object.find('div.queue');
					if(queue.find('ul').size() > 0) {
						queue.find('ul').slideUp(settings.queue_speed);
					}
				},
				
				search: function(event) {

					var search = jQuery.trim(jQuery(event.target).val()).toLowerCase().split(' ');

					// Build search index
					if(!this.search_index) {
						this.search_index = object.find('div.queue li').map(function() {
							return this.innerText.toLowerCase();
						});
					}
					
					// Searching
					var items = object.find('div.queue ul li');
					if(search.length > 0 && search[0] != '') {					
						this.search_index.each(function(index, content) {
						
							var found = true;
							var item = items.filter(':nth(' + index + ')');

							// Items have to match all search strings
							jQuery.each(search, function(index, string) {
								if(content.search(string) == -1) found = false;
							});
						
							// Show matching items
							if(found) {
								item.slideDown(settings.speed);
							}
	
							// Hide other items
							else {
								item.slideUp(settings.speed);
							}

						});
					}
					
					// Not searching 
					else {
						items.slideDown(settings.speed);
					}
					
				}
				
			};
			
			if(settings.delay_initialize !== true) {
				object.stage.initialize();
			}
			
			return object;
		});
		
		return objects;
	};
 