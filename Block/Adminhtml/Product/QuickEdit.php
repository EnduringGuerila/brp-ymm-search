<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Pektsekye_Ymm_Block_Adminhtm        }
    }
    
    public function debug_bulk_edit() {
        if ( isset( $_POST['post_type'] ) && $_POST['post_type'] === 'product' && isset( $_POST['bulk_edit'] ) ) {
            error_log( 'YMM Bulk Edit Debug: POST data = ' . print_r( $_POST, true ) );
        }
    }
    
    public function save_bulk_edit_posts( $post_ids ) {
        error_log( 'YMM Debug: save_bulk_edit_posts called with IDs: ' . print_r( $post_ids, true ) );
        
        if ( isset( $_POST['ymm_bulk_action'] ) && $_POST['ymm_bulk_action'] !== '' ) {
            $action = sanitize_text_field( $_POST['ymm_bulk_action'] );
            $ymm_data = isset( $_POST['ymm_data'] ) ? sanitize_textarea_field( $_POST['ymm_data'] ) : '';
            
            error_log( 'YMM Debug: Bulk action = ' . $action . ', data = ' . $ymm_data );
            
            foreach ( $post_ids as $post_id ) {
                switch ( $action ) {
                    case 'replace':
                        $this->save_ymm_data( $post_id, $ymm_data );
                        break;
                    case 'add':
                        $existing_data = $this->_db->getYmmDataByProductId( $post_id );
                        $existing_formatted = $this->format_ymm_data_for_edit( $existing_data );
                        $combined_data = trim( $existing_formatted . "\n" . $ymm_data );
                        $this->save_ymm_data( $post_id, $combined_data );
                        break;
                    case 'remove':
                        $this->_db->deleteYmmDataByProductId( $post_id );
                        break;
                }
            }
        }
    }
    
    public function save_post_bulk_edit( $post_id ) {
        if ( isset( $_POST['bulk_edit'] ) && isset( $_POST['post_type'] ) && $_POST['post_type'] === 'product' ) {
            error_log( 'YMM Debug: save_post_bulk_edit called for post ID: ' . $post_id );
            
            if ( isset( $_POST['ymm_bulk_action'] ) && $_POST['ymm_bulk_action'] !== '' ) {
                $action = sanitize_text_field( $_POST['ymm_bulk_action'] );
                $ymm_data = isset( $_POST['ymm_data'] ) ? sanitize_textarea_field( $_POST['ymm_data'] ) : '';
                
                switch ( $action ) {
                    case 'replace':
                        $this->save_ymm_data( $post_id, $ymm_data );
                        break;
                    case 'add':
                        $existing_data = $this->_db->getYmmDataByProductId( $post_id );
                        $existing_formatted = $this->format_ymm_data_for_edit( $existing_data );
                        $combined_data = trim( $existing_formatted . "\n" . $ymm_data );
                        $this->save_ymm_data( $post_id, $combined_data );
                        break;
                    case 'remove':
                        $this->_db->deleteYmmDataByProductId( $post_id );
                        break;
                }
            }
        }
    }
    
    public function save_quick_edit_data( $product ) {oduct_QuickEdit {

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
            $formatted[] = $item['make'] . ', ' . $item['model'] . ', ' . $item['year_from'] . ', ' . $item['year_to'];
        }
        return implode( "\n", $formatted );
    }
    
    public function add_quick_edit_fields() {
        ?>
        <br class="clear" />
        <div class="inline-edit-group">
            <label class="alignleft">
                <span class="title"><?php _e( 'YMM Data', 'brp-ymm-search' ); ?></span>
                <textarea name="ymm_data" class="ymm-data-field" rows="3" placeholder="<?php _e( 'Make, Model, Year From, Year To (one per line)', 'brp-ymm-search' ); ?>"></textarea>
            </label>
            <p class="description">
                <?php _e( 'Format: Make, Model, Year From, Year To (one vehicle per line)', 'brp-ymm-search' ); ?>
                <br><?php _e( 'Example: Toyota, Camry, 2015, 2020', 'brp-ymm-search' ); ?>
            </p>
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
            if ( count( $parts ) >= 4 ) {
                $make = $parts[0];
                $model = $parts[1];
                $year_from = intval( $parts[2] );
                $year_to = intval( $parts[3] );
                
                $this->_db->insertYmmData( $product_id, $make, $model, $year_from, $year_to );
            }
        }
    }
    
    public function admin_scripts( $hook ) {
        if ( $hook === 'edit.php' && get_current_screen()->post_type === 'product' ) {
            wp_enqueue_script(
                'ymm-quick-edit',
                Pektsekye_YMM()->getPluginUrl() . 'view/adminhtml/web/quick-edit.js',
                array( 'jquery' ),
                '1.0.12.2',
                true
            );
            wp_enqueue_style(
                'ymm-quick-edit',
                Pektsekye_YMM()->getPluginUrl() . 'view/adminhtml/web/quick-edit.css',
                array(),
                '1.0.12.2'
            );
        }
    }
}
