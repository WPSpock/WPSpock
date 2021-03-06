<?php

/**
 * Say Hello,
 *  __    __  ___   __                  _
 * / / /\ \ \/ _ \ / _\_ __   ___   ___| | __
 * \ \/  \/ / /_)/ \ \| '_ \ / _ \ / __| |/ /
 *  \  /\  / ___/  _\ \ |_) | (_) | (__|   <
 *   \/  \/\/      \__/ .__/ \___/ \___|_|\_\
 *                    |_|
 *
 */

namespace WPScotty\WPSpock\Foundation;

use WPScotty\WPSpock\Database\WordPressOption;
use WPScotty\WPSpock\Footer\Footer;
use WPScotty\WPSpock\Header\Header;
use WPScotty\WPSpock\Post\Post;
use WPScotty\WPSpock\Support\MinifyHTML;
use WPScotty\WPSpock\Support\Str;


if (!defined('ABSPATH')) {
    exit;
}

class Theme
{

    /**
     * The get_template_directory(). You can use this path to "include" your files.
     *
     * @var $themePath
     */
    protected $themePath;

    /**
     * The get_template_directory_uri(). You can use this uri for scripts and styles.
     *
     * @var $themeUri
     */
    protected $themeUri;

    /**
     * The wp_get_theme() info.
     *
     * @var $theme
     */
    protected $theme;

    /**
     * Instance of Post class.
     *
     * @var Post
     */
    private $post = null;

    /**
     * Instance of Header class.
     *
     * @var Header
     */
    private $header = null;

    /**
     * Instance of Footer class.
     *
     * @var Footer
     */
    private $footer = null;

    /**
     * An instance of WordPress options class used to manage the options on the database.
     *
     * @var null
     */
    private $options_ = null;

    public function __construct()
    {

        $this->themePath = get_template_directory();
        $this->themeUri  = get_template_directory_uri();
        $this->theme     = wp_get_theme();

        $this->boot();
    }

