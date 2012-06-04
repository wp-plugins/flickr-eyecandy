<?php
/*
Plugin Name: flickr_eyecandy
Plugin URI: http://cheeso.members.winisp.net/wp/plugins/flickr-eyecandy/
Description: A Flickr random photo widget for your blog. You specify the photo tag id and the API Key, it selects one photo from Flickr with that tag, and displays it on your sidebar. Eye candy!
Version: 2012.06.03
Author: Dino Chiesa
Author URI: http://dinochiesa.net
Donate URI: http://cheeso.members.winisp.net/FlickrWidgetDonate.aspx
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
*/

if ( !function_exists('wpcom_time_since') ) {
    /* function taken from WordPress.com */
    function wpcom_time_since( $original, $do_more = 0 ) {
        // array of time period chunks
        $chunks = array(
            array(60 * 60 * 24 * 365 , 'year'),
            array(60 * 60 * 24 * 30 , 'month'),
            array(60 * 60 * 24 * 7, 'week'),
            array(60 * 60 * 24 , 'day'),
            array(60 * 60 , 'hour'),
            array(60 , 'minute'),
            );

        $today = time();
        $since = $today - $original;

        for ($i = 0, $j = count($chunks); $i < $j; $i++) {
            $seconds = $chunks[$i][0];
            $name = $chunks[$i][1];

            if (($count = floor($since / $seconds)) != 0)
                break;
        }

        $result = ($count == 1) ? '1 '.$name : "$count {$name}s";

        if ($i + 1 < $j) {
            $seconds2 = $chunks[$i + 1][0];
            $name2 = $chunks[$i + 1][1];

            // add second item if it's greater than 0
            if ( (($count2 = floor(($since - ($seconds * $count))/$seconds2)) != 0) &&
                 $do_more )
                $result .= ($count2 == 1) ? ', 1 '.$name2 : ", $count2 {$name2}s";
        }
        return $result;
    }
}



class FlickrGet {

    static $baseFlickrAddr = "http://api.flickr.com/services/rest/";
    // get API Key at http://www.flickr.com/services/apps/create/noncommercial/

    static function getCacheDir() {
        $temp = WP_CONTENT_DIR . '/flickr_eyecandy-cache/';

        if ( file_exists( $temp )) {
            if (@is_dir( $temp )) {
                return $temp;
            }
            else {
                return null;
            }
        }

        if ( @mkdir( $temp ) ) {
            $stat = @stat( dirname( $temp ) );
            $dir_perms = $stat['mode'] & 0007777;
            @chmod( $temp, $dir_perms );
            return $temp;
        }

        return null;
    }



    static function httpget($query) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, self::$baseFlickrAddr . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    public static function search($term, $key, $cache_life) {
        if (empty($term)) {
            echo "no search term.<br/>\n";
            return null;
        }

        $f = preg_replace("/,/", "%2C", $term);
        //$f = preg_replace("/-/", "%2D", $f);
        $cacheFile = self::getCacheDir() . 'dpc-' . $f . ".xml";

        if (file_exists($cacheFile)) {
            if (filemtime($cacheFile) > (time() - 60 * $cache_life)) {
                // The cache file is fresh.
                $fresh = file_get_contents($cacheFile);
                $photoList = simplexml_load_string($fresh);
                return $photoList->photos;
            }
            else {
                unlink($cacheFile);
            }
        }


        // use tag_mode=bool and exclude photos with some tags
        $query = 'api_key=' . $key .
            '&method=flickr.photos.search&format=rest&tag_mode=bool&tags=-fuck,-bitches,' . $term;
        $xmlString = self::httpget($query);

        file_put_contents($cacheFile, $xmlString, LOCK_EX);

        $photoList = simplexml_load_string($xmlString);
        return $photoList->photos;
    }
}



class FlickrEyeCandyWidget extends WP_Widget {
    /** constructor */
    function FlickrEyeCandyWidget() {
        $opts = array('classname' => 'widg-flickr-eye-candy',
                      'description' => __( 'Display random photos from Flickr') );
        parent::WP_Widget(false, $name = 'FlickrEyeCandy', $opts);

        // If in the future, I provide some possibilities for styling,
        // I may need to include the CSS and JS files here.
        //
        //$css = '/wp-content/plugins/whatever/css/something.css';
        //wp_enqueue_style('flickr_eyecandy', $css);
        //$js = '/wp-content/plugins/whatever/js/something.js';
        //wp_enqueue_script('flickr_eyecandy', $js);
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;
        if ( $title ) {
            echo $before_title . $title . $after_title;
        }
        $this->pickaFlickrPhoto($instance['tag'], $instance['api_key'],
                                $instance['cache_life']);
        echo $after_widget;
    }

    function pickaFlickrPhoto($tag_text, $api_key, $cache_life) {
        $tags = explode('|', $tag_text); // choices separated by |
        // select one tag or set of tags at random.
        $tag = $tags[rand(0, count($tags)-1)];
        // get a bunch of photos
        $photos = FlickrGet::search($tag, $api_key, $cache_life);
        if (isset($photos)) {
            // select one random photo of those returned
            $n = rand(0, count($photos->photo));
            $p = $photos->photo[$n];
            $attrs = $p->attributes();
            printf("<div><a target='_blank' href='http://www.flickr.com/photos/%s/%s' " .
                   " title='%s - click to view on Flickr'>" .
                   "<img src='http://farm%d.staticflickr.com/%d/%s_%s_n.jpg'/></a></div>",
                   $attrs->owner, $attrs->id, $attrs->title,
                   $attrs->farm, $attrs->server, $attrs->id, $attrs->secret);
        }
        else {
            echo "--no photo--<br/>";
        }
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['tag'] = strip_tags($new_instance['tag']);
        $instance['api_key'] = strip_tags($new_instance['api_key']);
        $instance['cache_life'] = intval($new_instance['cache_life'],10);
        return $instance;
    }

    function widget_FormTextBox($fieldId, $label, $hint, $value) {
        echo "  <p>\n" .
            "      <label for='" . $this->get_field_id($fieldId) . "'>" . _e($label) .
            "</label>\n" .
            "      <input class='widefat' id='" . $this->get_field_id($fieldId) .
            "' name='" . $this->get_field_name($fieldId) .
            "' title='" .  _e($hint) .
            "' type='text' value='" .  $value ."'/>\n  </p>\n" ;
    }

    function form($instance) {
        $title = 'Flickr Eye Candy';
        $tag = '';
        $api_key = '';
        $cache_life = 10;

        if ($instance) {
            $title = esc_attr($instance['title']);
            $tag = esc_attr($instance['tag']);
            $api_key = esc_attr($instance['api_key']);
            $cache_life = intval($instance['cache_life'],10);
        }
        else {
            $defaults = array('title' => $title,
                              'api_key' => '',
                              'cache_life' => 10,
                              'tag' => 'leaf');
            $instance = wp_parse_args( (array) $instance, $defaults );
        }

        $this->widget_FormTextBox('title', 'Title:', 'The title to display for the widget', $title);
        $this->widget_FormTextBox('tag', 'photo tag:', 'display only photos from Flickr with this tag', $tag);
        $this->widget_FormTextBox('api_key', 'Yahoo API Key:', 'Get this from http://www.flickr.com/services/apps/create/apply/', $api_key);
        $this->widget_FormTextBox('cache_life', 'Cache Lifetime:', 'The plugin will cache results for this many minutes.', $cache_life);
    }
}


add_action( 'widgets_init', 'flickr_eyecandy_widget_init' );
function flickr_eyecandy_widget_init() {
    register_widget('FlickrEyeCandyWidget');
}
