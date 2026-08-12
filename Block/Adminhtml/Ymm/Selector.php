<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


class Pektsekye_Ymm_Block_Adminhtml_Ymm_Selector {


  protected $_db;  



	public function __construct() {
    include_once( Pektsekye_YMM()->getPluginPath() . 'Model/Db.php');		
		$this->_db = new Pektsekye_Ymm_Model_Db();  
	}
 
 
 
  public function page_init()
  {
    include_once( Pektsekye_YMM()->getPluginPath() . 'view/adminhtml/templates/ymm/selector.php');
  }



  public function hasYmmData() 
  {
    return $this->_db->hasVehicleData();
  }
  
  
  
  public function getMessage() 
  {
    return Pektsekye_YMM()->getMessage();
  }



  public function getDisplayVehicleFitment() 
  {
    return get_option('ymm_display_vehicle_fitment') == 'yes';
  }  
  
  
  
  public function getDisplayCategoryDropdowns() 
  {
    return get_option('ymm_enable_category_dropdowns') == 'yes';
  }
  
  
  
  public function getDisplaySearchField() 
  {
    return get_option('ymm_enable_search_field') == 'yes';
  }  
  
  
  
  public function getExcludedCategoryIds() 
  {
    $excluded = get_option('ymm_excluded_category_ids', '');
    if (empty($excluded)) {
      // Default to the original hardcoded exclusions if no configuration is set
      return '48,2266,80,5706,376,5351,2268';
    }
    return $excluded;
  }  
  
  
}
