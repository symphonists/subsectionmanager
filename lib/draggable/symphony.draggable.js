/*
 * DRAGGABLE PLUGIN
 * for Symphony
 *
 * This plugin is based on the Symphony orderable plugin written by Rowan Lewis
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/draggable
 */


/*-----------------------------------------------------------------------------
	Draggable plugin
-----------------------------------------------------------------------------*/
	
	jQuery.fn.symphonyDraggable = function(custom_settings) {
		var objects = this;
		var settings = {
			items:				'li',			// What children do we use as items? 
			handles:			'*',			// What children do we use as handles? If set to false, items will be used as handles.
			droppables:			'textarea',		// What elements do we use for dropping items?
			droppable:			true,			// Can items be dropped?
			sortable:			true,			// Can items be sorted?
			click:				jQuery.noop(),	// Function to be executed on click
			radius:				3,				// Click radius.
			distance:			10,				// Distance for dragging item out of the list.
			delay_initialize:	false
		};
		
		jQuery.extend(settings, custom_settings);
		
	/*-------------------------------------------------------------------------
		Draggable
	-------------------------------------------------------------------------*/
		
		objects = objects.map(function() {
			var object = this;
			var state = null;
			
			var start = function(event, item) {
				
				// Setup state
				state = {
					item: item,
					min: null,
					max: null,
					delta: 0,
					coordinate: event.pageY
				};

				// Add events
				jQuery(document).mousemove(change);
				jQuery(document).mouseup(stop);
				
				// Start dragging
				object.addClass('dragging');
				state.item.addClass('dragging');
				object.trigger('dragstart', [state.item]);
				
				return false;
			};
			
			var change = function(event) {
				var item = state.item;
				var x = event.pageX;
				var y = event.pageY;
				var target, next, top = y;
				var a = item.height();
				var b = item.offset().top;
				var prev = item.prev();
				var parent = item.parents('ul.selection');
				var helper = jQuery('div.draghelper');
				var container = {
					top: parent.offset().top - settings.distance,
					left: parent.offset().left - settings.distance,
					bottom: parent.offset().top + parent.height() + settings.distance,
					right: parent.offset().left + parent.width() + settings.distance
				}
				
				// Moving outside the list = dragging
				if(settings.droppable && (x < container.left || x > container.right || y < container.top || y > container.bottom)) {

					// Prepare dropping
					jQuery(settings.droppables).bind('mouseover', object.draggable.dropover);
					jQuery('.dropper').bind('mouseout', object.draggable.dropout);
				
					// Add drag helper
					if(helper.size() == 0) {
						var classes = item.attr('class');
						var helper = jQuery('<div class="draghelper" />').addClass(classes).hide().appendTo(jQuery('body'));
					}
					if(helper.is(':hidden')) {
						helper.html(item[0].innerHTML);
						helper.fadeIn('slow');
					}
					
					// Set helper position
					helper.css({
						top: y - (helper.height() / 2),
						left: x + 10 
					});
				
				}
				
				// Moving inside the list = sorting
				else if(settings.sortable) {

					// Remove drag helper if it exists
					helper.fadeOut('fast');
				
					// State
					state.min = Math.min(b, a + (prev.size() > 0 ? prev.offset().top : -Infinity));
					state.max = Math.max(a + b, b + (item.next().height() ||  Infinity));
					
					// Move up
					if(top < state.min) {
						target = item.prev(settings.items);
						
						while(true) {
							state.delta--;
							next = target.prev(settings.items);
							
							if(next.length === 0 || top >= (state.min -= next.height())) {
								item.insertBefore(target); break;
							}
							
							target = next;
						}
					}
					
					// Move down
					else if(top > state.max) {
						target = item.next(settings.items);
						
						while(true) {
							state.delta++;
							next = target.next(settings.items);
							
							if(next.length === 0 || top <= (state.max += next.height())) {
								item.insertAfter(target); break;
							}
							
							target = next;
						}
					}
					
				}
				
				object.trigger('dragchange', [state.item]);
				
				return false;
			};
											
			var stop = function(event) {
			
				var dropper = jQuery('div.dropper').mouseout();

				jQuery(document).unbind('mousemove', change);
				jQuery(document).unbind('mouseup', stop);
				jQuery(settings.droppables).unbind('mouseover', object.draggable.dropover);
					
				if(state != null) {
				
					var helper = jQuery('div.draghelper');

					// Trigger click event
					if(state.coordinate - settings.radius < event.pageY && event.pageY < state.coordinate + settings.radius && helper.size() == 0) {
						settings.click(state.item);
					}
					
					// Prepare dropping
					else {
						var target = jQuery.data(document, 'target');
						if(settings.droppable && target !== undefined) {
							target.trigger('drop', [helper]);
							dropper.remove();
						}
					}
					
					// Remove helper
					helper.fadeOut('fast', function() {
						jQuery(this).remove();
						object.trigger('dragdrop');
					});

					// Stop dragging
					object.removeClass('dragging');
					state.item.removeClass('dragging');
					object.trigger('dragstop', [state.item]);
					state = null;
				}
				
				return false;
			};
			
		/*-------------------------------------------------------------------*/
			
			if(object instanceof jQuery === false) {
				object = jQuery(object);
			}
			
			object.draggable = {
				
				initialize: function() {
				
					// Prepare dragging
					object.addClass('draggable');
					object.bind('mousedown', function(event) {
						event.preventDefault();
						event.stopPropagation();		
						var current = jQuery(event.target);
						if(current.hasClass('destructor')) return;
						
						// Get handle
						if(settings.handles) {
							if(current.is(settings.handles)) start(event, current);
						}
						else {
							if(!current.is(settings.items)) current = current.parents(settings.items);
							start(event, current);
						}
					});
				
				},
				
				dropover: function(event) {
					var target = jQuery(event.target);
					var dropper = jQuery('div.dropper').hide();
					
					// Add drop area
					if(dropper.size() == 0) {
						var dropper = jQuery('<div class="dropper" />').hide().appendTo(jQuery('body'));
					}
					dropper.css({
						width: target.outerWidth(),
						height: target.outerHeight(),
						top: target.offset().top,
						left: target.offset().left
					}).fadeIn(250);
										
					// Store related target
					jQuery.data(document, 'target', target);
				},
				
				dropout: function() {
					jQuery('div.dropper').remove();
				}
											
			};
			
			if(settings.delay_initialize !== true) {
				object.draggable.initialize();
			}
			
			return object;
		});
		
		return objects;
	};
	
/*---------------------------------------------------------------------------*/
