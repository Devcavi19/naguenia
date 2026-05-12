<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 * @package    Wpragbot
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wpragbot
 * @author     Your Name <email@example.com>
 */
class Wpragbot {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Wpragbot_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'WPRAGBOT_VERSION' ) ) {
            $this->version = WPRAGBOT_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wpragbot';
        $this->load_textdomain();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->loader->run();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Wpragbot_Loader. Orchestrates the hooks of the plugin.
     * - Wpragbot_Admin. Defines all hooks for the admin area.
     * - Wpragbot_Public. Defines all hooks for the public side of the site.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpragbot-loader.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wpragbot-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wpragbot-public.php';

        /**
         * The class responsible for API integration with Gemini and Qdrant.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpragbot-api.php';

        /**
         * The class responsible for analytics and reporting.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpragbot-analytics-supabase.php';

        $this->loader = new Wpragbot_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Wpragbot_Admin( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'settings_init' );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new Wpragbot_Public( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_ajax_wpragbot_chat', $plugin_public, 'handle_chat' );
        // NOTE: Removed wp_ajax_nopriv_wpragbot_chat to require user authentication for chat
        // $this->loader->add_action( 'wp_ajax_nopriv_wpragbot_chat', $plugin_public, 'handle_chat' );
        $this->loader->add_action( 'wp_footer', $plugin_public, 'display_chat_widget' );
        
        // Register shortcode
        add_shortcode( 'wpragbot', array( $plugin_public, 'display_chatbot_shortcode' ) );
    }

    /**
     * Retrieve the plugin name.
     *
     * @since    1.0.0
     * @return    string    The plugin name.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version.
     *
     * @since    1.0.0
     * @return    string    The plugin version.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'wpragbot',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
        );
    }

}