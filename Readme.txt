=== FlickrEyeCandy ===
Contributors: dpchiesa
Donate link: http://cheeso.members.winisp.net/FlickrWidgetDonate.aspx
Tags: Flickr, photo, widget, Yahoo
Requires at least: 3.2
Tested up to: 3.2
Stable tag: 2012.05.19
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt

== Description ==

flickr_eyecandy is a very simple Wordpress Plugin.

It provides a Wordpress Widget that displays a random photo from Flickr
in the sidebar. The plugin uses the Flickr API, and authenticates via
an API key that YOU (the installer) acquire via the Yahoo API Console.

You specify a tag or set of tags to filter the photos.
For example, if you specify "usa,flag", you will get only photos tagged
with "usa" and "flag".


== Installation ==

1. Download flickr-eyecandy-wp-plugin.zip and unzip into the
  `/wp-content/plugins/` directory

2. From the Wordpress admin backend, Activate the plugin through the
   'Plugins' menu

3. From the Wordpress admin backend, in the Widgets menu, drag-and-drop
   the widget to your sidebar.  You can place it in any position you
   like.

4. Specify the settings for the widget:
   Title, API Key, and the tag(s).  For the tags, you can specify :

    - a single word, like "fish".  This will retrieve a random
      image tagged with the word "fish".

    - a comma-separated list. This retrieves a photo tagged
      with all of the terms in the list.

    - a pipe-separated list of the above. For example, if you specify
      "fish|leaf|blue,water", then you will get a photo that is tagged
      with either "fish" or "leaf" or "blue and water".


That's it !


== Frequently Asked Questions ==

= Where do I get an API Key? =

To get an API Key, you need to visit
http://www.flickr.com/services/apps/create/apply/

Walk through the steps.
Copy and paste the API Key to the appropriate place in the Widget
configuration menu.  You don't need the secret.

= Why do I need an API Key? =

You need an API key from Yahoo so that the requests that  your wordpress
page sends to Yahoo, will be tracked and allowed. Every time the
page loads, it sends out a request to Yahoo, and gets a list of
photos.  Yahoo wants to know who's asking for this information, and
the API key lets them track that.

= Will I be charged by Yahoo for the requests? =

No.  The API key is free to get; I don't speak for Yahoo, but it seems
to me they use the key only for tracking purposes.

Yahoo may throttle the level of requests if you use this plugin on a
heavily loaded site. In that case you may need to use OAuth2.0, which I
have not yet built into the plugin.  But I could be convinced, for the
right price. ;)

= Can I set the visual style of the widget from the admin backend? =

No, I haven't built that capability into this simple plugin, just yet.
Let me know if you have strong requirements in this area.


== Screenshots ==

1. This shows the rendering of the Widget in the sidebar of a WP blog.
2. This shows how to Activate gplus in the Plugins menu in the WP Admin backend
3. Configuring the settings for the gplus widget in the WP Admin backend.


== Changelog ==

= 2012.5.18 =
* initial release

== Dependencies ==

This plugin depends on and uses the published Flickr REST API.
It also relies on and uses the Curl library within PHP.


== Thanks ==

Thanks for your interest!

-Dino Chiesa

