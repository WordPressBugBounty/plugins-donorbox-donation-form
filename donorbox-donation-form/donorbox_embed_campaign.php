<?php
/*
Plugin Name: Donorbox Donation Form
Plugin URI: https://donorbox.org
Description: This plugin will embed Donorbox Donation Form to your site using shortcode '[donate]' and '[donate-with-info]'. The settings is very simple. One input box for either the full Donorbox url e.g. https://donorbox.org/campaign-id or the part after the '/' 'campaign_id'. No embed code needed. We will generate the embed code for you!
Author: rebelidealist
Author URI: https://donorbox.org
Tags: donation, donations, nonprofit, nonprofits, fundraising, payment, payments, crowdfunding, campaign, stripe, campaigns, social causes, causes, credit card, credit cards
Version: 7.1.12
License: GPLv2 or later.
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Donorbox class
 */
class Donorbox_donation_form {
    public $options; // variable to hold the options values

    /**
     * Class constructor
     */
    public function __construct() {
        $this->options = get_option('donorbox_embed_campaign_options'); // get the existing plugin options
        $this->donorbox_register_settings_and_fields(); // invoke to register the plugin settings and admin sections
    }

    /**
     * Generate the admin settings page
     */
    public static function donorbox_display_options_page() { ?>
        <div class="wrap">
            <h2>Donorbox Settings</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('donorbox_embed_campaign_options');
                do_settings_sections(__FILE__);
                ?>
                <p class="submit">
                    <input name="submit" type="submit" class="button-primary" value="Save Changes" />
                </p>
            </form>
        </div><?php
    }

    /**
     * Add menu page
     */
    public static function donorbox_add_menu_page() {
        add_options_page('Donorbox', 'Donorbox', 'administrator', __FILE__, array('Donorbox_donation_form','donorbox_display_options_page'));
    }

    /**
     * Register fields and sections
     */
    public function donorbox_register_settings_and_fields() {
        register_setting('donorbox_embed_campaign_options', 'donorbox_embed_campaign_options');
        add_settings_section('donorbox_campaign_settings_section', 'Campaign Settings', array($this, 'donorbox_embed_campaign_callback'), __FILE__);
        add_settings_field('donorbox_embed_campaign_instructions', 'Instructions', array($this, 'donorbox_embed_campaign_instructions_text'), __FILE__, 'donorbox_campaign_settings_section');
        add_settings_field('donorbox_embed_campaign_id', 'Campaign URL', array($this, 'donorbox_embed_campaign_id_settings'), __FILE__, 'donorbox_campaign_settings_section');
    }

    /**
     * Callback - can be used for extending features
     */
    public function donorbox_embed_campaign_callback() {}

    /**
     * Campaign instructions
     */
    public function donorbox_embed_campaign_instructions_text() { ?>
        <p class="description">1. After signing up on <a href="https://donorbox.org" target="_blank">Donorbox.org</a>, create a donation campaign from the dashboard.</p>
        <p class="description">2. Paste in either the full Donorbox campaign url e.g. https://donorbox.org/campaign-id or the part after the '/' 'campaign-id'.</p>
        <p class="description">3. Use the shortcode <strong>[donate]</strong> to embed the donation form. You can also use the shortcode <strong>[donate-with-info]</strong> which will include the campaign description and legal disclaimer that was on Donorbox.</p>
        <?php
    }

    /**
     * Campaign details input
     */
    public function donorbox_embed_campaign_id_settings() { ?>
        <input name="donorbox_embed_campaign_options[donorbox_embed_campaign_id]" type="text" value="<?php echo esc_url(isset($this->options['donorbox_embed_campaign_id']) ? $this->options['donorbox_embed_campaign_id'] : ''); ?>" class="regular-text" />
        <?php
    }
}

/**
 * Add to admin menu
 */
function donorbox_add_options_page_function() {
    Donorbox_donation_form::donorbox_add_menu_page();
}

/**
 * Class object creation
 */
