<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Pektsekye_Ymm_Model_Db
{


    protected $_wpdb;
    protected $_mainTable;
    protected $_config;         

        
    public function __construct() {
			global $wpdb;
			
			$this->_wpdb = $wpdb;   
      $this->_mainTable = "{$wpdb->base_prefix}ymm"; 
            
      include_once( Pektsekye_YMM()->getPluginPath() . 'etc/config.php');		
      $this->_config = new Pektsekye_Ymm_Config();              
    }    


     public function fetchColumnValues($params = array(), $categoryId = 0)     
    {
      $values = array();

      $whereProducts = '';
      if ($categoryId > 0){
        $productIds = $this->getProductIdsOfCategory($categoryId);
        if (count($productIds) > 0){
          $whereProducts = ' AND product_id IN ('.implode(',', $productIds).')';
        }
      }
      
      $nextlevel = count($params);
            
      if ($nextlevel == 0){
        $select = "SELECT DISTINCT category FROM {$this->_mainTable} WHERE category != '' {$whereProducts} ORDER BY category";
        $values = (array) $this->_wpdb->get_col($select);

      } else if ($nextlevel == 1){
        $category = esc_sql($params[0]);
        $select = "SELECT DISTINCT year_from, year_to FROM {$this->_mainTable} WHERE category = '{$category}' AND (year_from != 0 OR year_to != 0) {$whereProducts}";
        $rows = (array) $this->_wpdb->get_results($select, ARRAY_A);

        $y = array();

        // Derive expansion bounds from real data so 0/0 does not force an arbitrary 1950-2040 range.
        $minYear = null;
        $maxYear = null;
        foreach ($rows as $r) {
          $fromRaw = (int) $r['year_from'];
          $toRaw = (int) $r['year_to'];

          if ($fromRaw > 0){
            $minYear = is_null($minYear) ? $fromRaw : min($minYear, $fromRaw);
            $maxYear = is_null($maxYear) ? $fromRaw : max($maxYear, $fromRaw);
          }

          if ($toRaw > 0){
            $minYear = is_null($minYear) ? $toRaw : min($minYear, $toRaw);
            $maxYear = is_null($maxYear) ? $toRaw : max($maxYear, $toRaw);
          }
        }

        // If category has only open-ended rows, fall back to global non-zero years.
        if (is_null($minYear) || is_null($maxYear)){
          $boundsSelect = "
            SELECT MIN(val) AS min_year, MAX(val) AS max_year
            FROM (
              SELECT year_from AS val FROM {$this->_mainTable} WHERE year_from > 0 {$whereProducts}
              UNION ALL
              SELECT year_to AS val FROM {$this->_mainTable} WHERE year_to > 0 {$whereProducts}
            ) years
          ";
          $bounds = (array) $this->_wpdb->get_row($boundsSelect, ARRAY_A);
          if (!empty($bounds)){
            if (!is_null($bounds['min_year'])){
              $minYear = (int) $bounds['min_year'];
            }
            if (!is_null($bounds['max_year'])){
              $maxYear = (int) $bounds['max_year'];
            }
          }
        }

        // Last-resort safety bound if the table contains only zero years.
        if (is_null($minYear) || is_null($maxYear)){
          $currentYear = (int) date('Y');
          $minYear = $currentYear;
          $maxYear = $currentYear;
        }

        foreach ($rows as $r) {
          $from = (int) $r['year_from'];
          $to = (int) $r['year_to'];

          if ($from == 0 && $to == 0){
            $from = $minYear;
            $to = $maxYear;
          } elseif ($from == 0){
            $from = $minYear;
          } elseif ($to == 0){
            $to = $maxYear;
          }

          if ($from == $to){
            $y[$from] = 1;
          } elseif ($from < $to){
            while ($from <= $to){
              $y[$from] = 1;
              $from++;
            }
          } else {
            // Guard against malformed ranges where year_from > year_to.
            $y[$from] = 1;
            $y[$to] = 1;
          }
        }

        krsort($y);
        $values = array_keys($y);

      } else if ($nextlevel == 2){
        $category = esc_sql($params[0]);
        $year = (int) $params[1];

        $select = "SELECT make, year_from, year_to FROM {$this->_mainTable} WHERE category = '{$category}' AND make != '' {$whereProducts}";
        $rows = (array) $this->_wpdb->get_results($select, ARRAY_A);

        $globalMin = null;
        $globalMax = null;
        $boundsByMake = array();

        foreach ($rows as $r){
          $makeKey = (string) $r['make'];
          $fromRaw = (int) $r['year_from'];
          $toRaw = (int) $r['year_to'];

          if (!isset($boundsByMake[$makeKey])){
            $boundsByMake[$makeKey] = array('min' => null, 'max' => null);
          }

          if ($fromRaw > 0){
            $boundsByMake[$makeKey]['min'] = is_null($boundsByMake[$makeKey]['min']) ? $fromRaw : min($boundsByMake[$makeKey]['min'], $fromRaw);
            $boundsByMake[$makeKey]['max'] = is_null($boundsByMake[$makeKey]['max']) ? $fromRaw : max($boundsByMake[$makeKey]['max'], $fromRaw);
            $globalMin = is_null($globalMin) ? $fromRaw : min($globalMin, $fromRaw);
            $globalMax = is_null($globalMax) ? $fromRaw : max($globalMax, $fromRaw);
          }

          if ($toRaw > 0){
            $boundsByMake[$makeKey]['min'] = is_null($boundsByMake[$makeKey]['min']) ? $toRaw : min($boundsByMake[$makeKey]['min'], $toRaw);
            $boundsByMake[$makeKey]['max'] = is_null($boundsByMake[$makeKey]['max']) ? $toRaw : max($boundsByMake[$makeKey]['max'], $toRaw);
            $globalMin = is_null($globalMin) ? $toRaw : min($globalMin, $toRaw);
            $globalMax = is_null($globalMax) ? $toRaw : max($globalMax, $toRaw);
          }
        }

        if (is_null($globalMin) || is_null($globalMax)){
          $currentYear = (int) date('Y');
          $globalMin = $currentYear;
          $globalMax = $currentYear;
        }

        $matchedMakes = array();

        foreach ($rows as $r){
          $make = (string) $r['make'];
          $from = (int) $r['year_from'];
          $to = (int) $r['year_to'];

          $makeMin = isset($boundsByMake[$make]) ? $boundsByMake[$make]['min'] : null;
          $makeMax = isset($boundsByMake[$make]) ? $boundsByMake[$make]['max'] : null;

          if ($from == 0 && $to == 0){
            $from = is_null($makeMin) ? $globalMin : $makeMin;
            $to = is_null($makeMax) ? $globalMax : $makeMax;
          } elseif ($from == 0){
            $from = is_null($makeMin) ? $globalMin : $makeMin;
          } elseif ($to == 0){
            $to = is_null($makeMax) ? $globalMax : $makeMax;
          }

          if ($from <= $year && $to >= $year){
            $matchedMakes[$make] = 1;
          }
        }

        $values = array_keys($matchedMakes);
        natcasesort($values);
        $values = array_values($values);
      } else {
        $category = esc_sql($params[0]);
        $year = (int) $params[1];
        $make = esc_sql($params[2]);

        $select = "SELECT model, year_from, year_to FROM {$this->_mainTable} WHERE category = '{$category}' AND make = '{$make}' AND model != '' {$whereProducts}";
        $rows = (array) $this->_wpdb->get_results($select, ARRAY_A);

        $globalMin = null;
        $globalMax = null;
        $boundsByModel = array();

        foreach ($rows as $r){
          $modelKey = (string) $r['model'];
          $fromRaw = (int) $r['year_from'];
          $toRaw = (int) $r['year_to'];

          if (!isset($boundsByModel[$modelKey])){
            $boundsByModel[$modelKey] = array('min' => null, 'max' => null);
          }

          if ($fromRaw > 0){
            $boundsByModel[$modelKey]['min'] = is_null($boundsByModel[$modelKey]['min']) ? $fromRaw : min($boundsByModel[$modelKey]['min'], $fromRaw);
            $boundsByModel[$modelKey]['max'] = is_null($boundsByModel[$modelKey]['max']) ? $fromRaw : max($boundsByModel[$modelKey]['max'], $fromRaw);
            $globalMin = is_null($globalMin) ? $fromRaw : min($globalMin, $fromRaw);
            $globalMax = is_null($globalMax) ? $fromRaw : max($globalMax, $fromRaw);
          }

          if ($toRaw > 0){
            $boundsByModel[$modelKey]['min'] = is_null($boundsByModel[$modelKey]['min']) ? $toRaw : min($boundsByModel[$modelKey]['min'], $toRaw);
            $boundsByModel[$modelKey]['max'] = is_null($boundsByModel[$modelKey]['max']) ? $toRaw : max($boundsByModel[$modelKey]['max'], $toRaw);
            $globalMin = is_null($globalMin) ? $toRaw : min($globalMin, $toRaw);
            $globalMax = is_null($globalMax) ? $toRaw : max($globalMax, $toRaw);
          }
        }

        if (is_null($globalMin) || is_null($globalMax)){
          $currentYear = (int) date('Y');
          $globalMin = $currentYear;
          $globalMax = $currentYear;
        }

        $matchedModels = array();

        foreach ($rows as $r){
          $model = (string) $r['model'];
          $from = (int) $r['year_from'];
          $to = (int) $r['year_to'];

          $modelMin = isset($boundsByModel[$model]) ? $boundsByModel[$model]['min'] : null;
          $modelMax = isset($boundsByModel[$model]) ? $boundsByModel[$model]['max'] : null;

          if ($from == 0 && $to == 0){
            $from = is_null($modelMin) ? $globalMin : $modelMin;
            $to = is_null($modelMax) ? $globalMax : $modelMax;
          } elseif ($from == 0){
            $from = is_null($modelMin) ? $globalMin : $modelMin;
          } elseif ($to == 0){
            $to = is_null($modelMax) ? $globalMax : $modelMax;
          }

          if ($from <= $year && $to >= $year){
            $matchedModels[$model] = 1;
          }
        }

        $values = array_keys($matchedModels);
        natcasesort($values);
        $values = array_values($values);
      }
      
      return $values;             
    }
    
    
    
     public function filterVehiclesForCategory($vehicles, $categoryId)     
    {
      $filteredVehicles = array();

      $productIds = $this->getProductIdsOfCategory((int)$categoryId);

      if (count($productIds) == 0)
        return array();
            
      foreach((array)$vehicles as $vehicle){
        $values = explode(',', $vehicle);
        if (count($values) < 3){
          continue;
        }

        if (count($values) >= 4){
          $category = esc_sql($values[0]);
          $year = (int) $values[1];
          $make = esc_sql($values[2]);
          $model = esc_sql($values[3]);
          $select = "SELECT make FROM {$this->_mainTable} WHERE category = '{$category}' AND make = '{$make}' AND model = '{$model}' AND (year_from <= {$year} and year_to >= {$year}) AND product_id IN (" . implode(',', $productIds) . ") LIMIT 1";
        } else {
          $year = (int) $values[0];
          $make = esc_sql($values[1]);
          $model = esc_sql($values[2]);
          $select = "SELECT make FROM {$this->_mainTable} WHERE make = '{$make}' AND model = '{$model}' AND (year_from <= {$year} and year_to >= {$year}) AND product_id IN (" . implode(',', $productIds) . ") LIMIT 1";
        }
        $result = $this->_wpdb->get_var($select);

        if ($result){
          $filteredVehicles[] = $vehicle;    
        }   
      }
      return $filteredVehicles;             
    }
    
    
          
     public function getProductIds($values)
    {    
      $level = count($values);

      // Backward compatibility for old cookie/url format: year, make, model.
      if ($level >= 3 && is_numeric($values[0])){
        $year = (int) $values[0];

        if ($level == 3){
          $select = "SELECT DISTINCT product_id FROM {$this->_mainTable} WHERE (year_from <= {$year} or year_from=0) AND (year_to >= {$year} or year_to=0) AND (make = '".esc_sql($values[1])."' or make = '') AND (model = '".esc_sql($values[2])."' or model = '')";
          return (array) $this->_wpdb->get_col($select);
        }
      }

      $category = esc_sql($values[0]);
      $year = isset($values[1]) ? (int) $values[1] : 0;
              
      if ($level == 1){      
        $select = "SELECT DISTINCT product_id FROM {$this->_mainTable} WHERE (category = '{$category}' or category = '')";
      } else if ($level == 2){
        $select = "SELECT DISTINCT product_id FROM {$this->_mainTable} WHERE (category = '{$category}' or category = '') AND (year_from <= {$year} or year_from=0) AND (year_to >= {$year} or year_to=0)";
      } else if ($level == 3){
        $select = "SELECT DISTINCT product_id FROM {$this->_mainTable} WHERE (category = '{$category}' or category = '') AND (year_from <= {$year} or year_from=0) AND (year_to >= {$year} or year_to=0) AND (make = '".esc_sql($values[2])."' or make = '')";
      } else {
        $select = "SELECT DISTINCT product_id FROM {$this->_mainTable} WHERE (category = '{$category}' or category = '') AND (year_from <= {$year} or year_from=0) AND (year_to >= {$year} or year_to=0) AND (make = '".esc_sql($values[2])."' or make = '') AND (model = '".esc_sql($values[3])."' or model = '')";
      }

      return (array) $this->_wpdb->get_col($select);    
    }



     public function searchRestrictions($query)     
    {
      $where = '';
  
      $words = preg_split("/\s+/", $query);           
      foreach ($words as $word){
        $w = "make LIKE '%".esc_sql($word)."%' OR model LIKE '%".esc_sql($word)."%'" ;  
        $where .= ($where != '' ? ' AND ' : '') . "({$w})";          
      }
  
      $select = "SELECT DISTINCT CONCAT_WS(', ', category, make, model, year_from, year_to, note) as restriction  FROM {$this->_mainTable} WHERE {$where} ORDER BY category, make, model, year_from, year_to LIMIT 64;";

      return (array) $this->_wpdb->get_col($select);         
    }
    
    
        
     public function getSampleVehicleData()     
    {
      $data = array(
        array("100001","H4184","Dirt Bikes","Daihatsu","Altis","2000","2008","note here"),
        array("100002","PPF5471","Dirt Bikes","Lexus","ES300","1992","1997",""),
        array("100002","PPF5471","Dirt Bikes","Lexus","GS300","1997","1999",""),
        array("100002","PPF5471","ADV Bikes","Lexus","RX300","1999","2003",""),
        array("100003","PPF5497","Truck","Toyota","Avalon","1999","2003",""),
        array("100003","PPF5497","Truck","Toyota","Caldina","1997","2008",""),
        array("100004","PPF5493","UTV/SXS","Toyota","Camry","1993","2000",""),
        array("100005","PPF5077","UTV/SXS","Toyota","Carina","1993","1998",""),
        array("100006","H4061","ADV Bikes","BMW","X5","2004","2008","")
      );
      
      $numberOfProducts = 6;
      $productIdsBySku = $this->getProductIdsBySku($numberOfProducts);
      
      if (count($productIdsBySku) == $numberOfProducts){ // if there are enough products we can use existing product SKUs for sample data
        $productSkus = array_keys($productIdsBySku);
        $data = array(
          array($productIdsBySku[$productSkus[0]],$productSkus[0],"Dirt Bikes","Daihatsu","Altis","2000","2008","note here"),
          array($productIdsBySku[$productSkus[1]],$productSkus[1],"Dirt Bikes","Lexus","ES300","1992","1997",""),
          array($productIdsBySku[$productSkus[1]],$productSkus[1],"Dirt Bikes","Lexus","GS300","1997","1999",""),
          array($productIdsBySku[$productSkus[1]],$productSkus[1],"ADV Bikes","Lexus","RX300","1999","2003",""),
          array($productIdsBySku[$productSkus[2]],$productSkus[2],"Truck","Toyota","Avalon","1999","2003",""),
          array($productIdsBySku[$productSkus[2]],$productSkus[2],"Truck","Toyota","Caldina","1997","2008",""),
          array($productIdsBySku[$productSkus[3]],$productSkus[3],"UTV/SXS","Toyota","Camry","1993","2000",""),
          array($productIdsBySku[$productSkus[4]],$productSkus[4],"UTV/SXS","Toyota","Carina","1993","1998",""),
          array($productIdsBySku[$productSkus[5]],$productSkus[5],"ADV Bikes","BMW","X5","2004","2008","")
        );
      }
      
      return $data;     
    }      

      
     public function getProductRestrictions($productId)     
    {
      $productId = (int) $productId;    
         
      $select = "
        SELECT category, make, model, year_from, year_to, note
        FROM {$this->_mainTable} 
		    WHERE product_id = {$productId} 
		    ORDER BY category, make
      ";
      
      return (array) $this->_wpdb->get_results($select, ARRAY_A);         
    }
    
    
     public function getAllProductRestrictions()     
    {
      $select = "
        SELECT DISTINCT category, make, model, year_from, year_to
        FROM {$this->_mainTable} 
		    ORDER BY category, make
      ";
      
      return (array) $this->_wpdb->get_results($select, ARRAY_A);         
    }
       
          
     public function getProductRestrictionText($productId)     
    {          
      $text = '';
    
      $result = $this->getProductRestrictions($productId);     
      foreach ($result as $row){
        $text .= "{$row['category']}, {$row['make']}, {$row['model']}, {$row['year_from']}, {$row['year_to']}, {$row['note']}\n";
      }
      
      return $text;     
    }
    
    
    
     public function saveProductRestrictionText($productId, $restriction)     
    {
      $productId = (int) $productId;    
         


      $data = array();
      
      $fieldNames = $this->_config->getCsvColumnNames();

      // Product-level restriction textarea contains only category/make/model/year/note columns.
      // CSV headers include product columns that should be ignored here.
      $fieldNames = array_values(array_diff($fieldNames, array('product_id', 'product_sku')));
          
      $numberOfFields = count($fieldNames);
      
      $lines = explode("\n", $restriction);
      foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line))
          continue;
        
        $values = explode(',', $line);
        
        if (count($values) != $numberOfFields){
          throw new Exception();          
          return;          
        }

        $category = trim($values[0]);
        if ($category == ''){
          $category = 'Needs Cat';
        }

        $make = trim($values[1]);
        $model = trim($values[2]);
        $yearFrom = (int) $values[3];
        $yearTo = (int) $values[4];
        $note = isset($values[5]) ? $values[5] : '';
               
        if ($yearFrom > 0){
          if ($yearFrom < 1950){
            $yearFrom = 1950;
          } elseif ($yearFrom > 2040){
            $yearFrom = 2040;
          }                        
        }
        
        if ($yearTo > 0){
          if ($yearTo < 1950){
            $yearTo = 1950;
          } elseif ($yearTo > 2040){
            $yearTo = 2040;
          }                        
        }        
                                
        $data[] = array($productId, $category, $make, $model, $yearFrom, $yearTo, $note);
      }
      
      $this->_wpdb->query("DELETE FROM {$this->_mainTable} WHERE product_id = {$productId}"); 
      
      if (count($data) > 0){
        $this->addValues($data);
      }                   
    }    



     public function getVehicleData($limit = 0)     
    {
      $queryLimit = $limit > 0 ? " LIMIT {$limit} " : '';    
    
      $select = "
        SELECT DISTINCT posts.ID as product_id, IF(LENGTH(postmeta.meta_value)>0, postmeta.meta_value, posts.ID) as product_sku, ymm.category, ymm.make, ymm.model, ymm.year_from, ymm.year_to, ymm.note
        FROM {$this->_wpdb->posts} AS posts 
		    LEFT JOIN {$this->_wpdb->postmeta} AS postmeta 
		      ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_sku' 
        JOIN {$this->_mainTable} ymm 
          ON ymm.product_id = posts.ID 
		    WHERE posts.post_type = 'product'
		    ORDER BY posts.ID 
		    {$queryLimit}		            
      ";
      
      return (array) $this->_wpdb->get_results($select, ARRAY_N);     
    }              
 
 
 
     public function hasVehicleData()     
    {
      $value = $this->_wpdb->get_var("SELECT EXISTS (SELECT 1 FROM {$this->_mainTable})");
      return $value == 1;         
    }
          
      
      
    public function getProductIdsBySku($limit = 0)
    {
      $queryLimit = $limit > 0 ? " LIMIT {$limit} " : '';
      
      $select = "
        SELECT IF(LENGTH(postmeta.meta_value)>0, postmeta.meta_value, posts.ID) as product_sku, posts.ID as product_id 
        FROM {$this->_wpdb->posts} AS posts 
		    LEFT JOIN {$this->_wpdb->postmeta} AS postmeta 
		      ON postmeta.post_id = posts.ID AND postmeta.meta_key = '_sku' 
		    WHERE posts.post_type = 'product'
		    {$queryLimit}          
      ";
      $result = (array) $this->_wpdb->get_results($select, ARRAY_A);

      $productIds = array();
      foreach ($result as $row){
        $productIds[$row['product_sku']] = $row['product_id'];
      }
       
      return $productIds;   
    }


    public function getProductIdsById($limit = 0)
    {
      $queryLimit = $limit > 0 ? " LIMIT {$limit} " : '';

      $select = "
        SELECT posts.ID as product_id
        FROM {$this->_wpdb->posts} AS posts
		    WHERE posts.post_type = 'product'
		    {$queryLimit}
      ";
      $result = (array) $this->_wpdb->get_col($select);

      $productIds = array();
      foreach ($result as $productId){
        $productId = (string) (int) $productId;
        $productIds[$productId] = (int) $productId;
      }

      return $productIds;
    }
      
      
      
    public function addValues($data)
    {         
      $valuesStr = '';    
      foreach ($data as $values){
        $cell = '';
        foreach ($values as $value)
          $cell .= ",'" . esc_sql(trim($value)). "'";
                       
        $valuesStr .= ($valuesStr != '' ? ',' : '') . "(NULL{$cell})";     
      }

      $this->_wpdb->query("INSERT IGNORE INTO {$this->_mainTable} (id, product_id, category, make, model, year_from, year_to, note) VALUES {$valuesStr}");
    }   



    function getProductIdsOfCategory($categoryId) 
    {    
      $query = new WP_Query( array(
          'post_type' => 'product',
          'post_status' => 'publish',
          'posts_per_page' => -1,          
          'fields' => 'ids', 
          'tax_query' => array(
              array(
                  'taxonomy' => 'product_cat',
                  'field' => 'term_id',
                  'terms' => (int) $categoryId,
                  'operator' => 'IN',
              )
          )
      ) );

      return (array) $query->posts;    
    }



    public function emptyTable()
    {      
      $this->_wpdb->query("TRUNCATE TABLE {$this->_mainTable}"); 
    }
    
    public function getYmmDataByProductId($product_id)
    {
        $product_id = (int) $product_id;
        $sql = "SELECT category, make, model, year_from, year_to, note FROM {$this->_mainTable} WHERE product_id = %d ORDER BY category, make, model, year_from";
        return $this->_wpdb->get_results($this->_wpdb->prepare($sql, $product_id), ARRAY_A);
    }
    
    public function deleteYmmDataByProductId($product_id)
    {
        $product_id = (int) $product_id;
        $this->_wpdb->delete($this->_mainTable, array('product_id' => $product_id), array('%d'));
    }
    
    public function insertYmmData($product_id, $category, $make, $model, $year_from, $year_to, $note = '')
    {
        $data = array(
            'product_id' => (int) $product_id,
        'category' => sanitize_text_field($category),
            'make' => sanitize_text_field($make),
            'model' => sanitize_text_field($model),
            'year_from' => (int) $year_from,
        'year_to' => (int) $year_to,
        'note' => sanitize_text_field($note)
        );
        
      $this->_wpdb->insert($this->_mainTable, $data, array('%d', '%s', '%s', '%s', '%d', '%d', '%s'));
    }	
       
}
