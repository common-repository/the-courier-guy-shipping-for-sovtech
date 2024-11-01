<?php

require_once CSM_DIR.'/helper/class-customhelper.php';
require_once plugin_dir_path(__DIR__) . 'Core/SovtechPayload.php';

/**
 * Zone setting for Indo Shipping
 */
class CSM_Zones_Method extends WC_Shipping_Method {
  private $main_settings;
  public $settings;
  private $total_shipping_codes;
  private $avail_shipping_options;
  private CSM_Init $csm;
  const TCG_SHIP_LOGIC_RESULT = 'tcg_ship_logic_result';
  const TCGS_SHIP_LOGIC_PARCEL = 'tcg_ship_logic_parcel';
  /**
   * @var WC_Logger
   */
  private static $log;
  private $parameters;
  private $logging = false;
  private $wclog;
  private $disable_specific_shipping_options = "";

  public function __construct($instance_id = 0) {
    $wc_session = WC()->session;
    $this->id = 'sovtech_tcg_zone';

    if(isset($_GET["instance_id"])){
      $this->instance_id = absint($_GET["instance_id"]);
    }else{
      $this->instance_id = absint($instance_id);
    }
    
    $tcg_config = $this->getCSMShippingSettings($instance_id);

    $this->wclog = wc_get_logger();
    $this->title = __('Sovtech TCG Zone');
    $this->method_title = __('Sovtech TCG Zone');
    $this->method_description = __( 'SovTech TCG Shipping Method for Courier' );

    if (isset($tcg_config['disable_specific_shipping_options'])) {
      $this->disable_specific_shipping_options = json_encode($tcg_config['disable_specific_shipping_options']);
    }

    if ($wc_session) {
      $wc_session->set('shipping_options', array());
    }

    $this->supports = array('shipping-zones', 'instance-settings',);
    $this->settings = $this->get_instance_form_fields();
    
    // global
    $this->main_settings = get_option('woocommerce_wcis_settings');
    
    add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    $this->init_shipping_codes();
  }

