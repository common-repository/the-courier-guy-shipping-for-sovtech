<?php

$pluginpath = plugin_dir_path(__DIR__);

require_once CSM_DIR.'/helper/class-customhelper.php';
require_once plugin_dir_path(__DIR__) . 'Core/SovtechPayload.php';

/**
 * @author The Courier Guy
 * @package tcg/Core
 * @version 1.0.0
 */

class TCGS_Plugin extends CustomPlugin
{
    public const TCGS_SWAGGER_SECRET_KEY    = 'tcgs_swagger_secret_key';
    public const TCGS_SWAGGER_TOKEN         = 'tcgs_swagger_token';
    public const TCGS_LOGGING               = 'tcgs_logging';
    public const TCGS_TRACKING_REFERENCE    = 'tcgs_tracking_reference';
    /**
     * @var WC_Logger
     */

    /**
     * TCG_Plugin constructor.
     *
     * @param $file
     */
    public function __construct($file)
    {
        parent::__construct($file);
        add_action('woocommerce_shipping_init', [$this, 'shipping_init']);
        add_filter('woocommerce_shipping_methods', [$this, 'shipping_method']);

        add_action('admin_enqueue_scripts', [$this, 'registerJavascriptResources']);
        add_action('wp_enqueue_scripts', [$this, 'registerJavascriptResources']);
        add_action('wp_enqueue_scripts', [$this, 'localizeJSVariables']);
        add_action('admin_enqueue_scripts', [$this, 'localizeJSVariables']);
        add_action('login_enqueue_scripts', [$this, 'localizeJSVariables']);
        
        add_action('woocommerce_checkout_update_order_review', [$this, 'updateShippingPropertiesFromCheckout']);

        
        add_filter('woocommerce_checkout_fields', [$this, 'addIihtcgFields'], 10, 1);
        add_filter('woocommerce_checkout_fields', [$this, 'overrideAddressFields'], 999, 1);

        //new added
        add_filter('woocommerce_form_field_tcg_place_lookup', [$this, 'getSuburbFormFieldMarkUp'], 1, 4);
        add_action('wp_ajax_submit_collection_from_listing_page', [$this, 'setCollectionFromOrderListingPage']);
        add_action('admin_post_print_waybill', [$this, 'printWaybillFromOrder'], 10, 0);


        add_action('woocommerce_order_actions', [$this, 'addSendCollectionActionToOrderMetaBox'], 10, 1);

        //new added
        add_action(
            'manage_shop_order_posts_custom_column',
            [$this, 'collectActionAndPrintWaybillOnOrderlistContent'],
            20,
            2
        );


        //new added
        add_action('woocommerce_order_actions', [$this, 'addPrintWayBillActionToOrderMetaBox'], 10, 1);
        add_filter('manage_edit-shop_order_columns', [$this, 'addCollectionActionAndPrintWaybillToOrderList'], 20);
        add_action('woocommerce_order_action_tcg_print_waybill', [$this, 'redirectToPrintWaybillUrl'], 10, 1);

        //new added
        add_action('admin_head', [$this, 'addCustomAdimCssForOrderList']);
        add_action('admin_head', [$this, 'addCustomJavascriptForOrderList']);
        add_filter('woocommerce_admin_shipping_fields', [$this, 'addShippingMetaToOrder'], 10, 1);

        

        add_action('woocommerce_order_action_tcg_send_collection', [$this, 'createShipmentFromOrder'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'getTrackingNo'], 111, 1);
        add_action('woocommerce_order_status_processing', [$this, 'createShipmentOnOrderProcessing'], 10, 1);
        add_action('woocommerce_order_action_tcg_send_collection', [$this, 'getTrackingNo'], 20, 1);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'updateShippingPropertiesOnOrder'], 10, 2);

        //new added
        add_action('woocommerce_shipping_packages', [$this, 'updateShippingPackages'], 20, 1);
        add_action('woocommerce_after_calculate_totals', [$this, 'getCartTotalCost'], 20, 1);

        add_action('woocommerce_checkout_billing', [$this, 'add_shipping_selector']);

        /* Add  Admin Disclaimer notice */
        add_action('admin_notices', [$this, 'addDisclaimer']);

        //new added
        add_action('wp_ajax_dismissed_notice_handler', [$this, 'ajax_notice_handler']);
        add_filter('thecourierguyshippingsovtech_flyer_fits_filter', [$this, 'flyer_fits_flyer_filter'], 10, 3);

        //new added
        add_action('wc_ajax_update_order_review', [$this, 'test_ajax']);

        add_action(
            'woocommerce_review_order_before_order_total',
            [$this, 'shipLogicRateOptins'],
            10,
            2
        );

        add_action(
            'woocommerce_review_order_before_order_total',
            [$this, 'getselectedOptions'],
            20,
            2
        );
    }

    /**
     * Remove flyer options if parcel does not fit into flyer package
     *
     * @param $result
     * @param $payload
     *
     * @return mixed
     */
    public
    function flyer_fits_flyer_filter(
        $result,
        $payload
    ) {
        $nonFlyer = ['LSF', 'LOF', 'NFS',];
        if ( ! $payload['contents']['fitsFlyer']) {
            foreach ($result as $j => $item) {
                foreach ($item['rates'] as $k => $rate) {
                    if (in_array($rate['service'], $nonFlyer)) {
                        unset($result[$j]['rates'][$k]);
                    }
                }
                $result[$j]['rates'] = array_values($result[$j]['rates']);
            }
            $result = array_values($result);
        }

        return $result;
    }

    function shipping_init() {
        require_once($this->getPluginPath().'/module-admin/init-zones.php');
        require_once($this->getPluginPath().'/module-admin/init-main.php');
    }

    function shipping_method($methods) {
        $methods['sovtech_tcg'] = 'CSM_Shipping_Method';
        $methods['sovtech_tcg_zone'] = 'CSM_Zones_Method';        
        return $methods;
    }

    /**
     *
     */
    protected
    function registerModel()
    {
        require_once $this->getPluginPath() . '/model/Product.php';
    }

    /**
     * @param string $postData
     */
    private function checkIfQuoteIsEmpty()
    {
        $shippingMethodSettings = $this->getShippingMethodSettings();

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        $message = '{"result":"failure","messages":"<ul class=\"woocommerce-error\" role=\"alert\">\n\t\t\t<li data-id=\"billing_tcg_place_lookup\">\n\t\t\t<strong>Failed to get shipping rates, please try another address<\/strong>';
        if ($wc_session = WC()->session) {
            $tcg_prohibited_vendor = $wc_session->get('tcg_prohibited_vendor');
            if ($tcg_prohibited_vendor != '') {
                $message = "Please note you have a product that can not be shipped by The Courier Guy in your cart.";
            }
        }
        $message .= '.\t\t<\/li>\n\t<\/ul>\n","refresh":true,"reload":false}';

        // Abort the order and return to the checkout page
        echo wp_kses_post($message);
        exit;
    }

    /**
     *
     */
    private
    function clearShippingCustomProperties()
    {

        if ($wc_session = WC()->session) {

            $customSettings = $wc_session->get('custom_properties');

            foreach ($customSettings as $customSetting) {
                $wc_session->set($customSetting, '');
            }
        }

    }

    /**
*
*/
public
function localizeJSVariables()
{
    //@todo The contents of this method is legacy code from an older version of the plugin, however slightly refactored.
    $southAfricaOnly = false;
    $shippingMethodSettings = $this->getShippingMethodSettings();
    if ( ! empty($shippingMethodSettings) && ! empty($shippingMethodSettings['south_africa_only']) && $shippingMethodSettings['south_africa_only'] == 'yes') {
        $southAfricaOnly = true;
    }
    $translation_array = [
        'url' => get_admin_url(null, 'admin-ajax.php'),
        'southAfricaOnly' => ($southAfricaOnly) ? 'true' : 'false',
    ];
    wp_localize_script($this->getPluginTextDomain() . '-main.js', 'theCourierGuyShippingSovtech', $translation_array);
}

    /**
     *
     */
    public
    function registerJavascriptResources()
    {
        $this->registerJavascriptResource('main.js', ['jquery']);
        $settings = $this->getShippingMethodSettings();
    }

    /**
     * Admin Disclaimer notice on Activation.
     */
    function addDisclaimer()
    {
        if ( ! get_option('dismissed-tcg_disclaimer', false)) { ?>
            <div class="updated notice notice-the-courier-guy is-dismissible" data-notice="tcg_disclaimer">
                <p><strong>The Courier Guy Shipping Sovtech</strong></p>
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
     * Add shipping selector
     */
    public function add_shipping_selector()
    {
        $settings = $this->getShippingMethodSettings();
        if (isset($settings['enablemethodbox']) && $settings['enablemethodbox'] === 'yes') {
         $output = '
         <div class="form-row form-row-wide" id="iihtcg_selector" style="border: 1px solid lightgrey; padding:5px;">
         <h3 style="margin-top: 0;">Shipping/Collection</h3>
         <h5 id="please_select" style="color: #ff0000;">Please select an option to proceed</h5>
         <h5 id="please_complete" style="color: red" hidden>Please complete all required fields to proceed</h5>
         <span class="woocommerce-input-wrapper">
         <span id="couriertome"><input type="radio" name="iihtcg_selector_input" id="iihtcg_selector_input_tcg" value="tcg"><span style="display:inline;margin-left 5px !important;"><label for="iihtcg_selector_input_tcg"><strong>Courier my order to me</strong></label></span></span>
         <span style="float: right;" id="collectorder"><input type="radio" name="iihtcg_selector_input" id="iihtcg_selector_input_collect" value="collect"><span style="display:inline;"><label for="iihtcg_selector_input_collect"><strong>I will collect my order</strong></label></span></span>
         </span>
         </div>';
         echo wp_kses( $output, array( 
            'div' => array(
                'class' => array(),
                'id' => array(),
                'style' => array()
            ),
            'h3' => array(
                'style' => array()
            ),
            'h5' => array(
                'id' => array(),
                'style' => array()
            ),
            'span' => array(
                'id' => array(),
                'style' => array(),
                'class' => array()
            ),
            'input' => array(
                'type' => array(),
                'name' => array(),
                'id' => array(),
                'value' => array()
            ),
            'label' => array(
                'for' => array()
            ),
            'strong' => array()
        ) );
     }
 }

 public function shipLogicRateOptins()
 {

    if ($wc_session = WC()->session) {

        $shipping = $wc_session->get('shipping_options');
        $post_data = array();
        if ( sanitize_text_field(isset(  $_POST['post_data']  ) ) ) {
            sanitize_text_field(parse_str($_POST['post_data'], $post_data));
        }
        $array = wc_string_to_array( $post_data, '&' );
        $rates               = $wc_session->get(CSM_Zones_Method::TCG_SHIP_LOGIC_RESULT);

        if ( ! isset($rates)) {
            return false;
        }


            //$disable_specific_options = json_decode($wc_session->get('disable_specific_shipping_options'));
        $settings = $this->getShippingMethodSettings();
        $disable_specific_options = $settings['disable_specific_shipping_options'];
        if ($disable_specific_options == null) {
            $disable_specific_options = array();
            $count                    = 0;
        } else {
            $count = count($disable_specific_options);
        }
        $server = $settings['connection_server'];
        if ( 'production' === $server ) {
          $api_url = $settings['prod_url'];
          $api_key = $settings['prod_apikey'];
      } else {
          $api_url = $settings['stage_url'];
          $api_key = $settings['stage_apikey'];
      }

      $helper_class = new Customhelper();

      if(isset($api_url)){
        $total_shipping_codes       = $helper_class->get_shipping_codes( $api_url ); 
        $this->total_shipping_codes = $total_shipping_codes;  

        $url                        = $api_url . '/shipping-options?token=' . $api_key;
        $api_key                    = $settings['maps_apikey'];
    }

    $store_address      = $settings["shop_street_and_name"];
    $store_address_2    = $settings["shop_suburb"];
    $store_address_3    = $settings["shop_state"];
    $store_city         = $settings["shop_city"];
    $store_postcode     = $settings["shop_postal_code"];
    $store_raw_country  = $settings["shop_country"];
    $store_email        = $settings["shop_email"];
    $store_name         = $settings["company_name"];
    $store_phone        = $settings["shop_phone"];
    $store_address_type = $settings["address_type"];

    $customerAddr = WC()->customer->get_changes();
    $address =  ! empty( $customerAddr['shipping']['address_1'] ) ? $customerAddr['billing']['address_1'] : '12 drury ln';
    $postCode =  ! empty( $customerAddr['shipping']['postcode'] ) ? $customerAddr['billing']['postcode'] : '8001';
    $city =  ! empty( $customerAddr['shipping']['city'] ) ? $customerAddr['billing']['city'] : 'Cape town';
    $province =  ! empty( $customerAddr['shipping']['country'] ) ? $customerAddr['billing']['country'] : '';
    $state =  ! empty( $customerAddr['shipping']['state'] ) ? $customerAddr['billing']['state']: '';

        // Created address string for Google maps API.
    $toaddress    = $address . '+' . $state . '+' . $city . '+' . $postCode . '+' . $province;
    $tolat_long   = $helper_class->get_lat_long( $toaddress, $api_key );

    $fromaddress  = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
    $fromlat_long = $helper_class->get_lat_long( $fromaddress, $api_key );

        // created an array for the TCG API request body.
    $shipping_option_request = array(
        'locationTo'   => array(
            'type'         => 'BUSINESS',
            'address'      => $address,
            'building'     => "",
            'suburb'       => $state,
            'phone'        => '',
            'email'        => '',
            'city'         => $city,
            'postCode'     => $postCode,
            'province'     => $province,
            'coordinates'  => '{"lat":' . $tolat_long['lat'] . ',"lng":' . $tolat_long['lng'] . '}',
            'businessName' => '',
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
            'coordinates'  => '{"lat":' . $fromlat_long['lat'] . ',"lng":' . $fromlat_long['lng'] . '}',
            'businessName' => ! empty( $store_name ) ? $store_name : 'SovTech',
        ),
    );

        // Converted array to JSON.
    $encoded_shipping_opt_request = wp_json_encode( $shipping_option_request, true );
    $avail_shipping_options     = $helper_class->get_available_shipping_options($url, $encoded_shipping_opt_request);
    $wc_session = WC()->session;
    $wc_session->set('all_shipping_options', $avail_shipping_options);

    if ( ! empty($disable_specific_options && isset($disable_specific_options)) && ($count > 0)) {
        $html       = '<tr><th>Shipping Options</th><td><ul>';
        $optinRates = $avail_shipping_options;
        if ( ! empty($avail_shipping_options)) {
            foreach ($avail_shipping_options as $key => $optin_rate) {
                $optinid = $optin_rate->id;
                if(array_key_exists("tcg_ship_logic_optins", $array))
                {
                   $tcg_ship_logic_optins_data = $array['tcg_ship_logic_optins'];
               }
                   // $tcg_ship_logic_optin_chosen = in_array($data['tcg_ship_logic_optins'], $avail_shipping_options[$key]->id);
               $tcg_ship_logic_optin_chosen = in_array($optinid, $tcg_ship_logic_optins_data);
               if (in_array($optinid, $disable_specific_options)) {
                $price                       = wc_price($optin_rate->fee);
                $html                        .= "
                <li class='update_totals_on_change'><input type='checkbox' value='$optin_rate->id' name='tcg_ship_logic_optins[$key]' class='shipping-method update_totals_on_change'";
                if ($tcg_ship_logic_optin_chosen == 1) {
                    $html .= ' checked';
                }
                $html .= ">
                <label>$optin_rate->type
                <span>$price
                </span>
                </label>
                </li>";
            }
        }
    }

    $html .= '</ul></td>';


    echo wp_kses( $html, array( 
            'li' => array(
                'class' => array()
            ),
            'tr' => array(),
            'th' => array(),
            'td' => array(),
            'ul' => array(),
            'span' => array(),
            'input' => array(
                'type' => array(),
                'name' => array(),
                'class' => array(),
                'value' => array()
            ),
            'label' => array()
        ) );
}
}
}

public function getselectedOptions()
{
    if ($wc_session = WC()->session) {
        $post_data = array();
        if ( sanitize_text_field( isset(  $_POST['post_data']  ) ) ) {
            sanitize_text_field( parse_str($_POST['post_data'], $post_data) );
        }
        return $data['tcg_ship_logic_optins'][0];
    }
}

    /**
     * @param WC_Order $order
     */
    private
    function createShipment(
        WC_Order $order
    ) {
        $logger = wc_get_logger();
        $wc_session = WC()->session;
        $selectedShipping = array();
        if ($wc_session && sanitize_text_field( $_POST['tcg_ship_logic_optins']) ) {
            $totalShippingOptions = $wc_session->get('all_shipping_options');
            foreach ($totalShippingOptions as $key => $option) {
                if(sanitize_text_field( $_POST['tcg_ship_logic_optins'][$key] ) == $option->id)
                {
                  array_push($selectedShipping, $option);
              }
          }
      }
      if ($this->hasTcgShippingMethod($order)) {
            //Returns settings given at admin level
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        $method_data = $data = $shippingMethodParameters;
        $total_shipping_codes = array();

        if(isset($data)){
          $server = $data['connection_server'];

          if ( 'production' === $server ) {
              $api_url = $method_data['prod_url'];
              $api_key = $method_data['prod_apikey'];
          } else {
              $api_url = $method_data['stage_url'];
              $api_key = $method_data['stage_apikey'];
          }

          $helper_class = new Customhelper();

          if(isset($api_url)){
            $total_shipping_codes       = $helper_class->get_shipping_codes( $api_url ); 
        }
    }


    $orderId          = $order->get_id();
    $getRatesResult   = get_post_meta($orderId, CSM_Zones_Method::TCG_SHIP_LOGIC_RESULT, true);

    $products = array();
            // Get and Loop Over Order Items.
    $parcel = array();
    $parcel_arr = array();
    foreach ( $order->get_items() as $item_id => $item ) {
      $product = $item->get_product();
      $parcel['quantity'] = $item->get_quantity();
      array_push( $products, $product );
  }
  foreach ($order->get_items('shipping') as $item_id => $item) {
    $shipping_title = $item['name'];

    $shipping_data = explode(":",$item["method_id"]);
    if(count($shipping_data) > 0){
        $method_id = $shipping_data[0];
        $shipping_title = $shipping_data[1];
        $rateId =  $shipping_data[2];
        $instance_id = $shipping_data[3];
    }
}
$ratesArr = json_decode($getRatesResult);
$service = '';

foreach($total_shipping_codes as $key => $value) {
    if($shipping_title == $value){
        $service = $key;
    }
}

$url = $api_url . '/submit-collection?token=' . $api_key;

$store_address      = $method_data["shop_street_and_name"];
$store_address_2    = $method_data["shop_suburb"];
$store_address_3    = $method_data["shop_state"];
$store_city         = $method_data["shop_city"];
$store_postcode     = $method_data["shop_postal_code"];
$store_raw_country  = $method_data["shop_country"];
$store_email        = $method_data["shop_email"];
$store_name         = $method_data["company_name"];
$store_phone        = $method_data["shop_phone"];
$store_address_type = $method_data["address_type"];
$logginSetting = $method_data["usemonolog"];

            // get meta data.
$meta_data = $order->get_meta_data();

$getParcelBody     = get_post_meta($orderId, CSM_Zones_Method::TCGS_SHIP_LOGIC_PARCEL, true);
$insuranceCost     = get_post_meta($orderId, "insuranceCost", true);
foreach ($getParcelBody as $key => $parcelArray) {
  $parcel['parcelWidth'] = $parcelArray['parcelWidth'];
  $parcel['parcelHeight'] = $parcelArray['parcelHeight'];
  $parcel['parcelLength'] = $parcelArray['parcelLength'];
  $parcel['parcelWeight'] = $parcelArray['parcelWeight'];
  $parcel['quantity']     = $parcelArray['quantity'];
  $parcel['parcelDescription'] = $parcelArray['parcelDescription'];
  $parcel_arr[] = $parcel;
}

$wholesaleValue = 0;
if(! empty($insuranceCost) && $insuranceCost != 0)
{
    $wholesaleValue = intval($insuranceCost);
}

            // Google maps now requires an API key.
$maps_api_key = $method_data['maps_apikey'];

            // Created address string for Google maps API.
$toaddress = $order->get_shipping_address_1() . '+' . $order->get_shipping_address_2() . '+' . $order->get_shipping_city() . '+' . $order->get_shipping_postcode() . '+' . $order->get_shipping_state();
$tolat_long = $helper_class->get_lat_long( $toaddress, $maps_api_key );

$fromaddress = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
$fromlat_long = $helper_class->get_lat_long( $fromaddress, $maps_api_key );

            // split store country and store state.
$store_country_statearr = explode( ':', $store_raw_country );

            // Get Countries.
$cntry = WC()->countries;

            // get full form of province using country and state code.
$from_provincearr = explode( ' ', $cntry->get_states( $store_country_statearr[0] )[ $store_address_3 ] );
$to_provincearr = explode( ' ', $cntry->get_states( $order->get_shipping_country() )[ $order->get_shipping_state() ] );

            // Converted province in upper case and relaced space from province with underscore.
$from_province = strtoupper( implode( '_', $from_provincearr ) );
$to_province = strtoupper( implode( '_', $to_provincearr ) );

            // get address type.
$addresstype = $meta_data[0]->get_data()['value'];

            // get Order created date.
$date = $order->get_date_created();

            // created an array for the TCG API request body.
$shipping_rate = array(
  'input' => array(
      'service' => $service,
      'parcels' => $parcel_arr,
      'locationFrom' => array(
        'type' => ! empty( $store_address_type ) ? $store_address_type : 'BUSINESS',
        'address' => ! empty( $store_address ) ? $store_address : '12 drury ln',
        'building' => ! empty( $store_address_2 ) ? $store_address_2 : 'Roeland Square',
        'suburb' => $store_address_3,
        'city' => ! empty( $store_city ) ? $store_city : 'Cape town',
        'postCode' => ! empty( $store_postcode ) ? $store_postcode : '8001',
        'province' => $from_province,
        'coordinates' => '{"lat":' . $tolat_long['lat'] . ',"lng":' . $tolat_long['lng'] . '}',
        'businessName' => ! empty( $store_name ) ? $store_name : 'SovTech',
    ),
      'locationTo' => array(
          'type' => $addresstype,
          'address' => $order->get_shipping_address_1(),
          'building' => ! empty( $order->get_shipping_address_2() ) ? $order->get_shipping_address_2() : 'cape town',
          'suburb' => $store_address_3,
          'city' => $order->get_shipping_city(),
          'postCode' => $order->get_shipping_postcode(),
          'province' => $to_province,
          'coordinates' => '{"lat":' . $fromlat_long['lat'] . ',"lng":' . $fromlat_long['lng'] . '}',
          'businessName' => ! empty( $order->get_billing_company() ) ? $order->get_billing_company() : 'test',
      ),
      'wholesaleValue' => $wholesaleValue,
      'shippingOptions' => $selectedShipping,
      'customCollectionDate' => $date->date_i18n(),
      'collectionReadyTime' => $date->date_i18n( 'H:i:s' ),
      'collectionCloseTime' => $date->date_i18n( 'H:i:s' ),
      'sender' => array(
        'phoneNumber' => ! empty( $store_phone ) ? $store_phone : '9874563210',
        'firstName' => ! empty( $store_name ) ? $store_name : 'SovTech',
        'lastName' => ! empty( $store_name ) ? $store_name : 'SovTech',
        'email' => ! empty( $store_email ) ? $store_email : 'test@gmail.com',
        'alternativeContactNumber' => '',
        'instructions' => '',
    ),
      'recipient' => array(
        'phoneNumber' => $order->get_billing_phone(),
        'firstName' => $order->get_billing_first_name(),
        'lastName' => $order->get_billing_last_name(),
        'email' => $order->get_billing_email(),
        'alternativeContactNumber' => '',
        'instructions' => '',
    ),
  ),
);

          // converted array to JSON.
$encodedshipping_rate = wp_json_encode( $shipping_rate, true );
          // CURL Request for TCG API Submit Collection.
$helper_class = new Customhelper();


try {
    $reference_id = $helper_class->create_tcg_order( $url, $encodedshipping_rate );

    $tracking_no = $helper_class->get_tracking_number( $api_url,$reference_id,$api_key );

    update_post_meta($orderId, 'swagger_order_id', $reference_id);

    update_post_meta(
        $orderId,
        self::TCGS_TRACKING_REFERENCE,
        $reference_id
    );
    $order->add_order_note('TCGS Sovtech Order Id: ' . $reference_id);
    $order->save();

    if("yes" == $logginSetting)
    {
        $logger->info( 'Order Created with Reference Id: ' . $reference_id );
    }

} catch (Exception $exception) {
    if("yes" == $logginSetting)
    {
        $logger->info( 'TCGS Order Not Created: ' . $exception->getMessage() );
    }
    $order->add_order_note('TCGS Order Not Created: ' . $exception->getMessage());
    $order->save();
}
}
}

    /**
     * @param array $actions
     *
     * @return mixed
     */
    public
    function addSendCollectionActionToOrderMetaBox(
        $actions
    ) {
        $orderId           = sanitize_text_field($_GET['post']);
        $order             = wc_get_order($orderId);
        $hasShippingMethod = $this->hasTcgShippingMethod($order);
        $waybill           = get_post_meta($orderId, self::TCGS_TRACKING_REFERENCE, true);
        if ($hasShippingMethod && $waybill === '') {
            $actions['tcg_send_collection'] = __('Send Order to Courier Guy Sovtech', 'woocommerce');
        }

        return $actions;
    }

    private
    function hasTcgShippingMethod(
        $order
    ) {

        $result = false;
        if ( ! empty($order)) {

            $shipping_data = json_decode(get_post_meta($order->get_id(), '_order_shipping_data', true), true);
            //$shipping_data   = get_post_meta($order->get_id(), '', true);
            if (is_array($shipping_data)) {
                array_walk(
                    $shipping_data,
                    function ($shippingItem) use (&$result) {
                        if (is_string($shippingItem) && strstr($shippingItem, 'sovtech_tcg_zone')) {
                            $result = true;
                        }
                    }
                );
            }
        }

        return $result;
    }

    /**
     * @param int $orderId
     * @param array $data
     */
    public function updateShippingPropertiesOnOrder($orderId, $data)
    {

        if ($wc_session = WC()->session) {
            $order = new WC_Order($orderId);

            $getRatesBody = $wc_session->get(CSM_Zones_Method::TCGS_SHIP_LOGIC_PARCEL);
            if ($getRatesBody) {
                update_post_meta($orderId, CSM_Zones_Method::TCGS_SHIP_LOGIC_PARCEL, $getRatesBody);
            }

            $insuranceCost = $wc_session->get("insuranceCost");
            if ($insuranceCost) {
                update_post_meta($orderId,"insuranceCost", $insuranceCost);
            }

            $getRatesResult = $wc_session->get(CSM_Zones_Method::TCG_SHIP_LOGIC_RESULT);

            if ($getRatesResult) {
                update_post_meta($orderId, CSM_Zones_Method::TCG_SHIP_LOGIC_RESULT, $getRatesResult);
            } else {
                if (sanitize_text_field( isset($_POST['iihtcg_selector_input']))) {
                    if ( sanitize_text_field($_POST['iihtcg_selector_input']) == 'tcg') {
                        $this->checkIfQuoteIsEmpty();
                    }
                } else {
                    $this->checkIfQuoteIsEmpty();
                }
            }

            $shippingMethods = $data['shipping_method'] ?? null;



            update_post_meta($orderId, '_order_shipping_data', json_encode($shippingMethods));

            $order->calculate_totals();

            $order->save();
            $order->add_order_note('Order shipping total on order: ' . $order->get_shipping_total());

            $order->save();
            $order->save_meta_data();

            $this->clearShippingCustomProperties();
            $wc_session->set('customer_cart_subtotal', '');
        }
    }

    /**
     * @param WC_Order $orderwoocommerce_my_account_my_orders_actions
     */
    public function createShipmentFromOrder($order)
    {
        $this->createShipment($order);
    }

    /**
     * @param WC_Order get_Sovtech_tracking_no
     */
    public function getTrackingNo($orderId)
    {


        $order = new WC_Order( $orderId );
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        $method_data = $data = $shippingMethodParameters;
        if(isset($data)){
            $server = $data['connection_server'];
            if ( 'production' === $server ) {
              $api_url = $method_data['prod_url'];
              $api_key = $method_data['prod_apikey'];
          } else {
              $api_url = $method_data['stage_url'];
              $api_key = $method_data['stage_apikey'];
          }
          $reference_id           = get_post_meta($order->get_id(), "swagger_order_id", true);
          $helper_class = new Customhelper();

          $tracking_no = $helper_class->get_tracking_number( $api_url,$reference_id,$api_key );
          sleep(30);
          $trackingData = json_decode($tracking_no);
          if(empty($trackingData->trackingNumber))
          {
            $tracking_no = $helper_class->get_tracking_number( $api_url,$reference_id,$api_key );
            $trackingData = json_decode($tracking_no);

        }
        $order->add_order_note('TCGS Tracking Reference: ' . $trackingData->trackingNumber);
        $order->save();
    }
}

    /**
     * @param int $orderId
     */
    public function createShipmentOnOrderProcessing($orderId)
    {

        $order                    = new WC_Order($orderId);
        
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        
        if ($this->hasTcgShippingMethod(
            $order
        ) && $shippingMethodParameters['automatically_submit_collection_order'] === 'yes') {

            $this->createShipment($order);
        }
    }

    private function getShippingMethodParameters(WC_Order $order): array
    {

        if ($this->hasTcgShippingMethod($order)) {

            return $this->getShippingMethodSettings();
        }

        return [];
    }

    /**
     * @param string $postData
     */
    public function updateShippingPropertiesFromCheckout($postData)
    {
        if ($wc_session = WC()->session) {
            parse_str($postData, $parameters);

            $addressPrefix = 'shipping_';
            if ( ! isset($parameters['ship_to_different_address']) || $parameters['ship_to_different_address'] != true) {
                $addressPrefix = 'billing_';
            }
            $insurance = false;
            if ( ! empty($parameters[$addressPrefix . 'insurance']) && $parameters[$addressPrefix . 'insurance'] == '1') {
                $insurance = true;
            }
            $shippingMethods  = isset($parameters['shipping_method']) ? $parameters['shipping_method'] : WC()->shipping->get_shipping_methods();


            $customProperties = [
                'tcgs_insurance' => $insurance,
            ];


            if (is_array($shippingMethods)) {
                foreach ($shippingMethods as $vendorId => $shippingMethod) {
                    if ($vendorId === 0) {
                        $customProperties['tcgs_shipping_method'] = $shippingMethod;
                        $qn                                      = json_encode(
                            $wc_session->get('tcgs_quote_response')
                        );
                        if ($qn == 'null' || strlen($qn) < 3) {
                            $qn = $wc_session->get('tcgs_response');
                        }
                        if (isset($qn) && strlen($qn) > 2) {
                            $quote = json_decode($qn, true);
                            if (isset($quote[0])) {
                                $customProperties['tcgs_quoteno'] = $quote[0]['quoteno'];
                                $shippingService                 = explode(':', $shippingMethod)[1];
                                $rates                           = $quote[0]['rates'];
                                foreach ($rates as $service) {
                                    if ($shippingService === $service['service']) {
                                        $customProperties['shippingCartage'] = $service['subtotal'];
                                        $customProperties['shippingVat']     = $service['vat'];
                                        $customProperties['shippingTotal']   = $service['total'];
                                    }
                                }
                            }
                        }
                    } else {
                        $customProperties['tcgs_shipping_method_' . $vendorId] = $shippingMethod;
                        $vendorId                                             = $vendorId === 0 ? '' : $vendorId;
                        $qn                                                   = json_encode(
                            $wc_session->get('tcgs_quote_response' . $vendorId)
                        );
                        if ($qn == 'null' || strlen($qn) < 3) {
                            $qn = $wc_session->get('tcgs_response' . $vendorId);
                        }
                        if (isset($qn) && strlen($qn) > 2) {
                            $quote = json_decode($qn, true);
                            if (isset($quote[0])) {
                                $customProperties['tcgs_quoteno_' . $vendorId] = $quote[0]['quoteno'];
                                $shippingService                              = explode(':', $shippingMethod)[1];
                                $rates                                        = $quote[0]['rates'];
                                foreach ($rates as $service) {
                                    if ($shippingService === $service['service']) {
                                        $customProperties['shippingCartage_' . $vendorId] = $service['subtotal'];
                                        $customProperties['shippingVat_' . $vendorId]     = $service['vat'];
                                        $customProperties['shippingTotal_' . $vendorId]   = $service['total'];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->setShippingCustomProperties($customProperties);
            $this->removeCachedShippingPackages();
        }
    }
        /**
     * @param array $customProperties
     */
        private
        function setShippingCustomProperties(
            $customProperties
        ) {
            if ($wc_session = WC()->session) {
                $properties = [];
                foreach ($customProperties as $key => $customProperty) {
                    $properties[] = $key;
                    $wc_session->set($key, filter_var($customProperty, FILTER_SANITIZE_STRING));
                }
                $wc_session->set('custom_properties', $properties);
            }
        }


    /**
     *
     */
    public
    function removeCachedShippingPackages()
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        $packages = WC()->cart->get_shipping_packages();
        if ($wc_session = WC()->session) {
            foreach ($packages as $key => $value) {
                $shipping_session = "shipping_for_package_$key";
                $wc_session->set($shipping_session, '');
            }
        }
        $wc_session->set('tcgs_prohibited_vendor', '');
        $this->updateCachedQuoteRequest([], '');
        $this->updateCachedQuoteResponse([], '');
    }

    /**
     * @param array $quoteParams
     * @param $vendorId - '' if multivendor not enabled
     */
    private
    function updateCachedQuoteRequest(
        $quoteParams,
        $vendorId
    ) {
        // Current timestamp
        $ts = time();

        $vendorId = $vendorId === 0 ? '' : $vendorId;

        if ($wc_session = WC()->session) {
            $wc_session->set(
                'tcg_quote_request' . $vendorId,
                hash('md5', json_encode($quoteParams) . $vendorId) . '||' . $ts
            );
            $wc_session->set(
                'tcg_request' . $vendorId,
                hash(
                    'md5',
                    json_encode($quoteParams) . $vendorId . $ts
                )
            );
        }
    }

    /**
     * @param $quoteResponse
     * @param $vendorId
     */
    private
    function updateCachedQuoteResponse(
        $quoteResponse,
        $vendorId
    ) {
        $ts = time();

        if (count($quoteResponse) > 0) {
            $quoteResponse['ts'] = $ts;
        }

        $vendorId = $vendorId === 0 ? '' : $vendorId;

        if ($wc_session = WC()->session) {
            $wc_session->set(
                'tcgs_response' . $vendorId,
                json_encode($quoteResponse)
            );
            $wc_session->set(
                'tcgs_response' . $vendorId,
                json_encode($quoteResponse)
            );
            $wc_session->set('tcgs_quote_response' . $vendorId, $quoteResponse);
        }
    }

    public function addIihtcgFields($fields)
    {
        $settings = $this->getShippingMethodSettings();

        if (isset($settings['enablemethodbox']) && $settings['enablemethodbox'] === 'yes') {
            $fields['billing']['iihtcg_method'] = [
                'label'    => 'iihtcg_method',
                'type'     => 'text',
                'required' => true,
                'default'  => 'none',
            ];
        }

        return $fields;
    }

    /**
     * @param $field
     * @param $key
     * @param $args
     * @param $value
     *
     * @return string
     */
    public
    function getSuburbFormFieldMarkUp(
        $field,
        $key,
        $args,
        $value
    ) {
        //@todo The contents of this method is legacy code from an older version of the plugin.
        if ($args['required']) {
            $args['class'][] = 'validate-required';
            $required        = ' <abbr class="required" title="' . esc_attr__(
                'required',
                'woocommerce'
            ) . '">*</abbr>';
        } else {
            $required = '';
        }
        $options                  = $field = '';
        $label_id                 = $args['id'];
        $sort                     = $args['priority'] ? $args['priority'] : '';
        $field_container          = '<p class="form-row %1$s" id="%2$s" data-sort="' . esc_attr($sort) . '">%3$s</p>';
        $customShippingProperties = $this->getShippingCustomProperties();
        $option_key               = isset($customShippingProperties['tcg_place_id']) ? $customShippingProperties['tcg_place_id'] : '';
        $option_text              = isset($customShippingProperties['tcg_place_label']) ? $customShippingProperties['tcg_place_label'] : '';
        $options                  .= '<option value="' . esc_attr($option_key) . '" ' . selected(
            $value,
            $option_key,
            false
        ) . '>' . esc_attr($option_text) . '</option>';
        $field                    .= '<input type="hidden" name="' . esc_attr(
            $key
        ) . '_place_id" value="' . $option_key . '"/>';
        $field                    .= '<input type="hidden" name="' . esc_attr(
            $key
        ) . '_place_label" value="' . $option_text . '"/>';
        $field                    .= '<select id="' . esc_attr($args['id']) . '" name="' . esc_attr(
            $args['id']
        ) . '" class="select ' . esc_attr(
         implode(' ', $args['input_class'])
         ) . '" ' . ' data-placeholder="' . esc_attr($args['placeholder']) . '">
        ' . $options . '
        </select>';
        if ( ! empty($field)) {
            $field_html = '';
            if ($args['label'] && 'checkbox' != $args['type']) {
                $field_html .= '<label for="' . esc_attr($label_id) . '" class="' . esc_attr(
                    implode(' ', $args['label_class'])
                ) . '">' . $args['label'] . $required . '</label>';
            }
            $field_html .= $field;
            if ($args['description']) {
                $field_html .= '<span class="description">' . esc_html($args['description']) . '</span>';
            }
            $container_class = esc_attr(implode(' ', $args['class']));
            $container_id    = esc_attr($args['id']) . '_field';
            $field           = sprintf($field_container, $container_class, $container_id, $field_html);
        }

        return $field;
    }

    /**
     * @return array
     */
    public function getShippingCustomProperties($order = null)
    {
        $result = [];
        if ($wc_session = WC()->session) {
            $customProperties = $wc_session->get('custom_properties');
            if ($customProperties && is_array($customProperties)) {
                foreach ($customProperties as $customProperty) {
                    $result[$customProperty] = $wc_session->get($customProperty);
                }
            }
        }

        return $result;
    }

    /**
     * @param array $actions
     *
     * @return mixed
     */
    public
    function addPrintWayBillActionToOrderMetaBox(
        $actions
    ) {
        $orderId           = sanitize_text_field($_GET['post']);
        $order             = wc_get_order($orderId);
        $hasShippingMethod = $this->hasTcgShippingMethod($order);
        $waybill           = get_post_meta($orderId, self::TCGS_TRACKING_REFERENCE, true);
        if ($hasShippingMethod && $waybill !== '') {
            $actions['tcg_print_waybill'] = __('Print Waybill', 'woocommerce');
        }

        return $actions;
    }

    /**
     * @param array $columns
     *
     * @return mixed
     */
    public
    function addCollectionActionAndPrintWaybillToOrderList(
        $columns
    ) {
        $reordered_columns = [];
        foreach ($columns as $key => $column) {
            $reordered_columns[$key] = $column;
            if ($key == 'order_status') {
                $reordered_columns['courier_guy_shipping_sovtech'] = __('Courier Guy Shipping Sovtech', 'theme_domain');
            }
        }

        return $reordered_columns;
    }

    public
    function collectActionAndPrintWaybillOnOrderlistContent(
        $column,
        $post_id
    ) {
        if ($column == "courier_guy_shipping_sovtech") {
            $orderId           = $post_id;
            $order             = wc_get_order($orderId);
            $hasShippingMethod = $this->hasTcgShippingMethod($order);
            $waybill           = get_post_meta($orderId, self::TCGS_TRACKING_REFERENCE, true);

            if ($hasShippingMethod) {
                if ($waybill === '') {
                    ?>
                    <a href="#" tcg_order_id_ol='<?php
                    echo wp_kses_post($orderId); ?>' class='send-tcg-order_order-list' title='Send Order To Courier Guy'><?php
                    echo wc_help_tip("Send Order to Courier Guy Shipping Wordpress"); ?></a>
                    <?php
                } else {
                    ?>
                    <a href='/wp-admin/admin-post.php?action=print_waybill&order_id=<?php
                    echo wp_kses_post($orderId); ?>' class='print-tcg-waybill_order-list' title='Print Waybill'><?php
                    echo wc_help_tip("Print Courier Guy Shipping Sovtech Waybill"); ?></a>
                    <?php
                }
            }
        }
    }

    public
    function setCollectionFromOrderListingPage()
    {
        if (wp_doing_ajax()) {
            $post_id = filter_input(INPUT_POST, 'post_id');

            if (get_post_type($post_id) != "shop_order") {
                echo json_encode(array('success' => false, 'result' => "Invalid order number."));
                exit;
            } else {
                $orderId           = sanitize_text_field($post_id);
                $order             = wc_get_order($orderId);
                $hasShippingMethod = $this->hasTcgShippingMethod($order);
                $waybill           = get_post_meta($orderId, 'dawpro_waybill');

                if ( ! $this->hasTcgShippingMethod($order)) {
                    echo json_encode(
                        array(
                            'success' => false,
                            'result'  => "This order's shipping is method not The Courier Guy Shipping Sovtech."
                        )
                    );
                    exit;
                } elseif ($hasShippingMethod && count($waybill) === 0) {
                    $this->createShipmentFromOrder($order);
                    $this->getTrackingNo($orderId);

                    echo json_encode(
                        array(
                            'success'         => true,
                            'result'          => "Order sent to Courier Guy Shipping Sovtech.",
                            'tcg_order_id_ol' => $orderId
                        )
                    );
                    exit;
                } else {
                    echo json_encode(array('success' => false, 'result' => "Order already sent to Courier Guy Shipping Sovtech."));
                    exit;
                }
            }
        }
    }

    /**
     * Latest API functionality downloads the waybill created by TCG
     * We no longer need to develop the waybill within the plugin
     */
    public function printWaybillFromOrder()
    {
        $orderId          = filter_var($_GET['order_id'], FILTER_SANITIZE_NUMBER_INT);
        $waybillId = get_post_meta($orderId, self::TCGS_TRACKING_REFERENCE, true);
        $helper_class = new Customhelper();
        $order = new WC_Order( $orderId );
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        $method_data = $shippingMethodParameters;
        $server = $method_data['connection_server'];
        if ( 'production' === $server ) {
          $api_url = $method_data['prod_url'];
          $api_key = $method_data['prod_apikey'];
      } else {
          $api_url = $method_data['stage_url'];
          $api_key = $method_data['stage_apikey'];
      }
      if(isset($api_url)){
        $url = $api_url."/shipment-label/?reference=".$waybillId."&token=".$api_key;
        $waybill       = $helper_class->get_waybill( $url ); 
    }            
    wp_redirect($waybill);
}

    /**
     * @param WC_Order $order
     */
    public function redirectToPrintWaybillUrl($order)
    {
        $order_data = $order->get_data();
        $orderId = $order_data['id'];
        $waybillId = get_post_meta($orderId, self::TCGS_TRACKING_REFERENCE, true);
        $helper_class = new Customhelper();
        $shippingMethodParameters = $this->getShippingMethodParameters($order);
        $method_data = $shippingMethodParameters;
        if ( 'production' === $server ) {
          $api_url = $method_data['prod_url'];
          $api_key = $method_data['prod_apikey'];
      } else {
          $api_url = $method_data['stage_url'];
          $api_key = $method_data['stage_apikey'];
      }
      if(isset($api_url)){
        $url = $api_url."/shipment-label/?reference=".$waybillId."&token=".$api_key;
        $waybill       = $helper_class->get_waybill( $url ); 
    }            
    wp_redirect($waybill);
    exit;
}

public
function addCustomJavascriptForOrderList()
{
    ?>
    <script>
      jQuery(function () {
        jQuery(document.body).on('click', '.send-tcg-order_order-list', function (event) {

          event.preventDefault()

          let postId = jQuery(this).attr('tcg_order_id_ol')
          jQuery('<img id=\'' + postId + '_ol_loader\' src=\'data:image/gif;base64,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\'/>').insertAfter(jQuery('[tcg_order_id_ol=\'' + postId + '\']'))
          jQuery.ajax({
            type: 'post',
            url: '/wp-admin/admin-ajax.php',
            data: {
              action: 'submit_collection_from_listing_page',
              post_id: postId,
          },
          success: function (response) {

              response = JSON.parse(response)

              if (!response.success) {
                jQuery('#wpbody-content').prepend('<div class="error"><p>' + response.result + '</p></div>')
                jQuery('html,body').animate({ scrollTop: 0 }, 'slow')
            } else {
                let waybillURL = '/wp-admin/admin-post.php?action=print_waybill&order_id=' + response.tcg_order_id_ol
                let elementId = '[tcg_order_id_ol=\'' + response.tcg_order_id_ol + '\']'
                jQuery(elementId).removeClass('send-tcg-order_order-list').addClass('print-tcg-waybill_order-list').attr('href', waybillURL)
                jQuery(elementId + ' > span.woocommerce-help-tip').attr('data-tip', 'Print Courier Guy Shipping Sovtech Waybill')
                jQuery('#' + response.tcg_order_id_ol + '_ol_loader').remove()

            }
        }

    })

      })
})
</script><?php
}

public
function addCustomAdimCssForOrderList()
{
    ?>
    <style>.print-tcg-waybill_order-list::after {
        font-family: woocommerce !important;
        content: "\e019" !important;
    }

    .send-tcg-order_order-list::after {
        font-family: woocommerce !important;
        content: "\e02d" !important;
    }

    a.print-tcg-waybill_order-list, a.send-tcg-order_order-list {
        font-size: 15px;
    }</style>
    <?php
}

    /**
     * @param array $adminShippingFields
     *
     * @return array
     */
    public function addShippingMetaToOrder($adminShippingFields = [])
    {
        $tcgAdminShippingFields = [
            'insurance' => [
                'label'    => __('Courier Guy Shipping Sovtech Insurance'),
                'class'    => 'wide',
                'show'     => true,
                'readonly' => true,
                'type',
                'checkbox'
            ],
            'area'      => [
                'label'             => __('Courier Guy Sovtech Shipping Area Code'),
                'wrapper_class'     => 'form-field-wide',
                'show'              => true,
                'custom_attributes' => [
                    'disabled' => 'disabled',
                ],
            ],
            'place'     => [
                'label'             => __('Courier Guy Sovtech Shipping Area Description'),
                'wrapper_class'     => 'form-field-wide',
                'show'              => true,
                'custom_attributes' => [
                    'disabled' => 'disabled',
                ],
            ],
        ];

        return array_merge($adminShippingFields, $tcgAdminShippingFields);
    }

    /**
     * @param $rates
     *
     * @return mixed
     */
    public function updateShippingPackages($rates)
    {
        $settings = $this->getShippingMethodSettings();
        $maxRates = [];
        if (isset($settings['multivendor_single_override']) && $settings['multivendor_single_override'] === 'yes') {
            foreach ($rates as $key => $vendor_rate) {
                $maxR = 0;
                foreach ($vendor_rate['rates'] as $k => $r) {
                    if (strpos($k, 'sovtech_tcg_zone') !== false) {
                        $maxR = max($maxR, (float)($r->get_cost() + $r->get_shipping_tax()));
                    }
                }
                $maxRates[] = ['key' => $key, 'val' => $maxR];
            }
        }
        usort(
            $maxRates,
            function ($a, $b) {
                if ($a['val'] === $b['val']) {
                    return 0;
                }

                return $a['val'] > $b['val'] ? -1 : 1;
            }
        );
        $cnt = 0;
        foreach ($maxRates as $maxRate) {
            if ($cnt !== 0) {
                foreach ($rates[$maxRate['key']]['rates'] as $vendor_rate) {
                    $method = $vendor_rate->get_method_id();
                    $label  = $vendor_rate->get_label();
                    if (strpos($method, 'sovtech_tcg_zone') !== false) {
                        $vendor_rate->set_cost(0);
                        $taxes = $vendor_rate->get_taxes();
                        foreach ($taxes as $key => $tax) {
                            $taxes[$key] = 0;
                        }
                        $vendor_rate->set_taxes($taxes);
                        if (strpos($label, 'Free Shipping') === false) {
                            $vendor_rate->set_label($label . ': Free Shipping');
                        }
                    }
                }
            }
            $cnt++;
        }

        $postdata = [];
        if (sanitize_text_field(isset($_POST['post_data']))) {
            sanitize_text_field(parse_str($_POST['post_data'], $postdata));
        }

        if (isset($postdata['iihtcg_method'])) {
            switch ($postdata['iihtcg_method']) {
                case 'none':
                return [];
                break;
                case 'collect':
                foreach ($rates[0]['rates'] as $method => $rate) {
                    if (strpos($method, 'local_pickup') === false) {
                        unset($rates[0]['rates'][$method]);
                    }
                }
                break;
                default:
                break;
            }
        }

        return $rates;
    }

    /**
     * Store cart total for multi-vendor in session
     * Packages are passed by vendor to shipping so cart total can't be seen
     *
     * @param $cart
     */
    public function getCartTotalCost($cart)
    {
        if ($wc_session = WC()->session) {
            $settings = $this->getShippingMethodSettings();
            if (isset($settings['multivendor_single_override']) && $settings['multivendor_single_override'] === 'yes') {
                $wc_session->set('customer_cart_subtotal', $cart->get_subtotal() + $cart->get_subtotal_tax());
            }
        }
    }

    public function test_ajax($fields)
    {
        $fields['swagger_opt_ins'] = [
            'label'    => 'Opt Ins',
            'required' => false,
            'type'     => 'text',
        ];

        return $fields;
    }

    public function ajax_notice_handler()
    {
        if (sanitize_html_class(isset($_POST['type']))) {
            $type = sanitize_html_class($_POST['type']);
            update_option('dismissed-' . $type, true);
        }
    }

    /**
     * @param string $addressType
     * @param array $fields
     *
     * @return array
     */
    private
    function addAddressFields(
        $addressType,
        $fields
    ) {
        $addressFields          = $fields[$addressType];
        $shippingMethodSettings = $this->getShippingMethodSettings();
        if ( ! empty($shippingMethodSettings) && ! empty($shippingMethodSettings['south_africa_only']) && $shippingMethodSettings['south_africa_only'] == 'yes') {
            $required = false;
        }



        $addressFields = array_merge(
            $addressFields,
            [
                $addressType . '_postcode' => [
                    'type'     => 'text',
                    'label'    => 'Postcode',
                    'required' => true,
                    'class'    => ['form-row-last'],
                ],
            ]
        );
        if (isset($shippingMethodSettings['billing_insurance']) && $shippingMethodSettings['billing_insurance'] === 'yes') {
            $addressFields[$addressType . '_insurance'] = [
                'type'     => 'checkbox',
                'label'    => 'Would you like to include Shipping Insurance',
                'required' => false,
                'class'    => ['form-row-wide', 'tcg-insurance'],
                'priority' => 90,
            ];
        }
        $legacyFieldProperties                        = [
            'type'     => 'hidden',
            'required' => false,
        ];

        $addressFields[$addressType . '_place'] = $legacyFieldProperties;
        $fields[$addressType]                   = $addressFields;

        return $fields;
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    public
    function overrideAddressFields(
        $fields
    ) {
        $fields = $this->addAddressFields('billing', $fields);
        //$fields = $this->addAddressFields('shipping', $fields);

        return $fields;
    }

    private 
    function getShippingMethodSettings()
    {
        $shippingMethodSettings = [];
        $existingZones          = WC_Shipping_Zones::get_zones();
        $data = array();
        foreach ($existingZones as $zone) {
            $shippingMethods = $zone['shipping_methods'];
            foreach ($shippingMethods as $shippingMethod) {

                if ($shippingMethod->id == 'sovtech_tcg_zone') {
                    $courierGuyShippingMethod = $shippingMethod;
                    $data = get_option( 'woocommerce_' . $shippingMethod->id . '_' . $shippingMethod->instance_id . '_settings' );
                }
            }

        }
        $shippingMethodSettings = $data;

        return $shippingMethodSettings;
    }

}