function donorbox_initiate_class() {
    new Donorbox_donation_form();
}

/**
 * Get the meta options file and set settings accordingly
 */
function donorbox_embed_campaign_set_plugin_meta($links, $file) {
    $plugin = plugin_basename(__FILE__);
    if ($file == $plugin) {
        return array_merge(
            $links,
            array( sprintf( '<a href="options-general.php?page=%s">%s</a>', $plugin, __('Settings') ) )
        );
    }
    return $links;
}

/**
 * Generalized function to generate iframe code - based on input parameter adds or skips info section
 * $info_details - Parameter - Determines whether to add info section or not
 * Input value to add info - 'with-info'
 * Returns iframe embed code
 */
function generate_donorbox_iframe_src($info_details, $override_url) {
    // this points to the base donorbox domain - embed codes are generated based on this
    $donorbox_domain = 'https://donorbox.org';

    // get the existing options entries for the plugin
    $options = get_option('donorbox_embed_campaign_options');

    $donorbox_campaign_input = $options['donorbox_embed_campaign_id']; // get the campaign id

    // check if an override url was provided
    if ($override_url != "") {
        $donorbox_campaign_input = $override_url; // if shortcode provides an URL then use that instead
    }
    $campaign_keys = parse_url($donorbox_campaign_input); // parse the url
    $path = explode("/", $campaign_keys['path']); // splitting the path
    $campaign_id = end($path); // get the value of the last element
    $style = 'style="max-width:500px; min-width:310px;"'; // default inline style for widget width management
    // if user entered a slash at end of the url, then check if a valid campaign id can be extracted from the url or not
    if (empty($campaign_id)) {
        $campaign_id = prev($path); // traceback to previous url segments
    }

    // append query parameters if present or if info details is required
    $pars = Array();
    if($info_details === 'with-info') {
      $pars[] = 'show_content=true';
      $style = 'style="max-width:100%; min-width:100%;"';
    }
    if(isset($campaign_keys['query']) && $campaign_keys['query'])
      $pars[] = $campaign_keys['query'];
    if(!empty($pars))
      $campaign_id .= '?' . implode('&', $pars);

    $campaign_id = esc_attr($campaign_id);

    // generate the iframe code
    $donorbox_iframe_embed_code = '<script src="https://donorbox.org/widget.js" type="text/javascript"></script><iframe src="'.$donorbox_domain.'/embed/'.$campaign_id.'" width="100%" '.$style.' seamless="seamless" id="dbox-form-embed" name="donorbox" frameborder="0" scrolling="no" allowpaymentrequest></iframe>';
    // return the embed code to calling event i.e. shortcode replacement
    return $donorbox_iframe_embed_code;
}

/**
 * Replace the shortcode with the embed code - no info
 */
function display_donorbox_iframe($atts) {
    $set_url = "";
    if (is_array($atts) && array_key_exists('url', $atts)) {
        if ($atts["url"] != "") {
            $set_url = $atts["url"];
        }
    }
    return generate_donorbox_iframe_src('', $set_url);
}

/**
 * Replace the shortcode with the embed code - with info
 */
function display_donorbox_iframe_with_info($atts) {
    $set_url = "";
    if (is_array($atts) && array_key_exists('url', $atts)) {
        if ($atts["url"] != "") {
            $set_url = $atts["url"];
        }
    }
    return generate_donorbox_iframe_src('with-info', $set_url);
}

add_shortcode('donate', 'display_donorbox_iframe'); // add [donate] shortcode
add_shortcode('donate-with-info', 'display_donorbox_iframe_with_info'); // add [donate-with-info] shortcode
add_action('admin_menu', 'donorbox_add_options_page_function'); // add item to admin menu
add_action('admin_init', 'donorbox_initiate_class'); // plugin initialization action
add_filter( 'plugin_row_meta', 'donorbox_embed_campaign_set_plugin_meta', 10, 2 ); // plugin meta options

?>