  /*
  * Get Shipping codes for admin. 
  */
  public function init_shipping_codes() {
    $available_methods = WC()->shipping->get_shipping_methods();

    if ( !empty($available_methods )) {
      foreach ( $available_methods as $m ) {
        $data = get_option( 'woocommerce_' . $m->id . '_zone_' . $this->instance_id . '_settings' );
      }
    }

    if(isset($data)){
      $server = !empty($data['connection_server']) ? $data['connection_server'] : '';

      if ( 'production' === $server ) {
        $api_url = !empty($data['prod_url']) ? $data['prod_url'] :'';
        $api_key = !empty($data['prod_apikey']) ? $data['prod_apikey'] : '';
      } else {
        $api_url = !empty($data['stage_url']) ? $data['stage_url'] : '';
        $api_key = !empty($data['stage_apikey']) ? $data['stage_apikey'] : '';
      }
      
      $helper_class = new Customhelper();
      
      if(isset($api_url)){
        $total_shipping_codes       = $helper_class->get_shipping_codes( $api_url ); 
        $this->total_shipping_codes = $total_shipping_codes;  

        $url                        = $api_url . '/shipping-options?token=' . $api_key;
        $maps_apikey                    = !empty($data['maps_apikey']) ? $data['maps_apikey'] : '';
      }

      $store_address      = !empty($data["shop_street_and_name"]) ?  $data["shop_street_and_name"] : '12 drury ln';
      $store_address_2    = !empty($data["shop_suburb"]) ? $data["shop_suburb"] : 'EC';
      $store_address_3    = !empty($data["shop_state"]) ? $data["shop_state"] : 'Roeland Square'; 
      $store_city         = !empty($data["shop_city"]) ? $data["shop_city"] : 'Cape town'; 
      $store_postcode     = !empty($data["shop_postal_code"]) ? $data["shop_postal_code"] : '8001';
      $store_raw_country  = !empty($data["shop_country"]) ? $data["shop_country"] : 'ZA';
      $store_email        = !empty($data["shop_email"]) ? $data["shop_email"] : 'test@gmail.com';
      $store_name         = !empty($data["company_name"]) ? $data["company_name"] : 'SovTech';
      $store_phone        = !empty($data["shop_phone"]) ? $data["shop_phone"] : '9876543210';
      $store_address_type = !empty($data["address_type"]) ? $data["address_type"] : 'BUSINESS';

      // Created address string for Google maps API.
      $toaddress    = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
      $tolat_long   = $helper_class->get_lat_long( $toaddress, $maps_apikey );

      $to_lat = '';
      $to_long = '';
      if(!empty($tolat_long))
      {
        $to_lat = isset($tolat_long['lat']) ? $tolat_long['lat'] : '';
        $to_long = isset($tolat_long['lng']) ? $tolat_long['lng'] : '';
      }

      $fromaddress  = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
      $fromlat_long = $helper_class->get_lat_long( $fromaddress, $maps_apikey );

      $from_lat = '';
      $from_long = '';
      if(!empty($tolat_long))
      {
        $from_lat = isset($fromlat_long['lat']) ? $fromlat_long['lat'] : '';
        $from_long = isset($fromlat_long['lng']) ? $fromlat_long['lng'] : '';
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
          'coordinates'  => '{"lat":' . $to_lat . ',"lng":' . $to_long . '}',
          'businessName' => ! empty($data['billing_company']) ? $data['billing_company'] : '',
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
          'coordinates'  => '{"lat":' . $from_lat . ',"lng":' . $from_long . '}',
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

    $csm = new CSM_Init();
    if(isset($this->total_shipping_codes) && !empty($this->total_shipping_codes)){
      $csm->setShippingCodes($this->total_shipping_codes);  
    }
    if(isset($this->avail_shipping_options) && !empty($this->avail_shipping_options)){
      $csm->setShippingOptions($this->avail_shipping_options );  
    }
  }

  /**
  * Get calculated shipping rates from TCG.
  * @param array $package for fetching data. 
  */
  public function calculate_shipping( $package = array() ) {
    // Get Helper class.
    $wc_session = WC()->session;
    $helper_class = new Customhelper();

    // created parcel array.
    $parcel     = array();
    $parcel_arr = array();

    $products = array();
    if ($wc_session) {
      $wc_session->set(self::TCG_SHIP_LOGIC_RESULT, null);

      $post_data = array();
      if ( sanitize_text_field(isset(  $_POST['post_data']  ) ) ) {
        sanitize_text_field(parse_str($_POST['post_data'], $post_data));
      }
      if ( isset(  $post_data  ) ) {

        if (isset($post_data['tcg_ship_logic_optins'])) {
          $package['ship_logic_optins'] = [];
          foreach ($post_data['tcg_ship_logic_optins'] as $val) {
            $package['ship_logic_optins'][] = (int)$val;

          }
        }
        $wc_session->set('shipping_options' ,$package['ship_logic_optins']);
        
        $totalShippingOptions = $wc_session->get('all_shipping_options');

        $selectedShipping = array();
        foreach ($totalShippingOptions as $key => $option) {
          if($package['ship_logic_optins'][$key] == $option->id)
          {
            array_push($selectedShipping, $option);
          }
        }

        // split post data string into pieces.
        $data  = $post_data;
        /*foreach ( $post_data as $key => $value ) {
          $kv             = wc_string_to_array( $value, '=' );
          $data[ $kv[0] ] = $kv[1];
        }*/
        // Get names of all the shipping classes
        $shipping_class_names = WC()->shipping->get_shipping_method_class_names();

      // Create an instance of the shipping method passing the instance ID
        $method_instance = new $shipping_class_names['sovtech_tcg_zone']( $this->instance_id );

        $method_data = $helper_class->get_settings($method_instance);
      // Get the field value from my shipping instance
        $available_methods = WC()->shipping->get_shipping_methods();
        
        /*if ( !empty($available_methods )) {
          foreach ( $available_methods as $m ) {
              //echo "instance_id " .$m->id;
            $md = get_option( 'woocommerce_' . $m->id . '_zone_' . $this->instance_id . '_settings' );
            if(!empty($md)){
              $method_data = $md;
            }

          }
        }*/

        


          // get store settings.
        $services = array();
        if(isset($method_instance)){
          $server = $method_instance->get_option('connection_server');

          if ( 'production' === $server ) {
            $api_url = $method_instance->get_option('prod_url');
            $api_key = $method_instance->get_option('prod_apikey');
          } else {
            $api_url = $method_instance->get_option('stage_url');
            $api_key = $method_instance->get_option('stage_apikey');
          }
          $url              = $api_url . '/possible-rates?token=' . $api_key;
          $shipping_codes   = $helper_class->get_shipping_codes( $api_url );

          foreach($shipping_codes as $key => $shipping_code){
            $services[] = $key;
          }
          $parcels = array();
            // Google maps now requires an API key.
          $mapsapi_key = $method_instance->get_option('maps_apikey');
          foreach ( $package["contents"] as $item_id => $values ) {
            $product                = $values['data']->get_data();
            $parcel['quantity']     = $values['quantity'];
            $parcel_arr[]           = $parcel;
              $total      += $product['price']; // Item subtotal discounted
              $products[] = $product;
            }
            $payloadApi   = new SovtechPayload();
            $parcelsArray = $payloadApi->getContentsPayload($method_data, $package['contents']);
            unset($parcelsArray["fitsFlyer"]);
            foreach ($parcelsArray as $key => $parcelArray) {
              $parcel['parcelLength'] = $parcelArray['dim1'];
              $parcel['parcelWidth']  = $parcelArray['dim2'];
              $parcel['parcelHeight'] = $parcelArray['dim3'];
              $parcel['parcelWeight'] = wc_get_weight($parcelArray['actmass'], 'kg');
              $parcel['quantity']     = $parcel_arr[$key]['quantity'];
              $parcel['parcelDescription']     = $parcelArray['description'];
              $parcels[]                   = $parcel;
            }            
          }
          if(!empty($parcels)){
            $wc_session->set(self::TCGS_SHIP_LOGIC_PARCEL, $parcels);  
          }
          $store_address      = $method_instance->get_option("shop_street_and_name");
          $store_address_2    = $method_instance->get_option("shop_suburb");
          $store_address_3    = $method_instance->get_option("shop_state");
          $store_city         = $method_instance->get_option("shop_city");
          $store_postcode     = $method_instance->get_option("shop_postal_code");
          $store_raw_country  = $method_instance->get_option("shop_country");
          $store_email        = $method_instance->get_option("shop_email");
          $store_name         = $method_instance->get_option("company_name");
          $store_phone        = $method_instance->get_option("shop_phone");
          $store_address_type = $method_instance->get_option("address_type");

          $billingInsurance = $data['billing_insurance'];
          $wholesaleValue = 0;
          if(1 == $billingInsurance)
          {
            $wholesaleValue = (float)$total;
            $wc_session->set("insuranceCost", $wholesaleValue);
          }

          if($data['ship_to_different_address'] == 1)
          {
           $shipping_type = !empty($data['shipping_type']) ? $data['shipping_type'] : 'RESIDENTIAL';
           $shipping_phone = !empty($data['shipping_phone']) ? $data['shipping_phone'] : '9876543210';
           $shipping_company = !empty($data['shipping_company']) ? $data['shipping_company'] : 'TCG';
           $shipping_email = !empty($data['shipping_email']) ? $data['shipping_email'] : 'tcg@gmail.com';
         }
         else
         {
           $shipping_type = !empty($data['billing_type']) ? $data['billing_type'] : 'RESIDENTIAL';
           $shipping_phone = !empty($data['billing_phone']) ? $data['billing_phone'] : '9876543210';
           $shipping_company = !empty($data['billing_company']) ? $data['billing_company'] : 'TCG';
           $shipping_email = !empty($data['billing_email']) ? $data['billing_email'] : 'tcg@gmail.com';
         }

          // Created address string for Google maps API.
         $toaddress    = $package['destination']['address'] . '+' . $package['destination']['address_2'] . '+' . $package['destination']['address_3'] . '+' . $package['destination']['city'] . '+' . $package['destination']['postcode'] . '+' . $package['destination']['state'] . '+' . $package['destination']['country'];
         $tolat_long   = $helper_class->get_lat_long( $toaddress, $mapsapi_key );
         $fromaddress  = $store_address . '+' . $store_address_2 . '+' . $store_address_3 . '+' . $store_city . '+' . $store_postcode . '+' . $store_raw_country;
         $fromlat_long = $helper_class->get_lat_long( $fromaddress, $mapsapi_key );



          // created an array for the TCG API request body.
         $shipping_rate = array(
          'services'     => $services,
          'parcels'      => $parcels,
          'locationTo'   => array(
            'type'         => $shipping_type,
            'address'      => $package['destination']['address'],
            'building'     => $package['destination']['address_1'],
            'suburb'       => $package['destination']['state'],
            'phone'        => $shipping_phone,
            'email'        => $shipping_email,
            'city'         => $package['destination']['city'],
            'postCode'     => $package['destination']['postcode'],
            'province'     => $package['destination']['country'],
            'coordinates'  => '{"lat":' . $tolat_long['lat'] . ',"lng":' . $tolat_long['lng'] . '}',
            'businessName' => $shipping_company,
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
          'wholesaleValue' => $wholesaleValue,
          'shippingOptions' => $selectedShipping,
        );


          // Converted array to JSON.
         $encodedshipping_rate = wp_json_encode( $shipping_rate, true );

          // CURL Request.
         $response = $helper_class->get_possible_rates( $url, $encodedshipping_rate );
         if(!empty($response)){
          $wc_session->set(self::TCG_SHIP_LOGIC_RESULT, $response);  
        }


        $isFreeShippingArr  = array();
        $productProhibitTcg = false;
        $isFreeShipping = false;
        if($method_instance->get_option("product_free_shipping") == "yes"){
          foreach ($products as $index => $pro) {
            $post_meta = get_post_meta($pro["id"], '',false);
            if($post_meta["product_free_shipping"][0] == "on"){
              $isFreeShippingArr[] = true;
            }else{
              $isFreeShippingArr[] = false;
            }
            if($post_meta["product_prohibit_tcg"][0] == "on"){
              $productProhibitTcg = true;
            }

          }

          $isFreeShipping = false;
          foreach ($isFreeShippingArr as $value) {
            if($value){
              $isFreeShipping = true;
            }else{
              $isFreeShipping = false;
              break;
            }
          }
        }


          // Check response is set or not.
        if ( isset( $response ) && ( ! $response->expose ) ) {
            // Converted JSON to array.
          $decoded_response = json_decode( $response );

            // Get total shipping codes
          $total_shipping_codes       = $helper_class->get_shipping_codes( $api_url ); 
          $excludedCodes = array();

            // get the excluded rates from global settings 
          foreach ($total_shipping_codes as $key => $codes) {
            if(in_array($key, $method_instance->get_option("excludes")))
            {
              $excludedCodes[] = $codes;
            } 
          }

            // Check if decoded_response count is greater than 0.
          if ( is_array( $decoded_response ) && count( $decoded_response ) > 0 ) {
                // set Shipping rates on checkout.
            foreach ( $decoded_response as $value ) {
                // exclude the optin rates from checkout 
              if(!in_array($value->title, $excludedCodes))
              {
                $rateId = $value->rateId;
                $price = $value->price;
                $title = $value->title;

                  // Prohibit TCG Sovtech courier guy shipping from checkout
                if(!$productProhibitTcg){
                    // check if the global settings data is not empty 
                  if(!empty($method_instance)){
                      // check if the percentage markup is not empty and add the amount in price 
                    if ( ! empty($method_instance->get_option('percentage_markup'))) {
                     $price = ($value->price + ($value->price * $method_data['percentage_markup'] / 100));
                     $price = number_format($price, 2, '.', '');
                   }

                     // Get label service data from global settings
                   $label_overrides = json_decode($method_data["label_override_per_service"]);

                     // check if free shipping is checked or not and Product free shippinng is not checked in global settings
                   if($method_data["free_shipping"] == "yes" && $method_data["product_free_shipping"] != "yes"){

                      // check if Rates for shipping is not empty
                    if((!empty($method_data["rates_for_free_shipping"]))){
                      foreach($method_data["rates_for_free_shipping"] as $rateVal) {
                        if($shipping_codes[$rateVal] == $value->title && $package["contents_cost"] >= $method_data["amount_for_free_shipping"]){
                          $price = 0;
                          $title = $value->title.": Free Shipping";
                        }
                      }
                    }else{
                      $price;
                      $title = $value->title;
                    }
                  }

                    // check if product for freec shipping is checked and Rates for free shipping is not empty
                  else if($method_data["product_free_shipping"] == "yes" && (!empty($method_data["rates_for_free_shipping"]))){
                    foreach($method_data["rates_for_free_shipping"] as $rateVal) {
                      if($shipping_codes[$rateVal] == $value->title && $package["contents_cost"] >= $method_data["amount_for_free_shipping"]){
                        $price = 0;
                        $title = $value->title.": Free Shipping";
                      }
                    }
                  }

                  // Check if Override label service is not empty
                  if(isset($label_overrides) && (!empty($label_overrides))){

                      // get price override rates 
                    $price_rates_override = json_decode($method_data["price_rate_override_per_service"]);
                    if(!empty($price_rates_override))
                    {
                      foreach($price_rates_override as $rateKey => $rateVal) {
                        // Check if shipping code and price rate service is matched 
                        if($shipping_codes[$rateKey] == $value->title){

                          // label override is not empty and product free shipping / free shipping is false
                          if(!empty($label_overrides->$rateKey) && ($method_data["free_shipping"] == "no" && $method_data["product_free_shipping"] == "no")){
                            $title = $label_overrides->$rateKey;
                          }

                          //label override is not empty and  product free shipping / free shipping is true
                          else if(!empty($label_overrides->$rateKey) && ($method_data["free_shipping"] == "yes" || $method_data["product_free_shipping"] == "yes")){
                            if(!empty($method_data["rates_for_free_shipping"]))
                            {
                              foreach($method_data["rates_for_free_shipping"] as $rateVal) {
                                if($shipping_codes[$rateKey] == $value->title && $package["contents_cost"] >= $method_data["amount_for_free_shipping"] && $shipping_codes[$rateVal] == $value->title){
                                  $price = 0;
                                  $title = $label_overrides->$rateKey.": Free Shipping";
                                }
                              }
                            }
                          }
                          else{
                            $title = $value->title;  
                          }
                        }
                      }
                    }
                    else if(!empty($label_overrides))
                    {
                      $flippedShippingCodes = array_flip($total_shipping_codes);
                      foreach($label_overrides as $rateKey => $rateVal) {
                        $getTitleCode = $flippedShippingCodes[$value->title];
                        // Check if shipping code and label override service is matched 
                        if(!empty($label_overrides->$rateKey) && $rateKey == $getTitleCode ){
                          // label override is not empty and product free shipping / free shipping is false
                          if($method_data["free_shipping"] == "no" && $method_data["product_free_shipping"] == "no"){
                            $title = $label_overrides->$rateKey;
                          }

                          //label override is not empty and  product free shipping / free shipping is true
                          else if($method_data["free_shipping"] == "yes" || $method_data["product_free_shipping"] == "yes"){
                            if(!empty($method_data["rates_for_free_shipping"]))
                            {
                              foreach($method_data["rates_for_free_shipping"] as $rateVal) {
                                if($shipping_codes[$rateKey] == $value->title && $package["contents_cost"] >= $method_data["amount_for_free_shipping"] && $shipping_codes[$rateVal] == $value->title){
                                  $price = 0;
                                  $title = $label_overrides->$rateKey.": Free Shipping";
                                }
                              }
                            }
                          }
                          else{
                            $title = $value->title;  
                          }
                        }
                      }
                    }
                  }

                    // check if price rate is not empty and product free shipping / free shipping is false
                  if((!empty($method_data["price_rate_override_per_service"])) && $method_data["free_shipping"] == "no" && $method_data["product_free_shipping"] == "no"){
                    $price_rates_override = json_decode($method_data["price_rate_override_per_service"]);
                    $label_overrides = json_decode($method_data["label_override_per_service"]);
                    foreach($price_rates_override as $rateKey => $rateVal) {
                      if($shipping_codes[$rateKey] == $value->title){
                        $price = $rateVal;
                        if(!empty($label_overrides->$rateKey)){
                          $title = $label_overrides->$rateKey;
                        }else{
                          $title = $value->title;  
                        }
                      }
                    }
                  }
                }
                $this->id = 'sovtech_tcg_zone:'.$value->title.':'.$rateId.':'.$this->instance_id;
                if(isset($title) && (!empty($title))){
                  $rate = array(
                    'id'    => 'sovtech_tcg_zone:'.$value->title.':'.$rateId.':'.$this->instance_id,
                    'label' => $title,
                    'cost'  => $price,
                    'free'    => ($method_data["free_shipping"]=='yes'||$method_data["product_free_shipping"]=="yes")?true:false,
                    'package' => $package
                  );
                  $this->add_rate( $rate );
                }
                $this->method_title = $value->title;
              }
            }
          }
        }
      }
    }
  }
}

public function getCSMShippingSettings($instance_id)
{
  global $wpdb;
  $results = $wpdb->get_results(
    "SELECT * FROM $wpdb->options WHERE `option_name` like '%woocommerce_the_courier_guy_{$instance_id}_settings%'"
  );
  $raw     = stripslashes_deep($results);
  if ( ! empty($raw)) {
    return unserialize($raw[0]->option_value);
  }
}


  /**
     * This method is called to build the UI for custom shipping setting of type 'sovtech_override_rate_service'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
  public function generate_sovtech_override_rate_service_html($key, $data)
  {
    $field_key      = $this->get_field_key($key);
    $defaults       = array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'placeholder'       => '',
      'type'              => 'text',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
      'options'           => array(),
    );
    $data           = wp_parse_args($data, $defaults);
    $overrideValue  = $this->get_option($key);
    $overrideValues = json_decode($overrideValue, true);
    ob_start();
    ?>
    <tr valign="top">
      <th scope="row" class="titledesc">
        <label for="<?php
        echo esc_attr($field_key); ?>_select"><?php
        echo wp_kses_post($data['title']); ?><?php
                    echo wp_kses_post($this->get_tooltip_html($data)); // WPCS: XSS ok.
                  ?></label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php
                    echo wp_kses_post($data['title']); ?></span></legend>
                    <select class="select <?php
                    echo esc_attr($data['class']); ?>" style="<?php
                    echo esc_attr($data['css']); ?>" <?php
                    disabled($data['disabled'], true); ?> <?php
                    echo wp_kses_post($this->get_custom_attribute_html($data)); // WPCS: XSS ok.
                  ?>>
                  <option value="">Select a Service</option>
                  <?php
                  $prefix = ' - ';
                  if ($field_key == 'woocommerce_sovtech_tcg_zone_price_rate_override_per_service') {
                    $prefix = ' - R ';
                  }
                  ?>
                  <?php
                  foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                    <option value="<?php
                    echo esc_attr($option_key); ?>" data-service-label="<?php
                    echo esc_attr($option_value); ?>"><?php
                    echo esc_attr(
                      $option_value
                      ); ?><?php echo ( ! empty($overrideValues[$option_key])) ? esc_attr($prefix . $overrideValues[$option_key]) : ''; ?></option>
                    <?php
                  endforeach; ?>
                </select>
                <?php
                foreach ((array)$data['options'] as $option_key => $option_value) : ?>
                  <span style="display:none;" class="<?php
                  echo esc_attr($data['class']); ?>-span-<?php echo esc_attr($option_key); ?>">
                  <?php
                  $class = '';
                  $style = '';
                  if ($field_key == 'woocommerce_sovtech_tcg_zone_price_rate_override_per_service') {
                    $class = 'wc_input_price ';
                    $style = ' style="width: 90px !important;" ';
                    ?>
                    <span style="position:relative; top:8px; padding:0 0 0 10px;">R </span>
                    <?php
                  }
                  ?>
                  <input data-service-id="<?php
                  echo esc_attr($option_key); ?>" class="<?php echo esc_attr($class); ?> input-text regular-input <?php
                  echo esc_attr($data['class']); ?>-input"
                  type="text"<?php echo esc_attr($style); ?> value="<?php echo isset($overrideValues[$option_key]) ? esc_attr($overrideValues[$option_key]) : ''; ?>"/>
                </span>
                <?php
              endforeach; ?>
              <?php
                    echo wp_kses_post($this->get_description_html($data)); // WPCS: XSS ok.
                    ?>
                    <input type="hidden" name="<?php
                    echo esc_attr($field_key); ?>" value="<?php echo esc_attr($overrideValue); ?>"/>
                  </fieldset>
                </td>
              </tr>
              <?php
              return ob_get_clean();
            }


    /**
     * This method is called to build the UI for custom shipping setting of type 'sovtech_percentage'.
     * This method must be overridden as it is called by the parent class WC_Settings_API.
     *
     * @param $key
     * @param $data
     *
     * @return string
     * @uses WC_Settings_API::get_custom_attribute_html()
     * @uses WC_Shipping_Method::get_option()
     * @uses WC_Settings_API::get_field_key()
     * @uses WC_Settings_API::get_tooltip_html()
     * @uses WC_Settings_API::get_description_html()
     */
    public function generate_sovtech_percentage_html($key, $data)
    {
        //@todo The contents of this method is legacy code from an older version of the plugin.
      $field_key = $this->get_field_key($key);
      $defaults  = [
        'title'             => '',
        'disabled'          => false,
        'class'             => '',
        'css'               => '',
        'placeholder'       => '',
        'type'              => 'text',
        'desc_tip'          => false,
        'description'       => '',
        'custom_attributes' => [],
      ];
      $data      = wp_parse_args($data, $defaults);
      ob_start();
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <label for="<?php
          echo esc_attr($field_key); ?>"><?php
          echo wp_kses_post($data['title']); ?><?php
                    echo wp_kses_post($this->get_tooltip_html($data)); // WPCS: XSS ok.
                  ?></label>
                </th>
                <td class="forminp">
                  <fieldset>
                    <legend class="screen-reader-text"><span><?php
                    echo wp_kses_post($data['title']); ?></span>
                  </legend>
                  <input class="wc_input_decimal input-text regular-input <?php
                  echo esc_attr($data['class']); ?>" type="text" name="<?php
                  echo esc_attr($field_key); ?>" id="<?php
                  echo esc_attr($field_key); ?>" style="<?php
                  echo esc_attr($data['css']); ?> width: 50px !important;" value="<?php
                  echo esc_attr(wc_format_localized_decimal($this->get_option($key))); ?>" placeholder="<?php
                  echo esc_attr($data['placeholder']); ?>" <?php
                  disabled($data['disabled'], true); ?> <?php
                    echo wp_kses_post($this->get_custom_attribute_html($data)); // WPCS: XSS ok.
                  ?> /><span style="vertical-align: -webkit-baseline-middle;padding: 6px;">%</span>
                  <?php
                    echo wp_kses_post($this->get_description_html($data)); // WPCS: XSS ok.
                    ?>
                  </fieldset>
                </td>
              </tr>
              <?php
              return ob_get_clean();
            }

          }