    protected function boot()
    {
        add_action('after_setup_theme', function () {

            // load the theme configuration
            $theme = require get_template_directory() . '/config/theme.php';

            if (!empty($theme)) {

                // add_editor_style
                if (isset($theme['add_editor_style']) && $theme['add_editor_style']) {
                    add_editor_style();
                }

                // add_theme_support
                if (isset($theme['theme_support']) && is_array($theme['theme_support'])) {
                    foreach ($theme['theme_support'] as $key => $value) {
                        if (is_numeric($key)) {
                            add_theme_support($value);
                        } else {
                            add_theme_support($key, $value);
                        }
                    }
                }

                // Custom service provider
                if (isset($theme['providers'])) {
                    foreach ($theme['providers'] as $key => $className) {
                        $GLOBALS["spock_service_provider_{$key}"] = new $className;
                    }
                }

                // Minify HTML
                if (isset($theme['minify']) && $theme['minify']) {
                    MinifyHTML::init();
                }

                /**
                 * Make theme available for translation.
                 * Translations can be filed in the /languages/ directory.
                 */
                load_theme_textdomain('spock', get_template_directory() . '/languages');
            }

            // load the menu configuration
            $menu = require get_template_directory() . '/config/menus.php';

            // This theme uses wp_nav_menu() in one location.
            if (!empty($menu)) {
                register_nav_menus($menu);
            }

            // load the wordpress configuration
            $wordpress = require get_template_directory() . '/config/wordpress.php';

            if (!empty($wordpress)) {
                // admin bar
                if (isset($wordpress['show_admin_bar'])
                    && false === $wordpress['show_admin_bar'] && !is_admin()) {
                    add_action('init', function () {
                        add_filter('show_admin_bar', '__return_false');
                        wp_deregister_script('admin-bar');
                        wp_deregister_style('admin-bar');
                        remove_action('wp_footer', 'wp_admin_bar_render', 1000);
                        show_admin_bar(false);
                    });
                }

                // wordpress version
                if (isset($wordpress['wp_version'])
                    && false === $wordpress['wp_version']) {
                    remove_action('wp_head', 'wp_generator');
                    add_filter('the_generator', function () {
                        return '';
                    });
                }

                // clean wp_head
                if (!empty($wordpress['clean_wp_head'])) {
                    remove_action('wp_head', 'wp_resource_hints', 2);
                    remove_action('template_redirect', 'wp_shortlink_header', 11);
                    remove_action('wp_head', 'wlwmanifest_link');
                    remove_action('wp_head', 'rsd_link');
                    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
                    remove_action('wp_head', 'feed_links', 2);
                    remove_action('wp_head', 'feed_links_extra', 3);
                }

                // disable autentication by email
                if (isset($wordpress['authenticate_email_password'])
                    && false === $wordpress['authenticate_email_password']) {
                    remove_filter('authenticate', 'wp_authenticate_email_password', 20);
                }

                // remove the author
                if (isset($wordpress['comments']['author_link'])
                    && false === $wordpress['comments']['author_link']) {
                    remove_filter('get_comment_author_link', '__return_false');
                }

                // remove the author link
                if (isset($wordpress['comments']['author_link'])
                    && false === $wordpress['comments']['author_link']) {
                    remove_filter('get_comment_author_link', '__return_false');
                    add_filter('get_comment_author_link',
                        function ($return, $author) {
                            $return = $author;

                            return $return;

                        }, 10, 2
                    );
                }

                // excerpt_length
                if (isset($wordpress['posts']['excerpt_length'])) {
                    $count = $wordpress['posts']['excerpt_length'];
                    add_filter('excerpt_length',
                        function ($words) use ($count) {
                            return $count;
                        }, 99);
                }

                // feed
                if (isset($wordpress['feed'])
                    && false === $wordpress['feed']) {

                    $spock_disable_feed_hoook = function () {
                        wp_die(__('<h1>Feed not available, please visit our <a href="' . get_bloginfo('url') . '">Home Page</a>!</h1>'));
                    };

                    add_action('do_feed', $spock_disable_feed_hoook, 1);
                    add_action('do_feed_rdf', $spock_disable_feed_hoook, 1);
                    add_action('do_feed_rss', $spock_disable_feed_hoook, 1);
                    add_action('do_feed_rss2', $spock_disable_feed_hoook, 1);
                    add_action('do_feed_atom', $spock_disable_feed_hoook, 1);
                }

                // widget shortcode
                if (isset($wordpress['shortcode']) && $wordpress['feed']) {
                    add_filter('widget_text', 'do_shortcode');
                }
            }

            // load the editor configuration
            $editor = require get_template_directory() . '/config/editor.php';

            if (!empty($editor['editor-styles'])) {
                // Add support for editor styles.
                add_theme_support('editor-styles');

                // Enqueue editor styles.
                add_editor_style($editor['editor-styles']);
            }

            if (!empty($editor['editor-font-sizes'])) {
                add_theme_support('editor-font-sizes', $editor['editor-font-sizes']);

                add_action('wp_head', function () use ($editor) {
                    ?>
                  <style type="text/css">
                  <?php foreach($editor['editor-font-sizes'] as $font) : ?>
                  .has-<?php echo $font['slug'] ?>-font-size
                  {
                    font-size : <?php echo $font['size'] ?>px;
                  }

                  <?php endforeach; ?>
                  </style><?php
                });
            }

            if (!empty($editor['editor-color-palette'])) {
                add_theme_support('editor-color-palette', $editor['editor-color-palette']);

                add_action('wp_head', function () use ($editor) {
                    ?>
                  <style type="text/css">
                  <?php foreach($editor['editor-color-palette'] as $color) : ?>
                  .has-text-color.has-<?php echo $color['slug'] ?>-color
                  {
                    color : <?php echo $color['color'] ?>;
                  }

                  .has-background.has-<?php echo $color['slug'] ?>-background-color
                  {
                    background-color : <?php echo $color['color'] ?>;
                  }

                  <?php endforeach; ?>
                  </style><?php
                });
            }

            if (!empty($editor['upload_mimes'])) {
                $upload_mimes = $editor['upload_mimes'];
                add_filter('upload_mimes', function ($mimes = []) use ($upload_mimes) {
                    return array_merge($mimes, $upload_mimes);
                });
            }

        });

        /**
         * Set the content width in pixels, based on the theme's design and stylesheet.
         *
         * Priority 0 to make it available to lower priority callbacks.
         *
         * @global int $content_width
         */

        add_action('after_setup_theme', function () {
            // This variable is intended to be overruled from themes.
            // Open WPCS issue: {@link https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/1043}.
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            $GLOBALS['content_width'] = apply_filters('spock_content_width', 640);
        }, 0);

        /**
         * Register widget area.
         *
         * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
         */
        add_action('widgets_init', function () {

            $sidebars = require get_template_directory() . '/config/sidebars.php';

            if (!empty($sidebars)) {
                foreach ($sidebars as $sidebar) {
                    register_sidebar($sidebar);
                }
            }
        });

        /**
         * Enqueue scripts and styles.
         */
        add_action('wp_enqueue_scripts', function () {

            // enqueue the main theme styles
            wp_enqueue_style('spock-style', get_stylesheet_uri(), [], wp_get_theme()->version);

            if (is_singular() && comments_open() && get_option('thread_comments')) {
                wp_enqueue_script('comment-reply');
            }

            $scripts = require get_template_directory() . '/config/scripts.php';

            if (!empty($scripts)) {
                foreach ($scripts as $key => $script) {
                    wp_enqueue_script($key, get_template_directory_uri() . "/public/js/{$script}", [], wp_get_theme()->version, true);
                }
            }

            $styles = require get_template_directory() . '/config/styles.php';

            if (!empty($styles)) {
                foreach ($styles as $key => $style) {
                    wp_enqueue_style($key, get_template_directory_uri() . "/public/css/{$style}", [], wp_get_theme()->version, true);
                }
            }

        });

        /**
         * Used to add anything in the head
         */

        add_action('wp_head', function () {
            require get_template_directory() . '/resources/wp_head.php';
        });

        /**
         * Used to add anything in the footer
         */

        add_action('wp_footer', function () {
            require get_template_directory() . '/resources/wp_footer.php';
        });

    }

