# Draggable

A jQuery plugin for Symphony which enables sorting, dragging and dropping of list items.

- Version: 1.0.1
- Date: 22nd March 2010
- Requirements: Symphony CMS 2.0.8 or newer, <http://github.com/symphony/symphony-2>
- Author: Nils Hörrmann, post@nilshoerrmann.de
- Constributors: [A list of contributors can be found in the commit history](http://github.com/nilshoerrmann/subsectionmanager/commits/master)
- GitHub Repository: <http://github.com/nilshoerrmann/draggable>

## Installation

This jQuery plugin for the Symphony backend which can be used by extension developers. Please include a copy inside your extension, e. g. `lib/draggable/symphony.draggable.js`. You can call it using the `addScriptToHead()` function:

	Administration::instance()->Page->addScriptToHead(URL . '/extensions/yourextension/lib/draggable/symphony.draggable.js', 101, false);

The third argument (`false`) makes sure that it is only added to the page head if it has not been added before (by another extension or another instance of your own extension for example).

### Plugin options

	jQuery('ul').symphonyDraggable({
		items:				'li',			// What children do we use as items? 
		handles:			'*',			// What children do we use as handles? If set to false, items will be used as handles.
		droppables:			'textarea',		// What elements do we use for dropping items?
		droppable:			true,			// Can items be dropped?
		sortable:			true,			// Can items be sorted?
		click:				jQuery.noop(),	// Function to be executed on click
		radius:				3,				// Click radius.
		distance:			10,				// Distance for dragging item out of the list.
		delay_initialize:	false
	});


## Change Log

**Version 1.0.1: 22nd March 2010**

- Remove Subsection Manager related code.

**Version 1.0: 22nd March 2010** 

- Initial release.