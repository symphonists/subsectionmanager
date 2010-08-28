# Subsection Manager

Subsection management for Symphony.  

- Version: 1.0.1
- Date: 28th August 2010
- Requirements: Symphony 2.1 or newer, <http://github.com/symphonycms/symphony-2/>
- Optional Requirement: JIT Image Manipulation (for image previews), <http://github.com/symphonycms/jit_image_manipulation/>
- Author: Nils Hörrmann, post@nilshoerrmann.de
- Constributors: [A list of contributors can be found in the commit history](http://github.com/nilshoerrmann/subsectionmanager/commits/development/)
- GitHub Repository: <http://github.com/nilshoerrmann/subsectionmanager/>
- Available languages: English (default), German, Italian

## Synopsis

Symphony offers an easy way to [create sections](http://symphony-cms.com/learn/concepts/view/sections/) and [model the fields](http://symphony-cms.com/learn/concepts/view/fields/) the way you like. Nevertheless, from time to time you need to connect the content of two sections: you might have an articles section you'd like to link images to, or you are building a books section you'd like to connect with authors. With a default Symphony install, you can use select boxes or selectbox links to create these connections, but you will not be able to see and manage all your content at once. The Subsection Manager tries to solve this problem by providing an inline management of another section's content. By adding the Subsection Manager field to your parent section, you can integrate another section as a subsection. The subsection's entries can be managed  through the inline interface as well as the regular Symphony section entry list. You can opt for inline editing only by simply hiding the specified section from the menu. 

Subsection Manager is the successor of [Mediathek](http://github.com/nilshoerrmann/mediathek/) and requires [Symphony 2.1 or newer](http://github.com/symphonycms/symphony-2/). Subsection Manager and Mediathek should not be used simultaneously. This extension comes with an upgrade script that automatically replaces all Mediathek fields with the Subsection Manager (see below).

## Installation

Subsection Manager contains three components:

- The Subsection Manager itself which handles the section interactions,
- [Stage](http://github.com/nilshoerrmann/stage/) which offers the interface for the inline section management and
- [Draggable](http://github.com/nilshoerrmann/draggable/) which provides drag and drop features.

If you are working with Git, please clone the `development` branch of this extension which contains all components as submodules. Please don't forget to pull the submodules as well. If you are not using Git and want to install this extension using FTP, please just download a copy of the `release` branch which bundles all needed submodules. More information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>. 

All interface related components of the Subsection Manager are JavaScript based. If you are upgrading from an earlier version, please make sure to clear your browser cache to avoid interface issues. If another extension or the Symphony core throws a JavaScript error, the Subsection Manager will stop working. 

## Upgrading Mediathek Fields

If you have Mediathek and Subsection Manager installed simultaneously, the interface of both extensions will be broken. While Mediathek is built to work with Symphony 2.0, Subsection Manager relies on the improved features of Symphony 2.1. If you have both extensions installed, the system will show a message in the extension overview which will help you to upgrade all your Mediathek fields to Subsection Manager.

Be aware that this upgrade process will alter your database and will uninstall your Mediathek extension. Make sure that you have an up-to-date backup of your site, containing all files and folders, and a copy of your database before you proceed with the upgrade. The upgrade cannot be undone.

## Release Notes

**Version 1.0.1**

- Fixed issue with the section editor display.
- Fixed issues with updates from beta versions.

**Version 1.0.0** 

- Initial release.
- The drag and drop feature is considered experimental. You may run into bugs.

## Acknowledgement

The drawer colors and layout were inspired by Scott Hughes' [Calendar Mock-up](http://symphony-cms.com/community/discussions/103/) and Rowan Lewis' [Calendar Overlay](http://github.com/rowan-lewis/calendaroverlay/).

A lot of people have been testing this extension and providing valuable feedback. A big thank you to all of you, in alphabetic order: Alistair Kearney, Allen Chang, Andrea Buran, Andrew Minton, Andrew Shooner, Brendan Abbott, Brian Zerangue, Brien Wright, Craig Zheng, Dale Tan, David Hund, Doug Stewart, Fazal Khan, Frode Danielsen, Giulio Trico, Grzegorz Michlicki, Huib Keemink, Jiri Vanmeerbeeck, Johanna Hörrmann, John Porter, Jonas Coch, Mark a.k.a. Ecko, Mark a.k.a. m165, Mark Lewis, Max Wheeler, Michael Eichelsdörfer, Nick Dunn, Nils Werner, Simone Economo, Stephen Bau, Tony Arnold and all of you who love and use Symphony and try to make it better every day.