<?php

/**
 * Plugin Name: Shesha by The Courier Guy
 * Description: Custom Shipping Method for WooCommerce
 * Version: 1.0.0
 * Author: The Courier Guy
 */

if(!defined('ABSPATH') ) { exit; } // exit if accessed directly



// Abort if WooCommerce not installed
if(!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
  return;
}

define('CSM_VERSION', '2.1.1');
define('CSM_PATH', plugins_url('', __FILE__));
define('CSM_DIR', __DIR__);

$plugin_dir = dirname(__FILE__);


require_once(CSM_DIR.'/Includes/ls-framework-custom/Core/CustomPluginDependencies.php');
require_once(CSM_DIR.'/Includes/ls-framework-custom/Core/CustomPlugin.php');
require_once(CSM_DIR.'/Includes/ls-framework-custom/Core/CustomPostType.php');

$ship_codes;
$avail_ship_options;

require_once CSM_DIR . '/helper/class-customhelper.php';
require_once(CSM_DIR. '/Core/TCGS_Plugin.php');

class CSM_Init{
    private $settings;
    private $enabled;
    private CSM_Zones_Method $csmMethod;

    function __construct() {
        $this->settings = get_option('dimative_shipping_instance_form_fields_filters');
        
        $this->enabled = isset($settings['enabled']) ? $settings['enabled'] : 'no';

        if($this->enabled === 'yes') {
          $this->admin_init();
          add_action('template_redirect', [$this, 'public_init']);
      }
      
  }
  

  public function setShippingCodes($shipping_codes=array(), $shipping_options=array()){
    global $ship_codes; 
    $ship_codes = $shipping_codes;
}

public function setShippingOptions($shipping_options=array()){
    global $avail_ship_options; 
    $avail_ship_options = $shipping_options;
}

    /**
    * Inititate the needed classes
    */
    function admin_init() {
        /*new WCIS_Ajax();*/
        new CSM_Checkout();
    }

    function public_init() {
        // change default
        // TODO: due to template_redirect action, Postcode might show up after refresh
        add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');
        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
    }


    /**
    * Initiate WC Shipping
    * @filter woocommerce_shipping_init
    */
    function shipping_init() {
        require_once('module-admin/init-zones.php');
    }

    /**
    * Add our custom Shipping method
    * @filter woocommerce_shipping_methods
    */
    function shipping_method($methods) {
        $methods['sovtech_tcg'] = 'CSM_Shipping_Method';
        $methods['sovtech_tcg_zone'] = 'CSM_Zones_Method';
        
        return $methods;
    }

}

$wdm_address_fields = array(
    'country',
    'first_name',
    'last_name',
    'company',
    'address_2',
    'address_1',
    'city',
    'state',
    'postcode',
    'type',
);

// global array only for extra fields.
$wdm_ext_fields = array( 'title' );

/**
 *
 *
 * Created custom field in general setting 'Phone Number'.
 *
 * @param array $settings for adding new settings. */
function general_settings_shop_phone( $settings ) {
    $key = 0;

    foreach ( $settings as $values ) {
        $new_settings[ $key ] = $values;
        $key++;

        // Inserting array just after the post code in "Store Address" section.
        if ( 'woocommerce_store_postcode' === $values['id'] ) {
            $new_settings[ $key ] = array(
                'title'    => __( 'Phone Number' ),
                'desc'     => __( 'Optional phone number of your business office' ),
                'id'       => 'woocommerce_store_phone', // <= The field ID (important)!!
                'default'  => '',
                'type'     => 'text',
                'desc_tip' => true, // or false.
            );
            $key++;
        }
    }
    return $new_settings;
}

add_filter( 'woocommerce_general_settings', 'general_settings_shop_phone' );


/**
 *
 *
 * Created custom field in general setting 'Address line 3'.
 *
 * @param array $settings for adding new settings. */
function general_settings_shop_address3( $settings ) {
    $key = 0;

    foreach ( $settings as $values ) {
        $new_settings[ $key ] = $values;
        $key++;

        // Inserting array just after the post code in "Store Address" section.
        if ( 'woocommerce_store_address_2' === $values['id'] ) {
            $new_settings[ $key ] = array(
                'title'    => __( 'Address line 3' ),
                'desc'     => __( 'An additional, optional address line for your business location.' ),
                'id'       => 'woocommerce_store_address_3', // <= The field ID (important)!!
                'default'  => '',
                'type'     => 'text',
                'desc_tip' => true, // or false.
            );
            $key++;
        }
    }
    return $new_settings;
}

