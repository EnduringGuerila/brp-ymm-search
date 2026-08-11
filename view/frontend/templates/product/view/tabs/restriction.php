<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="ymm-vehicle-fitment">
  <table id="ymm_applicable_list">
    <tr>
      <th> <?php echo __('Make', 'ymm-search') ?> </th>
      <th> <?php echo __('Model', 'ymm-search') ?> </th>
      <th> <?php echo __('Year', 'ymm-search') ?> </th>
    </tr>
  <?php foreach($this->getFormatedRestrictions() as $row): ?>
    <tr>
      <td> <?php echo $row['make'] ?> </td>
      <td> <?php echo $row['model'] ?> </td>
      <td> <?php echo $row['year'] ?> </td>
    </tr>
  <?php endforeach; ?>
  </table>       			          		      	          		      	      
</div>