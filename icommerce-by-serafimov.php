<?php
/*
*
* iCommerce by Serafimov Group + stock_qty management + variable products + unlink an old image, upload a new one
*
* Plugin Name: iCommerce by Serafimov Group
* Plugin URI: https://serafimov.mk
* Description: Seamlessly sync idents/products from the Pantheon software into your own WooCommerce website and immediately start selling your products online.
* Version: 1.1.1
* Author: Serafimov Group
* Author URI: https://serafimov.mk
* Licence: GPLv2 or later
* Text Domain: serafimovdbsyncpa
*/

define( 'PANTHEON_DBSYNC_P_DIR', plugin_dir_path( __FILE__ ) );
define( 'PANTHEON_DBSYNC_PLUGIN_URL', plugins_url() . '/icommerce-by-serafimov' );
define( 'PADBSYNC_IMG_UPLOADS_DIR', PANTHEON_DBSYNC_P_DIR . 'uploads/' );
define( 'PADBSYNC_ERROR_LOGS_DIR', PANTHEON_DBSYNC_P_DIR . 'error-logs/' );


// ENABLE LOGGING
// IF TRUE, CREATES SEPARATE LOGS FOR EVERY RECIEVED POST REQUEST
// LOGS LOCATION: plugins/icommerce-by-serafimov
define( 'PADBSYNC_ENABLE_LOGS', false );

// ENABLE LOGGING IN CASE OF FAILURE TO CREATE/UPDATE PRODUCT OR IF AN EXCEPTION IS THROWN
define( 'PADBSYNC_ENABLE_LOGS_ERR_OR_NOTSUCCESS', false );

// SETS THE CURLOPT_CONNECTTIMEOUT & CURLOPT_TIMEOUT
define( 'PADBSYNC_CURL_TIMEOUT', 0 );

require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/Client.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/BasicAuth.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/HttpClient.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/HttpClientException.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/OAuth.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/Options.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/Request.php');
require_once(PANTHEON_DBSYNC_P_DIR.'lib/WooCommerce/HttpClient/Response.php');
require_once(ABSPATH . 'wp-config.php'); 
require_once(ABSPATH . 'wp-includes/wp-db.php'); 
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php'); 

