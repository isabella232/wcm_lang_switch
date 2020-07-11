<?php
/**
 * Plugin Name:  User Language Switcher
 * Plugin URI:   https://github.com/wecodemore/wcm_lang_switch
 * Description:  Change the language per user, by the click of a button
 * Author:       Stephen Harris
 * Author URI:   https://plus.google.com/b/109907580576615571040/109907580576615571040/posts
 * Contributors: Franz Josef Kaiser, wecodemore
 * Version:      1.8.1
 * License:      GNU GPL 3
 */


# PUBLIC API #
/**
 * A function returns with returns the user's selected locale, if stored.
 *
 * @param bool $locale
 *
 * @return mixed string/bool $locale|$locale_new
 * @since  0.1
 */
function wcm_get_user_lang($locale = false)
{
    if ($locale_new = get_user_meta(
        get_current_user_id(),
        'user_language',
        true
    )) {
        return $locale_new;
    }

    return $locale;
}

add_action('plugins_loaded', array('WCM_User_Lang_Switch', 'init'), 5);

/**
 * Allows the user to change the systems language.
 * Saves the preference as user meta data.
 *
 * @since      0.1
 *
 * @author     Stephen Harris, Franz Josef Kaiser
 * @link       https://github.com/wecodemore/wcm_lang_switch
 *
 * @package    WordPress
 * @subpackage User Language Change
 */
class WCM_User_Lang_Switch
{
    /**
     * A unique name for this plug-in
     * @since  0.1
     * @static
     * @var    string
     */
    static public $name = 'wcm_user_lang';
    /**
     * Array of language names (in English & native language), indexed by language code
     * @since  1.3
     * @static
     * @var    string
     */
    static public $lang_codes;
    /**
     * Instance
     * @static
     * @access protected
     * @var object
     */
    static protected $instance;
    /**
     * @internal Enable Dev Tools?
     * @since 1.3
     * @var   bool
     */
    public $dev = true;

    /**
     * Hook the functions
     * @since  0.1
     */
    public function __construct()
    {
        if (isset($_REQUEST[self::$name])) {
            add_action('locale', array($this, 'update_user'));
        }

        add_filter('locale', 'wcm_get_user_lang', 20);
        add_action('admin_bar_menu', array($this, 'admin_bar'), 999);

        $this->dev && add_action('wp_dashboard_setup', array($this, 'dev_tools'), 99);
    }

    /**
     * Creates a new static instance
     * @return object|\WCM_User_Lang_Switch $instance
     * @since  0.2
     * @static
     */
    public static function init()
    {
        null === self::$instance && self::$instance = new self;

        return self::$instance;
    }

    /**
     * Update the user's option just in time!
     *
     * @param string $locale
     *
     * @return string $locale
     * @since  0.1
     */
    public function update_user($locale)
    {
        // The filter runs only once
        remove_filter(current_filter(), array($this, __FUNCTION__));

        update_user_meta(
            get_current_user_id(),
            'user_language',
            $_REQUEST[self::$name]
        );

        return wcm_get_user_lang($locale);
    }

    /**
     * The 'drop down' for the admin bar
     *
     * Based on Thomas "toscho" Scholz answer on the following WP.SE question by Stephen Harris:
     * @link http://goo.gl/6oqug
     *
     * @since   0.1
     * @uses    get_available_language()
     * @uses    format_code_lang()
     * @wp-hook admin_bar_menu
     *
     * @param $wp_admin_bar
     *
     * @return void
     */
    public function admin_bar($wp_admin_bar)
    {
        $locale = get_locale();

        $current = $this->format_code_lang($locale);
        $node_id = 'wcm_user_lang_pick';
        $wp_admin_bar->add_node(
            array(
                'id'    => $node_id,
                'title' => sprintf(
                    '<span class="ab-icon"></span><span class="ab-label">%s</span>',
                    $current
                ),
                'href'  => '#',
                'meta'  => array(
                    'title' => $current,
                ),
            )
        );
        // Admin Bar node (dash)icon: 'dashicon-translation'
        ?>
        <style>
        #wpadminbar #wp-admin-bar-<?php echo $node_id; ?> .ab-icon:before {
            content: '\f326';
            top: 2px;
        }

        @media screen and ( max-width: 782px ) {
            #wpadminbar #wp-admin-bar-<?php echo $node_id; ?> {
                display: block;
            }
        }
        </style><?php

        foreach ($this->get_langs() as $lang) {
            $lang_name = $this->format_code_lang($lang);
            $link = add_query_arg(
                self::$name,
                $lang
            );

            // Don't add the current language as menu item
            if ($lang === get_locale()) {
                continue;
            }

            $wp_admin_bar->add_node(
                array(
                    'parent' => 'wcm_user_lang_pick',
                    'id'     => "wcm_user_lang_pick-{$lang}",
                    'title'  => $lang_name,
                    'href'   => $link,
                    'meta'   => array(
                        'title' => sprintf(
                            "%s (%s)",
                            $this->format_code_lang($lang, 'int'),
                            $lang
                        ),
                        'class' => 'wcm_user_lang_item',
                    ),
                )
            );
        }
    }

    /**
     * Converts language code into 'human readable' form.
     *
     * Is an exact copy of the function format_code_lang()
     * Including wp-admin/includes/ms.php in non-ms sites displays message
     * prompting user to update network sites, hence we've just duplicated the function.
     *
     * @param string $code Language code, e.g. en_US or en
     * @param string $part Which part to return: International or native name?
     *
     * @return string The human readable language name, e.g. 'English', or the input on Error.
     * @link   http://codex.wordpress.org/Function_Reference/format_code_lang
     *
     * @since  0.2
     */
    public function format_code_lang($code = '', $part = 'native')
    {
        $label_code = strtok(strtolower($code), "_");
        if (null === self::$lang_codes) {
            $iso_639_2        = file(plugin_dir_path(__FILE__) . '/json/lang_codes.min.json');
            self::$lang_codes = json_decode(reset($iso_639_2), true);
        }

        if ( ! empty(self::$lang_codes['error'])) {
            return $code;
        }

        $codes = apply_filters('wcm_lang_codes', self::$lang_codes, $code);

        if ( ! isset($codes[$label_code])) {
            return $code;
        }

        return $codes[$label_code][$part];
    }

    /**
     * Get Languages
     * @return array
     * @since  0.3
     */
    public function get_langs()
    {
        return apply_filters(
            'wcm_get_langs',
            array_merge(
                get_available_languages(),
                array('en_US')
            )
        );
    }

    public function dev_tools()
    {
        if (
            ! is_admin()
            || ! current_user_can('manage_options')
        ) {
            return;
        }

        include_once plugin_dir_path(__FILE__) . '/dev_tools.class.php';
        new WCM_User_Lang_Switch_DevTools();
    }
}
