<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Pektsekye_Ymm_Block_Adminhtml_Product_QuickEdit {

    protected $_db;
    
    public function __construct() {
        include_once( Pektsekye_YMM()->getPluginPath() . 'Model/Db.php');		
        $this->_db = new Pektsekye_Ymm_Model_Db();
        
        // Add quick edit fields
        add_action( 'woocommerce_product_quick_edit_end', array( $this, 'add_quick_edit_fields' ) );
        
        // Save quick edit data
        add_action( 'woocommerce_product_quick_edit_save', array( $this, 'save_quick_edit_data' ), 10, 1 );
        
        // Add custom column to products list
        add_filter( 'manage_edit-product_columns', array( $this, 'add_product_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'populate_product_column' ), 10, 2 );
        
        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
    }
    
    public function add_product_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( $key === 'name' ) {
                $new_columns['ymm_data'] = __( 'YMM Data', 'brp-ymm-search' );
            }
        }
        return $new_columns;
    }
    
    public function populate_product_column( $column, $post_id ) {
        if ( $column === 'ymm_data' ) {
            $ymm_data = $this->_db->getYmmDataByProductId( $post_id );
            if ( ! empty( $ymm_data ) ) {
                echo '<span class="ymm-count">' . count( $ymm_data ) . ' ' . __( 'vehicle(s)', 'brp-ymm-search' ) . '</span>';
                echo '<div class="hidden ymm-data" data-product-id="' . $post_id . '">' . esc_attr( $this->format_ymm_data_for_edit( $ymm_data ) ) . '</div>';
            } else {
                echo '<span class="ymm-count">—</span>';
                echo '<div class="hidden ymm-data" data-product-id="' . $post_id . '"></div>';
            }
        }
    }
    
    private function format_ymm_data_for_edit( $ymm_data ) {
        $formatted = array();
        foreach ( $ymm_data as $item ) {
            $formatted[] = $item['category'] . ', ' . $item['make'] . ', ' . $item['model'] . ', ' . $item['year_from'] . ', ' . $item['year_to'] . ', ' . $item['note'];
        }
        return implode( "\n", $formatted );
    }
    
    public function add_quick_edit_fields() {
        ?>
        <br class="clear" />
        <div class="inline-edit-group ymm-quick-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e( 'YMM Data', 'brp-ymm-search' ); ?></span>
                <textarea name="ymm_data" class="ymm-data-field" rows="7" placeholder="<?php _e( 'Category, Make, Model, Year From, Year To, Note (one per line)', 'brp-ymm-search' ); ?>"></textarea>
            </label>
        </div>
        <?php
    }
    
    public function save_quick_edit_data( $product ) {
        if ( isset( $_POST['ymm_data'] ) ) {
            $product_id = $product->get_id();
            $ymm_data = sanitize_textarea_field( $_POST['ymm_data'] );
            $this->save_ymm_data( $product_id, $ymm_data );
        }
    }
    
    private function save_ymm_data( $product_id, $ymm_data ) {
        // First, delete existing data
        $this->_db->deleteYmmDataByProductId( $product_id );
        
        if ( empty( trim( $ymm_data ) ) ) {
            return;
        }
        
        // Parse and save new data
        $lines = explode( "\n", $ymm_data );
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            
            $parts = array_map( 'trim', explode( ',', $line ) );
            if ( count( $parts ) >= 5 ) {
                $category = $parts[0] !== '' ? $parts[0] : 'Needs Cat';
                $make = $parts[1];
                $model = $parts[2];
                $year_from = intval( $parts[3] );
                $year_to = intval( $parts[4] );
                $note = isset( $parts[5] ) ? $parts[5] : '';

                $this->_db->insertYmmData( $product_id, $category, $make, $model, $year_from, $year_to, $note );
            }
        }
    }
    
    public function admin_scripts( $hook ) {
        if ( $hook === 'edit.php' && get_current_screen()->post_type === 'product' ) {
            wp_enqueue_script(
                'ymm-quick-edit',
                Pektsekye_YMM()->getPluginUrl() . 'view/adminhtml/web/quick-edit.js',
                array( 'jquery' ),
                '1.0.12.8.11',
                true
            );
            wp_enqueue_style(
                'ymm-quick-edit',
                Pektsekye_YMM()->getPluginUrl() . 'view/adminhtml/web/quick-edit.css',
                array(),
                '1.0.12.8.11'
            );
        }
    }
}
