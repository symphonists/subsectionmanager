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
			autodiscover: false,
			delay_initialize: false
		};
		jQuery.extend(settings, custom_settings);


	/*-------------------------------------------------------------------------
		Subsection
	-------------------------------------------------------------------------*/

		objects = objects.map(function() {
		
			// Get elements
			var object = this;

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
