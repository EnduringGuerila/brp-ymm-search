<?php
/**
 * Plugin Name: BRP YMM Search
 * Description: Customer can search for parts by vehicle year, make, and model. A fork of ymm-search from Pektsekye
 * Version: 1.0.12.8
 * Author: BRP / Tim Kirtland
 * Author URI: https://github.com/EnduringGuerila/brp-ymm-search
 * License: GPLv2     
 * Requires at least: 4.7
 * Tested up to: 5.2
 *
 * Text Domain: brp-ymm-search
 * Domain Path: /i18n/languages
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.5.1
 * 
 * @package BrpYmm
 * @author BRP / Tim Kirtland
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


final class Pektsekye_Ymm {


  protected static $_instance = null;

  protected $_pluginUrl; 
  protected $_pluginPath;    
  protected $_registry;
  
  public $_message = array();    


  public static function instance() {
    if ( is_null( self::$_instance ) ) {
      self::$_instance = new self();
      self::$_instance->initApp();
    }
    return self::$_instance;
  }


  public function __construct() {

    $this->_pluginPath = plugin_dir_path( __FILE__ );
    $this->_pluginUrl  = plugins_url('/', __FILE__ );
  }


  public function initApp() {
    $this->includes();
    $this->init_hooks();
    $this->init_controllers();
  }
  
  
  public function includes() {    
    include_once( 'Widget/Selector.php' );     
    include_once( 'Widget/HorizontalSelector.php' ); 
           
    if ( $this->is_request( 'admin' ) ) {      
      include_once( 'Block/Adminhtml/Ymm/Selector.php' );
      
      include_once( 'Block/Adminhtml/Product/Edit/Restriction.php' );
      new Pektsekye_Ymm_Block_Adminhtml_Product_Edit_Restriction();
      
      include_once( 'Block/Adminhtml/Product/QuickEdit.php' );
      new Pektsekye_Ymm_Block_Adminhtml_Product_QuickEdit();                          
    }    
  }
  

  private function init_hooks() {  
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts') );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts') );
    
    add_action( 'plugins_loaded', array( $this, 'load_textdomain') );     
    add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_schema' ), 20 );
    add_action( 'widgets_init', array( $this, 'register_widgets') );    
    add_action( 'admin_menu', array( $this, 'set_admin_menu' ), 70 );
     
    add_shortcode( 'ymm_selector', array( $this, 'show_selector_by_shortcode' ) );

    add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'product_option_js') ); // For Product Options for WooCommerce plugin to show Selected Vehicle in cart and order

    
    add_action('init', array( $this, 'ex_register_my_new_sitemap'), 99 );           
    add_filter('wpseo_sitemap_index', array( $this, 'ex_add_sitemap_custom_items'));
    add_action("wpseo_do_sitemap_ymm", array( $this, 'ex_generate_origin_combo_sitemap'));    
    add_filter( 'wpseo_enable_xml_sitemap_transient_caching', '__return_false'); //disable caching for testing      

  }


  public function maybe_upgrade_schema() {
    if (!class_exists('Pektsekye_Ymm_Setup_Install')) {
      include_once('Setup/Install.php');
    }
    Pektsekye_Ymm_Setup_Install::maybe_upgrade_schema();
  }


  function ex_register_my_new_sitemap() {
    global $wpseo_sitemaps;
    if ($wpseo_sitemaps){
      $wpseo_sitemaps->register_sitemap('ymm', array( $this, 'ex_generate_origin_combo_sitemap') );
    }
  }

  
  function ex_add_sitemap_custom_items(){
    global $wpseo_sitemaps;
    $date = date('c', time()); //current date
    $smp ='';
        $smp .= '<sitemap>' . "\n";
    $smp .= '<loc>' . site_url() .'/ymm-sitemap.xml</loc>' . "\n";
    $smp .= '<lastmod>' . htmlspecialchars( $date ) . '</lastmod>' . "\n";
    $smp .= '</sitemap>' . "\n";
    return $smp;
  }
  
  
  function ex_generate_origin_combo_sitemap(){
    global $wpseo_sitemaps;    
       
    $urls = array();    
    
    include_once( Pektsekye_YMM()->getPluginPath() . 'Model/Db.php');		
    $db =  new Pektsekye_Ymm_Model_Db();
          
    foreach ($db->getAllProductRestrictions() as $k => $r) {
      $category = isset($r['category']) ? $r['category'] : '';
      $make = $r['make'];
      $model = $r['model'];      
      $from = (int) $r['year_from'];
      $to = (int) $r['year_to'];
    
      $years = array(); 
         
      if ($from != 0 || $to != 0){   
        if ($from == 0){
          $years[] = $to;          
        } elseif ($to == 0 || $from == $to){
          $years[] = $from;
        } elseif ($from < $to){          	
          $years = range($from, $to);            
        }     
      }
      
      if (count($years) == 0){
        $urls[] = get_site_url(null, '/') . '?s=&ymm_search=1&post_type=product&_category='.urlencode($category).'&_make='.urlencode($make).'&_model='.urlencode($model);
      } else {
        foreach ($years as $year) {
          $urls[] = get_site_url(null, '/') . '?s=&ymm_search=1&post_type=product&_category='.urlencode($category).'&_year='.$year.'&_make='.urlencode($make).'&_model='.urlencode($model);
        }
      }
    }    

    $output = '';    
   
    foreach($urls as $url){
      $p = array();
      $p['mod'] = date('c', time());//current date
      $p['loc'] = $url;
      $p['chf'] = 'weekly';//possible values always, hourly, daily, weekly, monthly, yearly, never
      $p['pri'] = 1.0;//possible values 0, 0.1, 0.2, 0.3 ... 1
      $output .= $wpseo_sitemaps->renderer->sitemap_url($p);      
      
    }	
    
    if (empty($output)) {
      $wpseo_sitemaps->bad_sitemap = true;
      return;
    }
    //Build the full sitemap
    $sitemap = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
    $sitemap .= 'xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" ';
    $sitemap .= 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $sitemap .= $output . '</urlset>';
    //echo $sitemap;
    $wpseo_sitemaps->set_sitemap($sitemap);    
    
  }  


  private function init_controllers() {

    add_filter('woocommerce_product_tabs', array( $this, 'add_product_tabs' ) );
  
    if ($this->is_request('frontend')){
      include_once( 'Controller/Product.php' );
      $controller = new Pektsekye_Ymm_Controller_Product();    	
      add_filter('pre_get_posts', array( $controller, 'product_query'), 9);//call it before wc pre_get_posts
    }    

    if ( $this->is_request( 'admin' )){
      if ( isset($_GET['page']) && $_GET['page'] == 'ymm') {
        include_once( 'Controller/Adminhtml/Ymm/Selector.php' );
        add_action( 'init', array( new Pektsekye_Ymm_Controller_Adminhtml_Ymm_Selector(), 'execute' ) );
      }		  
    } 
    
    if ( $this->is_request( 'ajax' ) && isset($_GET['action'])){			
      if ($_GET['action'] == 'ymm_selector_fetch') {
        include_once( 'Controller/Selector.php' );
        $controller = new Pektsekye_Ymm_Controller_Selector();         
        add_action( 'wp_ajax_ymm_selector_fetch', array( $controller, 'fetch') );		    	       
        add_action( 'wp_ajax_nopriv_ymm_selector_fetch', array( $controller, 'fetch') );
        
      } elseif ($_GET['action'] == 'ymm_selector_get_categories') {
        include_once( 'Controller/Selector.php' );
        $controller = new Pektsekye_Ymm_Controller_Selector();		    
        add_action( 'wp_ajax_ymm_selector_get_categories', array( $controller, 'getCategories') );		    	       
        add_action( 'wp_ajax_nopriv_ymm_selector_get_categories', array( $controller, 'getCategories') );  
              
      } elseif ($_GET['action'] == 'ymm_restriction_search') {      
        include_once( 'Controller/Adminhtml/Ymm/Selector.php' );
        $controller = new Pektsekye_Ymm_Controller_Adminhtml_Ymm_Selector();        
        add_action( 'wp_ajax_ymm_restriction_search', array( $controller, 'searchRestrictions') );
      }	                					
    }  	                 	  
  }


  public function add_product_tabs($tabs) {
    if ($this->is_request( 'admin' )){ //WooCommerce Tab Manager
      return $tabs;
    }
        
    if (get_option('ymm_display_vehicle_fitment') != 'yes'){
      return $tabs;
    }
    
    include_once('Block/Product/View/Tabs/Restriction.php');      
    $block = new Pektsekye_Ymm_Block_Product_View_Tabs_Restriction();

    if (count($block->getRestrictions()) == 0){
      return $tabs;        
    }            

    $tabs['ymm'] = array(
      'title'    => __( 'Vehicle Fitment', 'brp-ymm-search'),
      'priority' => 40,
      'callback' => array($block, 'page_init')
    ); 
    
    return $tabs;
  }


  public function enqueue_admin_scripts() {
    global $pagenow;
        
    if (isset($_GET['page']) && $_GET['page'] == 'ymm') {		
      wp_enqueue_style('ymm-manage-selector', $this->_pluginUrl . 'view/adminhtml/web/ymm/main.css');
    } elseif ((isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] == 'edit')
            || ('post-new.php' == $pagenow && isset($_GET['post_type']) && $_GET['post_type'] == 'product')){  
      wp_enqueue_script('ymm_search_restriction', $this->_pluginUrl . 'view/adminhtml/web/product/edit/main.js', array('jquery', 'jquery-ui-widget'));
      wp_enqueue_style('ymm_search_restriction', $this->_pluginUrl . 'view/adminhtml/web/product/edit/main.css');		    
    }		  
  }


  public function enqueue_frontend_scripts() {
    wp_enqueue_script('ymm', $this->_pluginUrl . 'view/frontend/web/main.js', array('jquery', 'jquery-ui-widget', 'jquery-cookie'));
    wp_enqueue_style( 'ymm', $this->_pluginUrl . 'view/frontend/web/main.css' );	
    if (get_post_type() == 'product') {
      wp_enqueue_style( 'ymm_product_restriction', $this->_pluginUrl . 'view/frontend/web/product/restriction.css' );
    }      	  		  			
  }


  public function load_textdomain() {
    load_plugin_textdomain( 'brp-ymm-search', false, basename( dirname( __FILE__ ) ) . '/i18n/languages' ); 
  }
  
  
  public function register_widgets() {
    register_widget('Pektsekye_Ymm_Widget_Selector');
    register_widget('Pektsekye_Ymm_Widget_HorizontalSelector');      
  }


  public function set_admin_menu() {
    add_menu_page( _x( 'BRP YMM Search', 'Admin menu', 'brp-ymm-search'), _x( 'BRP YMM Search', 'Admin menu', 'brp-ymm-search'), 'manage_woocommerce', 'ymm', array( new Pektsekye_Ymm_Block_Adminhtml_Ymm_Selector(), 'page_init' ) );  
  }
  

  public function show_selector_by_shortcode($atts) {
  
    include_once($this->getPluginPath() . 'Block/Selector.php');
          
    $block = new Pektsekye_Ymm_Block_Selector();
    $block->setWidgetId('content');
   
    if (isset($atts['template'])){
      $template = str_replace('/', '', trim($atts['template'])); // do not allow to change directory
      $block->setTemplate($template);
    } 
    
    $garageEnabled = isset($atts['garage']) && $atts['garage'] == 1 ? 1 : 0;
    $block->setGarageEnabled($garageEnabled);
	$block->setFilterCategoryPage(false);
           
    ob_start();

    $block->page_init();

    $contents = ob_get_clean();
    
    return $contents;
  }    

  public function product_option_js() {
  
    include_once($this->getPluginPath() . 'Block/Selector.php');
          
    $block = new Pektsekye_Ymm_Block_Selector();    
    $values = $block->getSelectedValues();
    
    if (empty($values)){
      return ''; 
    }
    
    $vehicle = implode(' ', $values);
?>
<script type="text/javascript">
  var vehicle = '<?php echo str_replace("'", "", strip_tags($vehicle));?>';
  jQuery('input[type="text"].pofw-option').val(vehicle);
</script>
<?php

  }
  

  private function is_request( $type ) {
    switch ( $type ) {
      case 'admin' :
        return is_admin();
      case 'ajax' :
        return defined( 'DOING_AJAX' );
      case 'cron' :
        return defined( 'DOING_CRON' );
      case 'frontend' :
        return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
    }
  }
  
  
  public function getPluginUrl() {
    return $this->_pluginUrl;
  }
  
  
  public function getPluginPath() {
    return $this->_pluginPath;
  }


  public function register($key, $value) {
    return $this->_registry[$key] = $value;
  }    
    
      
  public function registry($key) {
    return isset($this->_registry[$key]) ? $this->_registry[$key] : null;
  }  
            
  
  public function setMessage($message, $type = 'text') {
    if ($type == 'error_lines'){
      $this->_message[$type][] = $message;        
    } else {    
      $this->_message[$type] = $message;                 
    }    
  }


  public function getMessage() {
    return $this->_message;
  }    
    
}


function Pektsekye_YMM() {
	return Pektsekye_Ymm::instance();
}

include_once('Setup/Install.php');
register_activation_hook(__FILE__, array('Pektsekye_Ymm_Setup_Install', 'install'));

// If WooCommerce plugin is installed and active.
if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins', array())) || in_array('woocommerce/woocommerce.php', array_keys((array) get_site_option('active_sitewide_plugins', array())))){
  Pektsekye_YMM();
}


//disable autoupdates
add_filter( 'http_request_args', 'ymm_widget_disable_update', 10, 2 );
function ymm_widget_disable_update( $r, $url ) {
    if ( 0 === strpos( $url, 'https://api.wordpress.org/plugins/update-check/' ) ) {
        $my_plugin = plugin_basename( __FILE__ );
        $plugins = json_decode( $r['body']['plugins'], true );
        unset( $plugins['plugins'][$my_plugin] );
        $r['body']['plugins'] = json_encode( $plugins );
    }
    return $r;
}
