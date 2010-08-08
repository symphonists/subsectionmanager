# Subsection Manager

Subsection management for Symphony.  

- Version: 1.0 Release Candidate 2
- Date: **unreleased**
- Requirements: Symphony CMS 2.1 or newer, <http://github.com/symphonycms/symphony-2/>
- Optional Requirement: JIT Image Manipulation (for image previews), <http://github.com/symphonycms/jit_image_manipulation/>
- Author: Nils Hörrmann, post@nilshoerrmann.de
- Constributors: [A list of contributors can be found in the commit history](http://github.com/nilshoerrmann/subsectionmanager/commits/development/)
- GitHub Repository: <http://github.com/nilshoerrmann/subsectionmanager/>
- Available languages: English (default), German

## Synopsis

Symphony offers an easy way to [create sections](http://symphony-cms.com/learn/concepts/view/sections/) and model the [fields](http://symphony-cms.com/learn/concepts/view/fields/) the way you like. Nevertheless, from time to time you need to connect the content of two sections, if you have an articles section you like to link images to, or you are building an books section you like to link authors to. With a default Symphony install you can use select boxes or selectbox links to create these connections but you will not be able to see and manage all your content at once. The Subsection Manager tries to solve this problem by providing and interface to show and edit the content of another section inline. Hence a subsection is a normal Symphony setion that ist created using the [section interface](http://symphony-cms.com/learn/concepts/view/sections/). Adding a Subsection Manager field to your parent section will integrate a specified section as subsection. The entries of a subsection can be managed using the inline interface and the Symphony section list. If you hide a section from the menu only inline editing will be available.

Subsection Manager is the successor of [Mediathek](http://github.com/nilshoerrmann/mediathek/) and requires [Symphony 2.1 or newer](http://github.com/symphonycms/symphony-2/). Subsection Manager and Mediathek should not be used simultaneously. This extension bundles an upgrade script that automatically replaces all Mediathek fields with Subsection Manager instances (see below).

## Installation

The Subsection Manager contains three components:

- Subsection Manager which handle the section interactions,
- [Stage](http://github.com/nilshoerrmann/stage/) which offers the interface for the inline section display and
- [Draggable](http://github.com/nilshoerrmann/draggable/) which enables drag and drop features.

If you are working with Git, please clone the `development` branch which contains these components as submodules. Please don't forget to pull the submodules as well. If you are not using Git and like to install this extension using FTP please just download a copy of the `release` branch which bundles all need submodules. More information about [installing and updating extensions](http://symphony-cms.com/learn/tasks/view/install-an-extension/) can be found in the Symphony documentation at <http://symphony-cms.com/learn/>. 

All interface related components of the Subsection Manager are JavaScript based. If you are upgrading from an earlier version, please make sure that you clear your browser cache to avoid interface issues. If any other extension or the Symphony core throws a JavaScript error, the Subsection Manager will stop working. 

## Upgrading Mediathek Fields

If you have Mediathek and Subsection Manager installed simultaneously the interface of both extensions will be broken. While Mediathek is built to work with Symphony 2.0, Subsection Manager relies on the improved features of Symphony 2.1. If you have both extension installed, the system will show a message in extension overview which will help you upgrading all your Mediathek fields to the Subsection Manager.

Please be aware that this upgrade process will alter your database and will uninstall your Mediathek extension. Make sure that you have an up-to-date backup of your site, containing all files and folders, and a copy of your database before you proceed with the upgrade. The upgrade cannot be undone.

## Change Log

**Version 1.0** 

- Initial release.

## Acknowledgement

The drawer colors and layout were inspired by Scott Hughes' [Calendar Mock-up](http://symphony-cms.com/community/discussions/103/) and Rowan Lewis' [Calendar Overlay](http://github.com/rowan-lewis/calendaroverlay/).

A lot of people have been testing this extension and have been proving feedback. A big thank you to all of you, in alphabetic order: Alistair Kearney, Allen Chang, Andrea Buran, Andrew Minton, Andrew Shooner, Brendan Abbott, Brian Zerangue, Brien Wright, Craig Zheng, Dale Tan, David Hund, Doug Stewart, Fazal Khan, Frode Danielsen, Giulio Trico, Grzegorz Michlicki, Jiri Vanmeerbeeck, Johanna Hörrmann, John Porter, Jonas Coch, Mark a.k.a. Ecko, Mark a.k.a. m165, Mark Lewis, Max Wheeler, Michael Eichelsdörfer, Nick Dunn, Nils Werner, Stephen Bau, Tony Arnold and all those that love and use Symphony and try to make it better every day.