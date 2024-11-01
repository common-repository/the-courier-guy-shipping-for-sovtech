<?php


require_once CSM_DIR . '/helper/class-customhelper.php';
require_once(CSM_DIR. '/Core/TCGS_Plugin.php');
/**
 * Global setting for Indo Shipping
 */


class CSM_Shipping_Method extends WC_Shipping_Method {
    private $total_shipping_codes;
    private $avail_shipping_options;
    /**
     * Constructor for your shipping class
     *
     * @access public
     * @return void
     */
    /**
     * CSM_Shipping_Method constructor.
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0) {
        $this->id = 'sovtech_tcg';
        $this->title = __('Sovtech TCG');
        $this->method_title = __('Sovtech TCG');
        $this->method_description = __( 'SovTech TCG Shipping Shipping Method for Courier', 'sovTech_tcg' );

        $this->enabled = $this->get_option('enabled');

        $this->init();
        $this->tax_status         = false;
        $this->availability = 'including';
        
        $this->countries = array(
            'US', // Unites States of America
            'CA', // Canada
            'DE', // Germany
            'GB', // United Kingdom
            'IT', // Italy
            'ES', // Spain
            'HR', // Croatia
            'ZA' //South Africa
        );

    }

    public function init() {
        $this->init_settings();
        $this->init_shipping_codes();
        $this->init_form_fields();
        
        $helper_class = new Customhelper();

        $post_data = sanitize_text_field( wp_unslash( $_POST ) );
        
        if ( sanitize_text_field(isset( $post_data['woocommerce_sovtech_tcg_connection_server'] ) ) && sanitize_text_field(! empty($post_data['woocommerce_sovtech_tcg_connection_server'] ))) {

            if ( sanitize_text_field(! isset( $post_data['woocommerce_sovtech_tcg_maps_apikey'] )) && sanitize_text_field(empty( $post_data['woocommerce_sovtech_tcg_maps_apikey'] ) ) ) {

                return;
            }

            $server = sanitize_text_field($post_data['woocommerce_sovtech_tcg_connection_server']);
            if ( 'production' === $server ) {
                if ( sanitize_text_field(isset( $post_data['woocommerce_sovtech_tcg_prod_url'] ) ) && sanitize_text_field(! empty( $post_data['woocommerce_sovtech_tcg_prod_url'] ) ) && sanitize_text_field(isset( $post_data['woocommerce_sovtech_tcg_prod_apikey'] ) ) && sanitize_text_field(! empty( $post_data['woocommerce_sovtech_tcg_prod_apikey'] ) ) ) {
                    $api_url = sanitize_text_field($post_data['woocommerce_sovtech_tcg_prod_url']);
                    $api_key = sanitize_text_field($post_data['woocommerce_sovtech_tcg_prod_apikey']);
                } else {
                    add_action( 'admin_notices', array( $this, 'prod_fields_admin_notice__error' ) );
                }
            } else {
                if ( sanitize_text_field(isset( $post_data['woocommerce_sovtech_tcg_stage_url'] ) ) && sanitize_text_field(! empty($post_data['woocommerce_sovtech_tcg_stage_url'] ) ) && sanitize_text_field(isset( $post_data['woocommerce_sovtech_tcg_stage_apikey'] ) ) && sanitize_text_field(! empty($post_data['woocommerce_sovtech_tcg_stage_apikey'] ) ) ) {
                    $api_url = sanitize_text_field($post_data['woocommerce_sovtech_tcg_stage_url']);
                    $api_key = sanitize_text_field($post_data['woocommerce_sovtech_tcg_stage_apikey']);
                } else {
                    add_action( 'admin_notices', array( $this, 'stage_fields_admin_notice__error' ) );
                }
            }
            $api_return = $helper_class->check_api_valid_or_not( $api_url, $api_key );
            if ( 'true' === $api_return['status'] ) {
                // allow save setting
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_transients']);
                add_action( 'admin_notices', array( $this, 'sample_admin_notice__success' ) );

            } else {
                add_action( 'admin_notices', array( $this, 'sample_admin_notice__error' ) );
            }
        } else {
            return;

        }
    }



    /**
     *
     *
     * Admin notices for success. */
    public function sample_admin_notice__success() {
        ?>
        <div class="notice notice-success is-dismissible" style="color:green;">
            <p><?php esc_attr_e( 'Connection established successfully.', 'sovTech_tcg' ); ?></p>
        </div>
        <?php
    }

