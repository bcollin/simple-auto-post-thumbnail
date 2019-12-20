= Simple Auto Post Thumbnail =

This Wordpress plugin turns the first image in the content of a post into that post's thumbnail.

== Description ==

This is a fork of version 3.4.1 of the Auto Post Thumbnail plugin.

Upon publishing a post, this plugin scans that post's content for an image. The first image it finds, it tries to turn into the post thumbnail (also called 'featured image').

It can do this both for images hosted on the same site as for images hosted elsewhere. 

=== What are thumbnails? ===

Wordpress offers webmasters the option to designate one image the Featured Image or Post Thumbnail. 

This image can then be used to represent the post outside the post's page on the website, for example in lists, feeds, Open Graph displays, other types of social media cards, and so on. (You will often have to install other plugins or adapt your themes to make this work.)

See https://en.support.wordpress.com/featured-images/ for an extended explanation.

Although it is probably best if the webmaster pre-determines the use of featured images they have and then manually add those featured images to each post based on those needs, sometimes you just want a default featured image just to have /something/. Simple Auto Post Thumbnail helps with this.

=== Missing functionality ===

The first thing I did when I forked Auto Post Thumbnail was rip out the bit that generates thumbnails for the entire site. The functionality that is left only generates a thumbnail when you manually save (Update or Publish) a post that did yet not have a thumbnail.

If you like SAPT and need that functionality, either use Auto Post Thumbnail, which is still being developed (see below), or talk to me.

=== Rationale ===

The original Auto Post Thumbnail plugin by Sanisoft was fairly useful and fairly popular, but also fairly buggy. At some point it simply stopped working. This was possibly due to changes in either Wordpress or PHP, the language that Wordpress is programmed in. 

Sometime during 2018 or 2019, development of the plugin was taken over by Creativemotion. I am not sure exactly what they changed, but on first glance they seem to have: 

* Made the plugin 26 times (!) bulkier.
* Put half of the free functionality behind a paywall.
* Made the code much less readable and much more bloated by using OOP.
* Never fixed the bug that stopped the plugin from generating thumbnails.

They also added a ton of functionality that I have absolutely no need for.

The result was that forking and fixing became both an option and a necessity for me.

The original Auto Post Thumbnail can be found at https://wordpress.org/plugins/auto-post-thumbnail/ .

## Changelog 

### 0.1 

* First release.

## Authors

* Aditya Mooley (pre-fork).
* Branko Collin.

## License

This project is licensed under GPL2, see https://www.gnu.org/licenses/gpl-2.0.html for more information.


