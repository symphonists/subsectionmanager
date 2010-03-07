/*
 * SUBSECTION for Symphony
 *
 * @author: Nils Hörrmann, post@nilshoerrmann.de
 * @source: http://github.com/nilshoerrmann/mediathek
 */
	

/*-----------------------------------------------------------------------------
	Layout Subsection
-----------------------------------------------------------------------------*/

	jQuery(document).ready(function() {
		
		// Is iframe?
		if(self != top) {
		
			// Get iframe
			var iframe = jQuery(window.frameElement);
			
			// Remove unneeded elements
			jQuery('body').addClass('subsection');
			jQuery('h1').remove();
			jQuery('h2').remove();
			jQuery('#nav').remove();
			jQuery('#usr').remove();
			
			// Resize frame to full height
			var body = jQuery('body');
			body.click(function(event) {
				iframe.height(jQuery('body')[0].scrollHeight);
			});
			body.click();	
			
		}
		
	});