    /**
     * Admin Disclaimer notice on Activation.
     */
    function addDisclaimer()
    {
        if ( ! get_option('dismissed-csm_disclaimer', false)) { ?>
            <div class="updated-5 notice notice-csm is-dismissible" data-notice="csm_disclaimer">
                <p><strong>The Courier Guy 2342</strong></p>
                <p>Parcel sizes are based on your packaging structure. The plugin will compare the cart’s total
                    dimensions against “Flyer”, “Medium” and “Large” parcel sizes to determine the best fit. The
                    resulting calculation will be submitted to The Courier Guy as using the parcel’s dimensions.
                    <strong>By downloading and using this plugin, you accept that incorrect ‘Parcel Size’ settings
                        may cause quotes to be inaccurate, and The Courier Guy will not be responsible for these
                    inaccurate quotes.</strong></p>
                </div>
                <?php
            }
        }

    /**
     *
     *
     * Admin notices for error. */
    public function sample_admin_notice__error() {
        $class   = 'notice notice-error';
        $message = __( 'Invalid API key.', 'sovTech_tcg' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html($message));
    }

    
    /**
     *
     *
     * Admin notices for Production mode error. */
    public function prod_fields_admin_notice__error() {
        $class   = 'notice notice-error';
        $message = __( 'Please Fill Fields For Production Mode.', 'sovTech_tcg' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }

    /**
     *
     *
     * Admin notices for Staging mode error. */
    public function stage_fields_admin_notice__error() {
        $class   = 'notice notice-error';
        $message = __( 'Please Fill Fields For Staging Mode.', 'sovTech_tcg' );
        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }

    /**
     *
     *
     * Get Shipping codes for admin. */
    public function init_shipping_codes() {
        if(isset($this->settings)){
            $server = !empty($this->settings['connection_server']) ? $this->settings['connection_server'] : '';
            if ( 'production' === $server ) {
                $api_url = !empty($this->settings['prod_url']) ? $this->settings['prod_url'] : '';
                $api_key = !empty($this->settings['prod_apikey']) ? $this->settings['prod_apikey'] : '';
            } else {
                $api_url = !empty($this->settings['stage_url']) ? $this->settings['stage_url'] : '';
                $api_key = !empty($this->settings['stage_apikey']) ? $this->settings['stage_apikey'] : '';
            }
            $helper_class               = new Customhelper();
            $total_shipping_codes       = $helper_class->get_shipping_codes($api_url);

            $this->total_shipping_codes = $total_shipping_codes;


            if(isset($api_url)){
                $total_shipping_codes       = $helper_class->get_shipping_codes( $api_url ); 
                $this->total_shipping_codes = $total_shipping_codes;  

                $url                        = $api_url . '/shipping-options?token=' . $api_key;
                $maps_apikey                    = !empty($this->settings['maps_apikey']) ? $this->settings['maps_apikey'] : 'APIAIzaSyAeWf6isBqsCCLfvcs6TNy5OJoqnshjfGI';
            }

            $store_address      = !empty($this->settings["shop_street_and_name"]) ?  $this->settings["shop_street_and_name"] : '12 drury ln';
            $store_address_2    = !empty($this->settings["shop_suburb"]) ? $this->settings["shop_suburb"] : 'EC';
            $store_address_3    = !empty($this->settings["shop_state"]) ? $this->settings["shop_state"] : 'Roeland Square'; 
            $store_city         = !empty($this->settings["shop_city"]) ? $this->settings["shop_city"] : 'Cape town'; 
            $store_postcode     = !empty($this->settings["shop_postal_code"]) ? $this->settings["shop_postal_code"] : '8001';
            $store_raw_country  = !empty($this->settings["shop_country"]) ? $this->settings["shop_country"] : 'ZA';
            $store_email        = !empty($this->settings["shop_email"]) ? $this->settings["shop_email"] : 'test@gmail.com';
            $store_name         = !empty($this->settings["company_name"]) ? $this->settings["company_name"] : 'Sovtech';
            $store_phone        = !empty($this->settings["shop_phone"]) ? $this->settings["shop_phone"] : '9874563210';
            $store_address_type = !empty($this->settings["address_type"]) ? $this->settings["address_type"] : 'BUSINESS';

        // Created address string for Google maps API.
            $toaddress    = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
            $tolat_long   = $helper_class->get_lat_long( $toaddress, $maps_apikey );
            $to_lat = '';
            $to_long = '';
            $todata = '';
            if(!empty($tolat_long))
            {
                $to_lat = !empty($tolat_long['lat']) ? $tolat_long['lat'] : '';
                $to_long = !empty($tolat_long['lng']) ? $tolat_long['lng'] : '';
                if(!empty($tolat_long['lat']) && !empty($tolat_long['lng']))
                {
                  $todata = '{"lat":' . $to_lat . ',"lng":' . $to_long . '}';
              }
              else
              {
                  $todata = '{"lat":-34.0420131,"lng":18.6068238}';
              }
          }

          $fromaddress  = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
          $fromlat_long = $helper_class->get_lat_long( $fromaddress, $maps_apikey );


          $from_lat = '';
          $from_long = '';
          $fromdata = '';
          if(!empty($tolat_long))
          {
            $from_lat = !empty($fromlat_long['lat']) ? $fromlat_long['lat'] : '';
            $from_long = !empty($fromlat_long['lng']) ? $fromlat_long['lng'] : '';
            if(!empty($fromlat_long['lat']) && !empty($fromlat_long['lng']))
            {
              $fromdata = '{"lat":' . $from_lat . ',"lng":' . $from_long . '}';
          }
          else
          {
              $fromdata = '{"lat":-34.0420131,"lng":18.6068238}';
          }
      }

        // created an array for the TCG API request body.
      $shipping_option_request = array(
        'locationTo'   => array(
            'type'         => ! empty( $store_address_type ) ? $store_address_type : 'BUSINESS',
            'address'      => ! empty( $store_address ) ? $store_address : '12 drury ln',
            'building'     => ! empty( $store_address_2 ) ? $store_address_2 : 'Roeland Square',
            'suburb'       => $store_address_3,
            'phone'        => ! empty( $store_phone ) ? $store_phone : '9874563210',
            'email'        => ! empty( $store_email ) ? $store_email : 'test@gmail.com',
            'city'         => ! empty( $store_city ) ? $store_city : 'Cape town',
            'postCode'     => ! empty( $store_postcode ) ? $store_postcode : '8001',
            'province'     => $store_raw_country,
            'coordinates'  => $todata,
            'businessName' => !empty($this->settings['billing_company']) ? $this->settings['billing_company'] : '',
        ),
        'locationFrom' => array(
            'type'         => ! empty( $store_address_type ) ? $store_address_type : 'BUSINESS',
            'address'      => ! empty( $store_address ) ? $store_address : '12 drury ln',
            'building'     => ! empty( $store_address_2 ) ? $store_address_2 : 'Roeland Square',
            'suburb'       => $store_address_3,
            'phone'        => ! empty( $store_phone ) ? $store_phone : '9874563210',
            'email'        => ! empty( $store_email ) ? $store_email : 'test@gmail.com',
            'city'         => ! empty( $store_city ) ? $store_city : 'Cape town',
            'postCode'     => ! empty( $store_postcode ) ? $store_postcode : '8001',
            'province'     => $store_raw_country,
            'coordinates'  => $fromdata,
            'businessName' => ! empty( $store_name ) ? $store_name : 'SovTech',
        ),
    );

        // Converted array to JSON.
      $encoded_shipping_opt_request = wp_json_encode( $shipping_option_request, true );

      $shipping_options     = $helper_class->get_available_shipping_options($url, $encoded_shipping_opt_request);
      $avail_shipping_options = array();
      if(isset($shipping_options) && !empty($shipping_options))
      {
        foreach ( $shipping_options as $key => $value ) {
            $avail_shipping_options[$value->id] = $value->type;
        }
    }
    $this->avail_shipping_options = $avail_shipping_options;
}
}

    /**
     *
     *
     * Create plugin settings. */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled'           => array(
                'title'       => __( 'Shipping Method Status', 'sovTech_tcg' ),
                'type'        => 'checkbox',
                'description' => __( 'Checkbox to enable/disable this shipping method.', 'sovTech_tcg' ),
                'default'     => 'yes',
                'label'       => __( 'enable/disable', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
            ),
            'title'             => array(
                'title'       => __( 'Name/Title:', 'sovTech_tcg' ),
                'type'        => 'text',
                'description' => __( 'Name to be display on shipping section of website.', 'sovTech_tcg' ),
                'default'     => __( 'Sovtech TCG Shipping', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Title' ),
            ),
            'connection_server' => array(
                'title'       => __( 'Connection Server', 'sovTech_tcg' ),
                'type'        => 'select',
                'options'     => array(
                    ''           => __( 'Select', 'sovTech_tcg' ),
                    'staging'    => __( 'Staging', 'sovTech_tcg' ),
                    'production' => __( 'Production', 'sovTech_tcg' ),
                ),
                'description' => __( 'Select Connection Server', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
            ),
            'prod_url'          => array(
                'title'       => __( 'Production URL', 'sovTech_tcg' ),
                'type'        => 'text',
                'description' => __( 'Production URL will be used when the setting is enabled for Production mode ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Production URL' ),
            ),
            'prod_apikey'       => array(
                'title'       => __( 'Production API Key', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Production mode API Key' ),
                'description' => __( 'Production API Key for Production mode server', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Production API Key' ),
            ),
            'stage_url'         => array(
                'title'       => __( 'Staging URL', 'sovTech_tcg' ),
                'type'        => 'text',
                'description' => __( 'Staging URL will be used when the setting is enabled for Staging mode ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Staging API Key' ),
            ),
            'stage_apikey'      => array(
                'title'       => __( 'Staging API Key', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Staging mode API Key' ),
                'description' => __( 'Staging API Key for Staging mode server ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Staging API Key' ),
            ),
            'maps_apikey'       => array(
                'title'       => __( 'Google Maps API Key', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Google Maps API Key' ),
                'description' => __( 'Google Maps API Key for fetching address details', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
            ),
            'shipping_codes'    => array(
                'title'       => __( 'Shipping Options', 'sovTech_tcg' ),
                'type'        => 'multiselect',
                'options'     => $this->total_shipping_codes,
                'default'     => $this->total_shipping_codes,
                'description' => __( 'Shipping Method available from TCG', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'custom_attributes' => [
                    'data-placeholder' => __('Select the shipping codes you would like to include', 'sovTech_tcg')
                ]
            ),
            'company_name'    => array(
                'title'       => __( 'Company Name', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Company Name' ),
                'description' => __( 'Company Name for Staging mode server ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Company Name' ),
                'default'     => '',
            ),
            'shop_street_and_name'    => array(
                'title'       => __( 'Shop Street Number and Name', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Shop Street Number and Name' ),
                'description' => __( 'Shop Street Number and Name for Staging mode server ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Shop Street Number and Name' ),
                'default'     => '',
            ),
            'shop_suburb'    => array(
                'title'       => __( 'Shop Suburb', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Shop Suburb' ),
                'description' => __( 'Shop Suburb for Staging mode server ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Shop Suburb' ),
                'default'     => '',
            ),
            'shop_city'    => array(
                'title'       => __( 'Shop City', 'sovTech_tcg' ),
                'type'        => 'text',
                'placeholder' => __( 'Please Enter Shop City' ),
                'description' => __( 'Shop City for Staging mode server ', 'sovTech_tcg' ),
                'desc_tip'    => true, // or false.
                'placeholder' => __( 'Please Enter Shop City' ),
                'default'     => '',
            ),
            'shop_state'                             => array(
                'title'       => __('Shop State or Province', 'sovTech_tcg'),
                'type'        => 'select',
                'description' => __(
                    'State / Province forms part of the shipping address',
                    'sovTech_tcg'
                ),
                'options'     => WC()->countries->get_states('ZA'),
                'default'     => '',
            ),
            'shop_country'                           => array(
                'title'       => __('Shop Country', 'sovTech_tcg'),
                'type'        => 'select',
                'description' => __(
                    'Country forms part of the shipping address e.g South Africa',
                    'sovTech_tcg'
                ),
                'options'     => WC()->countries->get_countries(),
                'default'     => 'ZA',
            ),
            'shop_postal_code'                        => array(
                'title'       => __('Shop Postal Code', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __(
                    'The address used to calculate shipping, this is considered the collection point for the parcels being shipping.',
                    'sovTech_tcg'
                ),
                'default'     => '',
            ),
            'shop_phone'                             => array(
                'title'       => __('Shop Phone', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __(
                    'The telephone number to contact the shop, this may be used by the courier.',
                    'sovTech_tcg'
                ),
                'default'     => '',
            ),
            'shop_email'                             => array(
                'title'       => __('Shop Email', 'sovTech_tcg'),
                'type'        => 'email',
                'description' => __(
                    'The email to contact the shop, this may be used by the courier.',
                    'sovTech_tcg'
                ),
                'default'     => '',
            ),
            'disable_specific_shipping_options'     => array(
                'title'             => __('Enable Specific shipping options', 'sovTech_tcg'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __(
                    'Select the shipping options that you wish to always be included from the available shipping options on the checkout page.',
                    'sovTech_tcg'
                ),
                'default'           => '',
                'options'           => $this->avail_shipping_options,
                'custom_attributes' => [
                    'data-placeholder' => __('Select the shipping option you would like to include', 'sovTech_tcg')
                ]
            ),
            'excludes'                              => array(
                'title'             => __('Exclude Rates', 'sovTech_tcg'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __(
                    'Select the rates that you wish to always be excluded from the available rates on the checkout page.',
                    'sovTech_tcg'
                ),
                'default'           => $this->total_shipping_codes,
                'options'           => $this->total_shipping_codes,
                'custom_attributes' => [
                    'data-placeholder' => __('Select the rates you would like to exclude', 'sovTech_tcg')
                ]
            ),
            'percentage_markup'                     => array(
                'title'       => __('Percentage Markup', 'sovTech_tcg'),
                'type'        => 'sovtech_percentage',
                'description' => __('Percentage markup to be applied to each quote.', 'sovTech_tcg'),
                'default'     => ''
            ),
            'automatically_submit_collection_order' => array(
                'title'       => __('Automatically Submit Collection Order', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will determine whether or not the collection order is automatically submitted to The Courier Guy after checkout completion.',
                    'sovTech_tcg'
                ),
                'default'     => 'no'
            ),
            'remove_waybill_description'            => array(
                'title'       => __('Generic waybill description', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'When enabled, a generic product description will be shown on the waybill.',
                    'sovTech_tcg'
                ),
                'default'     => 'no'
            ),
            'price_rate_override_per_service'       => array(
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
             'options'     => '',
             'default'     => $this->total_shipping_codes,
             'class'       => 'sovtech_override_rate_service',
         ),
            'label_override_per_service'            => array(
                'title'       => __('Label Override Per Service', 'sovTech_tcg'),
                'type'        => 'sovtech_override_rate_service',
                'description' => __(
                 'These labels will override The Courier Guy labels per service.',
                 'sovTech_tcg'
             ) . '<br />' . __('Select a service to add or remove label override.', 'sovTech_tcg'),
                'options'     => '$this->getRateOptions()',
                'default'     => '',
                'class'       => 'sovtech_override_rate_service',
            ),
            'flyer'                                 => array(
                'title'   => '<h3>Parcels - Flyer Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_1'           => array(
                'title'       => __('Length of Flyer (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the Flyer - required', 'sovTech_tcg'),
                'default'     => '42',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_1'            => array(
                'title'       => __('Width of Flyer (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the Flyer - required', 'sovTech_tcg'),
                'default'     => '32',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_1'           => array(
                'title'       => __('Height of Flyer (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the Flyer - required', 'sovTech_tcg'),
                'default'     => '12',
                'placeholder' => 'none',
            ),
            'medium_parcel'                         => array(
                'title'   => '<h3>Parcels - Medium Parcel Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_2'           => array(
                'title'       => __('Length of Medium Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the medium parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_2'            => array(
                'title'       => __('Width of Medium Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the medium parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_2'           => array(
                'title'       => __('Height of Medium Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the medium parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'large_parcel'                          => array(
                'title'   => '<h3>Parcels - Large Parcel Size</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_3'           => array(
                'title'       => __('Length of Large Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the large parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_3'            => array(
                'title'       => __('Width of Large Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the large parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_3'           => array(
                'title'       => __('Height of Large Parcel (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the large parcel - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'custom_parcel_size_1'                  => array(
                'title'   => '<h3>Custom Parcel Size 1</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_4'           => array(
                'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_4'            => array(
                'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_4'           => array(
                'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'custom_parcel_size_2'                  => array(
                'title'   => '<h3>Custom Parcel Size 2</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_5'           => array(
                'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_5'            => array(
                'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_5'           => array(
                'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'custom_parcel_size_3'                  => array(
                'title'   => '<h3>Custom Parcel Size 3</h3>',
                'type'    => 'hidden',
                'default' => '',
            ),
            'product_length_per_parcel_6'           => array(
                'title'       => __('Length of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Length of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_width_per_parcel_6'            => array(
                'title'       => __('Width of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Width of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'product_height_per_parcel_6'           => array(
                'title'       => __('Height of Custom Parcel Size (cm)', 'sovTech_tcg'),
                'type'        => 'text',
                'description' => __('Height of the Custom Parcel Size - optional', 'sovTech_tcg'),
                'default'     => '',
                'placeholder' => 'none',
            ),
            'billing_insurance'                     => array(
                'title'       => __('Enable shipping insurance ', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will enable the shipping insurance field on the checkout page',
                    'sovTech_tcg'
                ),
                'default'     => 'no'
            ),
            'free_shipping'                         => array(
                'title'       => __('Enable free shipping ', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __('This will enable free shipping over a specified amount', 'sovTech_tcg'),
                'default'     => 'no'
            ),
            'rates_for_free_shipping'               => array(
                'title'             => __('Rates for free Shipping', 'sovTech_tcg'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 450px;',
                'description'       => __('Select the rates that you wish to enable for free shipping', 'sovTech_tcg'),
                'default'           => '',
                'options'           => $this->total_shipping_codes,
                'custom_attributes' => [
                    'data-placeholder' => __(
                        'Select the rates you would like to enable for free shipping',
                        'sovTech_tcg'
                    )
                ]
            ),
            'amount_for_free_shipping'              => array(
                'title'             => __('Amount for free Shipping', 'sovTech_tcg'),
                'type'              => 'number',
                'description'       => __('Enter the amount for free shipping when enabled', 'sovTech_tcg'),
                'default'           => '1000',
                'custom_attributes' => [
                    'min' => '0'
                ]

            ),
            'product_free_shipping'                 => array(
                'title'       => __('Enable free shipping from product setting', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'This will enable free shipping if the product is included in the basket',
                    'sovTech_tcg'
                ),
                'default'     => 'no'
            ),
            'usemonolog'                            => array(
                'title'       => __('Enable WooCommerce Logging', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'Check this to enable WooCommerce logging for this plugin. Remember to empty out logs when done.',
                    'sovTech_tcg'
                ),
                'default'     => __('no', 'sovTech_tcg'),
            ),
            'enablemethodbox'                       => array(
                'title'       => __('Enable Method Box on Checkout', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __('Check this to enable the Method Box on checkout page', 'sovTech_tcg'),
                'default'     => 'no',
            ),
            'enablenonstandardpackingbox'           => array(
                'title'       => __('Use non-standard packing algorithm', 'sovTech_tcg'),
                'type'        => 'checkbox',
                'description' => __(
                    'Check this to use the non-standard packing algorithm.<br> This is more accurate but will also use more server resources and may fail on shared servers.',
                    'sovTech_tcg'
                ),
                'default'     => 'no',
            ),
        );

}
}
