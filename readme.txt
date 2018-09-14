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

This plugin basically allows you to have different versions of your site, each
one with specific content according to the visitor location.

= How this plugin changes my site? =

If your site is *mysite.com*, and you have created the locations *Japan* and
*Italy*, when the visitor goes to *mysite.com/japan* will only see posts
which belong to the Japan location, and when they go to *mysite.com/italy*
they will see posts which belong to the Italy location.

If they go to *mysite.com* they will only see posts which are not assigned
to a particular location, unless you define a default location. In that case,
   they will see posts which belong to such location. Note that assigning a
   post to the default location will make it appear in all locations.

= How do I assign posts to each location? =

Basically you create location terms, which are like any other WordPress
taxonomy (categories or tags), and then, when creating a post you assign that
location to that post.

= How do I create new locations? =

The location manager submenu is located inside the *Posts Menu*.

= What is the *Location Slug* setting for? =

Each market has its own *home* page, which is something like
*mysite.com/such-location*. But, in case you have defined a default location,
	in that page you will see posts from the specified location mixed with
	posts from the default location. In case you want to see only a list of
	posts from the specified location, there is another page which you may see
	by going to *mysite.com/location-slug/such-location*. That string,
	*location-slug* can be changed by modifying the setting *Location Slug*.

= How does the visitor redirection works? =

If you enable the visitor redirection (in the *Settings -> Geolocated Content*
		section), the system will attempt to determine which location the
visitor is nearer, and it will redirect them there. In order this feature to
work you must specify each location latitude and longitude coordinates (that
		is done when creating a new or editing a new location).

This redirection only works if the visitor is not navigating any location
(the visitor is browsing *mysite.com*, not *mysite.com/location*).

= How the system determines the visitor location? =

It uses the WordPress public geo API. You can check it out here:
[https://public-api.wordpress.com/geo/](https://public-api.wordpress.com/geo/).

= What is the tolerance radius? =

The system will always try to redirect the visitor to its nearest location
(only if the visitor redirection is enabled). If you specify a tolerance
radius, it will only redirect the visitor to its nearest location if they are
in a distance no longer that such radius of kilometers from the location.

= What does the geolocated settings do? =

If you enable this option, it allows to specify a different setting value for
a specific location. For example, if some plugin allows you to determine the
color of your site background, you can change this color for different
locations. Note that this feature may not work in all plugins, it will work in
plugins which use the **WordPress Settings API** for registering their
settings.

= Does this plugin makes my site slower? =

It should not, or at least not significantly. If well the geolocation process
requires communicating with an external API, it only calls it once a day per
visitor (the location of the visitor is stored in a cookie). Also, this plugin
is compatible with most full page cache plugins, like **Batcache** or **W3
Total Cache**, and does not any extra query in front-end requests.

= Why I cannot assign locations to pages? =

Because pages are not supposed to be listed in a archive page. The idea of
this plugin is to filter lists or archives according to the visitor location,
	 not to restrict content.

=== Changelog ===

= 0.1.1 =
* Added extended settings feature.
* Added FAQ section.
* Completed spanish translation.