add_filter( 'woocommerce_general_settings', 'general_settings_shop_address3' );


/**
 *
 *
 * Created custom field in general setting 'Address Type'.
 *
 * @param array $settings for adding new settings. */
function general_settings_shop_address_type( $settings ) {
    $key = 0;
    foreach ( $settings as $values ) {
        $new_settings[ $key ] = $values;
        $key++;
        // Inserting array just after the post code in "Store Address" section.
        if ( 'woocommerce_store_phone' === $values['id'] ) {
            $new_settings[ $key ] = array(
                'title'    => __( 'Address Type' ),
                'id'       => 'woocommerce_store_address_type', // <= The field ID (important)!!
                'desc'     => __( 'Select Address Type according to your location' ),
                'type'     => 'select',
                'desc_tip' => true, // or false.
                'options'  => array(
                    'RESIDENTIAL' => __( 'Residential', 'sovTech_tcg' ),
                    'BUSINESS'    => __( 'Business', 'sovTech_tcg' ),
                ),
            );
            $key++;
        }
    }
    return $new_settings;
}

add_filter( 'woocommerce_general_settings', 'general_settings_shop_address_type' );

/**
 *
 *
 * Added custom field on Checkout.
 *
 * @param array $address_fields for adding new address fields. */
function wdm_override_default_address_fields( $address_fields ) {
    $temp_fields = array();

    $address_fields['type'] = array(
        'label'    => __( 'Address Type', 'woocommerce' ),
        'required' => true,
        'class'    => array( 'form-row-wide' ),
        'type'     => 'select',
        'options'  => array(
            'RESIDENTIAL' => __( 'Residential', 'woocommerce' ),
            'BUSINESS'    => __( 'Business', 'woocommerce' ),
        ),
    );

    global $wdm_address_fields;

    foreach ( $wdm_address_fields as $fky ) {
        $temp_fields[ $fky ] = $address_fields[ $fky ];
    }

    $address_fields = $temp_fields;

    return $address_fields;
}
add_filter( 'woocommerce_default_address_fields', 'wdm_override_default_address_fields' );