use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class iCommerce {
    
    private $options;
    private $consumer_key = '';
    private $consumer_secret = '';
    private $rest_endpoint = '';
    
    private $woo_lib_err_msg = '';
    

    // constructor
    public function __construct(){
        
        $this->constantsAndIncludesInit();
        $this->createRestRoute();
        
        $this->init();
        
        if ( get_option( 'pa_dbsync_settings' ) ) {
            $this->options = get_option( 'pa_dbsync_settings' );
            
            $this->consumer_key = $this->options['pa_db_sync_woo_consumer_key'];
            $this->consumer_secret = $this->options['pa_db_sync_woo_secret_key'];
        }
        
    }
    
    public function init(){
        add_action( 'admin_menu', array($this, 'createAdminMenu') );
        add_action( 'admin_init', array($this, 'settingsInit') );
    }
    
    public function createAdminMenu() {
        add_menu_page( 'iCommerce by Serafimov.mk', 'iCommerce', 'manage_options', 'pantheon_dbsync', array($this, 'optionsPageHtml'), PANTHEON_DBSYNC_PLUGIN_URL . '/assets/icommerce.ico' );
    }
    
    function optionsPageHtml() { 
    
        ?>
        <form action='options.php' method='post'>
        <h2>iCommerce by Serafimov.mk</h2>
        
        <?php
            settings_fields( 'pantheonDbSync' );
            do_settings_sections( 'pantheonDbSync' );
            submit_button('Save Changes');
        ?>
        </form>
        <?php
        
    }
    
    public function settingsInit() {
        register_setting( 'pantheonDbSync', 'pa_dbsync_settings' );
        
        add_settings_section(
            'pa_dbsync_general_section', 
            __( 'General Settings.', 'serafimovdbsyncpa' ), 
            array($this, 'general_settings_callback'), 
            'pantheonDbSync'
        );
        
        add_settings_field( 
            'pa_db_sync_woo_consumer_key', 
            __( 'CONSUMER KEY', 'serafimovdbsyncpa' ), 
            array($this, 'woo_consumer_key_render'), 
            'pantheonDbSync', 
            'pa_dbsync_general_section' 
        );
        
        add_settings_field( 
            'pa_db_sync_woo_secret_key', 
            __( 'SECRET KEY', 'serafimovdbsyncpa' ), 
            array($this, 'woo_secret_key_render'), 
            'pantheonDbSync', 
            'pa_dbsync_general_section' 
        );

    }
    
    public function general_settings_callback() {}
    
    public function woo_consumer_key_render() {
        $options = get_option( 'pa_dbsync_settings' );
         
            ?>
            <input type='text' name='pa_dbsync_settings[pa_db_sync_woo_consumer_key]'
                value='<?php echo (isset($options['pa_db_sync_woo_consumer_key'])) ? $options['pa_db_sync_woo_consumer_key'] : "" ?>' style="width: 70%;">
                
            <p class="description">
                Enter you woocommerce consumer key which you can generate in woocommerce -> settings -> advanced -> rest api
            </p>
            
            <?php
    }
    
    public function woo_secret_key_render() {
        $options = get_option( 'pa_dbsync_settings' );
            ?>
            <input type='text' name='pa_dbsync_settings[pa_db_sync_woo_secret_key]'
                value='<?php echo (isset($options['pa_db_sync_woo_secret_key'])) ? $options['pa_db_sync_woo_secret_key'] : "" ?>' style="width: 70%;">
                
            <p class="description">
                Enter you woocommerce secret key which you can generate in woocommerce -> settings -> advanced -> rest api
            </p>
            
            <code style="margin-top: 20px; padding: 12px; display: block; width: 50%;"><?php echo get_site_url() . '/wp-json/paidents/v1/post'; ?></code>
                    <p class="description">
                        Copy this url, and use it for setting the Pantheon software. <br>
                        The software should send all the requests for syncing products to this url.
                    </p>
            
            <?php
    }
    
      
    public function constantsAndIncludesInit(){
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
      
    public function createRestRoute() {
              
       
        add_action( 'rest_api_init', function () {
            register_rest_route( 'paidents/v1', '/post', array(
                'methods' => 'POST',
                'callback' => array($this, 'receiveIdents'),
                'permission_callback' => '__return_true',
            ));
            
            register_rest_route( 'paidents/v2', '/post', array(
                'methods' => 'POST',
                'callback' => array($this, 'insertPictureForIdent'),
                'permission_callback' => '__return_true',
            ));
            
            register_rest_route( 'paidents/v3', '/post', array(
                'methods' => 'POST',
                'callback' => array($this, 'inTableInsertPictureForIdent'),
                'permission_callback' => '__return_true',
            ));
            
            register_rest_route( 'icommerce/v1', '/orders/get', array(
                'methods' => 'GET',
                'callback' => array($this, 'retrieveOrders'),
                'permission_callback' => '__return_true',
            ));
            
            register_rest_route( 'icommerce/v1', '/orders/post', array(
                'methods' => 'POST',
                'callback' => array($this, 'markOrderReceived'),
                'permission_callback' => '__return_true',
            ));
        });
    }
      
    public function receiveIdents() {
    //    var_dump(get_site_url());die();
        $post_request_size = (int) $_SERVER['CONTENT_LENGTH'];
        
        $countSuccessfullCreations = 0;
        $countSuccessfullUpdates = 0;
        
        $woocommerce = new Client(
            str_replace('/public', '', get_site_url()),
        //    get_site_url(),
            $this->consumer_key,
            $this->consumer_secret,
            [
               'wp_api'  => true,
                'version' => 'wc/v3',
                'query_string_auth' => true,
                'verify_ssl' => false,
                'timeout' => PADBSYNC_CURL_TIMEOUT
            ]
        );
        
        $getPostData = isset($_POST['data']) ? $_POST['data'] : '';
       
        
        $json_data = json_decode($getPostData, true);
        
        $strSlashes = stripslashes($getPostData);
        $replaced = str_replace(array("\r\n", "\r", "\n"), " ", $strSlashes);
        
        $products = json_decode($replaced, true);

        // LOG THE REQUEST
        if ( PADBSYNC_ENABLE_LOGS ):

            date_default_timezone_set('Europe/Skopje');
            $logger_post_file = fopen(PANTHEON_DBSYNC_P_DIR."request_log_from_".date('Y-m-d_H-i-s').".txt", "a") or die("Unable to open post_file!");
            $temp_txtlog = "Created on: ".date('Y-m-d H:i:s')." - number of products in the request: ".count($products)." - post request size : ".$post_request_size."\n\n";
            fwrite($logger_post_file, $temp_txtlog);
            fwrite($logger_post_file, $def."\n\n");
            fclose($logger_post_file);

        endif;
        
        $temp_featured_image_check = false;
        $featured_image_tmp_location = '';
         
        if ( isset($getPostData) && is_array($products) && !empty($products) ) {
            foreach($products as $product) {
                // FEATURED IMAGE
                $current_img_path = '';

                if ( isset($product['acPicture']) && !empty($product['acPicture']) ) {
            						
                    $img_filename = base64_encode(microtime());   // Create random filename - smeni go so tvoja logika

                    $img_data = trim($product['acPicture']);
                	
                    $temp_type = substr($img_data, 0, 30);
                    $type_binary_check = "FFD8FFDB";  //jpg
                    if (substr($temp_type, 0, strlen($type_binary_check)) === $type_binary_check ) $img_type = '.jpg';
                		
                    $type_binary_check = "FFD8FFE000104A4649460001";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "FFD8FFEE";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "FFD8FFE1";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "89504E470D0A1A0A";  //png
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.png';
                
                    $type_binary_check = "424D";  //bmp
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.bmp';
                
                    $img_data_bin = pack("H*", $img_data);
     
                    file_put_contents(PADBSYNC_IMG_UPLOADS_DIR.$img_filename.$img_type, $img_data_bin);
                  
                    $temp_featured_image_check = true;
                    
                    $current_img_path = $img_filename.$img_type;
                    
                    if ( $img_type == '.bmp' ) {
                        $bmpToPngIM = imagecreatefrombmp(PADBSYNC_IMG_UPLOADS_DIR.$img_filename.$img_type);
                        imagepng($bmpToPngIM, PADBSYNC_IMG_UPLOADS_DIR.$img_filename.'.png');
                        imagedestroy($bmpToPngIM);
                    }
                }
                
                // FEATURED IMAGE
                $sifra  = trim($product['acIdent']);
                
                $ime = $product['acName'];
                
                // if (isset($product['acFieldSB'])) {
                //     $ime    = trim($product['acFieldSB']);
                // }
                
                if (isset($product['acDeclarForOriginType'])) {
                    $meni = $product['acDeclarForOriginType'];
                }
                
            //    $parent_kat = trim($product['acClassif']);
                $parent_kat = trim($product['acClassName']);
                $child_kat = trim($product['anSubClassif']);
               
                if (isset($product['anDimWidth'])) {
                    $shirina = explode(',', $product['anDimWidth']);
                }
                
                if (isset($product['anDimHeight'])) {
                    $visina = explode(',', $product['anDimHeight']);
                }
                
                if (isset($product['anDimWeight'])) {
                    $weightCom = str_replace(",", ".", $product['anDimWeight']);
                    $weight = preg_replace('/\.(?=.*\.)/', '', $weightCom);
                    $tezhina = $weight * 1000;
                }
                
                if (isset($product['anDimDepth'])) {
                    $dolzhina = explode(',', $product['anDimDepth']);
                }
                
                // if decimals are needed for woocommerce set this variable to true
                $showDecimalsInPrice = false;
                
                $raw_cena = (string)$product['anSalePrice'];
              
                $cena = '';
                $namalena_cena = '';
            
                if ( $showDecimalsInPrice ) {
                    $cena = str_replace(',0000', '', $raw_cena);
                } else {
                    $cena = $raw_cena;
                }
                
                $sale_price = $product['anSalePriceOff'];
                if( ($sale_price != '') && ($sale_price != 0) ) {
                    $namalena_cena = (string)$product['anSalePriceOff'];
                } else {
                    $namalena_cena = '';
                }
                
                if (!isset($product['acTechProcedure']) && isset($product['acDescr'])) {
                    $opis = $product['acDescr'];
                }
                
                if(isset($product['acTechProcedure']) && isset($product['acDescr'])) {
                    $opis = $product['acTechProcedure'];
                    $kratok_opis = $product['acDescr'];
                }
                
                $stock = '';
                $manage_stock   = true;
                $stock_qty      = 0;
                
                if ( isset($product['ac_stock']) ) {
                    $stock_qty = (int)$product['ac_stock'];
                    if ( $stock_qty == 0 ) {
                        $stock = 'outofstock';
                    } else {
                        $stock = 'instock';
                    }
                } elseif ( isset($product['acStock']) ) {
                    $stock_qty = (int)$product['acStock'];
                    if ( $stock_qty == 0 ) {
                        $stock = 'outofstock';
                    } else {
                        $stock = 'instock';
                    }
                } else {
                    $stock      = 'outofstock';
                }
                
                if ( $temp_featured_image_check ) {
                    $slika = get_site_url() . '/wp-content/plugins/icommerce-by-serafimov/uploads/' . $current_img_path;
                    $featured_image_tmp_location = PADBSYNC_IMG_UPLOADS_DIR . $current_img_path;
                } else {
                    $slika = '';
                }

                $categories = [];
               
                if (isset($meni) && $meni) {
                    $meniEx = term_exists(htmlspecialchars($meni), 'product_cat');
                    
                    if ($meniEx) {
                        $mData = [
                            'name' => $meni
                        ];

                        $categories[] = [
                            'id' => $meniEx['term_id']  
                        ];
                        
                        $mCat = $meniEx['term_id'];
                    } else {
                        $mData = [
                            'name' => $meni  
                        ];
                        
                        $new_meni = $woocommerce->post('products/categories', $mData);
                        
                        $mCat = $new_meni->id;
                        
                        $categories[] = [
                            'id' => $new_meni->id  
                        ];
                    }
                }
                 
                if (isset($parent_kat) && $parent_kat) {
                    $parExists = term_exists(htmlspecialchars($parent_kat), 'product_cat');
        
                    if ($parExists) {
                        // $parentCategory = $woocommerce->get('products/categories/' . $parExists['term_id']);
                        
                        $pData = [
                            'name' => $parent_kat,
                            'parent' => $mCat
                        ];
                        
                        if (!$meni) {
                            $pData = [
                                'name' => $parent_kat
                            ];
                        }
                        
                        $parent = $woocommerce->put('products/categories/'.$parExists['term_id'], $pData);
                        
                        $categories[] = [
                            'id' => $parent->id
                        ];
                        
                        $parCat = $parent->id;
                    } else {
                        $pData = [
                            'name' => $parent_kat,
                            'parent' => $mCat
                        ];
                        
                        if (!$meni) {
                            $pData = [
                                'name' => $parent_kat
                            ];
                        }
                                    
                        $new_cat = $woocommerce->post('products/categories', $pData);
                        
                        $parCat = $new_cat->id;
                        
                        $categories[] = [
                            'id' => $new_cat->id  
                        ];
                    }
                }
             
                if (isset($child_kat) && $child_kat) {
                    // $chExists = term_exists(htmlspecialchars($child_kat), 'product_cat');
                    $chExists = get_term_by('name', htmlspecialchars($child_kat), 'product_cat');
                    
                  //  $parentCategory = $woocommerce->get('products/categories/' . $chExists->parent);
                     
                  
                    $parentCategory = get_term_by('id', htmlspecialchars($chExists->parent), 'product_cat');
                     
                    $cData = [
                        'name' => $child_kat,
                        'parent' => $parCat
                    ];

                    $dbcats = [];
                  
                    for ($i = 1; $i <= 10; $i++) {
                        $dbcategories = $woocommerce->get('products/categories', ['page' => $i, 'per_page' => 100]);
                        
                        foreach ($dbcategories as $cat) {
                            $dbcats[] = $cat;
                        }
                    }
                  
                    if (mb_strtolower($parentCategory->name, 'UTF-8') != mb_strtolower($parent_kat, 'UTF-8')) {
                        if (mb_strtolower($chExists->name, 'UTF-8') == mb_strtolower($child_kat, 'UTF-8')) {
                            $childCatExists = false;
                            
                            foreach ($dbcats as $dc) {
                                if (mb_strtolower($child_kat, 'UTF-8') == mb_strtolower($dc->name, 'UTF-8')) {
                                    $parentOf = get_term_by('id', htmlspecialchars($dc->parent), 'product_cat');
                                    
                                    if (mb_strtolower($parentOf->name, 'UTF-8') == mb_strtolower($parent_kat, 'UTF-8')) {
                                        $childCatExists = true;
                                        $new_ch_cat = $dc;
                                    }
                                    
                                } else {
                                    continue;
                                }
                            }
                            
                            if (!$childCatExists) {
                                $cData['slug'] = str_replace(' ', '', $parent_kat) . '-' . str_replace(' ', '', $child_kat);
                                $cData['parent'] = $parCat;
                                
                                $new_ch_cat = $woocommerce->post('products/categories', $cData);
                            }
                        } else {
                            $new_ch_cat = $woocommerce->post('products/categories', $cData);
                        }
                        
                        $categories[] = [
                            'id' => $new_ch_cat->id
                        ];
                    } else {
                        $categories[] = [
                            'id' => $chExists->term_id
                        ];
                    }
                   
                }
                   
                //RETRIEVE GLOBAL ATTRIBUTES
               $globalAttributes = $woocommerce->get('products/attributes');
                  
                 // CHECK THE PRODUCT ATTRIBUTES
                 $attributes = [];
            
                 foreach ($globalAttributes as $attr) {
                     if ($attr->slug == 'pa_vozrast') {
                         if (isset($product['anFieldNF']) && isset($product['anFieldNG'])) {
                             $ageFromCom = str_replace(",",".",$product['anFieldNF']);
                             $ageFrom = preg_replace('/\.(?=.*\.)/', '', $ageFromCom);
                            
                             $ageToCom = str_replace(",",".",$product['anFieldNG']);
                             $ageTo = preg_replace('/\.(?=.*\.)/', '', $ageToCom);
                            
                             if ($ageFrom < 1 && $ageFrom != 0) {
                                $fromMonths = true;
                            }
                            
                             if ($ageTo < 1 && $ageTo != 0) {
                                 $toMonths = true;
                             }
                            
                             //CASE: AGE IS FROM - TO INTERVAL
                             if ($ageFrom >= 0 && $ageTo > 0) {
                                 $ageFromFinal = $fromMonths ? (floatval($ageFrom) * 12) . ' мес. - ' : round($ageFrom) . ' - ';
                                 $ageToFinal = $toMonths ? (floatval($ageTo) * 12) . ' мес.' : round($ageTo) . ' год.';
                                
                                 $ageForArray = $ageFromFinal . $ageToFinal;
                             } 

                             //CASE: OLDER THAN X YRS/MNTS
                             if ($ageFrom >= 0 && $ageTo == 0) {
                                 $ageForArray = $fromMonths ? (floatval($ageFrom) * 12) . '+ мес.' : round($ageFrom) . '+ год.';
                             }
                            
                             $vozrast = [
                                 'id' => $attr->id,
                                 'position' => 0,
                                 'visible' => true,
                                 'options' => [
                                     $ageForArray
                                 ]
                             ];
                            
                             $attributes[] = $vozrast;
                         }
                     }
                    
                     if ($attr->slug == 'pa_brend') {
                         if (isset($product['acFieldSC'])) {
                             $brend = [
                                 'id' => $attr->id,
                                 'position' => 0,
                                 'visible' => false,
                                 'options' => [
                                     $product['acFieldSC']
                                 ]
                             ];
                            
                             $attributes[] = $brend;
                         }
                     }
                    
                     if ($attr->slug == 'pa_proizvoditel') {
                         if (isset($product['acFieldSH']) && !empty( $product['acFieldSH'])) {
                             $acFieldSH = [
                                 'id' => $attr->id,
                                 'position' => 0,
                                 'visible' => true,
                                 'options' => [
                                     $product['acFieldSH']
                                 ]
                             ];
                            
                             $attributes[] = $acFieldSH;
                         }
                     }
                    
                     if ($attr->slug == 'pa_pol') {
                         if (isset($product['acFieldSI'])) {
                             $pol = [
                                 'id' => $attr->id,
                                 'position' => 0,
                                 'visible' => true,
                                 'options' => [
                                     $product['acFieldSI']
                                 ]
                             ];
                            
                             $attributes[] = $pol;
                         }
                     } 
                 }
                 
                if (isset($product['acFieldSG']) && !empty($product['acFieldSG'])) {
                    $uvoznik = [
                        'name' => 'Увозник',
                        'position' => 0,
                        'visible' => true,
                        'options' => [
                            $product['acFieldSG']
                        ]
                    ];
                    
                    $attributes[] = $uvoznik;
                }
                
                if (isset($product['acFieldSF']) && !empty($product['acFieldSF'])) {
                    $materijal = [
                        'name' => 'Материјал',
                        'position' => 0,
                        'visible' => true,
                        'options' => [
                            $product['acFieldSF']
                        ]
                    ];
                    
                    $attributes[] = $materijal;
                }
                    
                if (isset($product['acBarcode']) && !empty($product['acBarcode'])) {
                    $acBarcode = [
                        'name' => 'Баркод',
                        'position' => 0,
                        'visible' => true,
                        'options' => [
                            $product['acBarcode']
                        ]
                    ];
                    
                    $attributes[] = $acBarcode;
                }
                
                if (isset($product['anDeclarForOrigin']) && !empty($product['anDeclarForOrigin'])) {
                    $baterii = [
                        'name' => 'Батерии',
                        'position' => 0,
                        'visible' => true,
                        'options' => [
                            $product['anDeclarForOrigin']
                        ]
                    ];
                    
                    $attributes[] = $baterii;
                }
                
                if (isset( $product['utn_StockOnline']) && !empty($product['utn_StockOnline'])) {
                    $utnStockOnline = [
                        'name' => 'Онлајн залиха',
                        'position' => 0,
                        'visible' => false,
                        'options' => [
                            $product['utn_StockOnline']
                        ]
                    ];
                    
                    $attributes[] = $utnStockOnline;
                }
                
                //ADD BRAND
                 $brandTaxonomy['term_id'] = '';
                
          //      if (isset($product['acFieldSC']) && !empty($product['acFieldSC'])) {
                if (isset($product['acClassif2']) && !empty($product['acClassif2'])) {
           //         $brandTaxonomy = term_exists($product['acFieldSC'], 'product_brand');
					$trimmed = preg_replace('/\s+/', ' ', $product['acClassif2']);
					$brandExists = term_exists(htmlspecialchars($trimmed), 'product_brand');
                    $brandTaxonomy = $brandExists;
                    
                    if(!$brandExists) {
                   //     $brandTaxonomy = wp_create_term($product['acFieldSC'], 'product_brand');
                        $brandTaxonomy = wp_create_term($trimmed, 'product_brand');
                    }
                }

                //DATA ARRAY INSTANTIATION
                // $data = [
                //     'name' => $ime,
                //     'sku' => $sifra,
                //     'type' => 'simple',
                //     'regular_price' => (string)$cena,
                //     'sale_price' => $namalena_cena,
                //     'manage_stock' => $manage_stock,
                //     'stock_quantity' => $stock_qty,
                //     'stock_status' => $stock,
                //     'categories' => $categories
                // ];
                
                // if ($opis) {
                //     $data['description'] = $opis;
                // }
                
                // if ($kratok_opis) {
                //     $data['short_description'] = $kratok_opis;
                // }
                
                // if ($shirina && $visina && $dolzhina) {
                //     $data['dimensions'] = [
                //         'width' => (string)$shirina[0],
                //         'height' => (string)$visina[0],
                //         'length' => (string)$dolzhina[0]
                //     ];
                // }
                
                // if ($tezhina) {
                //     $data['weight'] = (string)$tezhina;
                // }
                
                // if (isset($brandTaxonomy) && $brandTaxonomy['term_id'] != '') {
                //     $data['brands'] = [
                //         $brandTaxonomy['term_id']
                //     ];
                // }
                
                // if (count($attributes) > 0) {
                //     $data['attributes'] = $attributes;
                // }

                //CHECK FOR EXISTING PRODUCT
                $skuData = [
                    'sku' => $sifra
                ];
                 
                $sku_check_exists = $woocommerce->get('products',$skuData);
            
                			//variable products
				if ( isset($product['variationOptions']) && !empty($product['variationOptions']) ) {
					
                    $data = [
                        'name' => $ime,
                        'sku' => $sifra,
                        'type' => 'variable',
                        'regular_price' => (string)$cena,
						'sale_price' => $namalena_cena,
						'manage_stock' => $manage_stock,
						'stock_quantity' => $stock_qty,
						'stock_status' => $stock,
						'categories' => $categories,
                        'attributes'  => [
                            [
                                'name' => 'Опции',
                                'position' => 0,
                                'visible' => true,
                                'variation' => true,
                                'options' => explode(',', $product['variationOptions']),
                            ],
                        ],
                    ];
					
					if ($opis) {
                    $data['description'] = $opis;
					}
					
					if ($kratok_opis) {
						$data['short_description'] = $kratok_opis;
					}
					
					if ($shirina && $visina && $dolzhina) {
						$data['dimensions'] = [
							'width' => (string)$shirina[0],
							'height' => (string)$visina[0],
							'length' => (string)$dolzhina[0]
						];
					}
					
					if ($tezhina) {
						$data['weight'] = (string)$tezhina;
					}
					
				if (isset($brandTaxonomy) && $brandTaxonomy['term_id'] != '') {
                    $data['brands'] = [
                        $brandTaxonomy['term_id']
                    ];
                }
            }
				
				else {

                    $data = [
                        'name' => $ime,
                        'sku' => $sifra,
                        'type' => 'simple',
                        'regular_price' => (string)$cena,
						'sale_price' => $namalena_cena,
						'manage_stock' => $manage_stock,
						'stock_quantity' => $stock_qty,
						'stock_status' => $stock,
						'categories' => $categories,
                    ];
					
					if ($opis) {
                    $data['description'] = $opis;
					}
					
					if ($kratok_opis) {
						$data['short_description'] = $kratok_opis;
					}
					
					if ($shirina && $visina && $dolzhina) {
						$data['dimensions'] = [
							'width' => (string)$shirina[0],
							'height' => (string)$visina[0],
							'length' => (string)$dolzhina[0]
						];
					}
					
					if ($tezhina) {
						$data['weight'] = (string)$tezhina;
					}

			if (isset($brandTaxonomy) && $brandTaxonomy['term_id'] != '') {
                    $data['brands'] = [
                        $brandTaxonomy['term_id']
                    ];
                }
			
					if (count($attributes) > 0) {
						$data['attributes'] = $attributes;
					}

                }

                if (count($sku_check_exists) < 1) {
           
                    try {
                       
                        $newProduct = $woocommerce->post('products', $data);
                        if ( $newProduct ) {
       
                            $jsonToArr = json_encode( $newProduct, true );
                            
                            $newProductID = $newProduct->id;

                            if ( $slika != '' ) {
                                $this->uploadAndSetFeaturedImage($newProductID, $slika, $featured_image_tmp_location);
                            }
                            
      //  unlink - delete the temp image stored in the plugin uploads folder
        if ( file_exists( $featured_image_tmp_location) ) {
            unlink( $featured_image_tmp_location );
        }
                            
              //if proruct is variable, extract and create/update its base
              if ( isset($product['variationOptions']) && !empty($product['variationOptions']) ) {
            	$variationOptions = explode(',', $product['variationOptions']);
            	$variationStock = explode(',', $product['variationStock']);
         //   if($variationOptions != '') {
    			foreach($variationOptions as $varOption) {
            	
						// check if variation exists
						$check_var_exists_data_for_created_product = [
							 'search' => $varOption,
									  
						];

						$check_variation_exists_for_created_product = $woocommerce->get("products/{$newProductID}/variations", $check_var_exists_data_for_created_product);

		
						$variation_dataUpdate_newProduct = [
					//		'regular_price' => (string)$cena,
					//		'sale_price' => $namalena_cena,
					//		'manage_stock' => $manage_stock,
					//		'stock_quantity' => $stock_qty,
					//		'stock_status' => $stock,
							'attributes'    => [
								[
									'name'  => 'Variation',
									'slug'  => 'pa_variation',
									'option' => $varOption,
								],
							],
						];
						
					foreach($variationStock as $varStock) {	
				
						$variation_dataUpdate_newProduct = [
								'regular_price' => (string)$cena,
								'sale_price' => $namalena_cena,
								'manage_stock' => $manage_stock,
								'stock_quantity' => $varStock,
								'stock_status' => $stock,
						];
					}

						if( !empty($check_variation_exists_for_created_product[0]->id) ) {
							$updateVariationForNewProduct = $woocommerce->put("products/{$newProductID}/variations/{$check_variation_exists_for_created_product[0]->id}", $variation_dataUpdate_newProduct);
						  
						} else {
							$updateVariationForNewProduct = $woocommerce->post("products/{$newProductID}/variations", $variation_dataUpdate_newProduct);
	   
						}
    			}
			
		    }
                            
                $countSuccessfullCreations++;
       } else {
           
                            $this->woo_lib_err_msg = $newProduct;
                        
                            $woo_pantheon_error_log = fopen(PADBSYNC_IMG_UPLOADS_DIR."woo_pantheon_error_log.txt", "a") or die("Unable to open post_file!");
                            $log_datetime_stamp = "Created on: ".date('Y-m-d H:i:s')."\n\n";
                            fwrite($woo_pantheon_error_log, $log_datetime_stamp);
                            fwrite($woo_pantheon_error_log, $newProduct."\n\n");
                            fclose($woo_pantheon_error_log);
                        }
                    } catch (HttpClientException $e) {
                        if ( $e->getMessage() == 'Error: Invalid or duplicated SKU. [product_invalid_sku]' ) {
                            $productIDtoUpdate = $sku_check_exists[0]->id;
                            $updateProduct = $woocommerce->put('products/'.$productIDtoUpdate, $data);
                            
                            if ( $updateProduct ) {
                                
                                $jsonToArr = json_encode( $updateProduct, true );
                                
                                $updatedProductID = $updateProduct->id;
                                
                                if ($slika != '' && count($updateProduct->images) < 1) {
                                    $this->uploadAndSetFeaturedImage($updatedProductID, $slika, $featured_image_tmp_location);
                                }
                             
                                
                                	// unlink - delete the temp image stored in the plugin uploads folder
        if ( file_exists( $featured_image_tmp_location) ) {
            unlink( $featured_image_tmp_location );
        }
                                
      //if proruct is variable, extract and create/update its base variation
      if ( isset($product['variationOptions']) && !empty($product['variationOptions']) ) {
			$variationOptions = explode(',', $product['variationOptions']);
			$variationStock = explode(',', $product['variationStock']);
       //  if($variationOptions != '') {
            $product_variations = wc_get_product($updatedProductID);
			$variations = $product_variations->get_available_variations();
			$variations_id = wp_list_pluck( $variations, 'variation_id' );                    

            if(is_array($variations_id) AND !empty($variations_id)) {
				foreach($variations_id as $varId) {	
					
						// check if variation exists
					//		$check_var_exists_data_for_created_product = [
					//			 'search' => $varId,
							  
					//		];
                    
			//		$check_variation_exists_for_created_product = $woocommerce->get("products/{$updatedProductID}/variations");
					
					foreach($variationOptions as $varOption) {
						$variation_dataUpdate_newProduct = [
						//    'regular_price' => (string)$cena,
						//    'sale_price' => $namalena_cena,
						//    'manage_stock' => $manage_stock,
						//    'stock_quantity' => $stock_qty,
						//    'stock_status' => $stock,
							'attributes'    => [
								[
									'name'  => 'Variation',
									'slug'  => 'pa_variation',
									'option' => $varOption,
								],
							],
						];
					}
			
					foreach($variationStock as $varStock) {	
				
						$variation_dataUpdate_newProduct = [
								'regular_price' => (string)$cena,
								'sale_price' => $namalena_cena,
								'manage_stock' => $manage_stock,
								'stock_quantity' => $varStock,
								'stock_status' => $stock,
						];
					}
						if($varId) {
								$updateVariationForNewProduct = $woocommerce->put("products/{$updatedProductID}/variations/{$varId}", $variation_dataUpdate_newProduct);

							} else {
								$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);

							}
				// 			if( !empty($check_variation_exists_for_created_product[0]->id) ) {
				// 				$updateVariationForNewProduct = $woocommerce->put("products/{$updatedProductID}/variations/{$check_variation_exists_for_created_product[0]->id}", $variation_dataUpdate_newProduct);

				// 			} else {
				// 				$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);

				// 			}
				}
			}else{	
					foreach($variationOptions as $varOption) {
						$variation_dataUpdate_newProduct = [
						//    'regular_price' => (string)$cena,
						//    'sale_price' => $namalena_cena,
						//    'manage_stock' => $manage_stock,
						//    'stock_quantity' => $stock_qty,
						//    'stock_status' => $stock,
							'attributes'    => [
								[
									'name'  => 'Variation',
									'slug'  => 'pa_variation',
									'option' => $varOption,
								],
							],
						];
					
			
						foreach($variationStock as $varStock) {	
					
							$variation_dataUpdate_newProduct = [
									'regular_price' => (string)$cena,
									'sale_price' => $namalena_cena,
									'manage_stock' => $manage_stock,
									'stock_quantity' => $varStock,
									'stock_status' => $stock,
							];
						}

						$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);
					}
                    }
         }
                                $countSuccessfullUpdates++;
                            }
                        }   
                    }
                } else {
               
                    $productIDtoUpdate = $sku_check_exists[0]->id;

                    $updateProduct = $woocommerce->put('products/'.$productIDtoUpdate, $data);
                    
                    if ($updateProduct) {

                        $jsonToArr = json_encode($updateProduct, true);
                        
                        $updatedProductID = $updateProduct->id;

                        if ($slika != '' && count($updateProduct->images) < 1) {
                            $this->uploadAndSetFeaturedImage($updatedProductID, $slika, $featured_image_tmp_location);
                        }
         
		// unlink - delete the temp image stored in the plugin uploads folder
        if ( file_exists( $featured_image_tmp_location) ) {
            unlink( $featured_image_tmp_location );
        }
                 //if proruct is variable, extract and create/update its base
                 if ( isset($product['variationOptions']) && !empty($product['variationOptions']) ) {
                $variationOptions = explode(',', $product['variationOptions']);
                $variationStock = explode(',', $product['variationStock']);
        //    if($variationOptions != '') {
	        $product_variations = wc_get_product($updatedProductID);
			$variations = $product_variations->get_available_variations();
			$variations_id = wp_list_pluck( $variations, 'variation_id' );
	

            if(is_array($variations_id) AND !empty($variations_id)) {
				foreach($variations_id as $varId) {	
					
						// check if variation exists
					//		$check_var_exists_data_for_created_product = [
					//			 'search' => $varId,
							  
					//		];
                    
			//		$check_variation_exists_for_created_product = $woocommerce->get("products/{$updatedProductID}/variations");
					
					foreach($variationOptions as $varOption) {
						$variation_dataUpdate_newProduct = [
						//    'regular_price' => (string)$cena,
						//    'sale_price' => $namalena_cena,
						//    'manage_stock' => $manage_stock,
						//    'stock_quantity' => $stock_qty,
						//    'stock_status' => $stock,
							'attributes'    => [
								[
									'name'  => 'Variation',
									'slug'  => 'pa_variation',
									'option' => $varOption,
								],
							],
						];
					}
			
					foreach($variationStock as $varStock) {	
				
						$variation_dataUpdate_newProduct = [
								'regular_price' => (string)$cena,
								'sale_price' => $namalena_cena,
								'manage_stock' => $manage_stock,
								'stock_quantity' => $varStock,
								'stock_status' => $stock,
						];
					}
						if($varId) {
								$updateVariationForNewProduct = $woocommerce->put("products/{$updatedProductID}/variations/{$varId}", $variation_dataUpdate_newProduct);

							} else {
								$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);

							}
				// 			if( !empty($check_variation_exists_for_created_product[0]->id) ) {
				// 				$updateVariationForNewProduct = $woocommerce->put("products/{$updatedProductID}/variations/{$check_variation_exists_for_created_product[0]->id}", $variation_dataUpdate_newProduct);

				// 			} else {
				// 				$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);

				// 			}
				}
			}else{	
					foreach($variationOptions as $varOption) {
						$variation_dataUpdate_newProduct = [
						//    'regular_price' => (string)$cena,
						//    'sale_price' => $namalena_cena,
						//    'manage_stock' => $manage_stock,
						//    'stock_quantity' => $stock_qty,
						//    'stock_status' => $stock,
							'attributes'    => [
								[
									'name'  => 'Variation',
									'slug'  => 'pa_variation',
									'option' => $varOption,
								],
							],
						];
					
			
						foreach($variationStock as $varStock) {	
					
							$variation_dataUpdate_newProduct = [
									'regular_price' => (string)$cena,
									'sale_price' => $namalena_cena,
									'manage_stock' => $manage_stock,
									'stock_quantity' => $varStock,
									'stock_status' => $stock,
							];
						}

						$updateVariationForNewProduct = $woocommerce->post("products/{$updatedProductID}/variations", $variation_dataUpdate_newProduct);
					}
				}

            }	
                        $countSuccessfullUpdates++;
                    }
                  
                }
            }
            
            $msg = $countSuccessfullCreations.' posts created. \n ' . $countSuccessfullUpdates.' products updated';
            
            return new WP_REST_Response(['message' => $msg]);
        }
        return new WP_REST_Response(['message' => 'BAD POST REQUEST OR INVALID JSON \n' . $this->woo_lib_err_msg]);
    }
    
    /**
     * Retrieve orders from the database 
     * */
    public function retrieveOrders() {
        $client = new Client(
             str_replace('/public', '', get_site_url()),
         //    get_site_url(),
            $this->consumer_key,
            $this->consumer_secret,
            [
                'wp_api'  => true,
                'version' => 'wc/v3',
                'query_string_auth' => true,
                'timeout' => PADBSYNC_CURL_TIMEOUT
            ]
        );
        
        global $wpdb;
        
        $query = "SELECT ID FROM {$wpdb->prefix}posts WHERE profaktura_status = %d AND post_type = %s ORDER BY post_date DESC";
        
        $sql = $wpdb->prepare($query, [0, 'shop_order']);
        
        $dbOrdersProStatZero = $wpdb->get_results($sql);
        
        $allOrders = $client->get('orders');
        
        $orders = [];
        
        if (count($allOrders) > 0 && count($dbOrdersProStatZero) > 0) {
            foreach ($allOrders as $o) {
                foreach ($dbOrdersProStatZero as $d) {
                    if ($o->id == $d->ID) {
                        $orders[] = $o;
                        continue;
                    }
                }
            } 
        }
        
        if (count($orders) < 1) {
            return new WP_REST_Response(['message' => 'No orders found!']);
        }
        
        return new WP_REST_Response($orders);
    }
    
    /**
     * Marks the queried order complete (profaktura_status = 1)
     * */
    public function markOrderReceived() {
        global $wpdb;
        
        $client = new Client(
            str_replace('/public', '', get_site_url()),
        //    get_site_url(),
            $this->consumer_key,
            $this->consumer_secret,
            [
                'wp_api'  => true,
                'version' => 'wc/v3',
                'query_string_auth' => true,
                'timeout' => PADBSYNC_CURL_TIMEOUT
            ]
        );
        
        $postData = $_POST['data'];
        
        $decoded = json_decode($postData, true);
        
        if (isset($postData) && $postData) {
            $strRep = str_replace(array("\r\n", "\r", "\n"), "", stripslashes($postData));
            $data = json_decode($strRep, true);
        }
        
        //check if item exists
        try {
            $order = $client->get('orders/'.$data['order_id']);
        } catch (HttpClientException $e) {
            return new WP_REST_Response(['message' => "Something went wrong!"]);
        }
        
        if (!$order) {
            return new WP_REST_Response(['message' => "No order found for such query!"]);
        }
       
        $result = $wpdb->update(
            $wpdb->prefix . "posts", 
            [ 'profaktura_status' => $data['profaktura_status'] ],
            [ 'ID' => $data['order_id'] ]
        );
        
        if (!$result) {
            return new WP_REST_Response(['message' => "Something went wrong!"]);
        }
        
        return new WP_REST_Response(['message' => "Status succesfully updated!"]);
    }
    
    public function inTableInsertPictureForIdent() {
		global $wpdb;
		
		$client = new Client(
			str_replace('/public', '', get_site_url()),
		//    get_site_url(),
			$this->consumer_key,
			$this->consumer_secret,
			[
				'wp_api'  => true,
				'version' => 'wc/v3',
				'query_string_auth' => true,
				'timeout' => PADBSYNC_CURL_TIMEOUT
			]
		);
		
		$postData = $_POST['data'];
        $decoded = json_decode($postData, true);
        
        if (isset($postData) && $postData) {
            $strRep = str_replace(array("\r\n", "\r", "\n"), "", stripslashes($postData));
            $data = json_decode($strRep, true);
        }
	    $temp_featured_image_check_image = false;
	    $featured_image_tmp_location_image = '';
	   
		 if (is_array($data) && !empty($data) ){
            foreach($data as $product) {
				
				 $ident = $product['acIdent'];
				 $pic = $product['acPicture'];
				 
				 $wpdb->insert(
					$wpdb->prefix . "icommerce_picture", 
					array('ac_ident' => $ident ,
						  'ac_picture' => $pic
						 ),
					array(
						'%s',
						'%s'
						)
    			);
			}
			echo "OK";
		 }
	}
    
    public function insertPictureForIdent() {
        global $wpdb;
        
        $client = new Client(
            str_replace('/public', '', get_site_url()),
        //    get_site_url(),
            $this->consumer_key,
            $this->consumer_secret,
            [
                'wp_api'  => true,
                'version' => 'wc/v3',
                'query_string_auth' => true,
                'timeout' => PADBSYNC_CURL_TIMEOUT
            ]
        );
	
	    $postData = $_POST['data'];
        $decoded = json_decode($postData, true);
        
        if (isset($postData) && $postData) {
            $strRep = str_replace(array("\r\n", "\r", "\n"), "", stripslashes($postData));
            $data = json_decode($strRep, true);
        }
	    $temp_featured_image_check_image = false;
	    $featured_image_tmp_location_image = '';
	   
		 if (is_array($data) && !empty($data) ){
            foreach($data as $product) {
                
            // FEATURED IMAGE
                 $current_img_path = '';

                if ( isset($product['acPicture']) && !empty($product['acPicture']) ) {
            						
                    $img_filename = base64_encode(microtime());   // Create random filename - smeni go so tvoja logika
                    $img_data = trim($product['acPicture']);
                	
                    $temp_type = substr($img_data, 0, 30);
                    $type_binary_check = "FFD8FFDB";  //jpg
                    if (substr($temp_type, 0, strlen($type_binary_check)) === $type_binary_check ) $img_type = '.jpg';
                		
                    $type_binary_check = "FFD8FFE000104A4649460001";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "FFD8FFEE";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "FFD8FFE1";  //jpg
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.jpg';
                
                    $type_binary_check = "89504E470D0A1A0A";  //png
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.png';
                
                    $type_binary_check = "424D";  //bmp
                    if ( substr( $temp_type, 0, strlen($type_binary_check) ) === $type_binary_check ) $img_type = '.bmp';
                
                    $img_data_bin = pack("H*", $img_data);
                    file_put_contents(PADBSYNC_IMG_UPLOADS_DIR.$img_filename.$img_type, $img_data_bin);
                  
                    $temp_featured_image_check_image = true;
                    
                    $current_img_path = $img_filename.$img_type;
                    
                    if ( $img_type == '.bmp' ) {
                        $bmpToPngIM = imagecreatefrombmp(PADBSYNC_IMG_UPLOADS_DIR.$img_filename.$img_type);
                        imagepng($bmpToPngIM, PADBSYNC_IMG_UPLOADS_DIR.$img_filename.'.png');
                        imagedestroy($bmpToPngIM);
                    }
                }
                
                 if ( $temp_featured_image_check_image ) {
                $slika_woo = get_site_url() . '/wp-content/plugins/icommerce-by-serafimov/uploads/' . $current_img_path;
                $featured_image_tmp_location_image = PADBSYNC_IMG_UPLOADS_DIR . $current_img_path;
                } else {
                    $slika_woo = '';
                }
            //    var_dump($slika_woo);die();
            //    var_dump($featured_image_tmp_location_image);die();
                $ident = $product['acIdent'];
	            $resIdent = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix . "icommerce_picture"." WHERE ac_ident = $ident"));
	
    	        $skuIdent = [
                        'sku' => $ident
                    ];
                
                $sku_check_sku = $client->get('products',$skuIdent);
                $image_sku = $sku_check_sku[0]->images;
              //  var_dump($image_sku);die();
		   	
		   	foreach ( $resIdent as $page )
            {
                $identTable = $page->ac_ident;
                $pictureTable = $page->ac_picture;
				$acPicData = trim($product['acPicture']);
            //    var_dump($ident);die();
            
              if($identTable == $ident)
                {
					if($pictureTable == $acPicData)
					{
						
    				  $wpdb->update(
    					$wpdb->prefix . "icommerce_picture", 
    					array('ac_ident' => $product['acIdent'] ,
                              'ac_picture' => $acPicData
                             ),
                        array(
                            '%s',
                            '%s'
                            )
    				);
    				
    				   echo "update";
					}
					else
					{
					    
        			    $upload_dir = wp_upload_dir();
                        foreach ( $image_sku as $img )
                        {
                            $img_src = strtolower($img->name);
                            $up = $upload_dir['basedir'].'/'.$img_src;
                            $images = glob( $up . "*".$img_type );
                            foreach( $images as $image ) {
                                unlink($image);
                            }
                        }
                            
                        $updateImageProductID = $sku_check_sku[0]->id;
        
                        if ($slika_woo != '') {
                            $this->uploadAndSetFeaturedImage($updateImageProductID, $slika_woo, $featured_image_tmp_location_image);
                        }
        // unlink - delete the temp image stored in the plugin uploads folder
                        if ( file_exists( $featured_image_tmp_location_image) ) {
                            unlink( $featured_image_tmp_location_image );
                        }
        
						echo "razlicno";
					}
                }
                else
                {

                      $wpdb->insert(
    					$wpdb->prefix . "icommerce_picture", 
    					array('ac_ident' => $product['acIdent'] ,
                              'ac_picture' => $product['acPicture']
                             ),
                        array(
                            '%s',
                            '%s'
                            )
    				);
                      echo "insert";
                }
      //      	var_dump($result);die();	   
// 				if (!$result) {
// 					return new WP_REST_Response(['message' => "Something went wrong!"]);
// 			}
        
//               return new WP_REST_Response(['message' => "Status succesfully inserted!"]);
            }
        }
		 }
    }
    
        public function uploadAndSetFeaturedImageWoo($postID, $imageUrl, $image_tmp_location, $image_src){
        // before uploading images, remove max time limit, then return to default limit
        $default = ini_get('max_execution_time');
        set_time_limit(0);
        
        $this->Generate_Featured_Image( $postID, $imageUrl );

        // unlink - delete the temp image stored in the plugin uploads folder
        if ( file_exists( $image_tmp_location ) ) {
            unlink( $image_tmp_location );
        }
        
        if ( file_exists( $image_src ) ) {
            unlink( $image_src );
        }

        // revert to default max time limit
        set_time_limit($default);
    }
    
    public function uploadAndSetFeaturedImage($postID, $imageUrl, $image_tmp_location){
        // before uploading images, remove max time limit, then return to default limit
        $default = ini_get('max_execution_time');
        set_time_limit(0);
        
        $this->Generate_Featured_Image( $postID, $imageUrl );

        // unlink - delete the temp image stored in the plugin uploads folder
        if ( file_exists( $image_tmp_location ) ) {
            unlink( $image_tmp_location );
        }

        // revert to default max time limit
        set_time_limit($default);
    }
    
    
    public function Generate_Featured_Image( $post_id = null, $url = null, $post_data = array()  ){
        if ( !$url || !$post_id ) return new WP_Error('missing', "Need a valid URL and post ID...");
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            // Download file to temp location, returns full server path to temp file, ex; /home/user/public_html/mysite/wp-content/26192277_640.tmp
            $tmp = download_url( $url );
         
            // If error storing temporarily, unlink
            if ( is_wp_error( $tmp ) ) {
                @unlink($file_array['tmp_name']);   // clean up
                $file_array['tmp_name'] = '';
                return $tmp; // output wp_error
            }
         
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);    // fix file filename for query strings
            $url_filename = basename($matches[0]);                                                  // extract filename from url for title
            $url_type = wp_check_filetype($url_filename);                                           // determine file type (ext and mime/type)
         
            // assemble file data (should be built like $_FILES since wp_handle_sideload() will be using)
            $file_array['tmp_name'] = $tmp;                                                         // full server path to temp file
     
                $file_array['name'] = $url_filename;
         
            // set additional wp_posts columns
            if ( empty( $post_data['post_title'] ) ) {
                $post_data['post_title'] = basename($url_filename, "." . $url_type['ext']);         // just use the original filename (no extension)
            }
         
            // make sure gets tied to parent
            if ( empty( $post_data['post_parent'] ) ) {
                $post_data['post_parent'] = $post_id;
            }
         
            // required libraries for media_handle_sideload
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
         
            // do the validation and storage stuff
            $att_id = media_handle_sideload( $file_array, $post_id, null, $post_data );             // $post_data can override the items saved to wp_posts table, like post_mime_type, guid, post_parent, post_title, post_content, post_status
         
            // If error storing permanently, unlink
            if ( is_wp_error($att_id) ) {
                @unlink($file_array['tmp_name']);   // clean up
                return $att_id; // output wp_error
            }
         
            // set as post thumbnail if desired
                set_post_thumbnail($post_id, $att_id);
         
            return $att_id;
    }
      
}


$iCommerce = new iCommerce();