<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Pektsekye_Ymm_Model_Db_CsvImportHandler
{

    protected $_db;
    protected $_config; 
       
    protected $_delimiter = ',';
    
    
    public function __construct() 
    {
        include_once( Pektsekye_YMM()->getPluginPath() . 'Model/Db.php');		
        $this->_db =  new Pektsekye_Ymm_Model_Db(); 
            
        include_once( Pektsekye_YMM()->getPluginPath() . 'etc/config.php');		
        $this->_config = new Pektsekye_Ymm_Config();                                         
    }


    public function importFromCsvFile($file, $mode)
    {
      if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        throw new Exception(__('Please select a .csv file and then click the Import button', 'ymm-search'));
      }
      
      $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
      if (strtolower($fileExt) != 'csv') {
        throw new Exception(sprintf(__('Invalid file type "%s". Please upload a .csv file.', 'ymm-search'), $file['name']));
      }        
      
      $rows = array();
      
      ini_set("auto_detect_line_endings", true);
            
      if (($handle = fopen($file['tmp_name'], "r" )) !== false) {
        while (($row = fgetcsv($handle, 0, $this->_delimiter)) !== false) {
          $rows[] = $row;
        }
        fclose($handle);
      }

      if (count($rows) == 0) {
        throw new Exception(sprintf(__('The file "%s" is empty', 'ymm-search'), $file['name']));
      }

      $fieldNames = $this->_config->getCsvColumnNames();
      $legacyFieldNames = array_values(array_diff($fieldNames, array('product_id')));

      $header = array_map('trim', $rows[0]);

      $isNewHeader = count($header) == count($fieldNames) && $header === $fieldNames;
      $isLegacyHeader = count($header) == count($legacyFieldNames) && $header === $legacyFieldNames;

      if ($isLegacyHeader){
        $fieldNames = $legacyFieldNames;
      } elseif (!$isNewHeader){
        throw new Exception(sprintf(__('The first row in the .csv file must contain correct column names in this order: "%s" (or legacy format: "%s").', 'ymm-search'), implode('","', $this->_config->getCsvColumnNames()), implode('","', $legacyFieldNames)));
        return;
      }

      $productIdsById = $this->_db->getProductIdsById();
      $productIdsBySku = $this->_db->getProductIdsBySku();
      
      if ($mode == 'delete_old'){
        $this->_db->emptyTable();
      }
      
      
      $data = array();
      
      $countRows = 0;    
      foreach ($rows as $rowIndex => $row) {
        
        if ($rowIndex == 0) // skip first row with column names
          continue;
    
        if (count($row) == 1 && $row[0] === null) // skip empty lines
          continue;
                             
        $d = array();       
        foreach ($fieldNames as $k => $v){
          $d[$v] = isset($row[$k]) ? trim($row[$k]) : '';
        }
        
        $productId = isset($d['product_id']) ? trim($d['product_id']) : '';
        $productSku = isset($d['product_sku']) ? trim($d['product_sku']) : '';
        $resolvedProductId = 0;

        if ($productId !== ''){
          $normalizedProductId = (string) (int) $productId;
          if ($normalizedProductId !== '0' && isset($productIdsById[$normalizedProductId])){
            $resolvedProductId = $productIdsById[$normalizedProductId];
          } else {
            Pektsekye_YMM()->setMessage(sprintf(__('Row #%d was not imported. The product with ID "%s" does not exist.', 'ymm-search'), $rowIndex, $productId), 'error_lines');
            continue;
          }

        } else {
          if (empty($productSku)){
            Pektsekye_YMM()->setMessage(sprintf(__('Row #%d was not imported. Provide "product_id" or "product_sku".', 'ymm-search'), $rowIndex), 'error_lines');
            continue;
          }

          if (!isset($productIdsBySku[$productSku])){
            Pektsekye_YMM()->setMessage(sprintf(__('Row #%d was not imported. The product with SKU "%s" does not exist.', 'ymm-search'), $rowIndex, $productSku), 'error_lines');
            continue;
          }

          $resolvedProductId = $productIdsBySku[$productSku];
        }
                
        $d['year_from'] = (int) $d['year_from'];
        $d['year_to'] = (int) $d['year_to']; 
               
        if ($d['year_from'] > 0){
          if ($d['year_from'] < 1950){
            $d['year_from'] = 1950;
          } elseif ($d['year_from'] > 2040){
            $d['year_from'] = 2040;
          }                        
        }
        
        if ($d['year_to'] > 0){
          if ($d['year_to'] < 1950){
            $d['year_to'] = 1950;
          } elseif ($d['year_to'] > 2040){
            $d['year_to'] = 2040;
          }                        
        }   
        
        $data[] = array(
          $resolvedProductId,
          $d['make'],
          $d['model'],
          $d['year_from'],
          $d['year_to'],
          $d['note']
        );
        
        if ($countRows % 1000 == 0){
          $this->_db->addValues($data);
          $data = array();            
        }
        
        $countRows++;        
      }           
         
      if (count($data) > 0)
        $this->_db->addValues($data);
 
                       
    }

}
