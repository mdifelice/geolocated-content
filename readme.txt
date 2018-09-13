=== Geolocated Content ===
Contributors: mdifelice
Stable tag: 0.1.1
Tags: geolocation
Tested up to: 4.9.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allows to deliver different content to visitors from different locations.

=== Frequently Asked Questions ===

= What does this plugin do? =

This plugin basically allows you to have different versions of your website, each one with specific content according to the visitor location.

= How do I assign posts to each location? =

Basically you create location terms, which are like any other WordPress taxonomy (categories or tags), and then, when creating a post you assign that location to that post.

= How do I create new locations? =

The location manager submenu is located inside the Posts Menu.

= How does the visitor redirection works? =

If you enable the visitor redirection (in the Geolocated Content Settings section), the system will attempt to determine which location the visitor is nearer, and it will redirect them there. In order this feature to work you must specify each location latitude and longitude coordinates (that is done when creating a new or editing a new location).

= How the system determines the visitor location? =

It uses the WordPress public geo API. You can check it out here: https://public-api.wordpress.com/geo/

= What is the tolerance radius? =

The system will always try to redirect the visitor to its nearest location (only if the visitor redirection is enabled). If you specify a tolerance radius, it will only redirect the visitor to its nearest location if they are in a distance no longer that such radius of kilometers from the location.

= What does the geolocated settings do? =

If you enable this option, it allows to specify a different setting value for a specific location. For example, if some plugin allows you to determine the color of your website background, you can change this color for different locations. Note that this feature may not work in all plugins, it will work in plugins which use the WordPress Settings API for registering their settings.

= Does this plugin makes my site slower? =

It should not, or at least not significantly. If well the geolocation process requires communicating with an external API, it only calls it once a day per visitor (the location of the visitor is stored in a cookie). Also, this plugin is compatible with most full page cache plugins, like Batcache or W3 Total Cache, and does not any extra query in front-end requests.

=== Changelog ===

= 0.1.1 =
* Added extended settings feature.
* Added FAQ section.
* Completed spanish translation.
