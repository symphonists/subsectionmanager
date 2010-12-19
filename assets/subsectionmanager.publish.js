
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
			'Are you sure you want to delete this item? It will be remove from all entries. This step cannot be undone.': false,
			'There are currently no items available. Perhaps you want create one first?': false
		}); 

		// Initialize Subsection Manager
		$('div.field-subsectionmanager').each(function() {
			var manager = $(this),
				stage = manager.find('div.stage'),
				selection = stage.find('ul.selection'),
				queue = stage.find('div.queue'),
				drawer = stage.data('templates.stage').templates.filter('.drawer').removeClass('template'),
				context = manager.find('input[name*=subsection_id]'),
				subsection = context.val(),
				subsectionmanager_id = context.attr('name').match(/\[subsection_id\]\[(.*)\]/)[1],
				subsection_link = drawer.find('iframe').attr('target');
					
		/*-----------------------------------------------------------------------*/

			// Constructing
			stage.bind('constructstop', function(event, item) {
				
				// New item
				if(item.is('.new')) {
					create(item);
				}
			});
			
			// Destructing
			stage.bind('destructstart', function(event, item) {
			
				// Hide drawer for new or single item
				if(item.is('.new') || stage.is('.single')) {
					item.next('li.drawer').slideUp('fast', function() {
						$(this).remove();
					})
				}
			});
			
			// Editing
			selection.delegate('li:not(.new, .drawer, .empty)', 'click', function(event) {
				var item = $(this),
					editor = item.next('.drawer');
				
				// Don't open editor for item that will be removed
				if(event.srcElement.className == 'destructor') return;
				
				// Open editor
				if(editor.size() == 0) {
					edit(item);
				}
				
				// Close editor
				else {
					editor.slideUp('fast', function() {
						$(this).remove();
					});
				}
			});
					
			// Searching
			stage.bind('browsestart', function(event) {
				browse();
			});
					
		/*-----------------------------------------------------------------------*/

			// Load subsection
			var load = function(item, editor, iframe) {
				content = iframe.contents();
				
				// Adjust interface
				content.find('body').addClass('inline subsection');
				content.find('h1, h2, #nav, #usr, #notice:not(.error):not(.success), #notice a').remove();
				content.find('fieldset input:first').focus();
			
				// Set frame and drawer height
				var height = content.find('form').outerHeight();
				iframe.height(height).animate({
					opacity: 1
				}, 'fast');
				editor.animate({
					height: height
				}, 'fast');
				
				// Fetch saving
				content.find('div.actions input').click(function() {
					iframe.animate({
						opacity: 0.01
					}, 'fast');
				})
				
				// Trigger update 
				if(content.find('#notice.success').size() > 0) {
					item.trigger('update');
				}
			};
			
			// Browse queue
			var browse = function() {

				// Append queue if it's not present yet
				if(queue.find('ul').size() == 0) {
					var list = $('<ul class="queue loading"></ul>').hide().appendTo(queue).slideDown('fast');

					// Get queue items
					$.ajax({
						async: false,
						type: 'GET',
						dataType: 'html',
						url: Symphony.Context.get('root') + '/symphony/extension/subsectionmanager/get/',
						data: { 
							id: subsectionmanager_id, 
							section: subsection 
						},
						success: function(result) {

							// Empty queue
							if(!result) {
								$('<li class="message"><span>' + Symphony.Language.get('There are currently no items available. Perhaps you want create one first?') + '</li>').appendTo(list);
							}
							
							// Append queue items
							else {
								$(result).hide().appendTo(list);
								
								// Highlight selected items
								selection.find('li').each(function(index, item) {
									list.find('li[data-value="' + $(item).attr('data-value') + '"]').addClass('selected');
								});
							}

							// Slide queue
							list.find('li').slideDown('fast', function() {
								$(this).parent('ul').removeClass('loading');
							});
						}
					});
				}
			};
			
			// Edit item
			var create = function(item) {
				stage.trigger('createstart', [item]);

				var editor = drawer.clone().hide().addClass('new');
				
				// Prepare iframe
				editor.find('iframe').css('opacity', '0.01').attr('src', subsection_link + '/new/').load(function() {
					iframe = $(this);
					load(item, editor, iframe);
				});
				
				// Show subsection editor
				editor.insertAfter(item).slideDown('fast');			

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
				editor.insertAfter(item).slideDown('fast');			
		
				stage.trigger('editstop', [item]);
			};

			//			
			
		});

	});
	
})(jQuery.noConflict());