    /**
     * Return the WP Spock vendor folder
     *
     * @return string
     */
    protected function vendor(): string
    {
        return '/vendor/wpspock/wpspock/src/';
    }

    /**
     * Return the Options theme instance used to get/set/delete options theme.
     * You'll be able to use spock()->options->get('myoption')
     *
     * @return \WPScotty\WPSpock\Database\WordPressOption|null
     */
    protected function getOptionsAttribute()
    {
        if (is_null($this->options_)) {
            $this->options_ = new WordPressOption($this);
        }

        return $this->options_;
    }

    /**
     * Return the slug of theme based on theme name.
     *
     * @return string
     */
    protected function getSlugAttribute()
    {
        return Str::snake($this->theme->Name);
    }

    public function __get($name)
    {
        $method = 'get' . Str::studly($name) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        if (property_exists($this->theme, $name)) {
            return $this->theme->{$name};
        }
    }

    /**
     * Load a theme resources.
     *
     * @param string $path Complete path/filename of resource.
     */
    public function resource(string $path)
    {
        $path = ltrim($path, '/');
        include "{$this->themePath}/resources/{$path}";
    }

    /**
     * Load a view from theme/resources/views
     *
     * @param string $path Path filename of view.
     */
    public function view(string $path)
    {
        $path = ltrim($path, '/');
        require "{$this->themePath}/resources/views/{$path}";
    }

    /**
     * Return an instance of a registered provider in the `config/theme.php`.
     *
     * @param string $key Provider key.
     * @return mixed|null
     */
    public function provider(string $key)
    {
        return $GLOBALS["spock_service_provider_{$key}"]??null;
    }

    /**
     * Return an instance of post
     *
     * @return \WPScotty\WPSpock\Post\Post
     */
    public function post(): Post
    {
        if (!$this->post) {
            $this->post = new Post;
        }

        return $this->post;
    }

    /**
     * Return an instance of post
     *
     * @return \WPScotty\WPSpock\Header\Header
     */
    public function header(): Header
    {
        if (!$this->header) {
            $this->header = new Header;
        }

        return $this->header;
    }

    /**
     * Return an instance of footer
     *
     * @return \WPScotty\WPSpock\Footer\Footer
     */
    public function footer(): Footer
    {
        if (!$this->footer) {
            $this->footer = new Footer;
        }

        return $this->footer;
    }

    /**
     * Helper
     *
     */
    public function theSlug()
    {
        global $post;

        echo $post->post_name??"";
    }

    /**
     * Wrapper of get_theme_mod() function.
     *
     * @param string         $name    Modification/setting name.
     * @param string|boolean $default Optional. Default value.
     * @return string
     */
    public function mod($name, $default = false)
    {
        return get_theme_mod($name, $default);
    }
}