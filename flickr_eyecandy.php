<?php
/*
Plugin Name: flickr_eyecandy
Plugin URI: http://wordpress.org/plugins/flickr-eyecandy/
Description: A Flickr photo widget for your blog. Specify the photo tag id and the API Key, it randomly selects one photo from Flickr with that tag, and displays it on your sidebar. Eye candy!
Version: 2014.07.03
Author: Dino Chiesa
Author URI: http://www.dinochiesa.net
Donate URI: http://dinochiesa.github.io/FlickrWidgetDonate.html
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.txt
*/

// prevent direct access
if ( !function_exists('flickr_eyecandy_safeRedirect') ) {
    function flickr_eyecandy_safeRedirect($location, $replace = 1, $Int_HRC = NULL) {
        if(!headers_sent()) {
            header('location: ' . urldecode($location), $replace, $Int_HRC);
            exit;
        }
        exit('<meta http-equiv="refresh" content="4; url=' .
             urldecode($location) . '"/>');
        return;
    }
}
if(!defined('WPINC')){
    flickr_eyecandy_safeRedirect("http://" . $_SERVER["HTTP_HOST"]);
}


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

    static $baseFlickrAddr = "https://api.flickr.com/services/rest/";
    // get API Key at http://www.flickr.com/services/apps/create/noncommercial/

    static function getCacheDir() {
        $temp = WP_CONTENT_DIR . '/cache/';

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
        $timeout = 8;
        $pathToCacert = WP_CONTENT_DIR . '/plugins/flickr-eyecandy/cacert.pem';
        curl_setopt($ch, CURLOPT_URL, self::$baseFlickrAddr . '?' . $query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, $pathToCacert);
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
        $cacheFile = self::getCacheDir() . 'flickr-eyecandy-' . $f . ".xml";

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
      $request_cycles = 0;
      $done = false;
      // Sometimes the cache is corrupt, or Flickr returns zero photos?
      // Maybe because of a overconstrained tag selection?  I don't
      // know. Anyway, this has a built-in retry to handle that case.
      while(!$done && $request_cycles < 6) {
        // select one tag or a set of tags at random.
        $tag = $tags[rand(0, count($tags)-1)];
        // get a bunch of photos with that tag / those tags
        $photos = FlickrGet::search($tag, $api_key, $request_cycles);
        if ($photos) {
          $c = count($photos->photo);
          printf("<!-- count of photos: %d -->", $c);
          if ($c>0) {
            $done = false;
            $scan_cycles = 0;
            while(!$done && $scan_cycles < 10) {
              $scan_cycles++;
              try {
                // select one random photo of those returned
                $n = rand(0, $c);
                $p = $photos->photo[$n];
                if ($p) {
                  // sometimes the selected item is not a photo...
                  $attrs = $p->attributes();
                  printf("<div><a target='_blank' href='http://www.flickr.com/photos/%s/%s' " .
                         " title='%s - click to view on Flickr'>" .
                         "<img src='http://farm%d.staticflickr.com/%d/%s_%s_n.jpg'/></a></div>",
                         $attrs->owner, $attrs->id, $attrs->title,
                         $attrs->farm, $attrs->server, $attrs->id, $attrs->secret);
                  $done = true;
                }
              }
              catch (Exception $e) {
                // gulp!
                printf("<!-- Exception: %s -->", $e);
              }
            }
            printf("<!-- required scan cycles: %d -->", $scan_cycles);
          }
        }
        else {
          // to a log file?
          //echo "--no photo available--<br/>";
        }
        $request_cycles++;
        if ($request_cycles > 2) {
          // After 3 tries, disable cache, and try some more.
          $cache_life = 0;
          // Not sure how well this will scale.
        }
      }
      if (!done) {
          echo "--no photo available--<br/>";
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

    function formTextBox($fieldId, $label, $hint, $value) {
        echo "  <p>\n" .
            "      <label for='" . $this->get_field_id($fieldId) . "'>" . _e($label) .
            "</label>\n" .
            "      <input class='widefat' id='" . $this->get_field_id($fieldId) .
            "' name='" . $this->get_field_name($fieldId) .
            "' type='text' value='" .  $value ."'/>\n " .
            "<em>" . __($hint) . "</em>\n" .
            "  </p>\n" ;
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
        $tagHelp = 'Display only photos from Flickr with this tag. ' .
            'You use commas to separate individual tags.  ' .
            'For example, using "red,stripe" here will select a photo tagged ' .
            'with both "red" AND "stripe". You can specify ' .
            'alternation with a vertical bar.  For example, using ' .
            '"blue,ocean|turtle" here will select a photo tagged ' .
            'with "blue" and "ocean", or a photo tagged with "turtle".' ;

        $this->formTextBox('title', 'Title:', 'The title to display for the widget', $title);
        $this->formTextBox('tag', 'Tag(s):', $tagHelp, $tag);

        $this->formTextBox('api_key', 'Yahoo API Key:', '(<a href="http://www.flickr.com/services/apps/create/apply/">Register</a>)', $api_key);
        $this->formTextBox('cache_life', 'Cache Lifetime:', 'The plugin will cache results for this many minutes.', $cache_life);
    }
}


if ( !function_exists('dpc_emit_paypal_donation_button') ) {
    function dpc_emit_paypal_donation_button($widget, $clazzName, $buttonCode) {
        if (!is_object($widget)) {
            echo "Not object<br/>\n";
            return;
        }
        $clz = get_class($widget);
        if ($clz == $clazzName) {
            echo
                "<a target='_blank' " .
                "href='https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=" .
                $buttonCode . "'>" .
                "<img src='https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif' border='0' alt='donate via PayPal'>" .
                "</a>\n" ;
        }
    }
}

add_action( 'in_widget_form', 'flickr_eyecandy_appendDonation' );
function flickr_eyecandy_appendDonation($widget, $arg2=null, $arg3=null) {
    dpc_emit_paypal_donation_button($widget, 'FlickrEyeCandyWidget', 'EHPN58TQ2D57W');
}


add_action( 'widgets_init', 'flickr_eyecandy_widget_init' );
function flickr_eyecandy_widget_init() {
    register_widget('FlickrEyeCandyWidget');
}