if ( ! function_exists( 'dimative_shipping_instance_form_fields_filters' ) ) {
    /**
     * Shipping Instance form add extra fields.
     * @param array $settings Settings.
     * @return array
     */
    
    function dimative_shipping_instance_form_add_extra_fields( $settings ) {
        global $ship_codes, $avail_ship_options;

        $settings['enabled'] = array(
            'title'       => __( 'Shipping Method Status', 'sovTech_tcg' ),
            'type'        => 'checkbox',
            'description' => __( 'Checkbox to enable/disable this shipping method.', 'sovTech_tcg' ),
            'default'     => 'yes',
            'label'       => __( 'enable/disable', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
        );

        $settings['title'] = array(
            'title'       => __( 'Name/Title:', 'sovTech_tcg' ),
            'type'        => 'text',
            'description' => __( 'Name to be display on shipping section of website.', 'sovTech_tcg' ),
            'default'     => __( 'Sovtech TCG Shipping', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Title' ),
        );
        $settings['connection_server'] = array(
            'title'       => __( 'Connection Server', 'sovTech_tcg' ),
            'type'        => 'select',
            'options'     => array(
                ''           => __( 'Select', 'sovTech_tcg' ),
                'staging'    => __( 'Staging', 'sovTech_tcg' ),
                'production' => __( 'Production', 'sovTech_tcg' ),
            ),
            'description' => __( 'Select Connection Server', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
        );

        $settings['prod_url'] = array(
            'title'       => __( 'Production URL', 'sovTech_tcg' ),
            'type'        => 'text',
            'description' => __( 'Production URL will be used when the setting is enabled for Production mode ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Production URL' ),
        );
        
        
        $settings['prod_apikey'] = array(
            'title'       => __( 'Production API Key', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Production mode API Key' ),
            'description' => __( 'Production API Key for Production mode server', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Production API Key' ),
        );

        $settings['stage_url'] = array(
            'title'       => __( 'Staging URL', 'sovTech_tcg' ),
            'type'        => 'text',
            'description' => __( 'Staging URL will be used when the setting is enabled for Staging mode ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Staging Url' ),
            'value'       => ''
        );

        $settings['stage_apikey'] = array(
            'title'       => __( 'Staging API Key', 'sovTech_tcg' ),
            'type'        => 'text',
            'description' => __( 'Staging API Key for Staging mode server ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Staging API Key' ),
        );

        $settings['maps_apikey'] = array(
            'title'       => __( 'Google Maps API Key', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Google Maps API Key' ),
            'description' => __( 'Google Maps API Key for fetching address details', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
            );

        $settings['company_name'] = array(
            'title'       => __( 'Company Name', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Company Name' ),
            'description' => __( 'Company Name for Staging mode server ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Company Name' ),
            'default'     => '',
        );
        $settings['shop_street_and_name'] = array(
            'title'       => __( 'Shop Street Number and Name', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Shop Street Number and Name' ),
            'description' => __( 'Shop Street Number and Name for Staging mode server ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Shop Street Number and Name' ),
            'default'     => '',
        );
        $settings['shop_suburb'] = array(
            'title'       => __( 'Shop Suburb', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Shop Suburb' ),
            'description' => __( 'Shop Suburb for Staging mode server ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Shop Suburb' ),
            'default'     => '',
        );
        $settings['shop_city'] = array(
            'title'       => __( 'Shop City', 'sovTech_tcg' ),
            'type'        => 'text',
            'placeholder' => __( 'Please Enter Shop City' ),
            'description' => __( 'Shop City for Staging mode server ', 'sovTech_tcg' ),
            'desc_tip'    => true, // or false.
            'placeholder' => __( 'Please Enter Shop City' ),
            'default'     => '',
        );
        $settings['shop_state'] = array(
            'title'       => __('Shop State or Province', 'sovTech_tcg'),
            'type'        => 'select',
            'description' => __(
                'State / Province forms part of the shipping address',
                'sovTech_tcg'
            ),
            'options'     => WC()->countries->get_states('ZA'),
            'default'     => '',
        );
        $settings['shop_country'] = array(
            'title'       => __('Shop Country', 'sovTech_tcg'),
            'type'        => 'select',
            'description' => __(
                'Country forms part of the shipping address e.g South Africa',
                'sovTech_tcg'
            ),
            'options'     => WC()->countries->get_countries(),
            'default'     => 'ZA',
        );
        $settings['shop_postal_code'] = array(
            'title'       => __('Shop Postal Code', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __(
                'The address used to calculate shipping, this is considered the collection point for the parcels being shipping.',
                'sovTech_tcg'
            ),
            'default'     => '',
        );
        $settings['shop_phone'] = array(
            'title'       => __('Shop Phone', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __(
                'The telephone number to contact the shop, this may be used by the courier.',
                'sovTech_tcg'
            ),
            'default'     => '',
        );

        $settings['address_type'] = array(
            'title'       => __( 'Address Type', 'sovTech_tcg' ),
            'type'        => 'select',
            'description' => __(
                'It will define which type of property it is using Address Type',
                'sovTech_tcg'
            ),
            'options'     => array('RESIDENTIAL' => 'Residential', 'BUSINESS' => 'Business'),
            'default'     => 'RESIDENTIAL',
            'desc_tip'    => true, // or false.
        );

        $settings['shop_email'] = array(
            'title'       => __('Shop Email', 'sovTech_tcg'),
            'type'        => 'email',
            'description' => __(
                'The email to contact the shop, this may be used by the courier.',
                'sovTech_tcg'
            ),
            'default'     => '',
        );
        $settings['disable_specific_shipping_options'] = array(
            'title'             => __('Enable Specific shipping options', 'sovTech_tcg'),
            'type'              => 'multiselect',
            'class'             => 'wc-enhanced-select',
            'css'               => 'width: 450px;',
            'description'       => __(
                'Select the shipping options that you wish to always be included from the available shipping options on the checkout page.',
                'sovTech_tcg'
            ),
            'default'           => $avail_ship_options,
            'options'           => $avail_ship_options,
            'custom_attributes' => [
                'data-placeholder' => __('Select the shipping option you would like to include', 'sovTech_tcg')
            ]
        );
        
        $settings['excludes'] = array(
            'title'             => __('Exclude Rates', 'sovTech_tcg'),
            'type'              => 'multiselect',
            'class'             => 'wc-enhanced-select',
            'css'               => 'width: 450px;',
            'description'       => __(
                'Select the rates that you wish to always be excluded from the available rates on the checkout page.',
                'sovTech_tcg'
            ),
            'default'           => '',
            'options'           => $ship_codes,
            'custom_attributes' => [
                'data-placeholder' => __('Select the rates you would like to exclude', 'sovTech_tcg')
            ]
        );
        
        $settings['percentage_markup'] = array(
            'title'       => __('Percentage Markup', 'sovTech_tcg'),
            'type'        => 'sovtech_percentage',
            'description' => __('Percentage markup to be applied to each quote.', 'sovTech_tcg'),
            'default'     => ''
        );
        $settings['automatically_submit_collection_order'] = array(
            'title'       => __('Automatically Submit Collection Order', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'This will determine whether or not the collection order is automatically submitted to The Courier Guy after checkout completion.',
                'sovTech_tcg'
            ),
            'default'     => 'no'
        );
        $settings['remove_waybill_description'] = array(
            'title'       => __('Generic waybill description', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'When enabled, a generic product description will be shown on the waybill.',
                'sovTech_tcg'
            ),
            'default'     => 'no'
        );

        $settings['price_rate_override_per_service'] = array(
            'title'       => __('Price Rate Override Per Service', 'sovTech_tcg'),
            'type'        => 'sovtech_override_rate_service',
            'description' => __(
             'These prices will override The Courier Guy rates per service.',
             'woocommerce'
         ) . '<br />' . __(
             'Select a service to add or remove price rate override.',
             'woocommerce'
         ) . '<br />' . __(
             'Services with an overridden price will not use the \'Percentage Markup\' setting.',
             'sovTech_tcg'
         ),
         'options'     => $ship_codes,
         'default'     => '',
         'class'       => 'sovtech-override-rate-service',
     );
        

        $settings['label_override_per_service'] = array(
            'title'       => __('Label Override Per Service', 'sovTech_tcg'),
            'type'        => 'sovtech_override_rate_service',
            'description' => __(
             'These labels will override The Courier Guy labels per service.',
             'sovTech_tcg'
         ) . '<br />' . __('Select a service to add or remove label override.', 'sovTech_tcg'),
            'options'     => $ship_codes,
            'default'     => '',
            'class'       => 'sovtech-override-rate-service',
        );
        

        $settings['flyer'] = array(
            'title'   => '<h3>Parcels - Flyer Size</h3>',
            'type'    => 'hidden',
            'default' => '',
        );

        $settings['product_length_per_parcel_1'] = array(
            'title'       => __('Length of Flyer (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the Flyer - required', 'sovTech_tcg'),
            'default'     => '42',
            'placeholder' => 'none',
        );
        $settings['product_width_per_parcel_1'] = array(
            'title'       => __('Width of Flyer (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the Flyer - required', 'sovTech_tcg'),
            'default'     => '32',
            'placeholder' => 'none',
        );
        $settings['product_height_per_parcel_1'] = array(
            'title'       => __('Height of Flyer (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the Flyer - required', 'sovTech_tcg'),
            'default'     => '12',
            'placeholder' => 'none',
        );
        $settings['medium_parcel'] = array(
            'title'   => '<h3>Parcels - Medium Parcel Size</h3>',
            'type'    => 'hidden',
            'default' => '',
        );
        $settings['product_length_per_parcel_2'] = array(
            'title'       => __('Length of Medium Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the medium parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_width_per_parcel_2'] = array(
            'title'       => __('Width of Medium Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the medium parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_height_per_parcel_2'] = array(
            'title'       => __('Height of Medium Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the medium parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['large_parcel'] = array(
            'title'   => '<h3>Parcels - Large Parcel Size</h3>',
            'type'    => 'hidden',
            'default' => '',
        );
        $settings['product_length_per_parcel_3'] = array(
            'title'       => __('Length of Large Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the large parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_width_per_parcel_3'] = array(
            'title'       => __('Width of Large Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the large parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_height_per_parcel_3'] = array(
            'title'       => __('Height of Large Parcel (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the large parcel - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['custom_parcel_size_1'] = array(
            'title'   => '<h3>Custom Parcel Size 1</h3>',
            'type'    => 'hidden',
            'default' => '',
        );
        $settings['product_length_per_parcel_4'] = array(
            'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_width_per_parcel_4'] = array(
            'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_height_per_parcel_4'] = array(
            'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['custom_parcel_size_2'] = array(
            'title'   => '<h3>Custom Parcel Size 2</h3>',
            'type'    => 'hidden',
            'default' => '',
        );
        $settings['product_length_per_parcel_5'] = array(
            'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_width_per_parcel_5'] = array(
            'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );
        $settings['product_height_per_parcel_5'] = array(
            'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );

        $settings['custom_parcel_size_3'] = array(
            'title'   => '<h3>Custom Parcel Size 3</h3>',
            'type'    => 'hidden',
            'default' => '',
        );

        $settings['product_length_per_parcel_6'] = array(
            'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );

        $settings['product_width_per_parcel_6'] = array(
            'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );

        $settings['product_height_per_parcel_6'] = array(
            'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
            'type'        => 'text',
            'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
            'default'     => '',
            'placeholder' => 'none',
        );

        $settings['billing_insurance'] = array(
            'title'       => __('Enable shipping insurance ', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'This will enable the shipping insurance field on the checkout page',
                'sovTech_tcg'
            ),
            'default'     => 'no'
        );

        $settings['free_shipping'] = array(
            'title'       => __('Enable free shipping ', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __('This will enable free shipping over a specified amount', 'sovTech_tcg'),
            'default'     => 'no'
        );

        $settings['rates_for_free_shipping'] = array(
            'title'             => __('Rates for free Shipping', 'sovTech_tcg'),
            'type'              => 'multiselect',
            'class'             => 'wc-enhanced-select',
            'css'               => 'width: 450px;',
            'description'       => __('Select the rates that you wish to enable for free shipping', 'sovTech_tcg'),
            'default'           => '',
            'options'           => $ship_codes,
            'custom_attributes' => [
                'data-placeholder' => __(
                    'Select the rates you would like to enable for free shipping',
                    'sovTech_tcg'
                )
            ]
        );
        
        $settings['amount_for_free_shipping'] = array(
            'title'             => __('Amount for free Shipping', 'sovTech_tcg'),
            'type'              => 'number',
            'description'       => __('Enter the amount for free shipping when enabled', 'sovTech_tcg'),
            'default'           => '1000',
            'custom_attributes' => [
                'min' => '0'
            ]
        );
        $settings['product_free_shipping'] = array(
            'title'       => __('Enable free shipping from product setting', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'This will enable free shipping if the product is included in the basket',
                'sovTech_tcg'
            ),
            'default'     => 'no'
        );

        $settings['usemonolog'] = array(
            'title'       => __('Enable WooCommerce Logging', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'Check this to enable WooCommerce logging for this plugin. Remember to empty out logs when done.',
                'sovTech_tcg'
            ),
            'default'     => __('no', 'sovTech_tcg'),
        );

        $settings['enablemethodbox'] = array(
            'title'       => __('Enable Method Box on Checkout', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __('Check this to enable the Method Box on checkout page', 'sovTech_tcg'),
            'default'     => 'no',
        );
        
        $settings['enablenonstandardpackingbox'] = array(
            'title'       => __('Use non-standard packing algorithm', 'sovTech_tcg'),
            'type'        => 'checkbox',
            'description' => __(
                'Check this to use the non-standard packing algorithm.<br> This is more accurate but will also use more server resources and may fail on shared servers.',
                'sovTech_tcg'
            ),
            'default'     => 'no',
        );
        
        return $settings;
    }

    /**
     * Shipping instance form fields.
     */
    function dimative_shipping_instance_form_fields_filters() {
        $shipping_methods = WC()->shipping->get_shipping_methods();
        foreach ( $shipping_methods as $shipping_method ) {
            add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method->id, 'dimative_shipping_instance_form_add_extra_fields' );
            add_filter( 'woocommerce_shipping_settings_' . $shipping_method->id, 'dimative_shipping_instance_form_add_extra_fields' );
        }
    }
    add_action( 'woocommerce_init', 'dimative_shipping_instance_form_fields_filters' );

    

}
$csmInit = new CSM_Init();
$TCGS_Plugin = new TCGS_Plugin(__FILE__);
$GLOBALS['TCGS_Plugin'] = $TCGS_Plugin;


