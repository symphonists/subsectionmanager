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
		'There are no selected items': false
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
			template:			'li.drawer.template',
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
			var edit = function(event) {
		
				object.trigger('editStart');
							
				var item = jQuery(event.target).parent('li');
				var template = object.find(settings.template).clone().removeClass('template');
				var iframe = template.find('iframe').css('opacity', '0.01');
				var source = iframe.attr('src');
				var id = item.attr('value');
				
				if(!item.next('li').hasClass('drawer')) {
						
					// Setup source
					source = source.replace('{$action}', 'edit').replace('{$id}', id);
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
							update(item.attr('value'), item);
						} 
						
					});
				
					// Automatically hide drawer later
					jQuery('body').bind('click', function(event) {
						if(jQuery(event.target).parents().filter('div.stage li.active, div.stage li.drawer').size() == 0) {
							object.find('div.stage li.active').removeClass('active');
							object.find('div.stage li.drawer:not(.template)').slideUp('normal', function(element) {
								jQuery(this).remove();
							});
							jQuery('body').unbind('click');
						}
					});

				}
		
				object.trigger('editEnd');
				
			};
			
			// Update item
			var update = function(id, item) {
			
				object.trigger('updateStart');
				
				var meta = object.find('input[name*=subsection_id]');
				var id = meta.attr('name').match(/\[subsection_id\]\[(.*)\]/)[1];
				var section = meta.val();

				// Load item data
				jQuery.ajax({
					type: 'GET',
					url: Symphony.WEBSITE + '/symphony/extension/subsectionmanager',
					data: { 
						id: id, 
						section: section,
						entry: id
					},
					dataType: 'html',
					success: function(result) {
					
						// Remove old data and replace it
						item.find('*:not(.destructor)').fadeOut('fast', function() {
							jQuery(this).remove();
							item.prepend(jQuery(result).contents()).fadeIn('fast');
						});
						
					}
				});

				object.trigger('updateEnd');
						
			};
			
			// Delete item
			var remove = function(id, item) {

				object.trigger('removeStart');
				
				
				
				object.trigger('removeEnd');
			
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
				
					// Initialize stage for subsections
					jQuery(document).ready(function() {
						object.find('div.stage').symphonyStage({
							source: object.find('select'),
							orderable: false,
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
					object.subsection.events();
					
					// Autoattach events for new items
					object.find('div.stage').bind('constructEnd', object.subsection.events);		

				},
				
				events: function() {

					// Attach edit event
					object.find(settings.items).find('span').click(edit);
				
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
