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
		
		// Is this an iframe?
		if(self != top) {
				
			// Remove unneeded elements
			jQuery('body').addClass('subsection');
			jQuery('h1').remove();
			jQuery('h2').remove();
			jQuery('#nav').remove();
			jQuery('#usr').remove();
			jQuery('#notice:not(.error):not(.success)').remove();
			jQuery('#notice a').remove();
			
			// Focus first input field
			jQuery('input:first').focus();
						
		}
		
	});
