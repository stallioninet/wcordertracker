<?php
/**
 * @package authorize
 */
/*
Plugin Name: WC Order Tracker
Plugin URI: https://stallioni.com/
Description: WC Order Tracker.
Version: 1.0
Author: Stallioni
License: GPLv2 or later
Text Domain: wc-order-tracker
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

// Make sure we don't expose any info if called directly

define( 'WC_ORDER_TRACKER_VERSION', '1.0' );
define( 'WC_ORDER_TRACKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_ORDER_TRACKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );


global $wc_order_tracker_db_version;

$wc_order_tracker_db_version = '1.0';

register_activation_hook( __FILE__, 'wc_order_tracker_activation_plugin');


// trigger the function when activate the plugin //
function wc_order_tracker_activation_plugin(){
    
    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        include_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    }
    
    if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {
    // Deactivate the plugin.
        deactivate_plugins( plugin_basename( __FILE__ ) );
    // Throw an error in the WordPress admin console.
        $error_message = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . esc_html__( 'This plugin requires ', 'wc-order-tracker' ) . '<a href="' . esc_url( 'https://wordpress.org/plugins/woocommerce/' ) . '" target="_blank">WooCommerce</a>' . esc_html__( ' plugin to be active.', 'wc-order-tracker' ) . '</p>';
        $error_message .= '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen-Sans,Ubuntu,Cantarell,\'Helvetica Neue\',sans-serif;font-size: 13px;line-height: 1.5;color:#444;">' . esc_html__( 'This plugin recommends ', 'wc-order-tracker' ) . '<a href="' . esc_url( 'https://woocommerce.com/products/woocommerce-cost-of-goods/' ) . '" target="_blank">Cost of Goods</a>' . esc_html__( ' plugin also.', 'wc-order-tracker' ) . '</p>';
        die( $error_message ); // WPCS: XSS ok.
    }

    global $wpdb;

    global $wc_order_tracker_db_version;

    $table = $wpdb->prefix . 'wc_order_tracker_supplier';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        product_id int(11) NOT NULL,
        supplier_link text NOT NULL,
        supplier_notes text NOT NULL,
        updated_by text NOT NULL,
        created_date datetime NOT NULL,
        updated_date datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
        status int(11) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'wc_order_tracker_db_version', $wc_order_tracker_db_version );

}

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

add_action("admin_menu", "wc_order_tracker_menu_pages");

function wc_order_tracker_menu_pages(){
    
    if ( class_exists( 'WooCommerce' ) ) {
        add_menu_page("Fulfilment", "Fulfilment", "manage_woocommerce", "wc-order-tracker", "wc_order_tracker_menu_ouput");
        add_submenu_page("wc-order-tracker", "Product Status", "Product Status", "manage_woocommerce", "wc-order-product-status","wc_order_tracker_product_status_menu_output" );
    }
}

function wc_order_tracker_menu_ouput(){
    ?>
    <style type="text/css">
    .filter-clear {
        width: 100%;
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px 15px;
        border-top: 0;
        display: flex;
        justify-content: center;
    }
     .statistics{
        width: 100%;
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px 0;
        display: grid;
        grid-template-columns: repeat(6, 1fr);
    }
    .filter-wrap{
        width: auto;
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px 15px;
        display: flex;
        border-top: 0;
        justify-content: space-between;
    }
    .filter-wrap .regular-text {
        width: 180px;
    }
    .statistics > * {
        padding: 0 10px;
    }
    .statistics .head .name {
        font-weight: 600;
        color: #0073aa;
        cursor: pointer;
        background: none;
        border: 0;
        padding: 0;
    }
    .statistics .head .count {
        float: right;
        font-weight: 600;
    }
    .statistics .body {
        background: #eaeaea;
        width: 100%;
        height: 10px;
        margin-top: 5px;
        border-radius: 5px;
        overflow: hidden;
    }
    .statistics .body .progressbar{
        height: 100%;
    }
    .statistics .body .red{
        background: rgb(255,0,0, 0.7);
    }
    .statistics .body .dark-blue {
        background: rgb(0,115,170, 0.7);
    }
    .statistics .body .orange{
        background: rgb(255,165,0, 0.7);
    }
    .statistics .body .blue{
        background: rgb(31,221,241, 0.7);
    }
    .statistics .body .green{
        background: rgb(13,208,13, 0.7);
    }
    .progress {
        height: 15px;
        margin-bottom: 10px;
        overflow: hidden;
        background-color: #F1F1F1;
        border-radius: 10px;
        -webkit-box-shadow: inset 0 1px 2px rgb(0 0 0 / 10%);
        box-shadow: inset 0 1px 2px rgb(0 0 0 / 10%);
        position: relative;
    }
    .progressbar.green {
        float: left;
        width: 0%;
        height: 100%;
        font-size: 9px;
        line-height: 16px;
        text-align: center;
        background-color: #66ab66;
        -webkit-box-shadow: inset 0 -1px 0 rgb(0 0 0 / 15%);
        box-shadow: inset 0 -1px 0 rgb(0 0 0 / 15%);
        -webkit-transition: width .6s ease;
        -o-transition: width .6s ease;
        transition: width .6s ease;
    }
    .progress.column-progress {
        height: 0px;
        margin-bottom: 0px;
        background: unset;
        border-radius: none;
        box-shadow: none;
    }
    .progressbar .label {
        position: absolute;
        left: 0;
        right: 0;
        color: #273a09;
    }
    
    </style>
    <div class="wrap">
        <?php 
        $params = array(
            'post_type'     =>   'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed')
        );
        $orders = wc_get_orders($params);
        if(!empty($orders)){
            $total_orders = count($orders);
            
        }else{
            $total_orders = 0;
        }

        //On Hold
        $order_onhold_params = array(
            'post_type'     =>   'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-on-hold')
        );
        $order_on_hold_query = wc_get_orders($order_onhold_params);
        $onhold_orders = count($order_on_hold_query);
        
        if($onhold_orders != 0){
            $onhold_percentage = ($onhold_orders/$total_orders)*100 .'%';
        }else{
            $onhold_percentage = '0%';
        }
        
        //In Progress
        $order_process_params = array(
            'post_type'     =>   'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-processing')
        );
        $order_process_query = wc_get_orders($order_process_params);
        $inprogress_orders = count($order_process_query);
        if($inprogress_orders !=0){
            $inprogress_percentage = ($inprogress_orders/$total_orders)*100 .'%';
        }else{
            $inprogress_percentage = '0%';
        }
        
        //Completed
        $order_completed_params = array(
            'post_type'     =>  'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-completed')
        );
        $order_completed_query = wc_get_orders($order_completed_params);
        $completed_orders = count($order_completed_query);
        if($completed_orders !=0){
            $completed_percentage = ($completed_orders/$total_orders)*100 .'%';
        }else{
            $completed_percentage = '0%';
        }

        //Cancelled
        $order_cancelled_params = array(
            'post_type'     =>  'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-cancelled')
        );
        $order_cancelled_query = wc_get_orders($order_cancelled_params);
        $cancelled_orders = count($order_cancelled_query);
        if( $cancelled_orders != 0 ){
            $cancelled_percentage = ($cancelled_orders/$total_orders)*100 .'%';
        }else{
            $cancelled_percentage = '0%';
        }
        
        //Cancelled
        $order_params = array(
            'post_type'     =>  'shop_order',
            'limit'         =>  -1,
            'post_status'   =>  array('wc-pending', 'wc-processing', 'wc-on-hold'),
            'meta_key'      =>  '_deadline_date',
        );
        
        $ovr_count = 0;
        
        $start_count = 0;

        $order_overdue_arr = array();

        $order_started_arr = array();
        
        $order_overdue_query = wc_get_orders($order_params);
        
        if(!empty($order_overdue_query)){
            //$ovr_i_count = 0;
            $current_date = strtotime(date("d-m-Y"));
            
            foreach( $order_overdue_query as $order_overdue ){
                
                $order_date = $order_overdue->get_date_created();
                $deadline_date = get_post_meta($order_overdue->get_id(), '_deadline_date', true );   
                
                $order_created_date = date("d-m-Y", strtotime($order_date));
                $order_deadlin_date = strtotime($deadline_date);
                
                if( $deadline_date ){
                    
                    if($current_date > $order_deadlin_date){
                        
                        $ovr_count = $ovr_count + 1;

                        $order_overdue_arr[] = $order_overdue->get_id();
                    
                    }
                    
                    if($current_date < $order_deadlin_date){
                        
                        $start_count = $start_count + 1;

                        $order_started_arr[] = $order_overdue->get_id();
                    
                    }
                }
               
            }
        }
        
        if($ovr_count != 0){
            $over_due_percentage = ($ovr_count/$total_orders)*100 .'%';
        }else{
            $over_due_percentage = '0%';
        }
        
        if($start_count != 0){
            $sart_percentage = ($start_count/$total_orders)*100 .'%';
        }else{
            $sart_percentage = '0%';
        }

        //echo $ovr_count;
        ?>
        <h1>Fulfilment Table</h1>
        <div class="statistics">
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="overdue">
                        <input type="hidden" name="overdue_orders" value="<?php echo implode(',', $order_overdue_arr); ?>">
                        <span class="count"><?php echo $ovr_count; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="Overdue">
                    </form>                    
                </div>
                <div class="body">
                    <div class="progressbar red" style="width: <?php echo $over_due_percentage; ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="started">
                        <input type="hidden" name="started_orders" value="<?php echo implode(',', $order_started_arr); ?>">
                        <span class="count"><?php echo $start_count; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="Started">
                    </form> 
                </div>
                <div class="body">
                    <div class="progressbar dark-blue" style="width: <?php echo $sart_percentage; ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="wc-on-hold">
                        <span class="count"><?php echo $onhold_orders; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="On Hold">
                    </form>
                </div>
                <div class="body">
                    <div class="progressbar orange" style="width: <?php echo $onhold_percentage; ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="wc-processing">
                        <span class="count"><?php echo $inprogress_orders; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="In Progress">
                    </form>
                </div>
                <div class="body">
                    <div class="progressbar blue" style="width: <?php echo $inprogress_percentage; ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="wc-cancelled">
                        <span class="count"><?php echo $cancelled_orders; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="Cancel">
                    </form>
                </div>
                <div class="body">
                    <div class="progressbar" style="width: <?php echo $cancelled_percentage; ?>"></div>
                </div>
            </div>
            <div class="col">
                <div class="head">
                    <form class="filter-form" name="filter-form" method="post">
                        <input type="hidden" name="orderstatus" value="wc-completed">
                        <span class="count"><?php echo $completed_orders; ?>/<?php echo $total_orders; ?></span>
                        <input type="submit" name="submit" id="submit" class="name" value="Complete">
                    </form>
                </div>
                <div class="body">
                    <div class="progressbar green" style="width: <?php echo $completed_percentage; ?>"></div>
                </div>
            </div>
        </div>
        <div class="filter-wrap">
            <form class="filter-form" name="filter-form" method="post">
                <label for=""><strong>Search Deadline</strong></label>
                <input name="deadline" type="date" value="<?php echo isset($_POST['deadline']) ? $_POST['deadline']: ''; ?>" class="deadline-date">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post">
                <select class="input-field" name="orderstatus">
                    <option value="">Order Status</option>
                    <option value="wc-pending" <?php if( $_POST['orderstatus'] == 'wc-pending'){ ?> selected="selected" <?php } ?>>Pending</option>
                    <option value="wc-processing" <?php if( $_POST['orderstatus'] == 'wc-processing'){ ?> selected="selected" <?php } ?>>Processing</option>
                    <option value="wc-on-hold" <?php if( $_POST['orderstatus'] == 'wc-on-hold'){ ?> selected="selected" <?php } ?>>On Hold</option>
                    <option value="wc-completed" <?php if( $_POST['orderstatus'] == 'wc-completed'){ ?> selected="selected" <?php } ?>>Completed</option>
                    <option value="wc-cancelled" <?php if( $_POST['orderstatus'] == 'wc-cancelled'){ ?> selected="selected" <?php } ?>>Cancelled</option>
                    <option value="wc-refunded" <?php if( $_POST['orderstatus'] == 'wc-refunded'){ ?> selected="selected" <?php } ?>>Refunded</option>
                    <option value="wc-failed" <?php if( $_POST['orderstatus'] == 'wc-failed'){ ?> selected="selected" <?php } ?>>Failed</option>
                </select>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post">
                <label for=""><strong>#</strong></label>
                <input name="orderid" type="text" value="<?php echo isset($_POST['orderid']) ? $_POST['orderid']: ''; ?>" class="orderid" placeholder="Search Order">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
                
            </form>
            <form class="filter-form" name="filter-form" method="post">
                <input name="customer_name" type="text" value="<?php echo isset($_POST['customer_name']) ? $_POST['customer_name']: ''; ?>" class="customer_name" placeholder="Search Customer">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post" id="exportFulfilment">

                <?php if(isset($_POST['deadline'])){?>

                    <input name="deadline" type="hidden" value="<?php echo isset($_POST['deadline']) ? $_POST['deadline']: ''; ?>">

                <?php }else if( isset($_POST['orderid']) ) { ?>

                    <input name="orderid" type="hidden" value="<?php echo isset($_POST['orderid']) ? $_POST['orderid']: ''; ?>">

                <?php } else if( isset($_POST['customer_name']) ) { ?>

                    <input name="customer_name" type="hidden" value="<?php echo isset($_POST['customer_name']) ? $_POST['customer_name']: ''; ?>">

                <?php }  else if( $_POST['orderstatus'] == 'wc-completed') { ?>

                    <input name="orderstatus" type="hidden" value="wc-completed">

                <?php } else if( $_POST['orderstatus'] == 'wc-cancelled') { ?>

                    <input name="orderstatus" type="hidden" value="wc-cancelled">

                <?php } else if( $_POST['orderstatus'] == 'wc-processing') { ?>

                    <input name="orderstatus" type="hidden" value="wc-processing">

                <?php } else if( $_POST['orderstatus'] == 'wc-on-hold') { ?>

                    <input name="orderstatus" type="hidden" value="wc-on-hold">

                <?php } else if( $_POST['orderstatus'] == 'wc-pending') { ?>

                    <input name="orderstatus" type="hidden" value="wc-pending">

                <?php } else if( $_POST['orderstatus'] == 'wc-refunded') { ?>

                    <input name="orderstatus" type="hidden" value="wc-refunded">

                <?php } else if( $_POST['orderstatus'] == 'wc-failed') { ?>

                    <input name="orderstatus" type="hidden" value="wc-failed">

                <?php } else if( $_POST['orderstatus'] == 'started') { ?>

                    <input name="orderstatus" type="hidden" value="started">

                    <input type="hidden" name="started_orders" value="<?php echo implode(',', $order_started_arr); ?>">

                <?php } else if( $_POST['orderstatus'] == 'overdue') { ?>

                    <input type="hidden" name="orderstatus" value="overdue">

                    <input type="hidden" name="overdue_orders" value="<?php echo implode(',', $order_overdue_arr); ?>">

                <?php } else{ ?>

                    <input type="hidden" name="export" value="all">

                <?php } ?>

                <input type="hidden" name="action" value="wpexportfulfilment">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Export">
                <a href="#" class="button button-primary" id="downloadexport" style="margin-left: 10px; display: none;">Download</a>
            </form>

        </div>
        

        <?php if( isset($_POST['deadline']) || isset($_POST['orderstatus']) || isset($_POST['orderid']) || isset($_POST['customer_name']) ){ ?>
            <div class="filter-clear">
                <a href="<?php echo admin_url('admin.php?page=wc-order-tracker'); ?>" class="button button-primary"><?php echo __('Clear Filter'); ?></a>
            </div>
        <?php } ?>
       

    <?php
    
        $ordersListTable = new Fullfilment_List_Table();
        //$ordersListTable->views();
        $ordersListTable->prepare_items();
        $ordersListTable->display();
        
    ?>
    </div>
    <script type="text/javascript">

        jQuery(document).ready( function(){

            jQuery( '#exportFulfilment' ).submit( function( event ) {
                
                event.preventDefault();

                jQuery.ajax({
                    type: "POST",
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    dataType: "html",
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(response){
                        if(response.length > 0 ){
                            jQuery("#downloadexport").attr("href", response);
                            jQuery("#downloadexport").show();
                        }

                    },
                    error : function(request,error){
                       console.log(error);
                    }
                });
            });

        });

    </script>
<?php 
}

add_action('wp_ajax_wpexportfulfilment', 'wpexportfulfilment' ); // executed when logged in
add_action('wp_ajax_nopriv_wpexportfulfilment', 'wpexportfulfilment' );

function wpexportfulfilment(){

    if( $_POST['action'] == 'wpexportfulfilment'){
        
        $data = array();

        $csv_headers = array();
        $csv_headers[] = 'Order';
        $csv_headers[] = 'Date';
        $csv_headers[] = 'Status';
        $csv_headers[] = 'Progress';
        $csv_headers[] = 'Deadline';
        $csv_headers[] = 'Overdue';
        $csv_headers[] = 'Cost($)';
        $csv_headers[] = 'Total($)';

        $upload_dir = wp_upload_dir();

        $new_dir = $upload_dir['basedir'] .'/export/';
        $base_url = $upload_dir['baseurl'] .'/export/';
        
        if ( ! is_dir( $new_dir ) ) {
            $file_dir = wp_mkdir_p( $new_dir );
        }else{
            $file_dir = $new_dir;
        }

        $outstream = fopen($new_dir."/export-fulfilment.csv", "w"); 

        fputcsv($outstream, $csv_headers);

        if($_POST['export'] == 'all'){

            $args = array(
                'post_type'         =>  'shop_order',
                'post_status'       =>  'any',
                'posts_per_page'    =>  -1,
                'orderby'           =>  'the_date',
                'order'             =>  'DESC',
                'post__in'          =>  '',
                'meta_query'        =>  ''            
            );

        }else{

            if($_POST['orderstatus'] == 'wc-completed'){

                $post_status = 'wc-completed';

            }else if($_POST['orderstatus'] == 'wc-cancelled'){

                $post_status = 'wc-cancelled';

            }else if($_POST['orderstatus'] == 'wc-processing'){

                $post_status = 'wc-processing';

            }else if($_POST['orderstatus'] == 'wc-on-hold'){

                $post_status = 'wc-on-hold';

            }else if($_POST['orderstatus'] == 'wc-pending'){

                $post_status = 'wc-pending';

            }else if($_POST['orderstatus'] == 'wc-refunded'){

                $post_status = 'wc-refunded';

            }else if($_POST['orderstatus'] == 'wc-failed'){

                $post_status = 'wc-failed';

            }else{

                $post_status = 'any';
            }
            
            if($_POST['orderstatus'] == 'started'){
            
                $order_post_in = explode(',', $_POST['started_orders']);

            }else if($_POST['orderstatus'] == 'overdue'){

                $order_post_in = explode(',', $_POST['overdue_orders']);

            }else if( isset($_POST['orderid']) ){

                $order_post_in = explode(',', $_POST['orderid']);

            }else{

                $order_post_in = array();
            }

            if( isset($_POST['deadline'])){

                $meta_query = array(
                    
                    array(

                        'key'      =>  '_deadline_date',
                        'value'    =>  $_POST['deadline']
                    )
                    
                );

            }else if( isset($_POST['customer_name'])){

                $meta_query = array(
                    'relation'          => 'OR',
                    array(
                        'key'       =>  '_billing_first_name',
                        'value'     =>  $_POST['customer_name'],
                        'compare'   =>  'LIKE',
                    ),
                    array(
                        'key'       =>  '_billing_last_name',
                        'value'     =>  $_POST['customer_name'],
                        'compare'   =>  'LIKE',
                    ),
                );

            }else{

                $meta_query = '';
            }

            $args = array(
                'post_type'         =>  'shop_order',
                'post_status'       =>  $post_status,
                'posts_per_page'    =>  -1,
                'orderby'           =>  'the_date',
                'order'             =>  'DESC',
                'post__in'          =>  $order_post_in,
                'meta_query'        =>  $meta_query
                //'limit'         =>  isset($_POST['deadline']) ? -1 : 10,
            
            );

        }

        $query = new WP_Query( $args );

        $query_posts = $query->get_posts();

        $postcount = count($query_posts);

        foreach( $query_posts as $orderdata ){

            $order = wc_get_order($orderdata->ID);

            $order_total_cost = $order->wc_cog_order_total_cost;
            $formatted_total  = wc_price( $order_total_cost );

            $buyer = '';

            if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                /* translators: 1: first name 2: last name */
                $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'wc-order-tracker' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
            } elseif ( $order->get_billing_company() ) {
                $buyer = trim( $order->get_billing_company() );
            } elseif ( $order->get_customer_id() ) {
                $user  = get_user_by( 'id', $order->get_customer_id() );
                $buyer = ucwords( $user->display_name );
            }

            $order_timestamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '';

            if ( ! $order_timestamp ) {
                echo '&ndash;';
                return;
            }

            // Check if the order was created within the last 24 hours, and not in the future.
            if ( $order_timestamp > strtotime( '-1 day', time() ) && $order_timestamp <= time() ) {
                $show_date = sprintf(
                    /* translators: %s: human-readable time difference */
                    _x( '%s ago', '%s = human-readable time difference', 'woocommerce' ),
                    human_time_diff( $order->get_date_created()->getTimestamp(), time() )
                );
            } else {
                $show_date = $order->get_date_created()->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', __( 'M j, Y', 'woocommerce' ) ) );
            }
            $status = esc_html( wc_get_order_status_name( $order->get_status() ) );
            //exit;           

            $total = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );

            $o_deadline_date = get_post_meta( $order->id,'_deadline_date', true) ? get_post_meta( $order->id,'_deadline_date', true) : '';
            //echo strtotime(date("d-m-Y"));
            if($o_deadline_date !=''){
                $order_created_date = date("d-m-Y", strtotime($order->get_date_created()));
                $order_deadlin_date = date("d-m-Y", strtotime($deadline_date));
                
                $o_current_date = date("d-m-Y");
                
                if(strtotime($o_current_date) > strtotime($o_deadline_date)){
                    //echo strtotime($o_deadline_date) . 'dfg';
                    $o_datediff = strtotime($o_current_date) - strtotime($o_deadline_date);
                    $o_due_date = abs(round($o_datediff / 86400));
                    $o_due_days = $o_due_date .' day(s)';
                }else{
                    $o_rder_created_date = '';
                    $o_order_deadlin_date = '';
                    $o_due_days = 0 .' day(s)';
                }
                               
            }else{
                $o_rder_created_date = '';
                $o_order_deadlin_date = '';
                $o_due_days = 0 .' day(s)';
            }

            $items = $order->get_items();

            $status_count = 0;

            $current_count = 0;

            foreach ( $order->get_items() as $item_id => $item ) {

                $tracking_status = wc_get_order_item_meta( $item_id, '_wc_tracking_status', true ); 

                if( ( $tracking_status != 'default') && ( $tracking_status != 'in_progress' ) && ( $tracking_status != 'discontinue' ) && ( $tracking_status != 'pending' ) && ( $tracking_status != 'owner' ) ) {

                    $current_count = $status_count + 1;

                    $status_count++; 
                }
            }

            $progress = $current_count > 0 ? round( ( $current_count / count($items) ) * 100 ): $current_count;

            $progressbar = $progress .'%';
 
            $data_row = array(
                '#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ),
                $show_date,
                $status,
                $progressbar,
                $o_deadline_date,
                $o_due_days,
                $order_total_cost,
                $order->get_total(),
                //'action'     => '-'
            );

            fputcsv($outstream, $data_row);

        }
        
        fclose( $outstream );

        if($postcount > 0 ){
            echo $base_url.'export-fulfilment.csv';
        }
        
    }
    wp_die();
}

function wc_order_tracker_product_status_menu_output(){ ?>

    <style type="text/css">
        .woocommerce-placeholder,
        .woo-product-thumbnail{
            width: 50px;
            height: 50px;
        }
        .filter-clear {
            width: 100%;
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 15px 15px;
            border-top: 0;
            display: flex;
            justify-content: center;
        }
        
        .filter-wrap{
            width: auto;
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 15px 15px;
            display: flex;
            border-top: 0;
            justify-content: space-between;
        }
        .filter-wrap .regular-text {
            width: 180px;
        }
      
    </style>
    <h3>Product Status</h3>
    <div class="filter-wrap">

            <form class="filter-form" name="filter-form" method="post">
                <select class="input-field" name="trackingstatus">
                    <option value="">Tracking Status</option>
                    <option value="default" <?php if( $_POST['trackingstatus'] == 'default'){ ?> selected="selected" <?php } ?>>Default</option>
                    <option value="in_progress" <?php if( $_POST['trackingstatus'] == 'in_progress'){ ?> selected="selected" <?php } ?>>In Progress</option>
                    <option value="discontinue" <?php if( $_POST['trackingstatus'] == 'discontinue'){ ?> selected="selected" <?php } ?>>Discontinue</option>
                    <option value="pending" <?php if( $_POST['trackingstatus'] == 'pending'){ ?> selected="selected" <?php } ?>>Pending</option>
                    <option value="owner" <?php if( $_POST['trackingstatus'] == 'owner'){ ?> selected="selected" <?php } ?>>Owner</option>
                    <option value="template" <?php if( $_POST['trackingstatus'] == 'template'){ ?> selected="selected" <?php } ?>>Template</option>
                    <option value="owner_check" <?php if( $_POST['trackingstatus'] == 'owner_check'){ ?> selected="selected" <?php } ?>>Owner Check</option>
                    <option value="local" <?php if( $_POST['trackingstatus'] == 'local'){ ?> selected="selected" <?php } ?>>Local</option>
                    <option value="in_stock" <?php if( $_POST['trackingstatus'] == 'in_stock'){ ?> selected="selected" <?php } ?>>In Stock</option>
                    <option value="labor" <?php if( $_POST['trackingstatus'] == 'labor'){ ?> selected="selected" <?php } ?>>Labor</option>
                    <option value="notes" <?php if( $_POST['trackingstatus'] == 'notes'){ ?> selected="selected" <?php } ?>>Notes</option>
                    <option value="picking_ready" <?php if( $_POST['trackingstatus'] == 'picking_ready'){ ?> selected="selected" <?php } ?>>Picking Ready</option>
                </select>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post">
                <select class="input-field" name="orderstatus">
                    <option value="">Order Status</option>
                    <option value="wc-pending" <?php if( $_POST['orderstatus'] == 'wc-pending'){ ?> selected="selected" <?php } ?>>Pending</option>
                    <option value="wc-processing" <?php if( $_POST['orderstatus'] == 'wc-processing'){ ?> selected="selected" <?php } ?>>Processing</option>
                    <option value="wc-on-hold" <?php if( $_POST['orderstatus'] == 'wc-on-hold'){ ?> selected="selected" <?php } ?>>On Hold</option>
                    <option value="wc-completed" <?php if( $_POST['orderstatus'] == 'wc-completed'){ ?> selected="selected" <?php } ?>>Completed</option>
                    <option value="wc-cancelled" <?php if( $_POST['orderstatus'] == 'wc-cancelled'){ ?> selected="selected" <?php } ?>>Cancelled</option>
                    <option value="wc-refunded" <?php if( $_POST['orderstatus'] == 'wc-refunded'){ ?> selected="selected" <?php } ?>>Refunded</option>
                    <option value="wc-failed" <?php if( $_POST['orderstatus'] == 'wc-failed'){ ?> selected="selected" <?php } ?>>Failed</option>
                </select>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>
            
            <form class="filter-form" name="filter-form" method="post">
                <input name="customer_name" type="text" value="<?php echo isset($_POST['customer_name']) ? $_POST['customer_name']: ''; ?>" class="input-field" placeholder="Customer Name">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post">
                <input name="order_id" type="text" value="<?php echo isset($_POST['order_id']) ? $_POST['order_id']: ''; ?>" class="input-field" placeholder="Order ID">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>
            <?php 
                $taxonomy     = 'product_cat';
                $orderby      = 'name';  
                $show_count   = 0;      // 1 for yes, 0 for no
                $pad_counts   = 0;      // 1 for yes, 0 for no
                $hierarchical = 0;      // 1 for yes, 0 for no  
                $empty        = 0;
                $args = array(
                    'taxonomy'     => $taxonomy,
                    'orderby'      => $orderby,
                    'show_count'   => $show_count,
                    'pad_counts'   => $pad_counts,
                    'hierarchical' => $hierarchical,
                    'hide_empty'   => $empty
                );

                $product_cat = get_categories( $args );

            ?>
            <form class="filter-form" name="filter-form" method="post">
                <select class="input-field" name="product_category">
                    <option value="">Select Category</option>
                    
                    <?php if(!empty($product_cat)){ 

                        foreach( $product_cat as $cat ){

                            if(isset($_POST['product_category'])){

                                if($cat->slug === $_POST['product_category']){

                                    echo '<option value="'. $cat->slug .'" selected="selected" >'. $cat->name .'</option>';

                                }else{

                                    echo '<option value="'. $cat->slug .'">'. $cat->name .'</option>';
                                }

                            }else{

                                echo '<option value="'. $cat->slug .'">'. $cat->name .'</option>';
                            }

                        }

                    } ?>

                </select>
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Filter">
            </form>

            <form class="filter-form" name="filter-form" method="post" id="exportproductstat">

                <?php if(isset($_POST['trackingstatus'])){?>
                    <input name="trackingstatus" type="hidden" value="<?php echo isset($_POST['trackingstatus']) ? $_POST['trackingstatus']: ''; ?>">
                <?php }else if( isset($_POST['orderstatus']) ) { ?>
                    <input name="orderstatus" type="hidden" value="<?php echo isset($_POST['orderstatus']) ? $_POST['orderstatus']: ''; ?>">
                <?php } else if( isset($_POST['customer_name']) ) { ?>
                    <input name="customer_name" type="hidden" value="<?php echo isset($_POST['customer_name']) ? $_POST['customer_name']: ''; ?>">
                <?php }  else if( isset($_POST['order_id']) ) { ?>
                    <input name="order_id" type="hidden" value="<?php echo isset($_POST['order_id']) ? $_POST['order_id'] : ''; ?>">
                <?php } else if( isset($_POST['product_category']) ) { ?>
                    <input name="product_category" type="hidden" value="<?php echo isset($_POST['product_category']) ? $_POST['product_category'] : ''; ?>">
                <?php } else{ ?>
                    <input type="hidden" name="export" value="all">
                <?php } ?>
                <input type="hidden" name="action" value="wpexportproductstatus">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Export">
                <a href="#" class="button button-primary" id="downloadexport" style="margin-left: 10px; display: none;">Download</a>
            </form>

        </div>

        <?php if( isset($_POST['trackingstatus']) || isset($_POST['orderstatus']) || isset($_POST['customer_name']) || isset($_POST['order_id']) || isset($_POST['product_category']) ){ ?>
            <div class="filter-clear">
                <a href="<?php echo admin_url('admin.php?page=wc-order-product-status'); ?>" class="button button-primary"><?php echo __('Clear Filter'); ?></a>
            </div>
        <?php } ?>
        <script type="text/javascript">

        jQuery(document).ready( function(){

            jQuery( '#exportproductstat' ).submit( function( event ) {
                
                event.preventDefault();

                jQuery.ajax({
                    type: "POST",
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    dataType: "html",
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    success: function(response){
                        if(response.length > 0 ){
                            jQuery("#downloadexport").attr("href", response);
                            jQuery("#downloadexport").show();
                        }

                    },
                    error : function(request,error){
                       console.log(error);
                    }
                });
            });

        });

    </script>
    <?php
    $productListTable = new Fullfilment_Product_Status_Table();
    $productListTable->prepare_items();
    $productListTable->display();
}



add_action('wp_ajax_wpexportproductstatus', 'wpexportproductstatus' ); // executed when logged in
add_action('wp_ajax_nopriv_wpexportproductstatus', 'wpexportproductstatus' );

function wpexportproductstatus(){

    if( $_POST['action'] == 'wpexportproductstatus'){
        
        $data = array();

        $csv_headers = array();
        $csv_headers[] = 'Order';
        $csv_headers[] = 'Item';
        $csv_headers[] = 'Description';
        $csv_headers[] = 'Status';
        $csv_headers[] = 'Tracking';
        $csv_headers[] = 'Expect Date';
        $csv_headers[] = 'Category';

        $upload_dir = wp_upload_dir();

        $new_dir = $upload_dir['basedir'] .'/export/';
        $base_url = $upload_dir['baseurl'] .'/export/';
        
        if ( ! is_dir( $new_dir ) ) {

            $file_dir = wp_mkdir_p( $new_dir );

        }else{

            $file_dir = $new_dir;
        }

        $outstream = fopen($new_dir."/export-product-status.csv", "w"); 

        fputcsv($outstream, $csv_headers);

        if($_POST['export'] == 'all'){

           $args = array(
                'post_type'         =>  'shop_order',
                'post_status'       =>  'any',
                'posts_per_page'    =>  -1,
                'orderby'           =>  'the_date',
                'order'             =>  'DESC',
                'post__in'          =>  '',
                'meta_query'        =>  '',
            
            );

        }else{

            $post_status = $_POST['orderstatus'] !='' ? $_POST['orderstatus'] : 'any';

            $order_post_in = isset($_POST['order_id']) ? array( $_POST['order_id'] ) : '';
            
            if( isset($_POST['customer_name'])){

                $meta_query = array(
                    'relation'          => 'OR',
                    array(
                        'key'       =>  '_billing_first_name',
                        'value'     =>  $_POST['customer_name'],
                        'compare'   =>  'LIKE',
                    ),
                    array(
                        'key'       =>  '_billing_last_name',
                        'value'     =>  $_POST['customer_name'],
                        'compare'   =>  'LIKE',
                    ),
                );

            }

            $args = array(
                'post_type'         =>  'shop_order',
                'post_status'       =>  $post_status,
                'posts_per_page'    =>  -1,
                'orderby'           =>  'the_date',
                'order'             =>  'DESC',
                'post__in'          =>  $order_post_in,
                'meta_query'        =>  $meta_query,
            
            );

        }


        $query = new WP_Query( $args );

        $query_posts = $query->get_posts();

        $postcount = count($query_posts);

        foreach( $query_posts as $orderdata ){

            $order = wc_get_order($orderdata->ID);

            foreach ( $order->get_items() as $item_id => $item ) {

                $product_id = $item->get_product_id();

                $buyer = '';

                if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                    /* translators: 1: first name 2: last name */
                    $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'wc-order-tracker' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
                } elseif ( $order->get_billing_company() ) {
                    $buyer = trim( $order->get_billing_company() );
                } elseif ( $order->get_customer_id() ) {
                    $user  = get_user_by( 'id', $order->get_customer_id() );
                    $buyer = ucwords( $user->display_name );
                }

                $image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'single-post-thumbnail' );

                $wc_tracking_status_meta = wc_get_order_item_meta( $item_id, '_wc_tracking_status', true );

                $wc_tracking_url_meta = wc_get_order_item_meta( $item_id, '_wc_tracking', true );

                $wc_expect_date_meta = wc_get_order_item_meta( $item_id, '_wc_expect_date', true );


                if($wc_tracking_status_meta == 'picking_ready'){

                    $tracking_status = 'Picking Ready';

                }else if ($wc_tracking_status_meta == 'notes') {

                    $tracking_status = 'Notes';
                    
                }else if ($wc_tracking_status_meta == 'labor') {

                    $tracking_status = 'Labor';
                    
                }else if ($wc_tracking_status_meta == 'in_stock') {

                    $tracking_status = 'In Stock';

                }else if ($wc_tracking_status_meta == 'local') {

                    $tracking_status = 'Local';
                    
                }else if ($wc_tracking_status_meta == 'owner_check') {
                    
                    $tracking_status = 'Owner Check';

                }else if ($wc_tracking_status_meta == 'template') {
                    
                    $tracking_status = 'Template';

                }else if ($wc_tracking_status_meta == 'owner') {
                    
                    $tracking_status = 'By Owner';

                }else if ($wc_tracking_status_meta == 'pending') {
                    
                    $tracking_status = 'Pending';

                }else if ($wc_tracking_status_meta == 'discontinue') {
                    
                    $tracking_status = 'Discontinue';
                
                }else if ($wc_tracking_status_meta == 'in_progress') {
                    
                    $tracking_status = 'In Progress';
                
                }else {
                    
                    $tracking_status = 'Default';
                }


                $product = new WC_Product($product_id);

                $thumbnail = $image !='' ? '<img src="'. $image[0] .'" class="woo-product-thumbnail">' : $product->get_image('shop_thumbnail');

                $terms = get_the_terms ( $product_id, 'product_cat' );

                $catgories = array();
                $catgories_slug = array();

                foreach ( $terms as $term ) {
                
                    if (array_key_exists($product_id, $catgories)) {
                    
                        $catgories[$product_id] = $catgories[$product_id] .','. $term->name;

                    }else{

                        $catgories[$product_id] = $term->name;
                    }

                    if (array_key_exists($product_id, $catgories_slug)) {
                    
                        $catgories_slug[$product_id] = $catgories_slug[$product_id] .','. $term->slug;

                    }else{

                        $catgories_slug[$product_id] = $term->slug;
                    }
                }

                if(isset($_POST['product_category'])){

                    $cat_slug = $_POST['product_category'];

                    $cat_slug_arr = explode(',', $catgories_slug[$product_id]);

                    if ( in_array( $cat_slug, $cat_slug_arr ) ) {

                        $data_row = array(
                           '#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ),
                            site_url( $product->get_slug() ),
                            $item->get_name(),
                            $tracking_status,
                            $wc_tracking_url_meta,
                            $wc_expect_date_meta,
                            $catgories[$product_id],
                            //'action'     => '-'
                        );

                        fputcsv($outstream, $data_row);
                    }

                }else if(isset($_POST['trackingstatus'])){

                    $post_tracking_status = $_POST['trackingstatus'];

                    if( $wc_tracking_status_meta == $post_tracking_status ){

                            $data_row = array(
                            '#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ),
                            site_url( $product->get_slug() ),
                            $item->get_name(),
                            $tracking_status,
                            $wc_tracking_url_meta,
                            $wc_expect_date_meta,
                            $catgories[$product_id],
                            //'action'     => '-'
                        );

                        fputcsv($outstream, $data_row);
                    }

                }else{

                    $data_row = array(
                        '#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ),
                        site_url( $product->get_slug() ),
                        $item->get_name(),
                        $tracking_status,
                        $wc_tracking_url_meta,
                        $wc_expect_date_meta,
                        $catgories[$product_id],
                        //'action'     => '-'
                    );

                    fputcsv($outstream, $data_row);

                }
                
            }
         
        }

        fclose( $outstream );

        if($postcount > 0 ){
            echo $base_url.'export-product-status.csv';
        }
        
    }
    wp_die();
}


/**
 * Create a new table class that will extend the WP_List_Table
 */
class Fullfilment_List_Table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = 20;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'id'            =>  'Order',
            'date'          =>  'Date',
            'status'        =>  'Status',
            'progress'      =>  'Progress',
            'deadline'      =>  'Deadline',
            'overdue'       =>  'Overdue',
            'cost'          =>  'Cost',
            'total'         =>  'Total',
            //'action'        =>  'Actions',
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array('id' => array('id', false));
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data()
    {
        
        
        $data = array();

        if( $_POST['orderstatus'] == 'wc-completed'){

            $post_status = 'wc-completed';

        }else if( $_POST['orderstatus'] == 'wc-cancelled'){

            $post_status = 'wc-cancelled';

        }else if( $_POST['orderstatus'] == 'wc-processing'){

            $post_status = 'wc-processing';

        }else if( $_POST['orderstatus'] == 'wc-on-hold'){

            $post_status = 'wc-on-hold';

        }else if( $_POST['orderstatus'] == 'wc-pending'){

            $post_status = 'wc-pending';

        }else if( $_POST['orderstatus'] == 'wc-refunded'){

            $post_status = 'wc-refunded';

        }else if( $_POST['orderstatus'] == 'wc-failed'){

            $post_status = 'wc-failed';

        }else{

            $post_status = 'any';
        }
        
        if( $_POST['orderstatus'] == 'started'){
        
            $order_post_in = explode(',', $_POST['started_orders']);

        }else if( $_POST['orderstatus'] == 'overdue'){

            $order_post_in = explode(',', $_POST['overdue_orders']);

        }else if( isset($_POST['orderid']) ){

            $order_post_in = explode(',', $_POST['orderid']);

        }else{

            $order_post_in = array();
        }

        if( isset($_POST['deadline'])){

            $meta_query = array(
                
                array(

                    'key'      =>  '_deadline_date',
                    'value'    =>  $_POST['deadline']
                )
                
            );

        }else if( isset($_POST['customer_name'])){

            $meta_query = array(
                'relation'          => 'OR',
                array(
                    'key'       =>  '_billing_first_name',
                    'value'     =>  $_POST['customer_name'],
                    'compare'   =>  'LIKE',
                ),
                array(
                    'key'       =>  '_billing_last_name',
                    'value'     =>  $_POST['customer_name'],
                    'compare'   =>  'LIKE',
                ),
            );

        }else{

            $meta_query = '';
        }

        $args = array(
            'post_type'         =>  'shop_order',
            'post_status'       =>  $post_status,
            'posts_per_page'    =>  -1,
            'orderby'           =>  'the_date',
            'order'             =>  'DESC',
            'post__in'          =>  $order_post_in,
            'meta_query'        =>  $meta_query
        
        );

        $query = new WP_Query( $args );


        $query_posts = $query->get_posts();

        foreach( $query_posts as $orderdata ){

            $order = wc_get_order($orderdata->ID);

            $order_total_cost = $order->wc_cog_order_total_cost;
            $formatted_total  = wc_price( $order_total_cost );

            $buyer = '';

            if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                /* translators: 1: first name 2: last name */
                $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'wc-order-tracker' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
            } elseif ( $order->get_billing_company() ) {
                $buyer = trim( $order->get_billing_company() );
            } elseif ( $order->get_customer_id() ) {
                $user  = get_user_by( 'id', $order->get_customer_id() );
                $buyer = ucwords( $user->display_name );
            }

            $order_timestamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '';

            if ( ! $order_timestamp ) {
                echo '&ndash;';
                return;
            }

            // Check if the order was created within the last 24 hours, and not in the future.
            if ( $order_timestamp > strtotime( '-1 day', time() ) && $order_timestamp <= time() ) {
                $show_date = sprintf(
                    /* translators: %s: human-readable time difference */
                    _x( '%s ago', '%s = human-readable time difference', 'woocommerce' ),
                    human_time_diff( $order->get_date_created()->getTimestamp(), time() )
                );
            } else {
                $show_date = $order->get_date_created()->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', __( 'M j, Y', 'woocommerce' ) ) );
            }
            $status = '<mark class="order-status '. esc_attr( sanitize_html_class( 'status-' . $order->get_status() ) ).'"><span>'. esc_html( wc_get_order_status_name( $order->get_status() ) ).'</span></mark>';
            //exit;           

            $total = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );

            $o_deadline_date = get_post_meta( $order->id,'_deadline_date', true) ? get_post_meta( $order->id,'_deadline_date', true) : '';
            //echo strtotime(date("d-m-Y"));
            if($o_deadline_date !=''){
                $order_created_date = date("d-m-Y", strtotime($order->get_date_created()));
                $order_deadlin_date = date("d-m-Y", strtotime($deadline_date));
                
                $o_current_date = date("d-m-Y");
                
                if(strtotime($o_current_date) > strtotime($o_deadline_date)){
                    //echo strtotime($o_deadline_date) . 'dfg';
                    $o_datediff = strtotime($o_current_date) - strtotime($o_deadline_date);
                    $o_due_date = abs(round($o_datediff / 86400));
                    $o_due_days = $o_due_date .' day(s)';
                }else{
                    $o_rder_created_date = '';
                    $o_order_deadlin_date = '';
                    $o_due_days = 0 .' day(s)';
                }
                               
            }else{
                $o_rder_created_date = '';
                $o_order_deadlin_date = '';
                $o_due_days = 0 .' day(s)';
            }

            $items = $order->get_items();

            $status_count = 0;

            $current_count = 0;

            foreach ( $order->get_items() as $item_id => $item ) {

                $tracking_status = wc_get_order_item_meta( $item_id, '_wc_tracking_status', true ); 

                if( ( $tracking_status != 'default') && ( $tracking_status != 'in_progress' ) && ( $tracking_status != 'discontinue' ) && ( $tracking_status != 'pending' ) && ( $tracking_status != 'owner' ) ) {

                    $current_count = $status_count + 1;

                    $status_count++; 
                }
            }

            $progress = $current_count > 0 ? round( ( $current_count / count($items) ) * 100 ): $current_count;

            $progressbar = '<div class="progress">';
                $progressbar .= '<div class="progressbar green" style="width:'. $progress .'%"><span class="label">'. $progress .'%</span></div>';
            $progressbar .= '</div>';
 
            $data[] = array(
                'id'         => '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $order->ID ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>',
                'date'       => $show_date,
                'status'     => $status,
                'progress'   => $progressbar,
                'deadline'   => $o_deadline_date,
                'overdue'    => $o_due_days,
                'cost'       => $formatted_total,
                'total'      => $total,
                //'action'     => '-'
            );
        }

       return $data;
    }

    protected function get_views() { 
        $status_links = array(
            "all"       => __("<a href='#'>All</a>",'wc-order-tracker'),
            "published" => __("<a href='#'>Processing</a>",'wc-order-tracker'),
            "trashed"   => __("<a href='#'>On Hold</a>",'wc-order-tracker')
        );
        return $status_links;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'id':
            case 'date':
            case 'status':
            case 'progress':
            case 'deadline':
            case 'overdue':
            case 'cost':
            case 'total':
            //case 'action':
                return $item[ $column_name ];

            default:
                return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby    = 'id';
        $order      = 'DESC';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'desc')
        {
            return $result;
        }

        return -$result;
    }
}



class Fullfilment_Product_Status_Table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = 20;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'id'            =>  'Order',
            'item'          =>  'Item',
            'description'   =>  'Description',
            'status'        =>  'Status',
            'tracking'      =>  'Tracking',
            'expert'        =>  'Expect Date',
            'category'      =>  'Category',
            //'action'        =>  'Actions',
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array('id' => array('id', true ));
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data()
    {
        
        
        $data = array();

        $post_status = $_POST['orderstatus'] !='' ? $_POST['orderstatus'] : 'any';

        $order_post_in = isset($_POST['order_id']) ? array( $_POST['order_id'] ) : '';
        
        if( isset($_POST['customer_name'])){

            $meta_query = array(
                'relation'          => 'OR',
                array(
                    'key'       =>  '_billing_first_name',
                    'value'     =>  $_POST['customer_name'],
                    'compare'   =>  'LIKE',
                ),
                array(
                    'key'       =>  '_billing_last_name',
                    'value'     =>  $_POST['customer_name'],
                    'compare'   =>  'LIKE',
                ),
            );

        }else{

            $meta_query = '';
        }

        $args = array(
            'post_type'         =>  'shop_order',
            'post_status'       =>  $post_status,
            'posts_per_page'    =>  -1,
            'orderby'           =>  'the_date',
            'order'             =>  'DESC',
            'post__in'          =>  $order_post_in,
            'meta_query'        =>  $meta_query,
            //'limit'         =>  isset($_POST['deadline']) ? -1 : 10,
        
        );

    
        $query = new WP_Query( $args );

        $query_posts = $query->get_posts();

        foreach( $query_posts as $orderdata ){

            $order = wc_get_order($orderdata->ID);

            foreach ( $order->get_items() as $item_id => $item ) {

                $product_id = $item->get_product_id();

                $buyer = '';

                if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
                    /* translators: 1: first name 2: last name */
                    $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'wc-order-tracker' ), $order->get_billing_first_name(), $order->get_billing_last_name() ) );
                } elseif ( $order->get_billing_company() ) {
                    $buyer = trim( $order->get_billing_company() );
                } elseif ( $order->get_customer_id() ) {
                    $user  = get_user_by( 'id', $order->get_customer_id() );
                    $buyer = ucwords( $user->display_name );
                }

                $image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'single-post-thumbnail' );

                $wc_tracking_status_meta = wc_get_order_item_meta( $item_id, '_wc_tracking_status', true );

                $wc_tracking_url_meta = wc_get_order_item_meta( $item_id, '_wc_tracking', true );

                $wc_expect_date_meta = wc_get_order_item_meta( $item_id, '_wc_expect_date', true );


                if($wc_tracking_status_meta == 'picking_ready'){

                    $tracking_status = 'Picking Ready';

                }else if ($wc_tracking_status_meta == 'notes') {

                    $tracking_status = 'Notes';
                    
                }else if ($wc_tracking_status_meta == 'labor') {

                    $tracking_status = 'Labor';
                    
                }else if ($wc_tracking_status_meta == 'in_stock') {

                    $tracking_status = 'In Stock';

                }else if ($wc_tracking_status_meta == 'local') {

                    $tracking_status = 'Local';
                    
                }else if ($wc_tracking_status_meta == 'owner_check') {
                    
                    $tracking_status = 'Owner Check';

                }else if ($wc_tracking_status_meta == 'template') {
                    
                    $tracking_status = 'Template';

                }else if ($wc_tracking_status_meta == 'owner') {
                    
                    $tracking_status = 'By Owner';

                }else if ($wc_tracking_status_meta == 'pending') {
                    
                    $tracking_status = 'Pending';

                }else if ($wc_tracking_status_meta == 'discontinue') {
                    
                    $tracking_status = 'Discontinue';
                
                }else if ($wc_tracking_status_meta == 'in_progress') {
                    
                    $tracking_status = 'In Progress';
                
                }else {
                    
                    $tracking_status = 'Default';
                }


                $product = new WC_Product($product_id);

                $thumbnail = $image !='' ? '<img src="'. $image[0] .'" class="woo-product-thumbnail">' : $product->get_image('shop_thumbnail');

                $terms = get_the_terms ( $product_id, 'product_cat' );

                $catgories = array();
                $catgories_slug = array();

                foreach ( $terms as $term ) {
                
                    if (array_key_exists($product_id, $catgories)) {
                    
                        $catgories[$product_id] = $catgories[$product_id] .','. $term->name;

                    }else{

                        $catgories[$product_id] = $term->name;
                    }

                    if (array_key_exists($product_id, $catgories_slug)) {
                    
                        $catgories_slug[$product_id] = $catgories_slug[$product_id] .','. $term->slug;

                    }else{

                        $catgories_slug[$product_id] = $term->slug;
                    }
                }

                if(isset($_POST['product_category'])){

                    $cat_slug = $_POST['product_category'];

                    $cat_slug_arr = explode(',', $catgories_slug[$product_id]);

                    if ( in_array( $cat_slug, $cat_slug_arr ) ) {

                        $data[] = array(
                            'id'            => '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $order->ID ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>',
                            'item'          => '<a href="'. site_url( $product->get_slug() ) .'" target="_blank">'. $thumbnail .'</a>',
                            'description'   => $item->get_name(),
                            'status'        => $tracking_status,
                            'tracking'      => $wc_tracking_url_meta,
                            'expert'        => $wc_expect_date_meta,
                            'category'      => $catgories[$product_id],
                            //'action'     => '-'
                        );
                    }

                }else if(isset($_POST['trackingstatus'])){

                    $post_tracking_status = $_POST['trackingstatus'];

                    if( $wc_tracking_status_meta == $post_tracking_status ){

                            $data[] = array(
                            'id'            => '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $order->ID ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>',
                            'item'          => '<a href="'. site_url( $product->get_slug() ) .'" target="_blank">'. $thumbnail .'</a>',
                            'description'   => $item->get_name(),
                            'status'        => $tracking_status,
                            'tracking'      => $wc_tracking_url_meta,
                            'expert'        => $wc_expect_date_meta,
                            'category'      => $catgories[$product_id],
                            //'action'     => '-'
                        );
                    }

                }else{

                    $data[] = array(
                        'id'            => '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $order->ID ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>',
                        'item'          => '<a href="'. site_url( $product->get_slug() ) .'" target="_blank">'. $thumbnail .'</a>',
                        'description'   => $item->get_name(),
                        'status'        => $tracking_status,
                        'tracking'      => $wc_tracking_url_meta,
                        'expert'        => $wc_expect_date_meta,
                        'category'      => $catgories[$product_id],
                        //'action'     => '-'
                    );
                }

                
            }
            
         
        }

       return $data;
    }

    protected function get_views() { 
        $status_links = array(
            "all"       => __("<a href='#'>All</a>",'wc-order-tracker'),
            "published" => __("<a href='#'>Processing</a>",'wc-order-tracker'),
            "trashed"   => __("<a href='#'>On Hold</a>",'wc-order-tracker')
        );
        return $status_links;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'id':
            case 'item':
            case 'description':
            case 'status':
            case 'tracking':
            case 'expert':
            case 'category':
            //case 'action':
                return $item[ $column_name ];

            default:
                return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby    = 'id';
        $order      = 'DESC';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'desc')
        {
            return $result;
        }

        return -$result;
    }
}

add_action('woocommerce_admin_order_data_after_order_details', 'custom_admin_order_extra_fields', 10, 1 );

function custom_admin_order_extra_fields( $order ) {

    $deadline_date = get_post_meta( $order->id,'_deadline_date', true) ? get_post_meta( $order->id,'_deadline_date', true) : '';
    
    $current_date = date("d-m-Y");
    
    ?>
        <p class="form-field form-field-wide">
            <label for="deadline_date"><?php echo __( 'Deadline Date', 'wc-order-tracker' ); ?></label>
            <input type="date" class="date-picker hasDatepicker" name="_deadline_date" maxlength="10" value="<?php echo $deadline_date; ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
        </p>
        <p class="form-field form-field-wide">
            <label for="deadline_date"><?php echo __( 'Cost', 'wc-order-tracker' ); ?></label>
            <?php 
            $currency_code = $order->get_currency();
            $currency_symbol = get_woocommerce_currency_symbol( $currency_code );

            echo '<strong class="cost">'. $currency_symbol . $order->get_total() .'</strong>'; ?>
        </p>
        <p class="form-field form-field-wide">
            <label for="deadline_date"><?php echo __( 'Overdue Date', 'wc-order-tracker' ); ?></label>
            <?php  
            
            if($deadline_date){
                $order_created_date = date("d-m-Y", strtotime($order->get_date_created()));
                $order_deadlin_date = date("d-m-Y", strtotime($deadline_date));
                $datediff = strtotime($current_date) - strtotime($order_deadlin_date);
                if( strtotime($current_date) > strtotime($order_deadlin_date) ){
                    $due_date = abs(round($datediff / 86400)); 
                    echo '<strong class="due_days">'. $due_date .' days </strong>';
                }else{
                    echo '<strong class="due_days">0 day(s) </strong>';
                }
            }
            else{
                echo '0 day(s)';
            }
            ?>
        </p>
    <?php
}

add_action( 'save_post_shop_order', 'update_admin_order_extra_fields', 10, 3 );

function update_admin_order_extra_fields( $post_id, $post, $update ){
    // Orders in backend only
    global $wbdb, $woocommerce; 
    //if( ! is_admin() ) return;
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
        return $post_id;

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return $post_id;

    if ( ! current_user_can( 'edit_shop_order', $post_id ) )
        return $post_id;
    
    $order = wc_get_order( $post_id );
    
    foreach ( $order->get_items() as $item_id => $item ) {
        if( isset( $_POST['_wc_tracking_'.$item_id] ) ) {
            wc_update_order_item_meta( $item_id, '_wc_tracking', $_POST['_wc_tracking_'.$item_id]);
        }
        if( isset( $_POST['_wc_tracking_status_'.$item_id] ) ) {
            wc_update_order_item_meta( $item_id, '_wc_tracking_status', $_POST['_wc_tracking_status_'.$item_id]);
        }
        if( isset( $_POST['_wc_expect_date_'.$item_id] ) ) {
            wc_update_order_item_meta( $item_id, '_wc_expect_date', $_POST['_wc_expect_date_'.$item_id]);
        }
    }
    if ( isset( $_POST[ '_deadline_date' ] ) ) {
        update_post_meta( $post_id, '_deadline_date', $_POST[ '_deadline_date' ] );
    }
  
}

// Optionally Keep the new meta key/value as hidden in backend
add_filter( 'woocommerce_hidden_order_itemmeta', 'additional_hidden_order_itemmeta', 10, 1 );
function additional_hidden_order_itemmeta( $array ) {
    $array = array('_wc_tracking', '_wc_tracking_status');
    return $array;
}

//woocommerce order item header
function action_woocommerce_admin_order_item_headers( $order ) {
    echo '<th class="item_status sortable" data-sort="float">Tracking Status</th>';
    echo '<th class="item_tracking sortable" data-sort="float">Tracking URL</th>';
    echo '<th class="item_expect sortable" data-sort="float">Expect Date</th>';
};
add_action( 'woocommerce_admin_order_item_headers', 'action_woocommerce_admin_order_item_headers', 10, 3 );


//woocommerce order item custom meta field
add_action( 'woocommerce_admin_order_item_values', 'pd_admin_order_item_values', 10, 3 );

function pd_admin_order_item_values( $product, $item, $item_id ) {

    $wc_tracking_status_meta = wc_get_order_item_meta($item_id, '_wc_tracking_status' , true);

    $wc_cog_item_cost = wc_get_order_item_meta($item_id, '_wc_cog_item_cost' , true);
    

    echo '<td class="item_status">';
        $select_args = array(
            //'label'         =>  __( 'Tracking Status', 'wc-order-tracker' ),
            'placholder'    =>  __( 'Status', 'wc-order-tracker' ),
            'name'          =>  '_wc_tracking_status_'.$item_id,
            'value'         =>  $wc_tracking_status_meta,
            'style'         =>  'width: 100%',
            'options'       => array(
                'default'           => __( 'Default', 'wc-order-tracker' ),
                'in_progress'       => __( 'In Progress', 'wc-order-tracker' ),
                'discontinue'       => __( 'Discontinue', 'wc-order-tracker' ),
                'pending'           => __( 'Pending', 'wc-order-tracker' ),
                'owner'             => __( 'By Owner', 'wc-order-tracker' ),
                'template'          => __( 'Template', 'wc-order-tracker' ),
                'owner_check'       => __( 'By Owner Check', 'wc-order-tracker' ),
                'local'             => __( 'Local', 'wc-order-tracker' ),
                'in_stock'          => __( 'In Stock', 'wc-order-tracker' ),
                'labor'             => __( 'Labor', 'wc-order-tracker' ),
                'notes'             => __( 'Notes', 'wc-order-tracker' ),
                'picking_ready'     => __( 'Picking Ready', 'wc-order-tracker' ),
                
                
            )
        );
      
        woocommerce_wp_select($select_args);
    echo '</td>';

    echo '<td class="item_tracking">';
        $wc_tracking_meta = wc_get_order_item_meta($item_id, '_wc_tracking' , true);
        $args = array(
            //'label'         =>  __( 'Tracking URL', 'wc-order-tracker' ),
            'placholder'    =>  __( 'Tracking URL', 'wc-order-tracker' ),
            'name'          =>  '_wc_tracking_'.$item_id,
            'value'         =>  $wc_tracking_meta,
        );
        woocommerce_wp_text_input($args);

    echo'</td>';

    echo '<td class="item_expert">';
        $wc_expecting_meta = wc_get_order_item_meta($item_id, '_wc_expect_date' , true);
        $args = array(
            //'label'         =>  __( 'Tracking URL', 'wc-order-tracker' ),
            'placholder'    =>  __( 'Expect Date', 'wc-order-tracker' ),
            'name'          =>  '_wc_expect_date_'.$item_id,
            'value'         =>  $wc_expecting_meta,
            'type'          => 'date',
        );
        woocommerce_wp_text_input($args);

    echo'</td>';

}

// Add a custom metabox only for shop_order post type (order edit pages)
add_action( 'add_meta_boxes', 'wc_order_chart_box' );
function wc_order_chart_box()
{
    add_meta_box( 'order_chart', __( 'Chart' ), 'wc_order_chart_metabox_content', 'shop_order', 'normal', 'high');
}

function wc_order_chart_metabox_content(){ 
    
    $order_id = get_the_ID();
    
    $order = wc_get_order( $order_id );
    
    $items = $order->get_items();
    
    $item_status_arr = array();

    $item_data_arr = array();

    $item_status_count_arr = array();

    foreach ( $order->get_items() as $item_id => $item ) {
        
        $item_status_arr[] = wc_get_order_item_meta( $item_id, '_wc_tracking_status', true );

    }

    $item_status_count_arr = array_count_values($item_status_arr);
    
    $item_status_val_arr = array(
        'default'       =>  0,
        'in_progress'   =>  1,
        'discontinue'   =>  2,
        'pending'       =>  3,
        'owner'         =>  4,
        'template'      =>  100,
        'owner_check'   =>  100,
        'local'         =>  100,
        'in_stock'      =>  100,
        'labor'         =>  100,
        'notes'         =>  100,
        'picking_ready' =>  100
    );


    foreach($item_status_arr as $status ){
        $item_data_arr[] = $item_status_val_arr[$status];
    }

    $item_data_stats = array_count_values($item_data_arr);

    if(!empty($item_data_stats)){ ?>

       <div id="piechart" style="width: 700px; height: 400px; margin: 0 auto;"></div>
       <script type="text/javascript">
          google.load('visualization', '1.0', {'packages':['corechart']});
          google.setOnLoadCallback(drawChart);

          function drawChart() {

            var options = {
              title: 'Order Tracking Chart',
               colors: [<?php foreach ($item_data_stats as $key => $value) {
                    if($key == '100'){
                        echo '"#019267",';
                    }else if($key == '4'){
                        echo '"#4697BD",';
                    }else if($key == '3'){
                        echo '"#FFC300",';
                    }else if($key == '2'){
                        echo '"#FC4F4F",';
                    }else if($key == '1'){
                        echo '"#F76E11",';
                    }else{
                        echo '"#999999",';
                    }
               }?>],
                is3D: true
            };

            var data = new google.visualization.DataTable();

                data.addColumn('string', 'Status');
                data.addColumn('number', 'Count');
                data.addRows([<?php foreach ($item_data_stats as $key => $value) {

                    if($key == '100'){
                        echo "['Complete',". $value ."],";
                    }else if($key == '4'){
                        echo "['By Owner',". $value ."],";
                    }else if($key == '3'){
                        echo "['Pending',". $value ."],";
                    }else if($key == '2'){
                        echo "['Discontinue',". $value ."],";
                    }else if($key == '1'){
                        echo "['In Progress',". $value ."],";
                    }else{
                        echo "['No Compilation',". $value ."],";
                    }

                } ?>]);
                
                var chart = new google.visualization.PieChart(document.getElementById('piechart'));

                chart.draw(data, options);
          }
        </script>
    <?php }

    $counted = array_intersect ($item_data_stats,[max($item_data_stats)]);
    
}


function wc_order_script_in_admin() {
    wp_register_script( 'gchartjs', WC_ORDER_TRACKER_PLUGIN_URL . 'js/gchart.js', array('jquery'),true );
    wp_enqueue_script('gchartjs');
}

add_action('admin_enqueue_scripts', 'wc_order_script_in_admin');


add_action( 'woocommerce_thankyou', 'wc_order_tracker_update_meta', 4 );

function wc_order_tracker_update_meta( $order_id ) {

    $order = wc_get_order($order_id);

    foreach ($order->get_items() as $item_id => $item_obj) {

        wc_update_order_item_meta($item_id, '_wc_tracking_status', 'default');

    }
 
    clean_post_cache( $order->get_id() );
    wc_delete_shop_order_transients( $order );
    wp_cache_delete( 'order-items-' . $order->get_id(), 'orders' );
    die();
}

function wc_order_tracker_create_product_custom_field() {

    $args = array(
        'id'        => 'wc_product_tracking_id',
        'label'     => __( 'Tracking ID', 'wc-order-tracker' ),
        'class'     => 'wc-product-tracking-id',
    );
    woocommerce_wp_text_input( $args );

    $args = array(
        'id'        => 'wc_product_supplier_link',
        'label'     => __( 'Supplier Link', 'wc-order-tracker' ),
        'class'     => 'wc-product-supplier-link',
    );
    woocommerce_wp_text_input( $args );

}

add_action( 'woocommerce_product_options_general_product_data', 'wc_order_tracker_create_product_custom_field' );



function wc_order_tracker_product_custom_fields_save($post_id)
{
    $product = wc_get_product( $post_id );

    $wc_product_tracking_id = isset( $_POST['wc_product_tracking_id'] ) ? $_POST['wc_product_tracking_id'] : '';

    $wc_product_supplier_link = isset( $_POST['wc_product_supplier_link'] ) ? $_POST['wc_product_supplier_link'] : '';

    $product->update_meta_data( 'wc_product_tracking_id', sanitize_text_field( $wc_product_tracking_id ) );

    $product->update_meta_data( 'wc_product_supplier_link', sanitize_text_field( $wc_product_supplier_link ) );

    $product->save();
}

add_action('woocommerce_process_product_meta', 'wc_order_tracker_product_custom_fields_save');


add_action('admin_init', 'supplier_repeater_meta_boxes', 2);

function supplier_repeater_meta_boxes() {
    add_meta_box( 'supplier-repeater-metabox', 'Manage Supplier Link', 'supplier_repeatable_meta_box_callback', 'product', 'normal', 'default');
}

function supplier_repeatable_meta_box_callback( $post ){

    add_thickbox();

    global $wpdb;

    $table = $wpdb->prefix .'wc_order_tracker_supplier';

    $product_id = $post->ID;

    $response = $wpdb->get_results ( "SELECT * FROM  $table WHERE product_id = $product_id" );

    echo '<table id="supplierTable" class="table" width="100%">';
        echo '<thead>';
            echo '<th>'. __('S.No') .'</th>';
            echo '<th>'. __('User') .'</th>';
            echo '<th>'. __('Supplier Link') .'</th>';
            echo '<th>'. __('Supplier Notes') .'</th>';
            echo '<th>'. __('Action') .'</th>';
        echo '</thead>';
        echo '<tbody>';
            if(!empty($response)){
                
                $i = 1;

                foreach( $response as $data ){

                    $user = get_user_by( 'id', $data->user_id );

                    echo '<tr>';
                        echo '<td>'. $i .'</td>';
                        echo '<td>';
                            echo '<div class="profile">';
                                echo '<div class="thumb">'. get_avatar( $data->user_id , 32 ) .'</div>';
                                echo '<div class="content">';
                                    echo '<span class="name">'. $user->user_login .'</span>';
                                    echo '<span class="email">'. $user->user_email .'</span>';
                                echo '</div>';
                            echo '</div>';
                        echo '</td>';
                        echo '<td><a href="'. esc_url( $data->supplier_link ).'" target="_blank">'. esc_url( $data->supplier_link ).'</a></td>';
                        echo '<td>'. $data->supplier_notes .'</td>';
                        echo '<td><a href="#" class="editModalPopup" data-sid="'. esc_attr( $data->id ) .'">Edit</a> | <a href="#" class="deleteModalPopup" data-sid="'. esc_attr( $data->id ) .'">Delete</a></td>';
                    echo '</tr>';

                    $i++;
                }
            }
        echo '</tbody>';
    echo '</table>';

    echo '<div id="addNewSupplierLinkForm">';
        echo '<div class="input-group">';
            echo '<input type="text" name="supplier_link" class="wc-field" value="" placeholder="Supplier Link">';
        echo '</div>';
        echo '<div class="input-group">';
            echo '<textarea name="supplier_notes" class="wc-field" placeholder="Notes"></textarea>';
        echo '</div>';
        echo '<div class="input-group">';
            echo '<input type="hidden" name="user_id" value="'. get_current_user_id() .'">';
            echo '<input type="hidden" name="product_id" value="'. esc_attr($post->ID) .'">';
            echo '<input type="submit" class="button button-primary" value="Add New Supplier Link" id="addSupplierLink">';
        echo '</div>';
    echo '</div>';
    ?>

    <div id="editModalPopup" style="display:none;">
       <span class="close">X</span>
       <div class="content">
           
       </div>
    </div>
    <div id="deleteModalPopup" style="display:none;">
       <span class="close">X</span>
       <h4 class="content"><?php echo __('Are you sure want to delete the link?'); ?></h4>
       <button type="button" class="deletesupplierlink button" data-sid="">Yes</button>
    </div>
    <style>
        #supplierTable{
            border-collapse: collapse;
        }
        #supplierTable th {
            text-align: left;
            background: #ebecef;
        }
        #supplierTable, #supplierTable th, #supplierTable td {
            border: 1px solid #c3c4c7;
            padding: 8px 10px;
        }
        #editModalPopup,
        #deleteModalPopup{
            position: fixed;
            left: 0;
            right: 0;
            max-width: 500px;
            background: #FFF;
            top: 50%;
            transform: translateY(-50%);
            -webkit-transform: translateY(-50%);
            -moz-transform: translateY(-50%);
            -ms-transform: translateY(-50%);
            -o-transform: translateY(-50%);
            margin: 0 auto;
            border: 1px solid #DDD;
            z-index: 1;
        }
        #editModalPopup .close,
        #deleteModalPopup .close {
            position: absolute;
            right: 15px;
            top: 10px;
            width: 28px;
            height: 28px;
            line-height: 28px;
            background: #DDD;
            text-align: center;
            border-radius: 50%;
            cursor: pointer;
        }
        #editModalPopup .content,
        #deleteModalPopup .content{
            padding: 40px 15px 15px;
        }
        #editModalPopup input, #editModalPopup textarea {
            width: 100%;
            margin: 5px 0;
        }
        #supplierTable .profile {
            display: flex;
            align-items: center;
        }
       #supplierTable .profile span {
            margin-left: 5px;
        }
        #supplierTable .content > * {
            display: block;
        }
        .deletesupplierlink {
            margin-bottom: 20px !important;
            background: #e60911 !important;
            color: #FFF !important;
            border-color: #e60911 !important;
        }
        #deleteModalPopup{
            text-align: center;
        }
        #addNewSupplierLinkForm {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-gap: 15px;
            margin-top: 20px;
            align-items: center;
        }
        #addNewSupplierLinkForm .wc-field {
            width: 100%;
        }
    </style>
    <script type="text/javascript">
        jQuery(document).ready( function(){
            jQuery("#addSupplierLink").click(function(e){
                e.preventDefault();
                var user_id = jQuery('#addNewSupplierLinkForm input[name="user_id"]').val();
                var product_id = jQuery('#addNewSupplierLinkForm input[name="product_id"]').val();
                var supplier_link = jQuery('#addNewSupplierLinkForm input[name="supplier_link"]').val();
                var supplier_notes = jQuery('#addNewSupplierLinkForm textarea[name="supplier_notes"]').val();

                if(supplier_link.length > 0 ){
                    jQuery.ajax({
                        type: "POST",
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: {
                            action: 'addnewSupplierLink',
                            user_id : user_id,
                            product_id: product_id,
                            supplier_link: supplier_link,
                            supplier_notes : supplier_notes
                        },
                        dataType: "html",
                        success: function(response){
                            
                            if(response == 1){
                                location.reload();
                            }
                        }
                    });
                }else{
                    alert("Please enter the Supplier Link");
                }
            });

            jQuery(document).on("click",".editModalPopup",function(e) {

                e.preventDefault();

                var sid = jQuery(this).data('sid');

                jQuery.ajax({
                    type: "POST",
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'getSupplierLink',
                        sid : sid,
                    },
                    dataType: "html",
                    success: function(response){
                        //console.log(response);
                        jQuery('#editModalPopup .content').html(response);
                        jQuery('#editModalPopup').show();
                    }
                });

            });

            jQuery(document).on("click","#editModalPopup .close",function(e) {

                e.preventDefault();

                jQuery('#editModalPopup .content').empty();
                jQuery('#editModalPopup').hide();
            });

            jQuery(document).on("click","#deleteModalPopup .close",function(e) {

                e.preventDefault();

                jQuery('#deleteModalPopup .button').attr('data-sid', '');

                jQuery('#deleteModalPopup').hide();

            });

            jQuery(document).on("click","#updateSupplier",function(e) {
                e.preventDefault();
                var sid = jQuery(this).attr('data-sid');
                var supplier_link = jQuery('#editModalPopup').find('input[name="supplier_link"]').val();
                var supplier_notes = jQuery('#editModalPopup').find('textarea[name="supplier_notes"]').val();
                if(supplier_link.length > 0 ){
                    jQuery.ajax({
                        type: "POST",
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: {
                            action: 'updateSupplierLink',
                            sid : sid,
                            supplier_notes : supplier_notes,
                            supplier_link : supplier_link,
                        },
                        dataType: "html",
                        success: function(response){
                            if(response == 1){
                                location.reload();
                            }
                            
                        }
                    });
                }else{
                    alert("Please enter the Supplier Link");
                }
            });

            jQuery(document).on("click",".deleteModalPopup",function(e) {

                e.preventDefault();

                var sid = jQuery(this).data('sid');

                jQuery('#deleteModalPopup .button').attr('data-sid', sid);
                
                jQuery('#deleteModalPopup').show();

            });

            jQuery(document).on("click",".deletesupplierlink",function(e) {
                
                e.preventDefault();
                
                var sid = jQuery(this).data('sid');

                jQuery.ajax({
                    type: "POST",
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'deleteSupplierLink',
                        sid : sid,
                    },
                    dataType: "html",
                    success: function(response){
                        if(response == 1){
                            location.reload();
                        }
                        
                    }
                });

            });
        });
    </script>
    <?php
}

add_action( 'wp_ajax_nopriv_deleteSupplierLink', 'deleteSupplierLink' );
add_action( 'wp_ajax_deleteSupplierLink', 'deleteSupplierLink' );

function deleteSupplierLink(){
    
    if($_POST['action'] == 'deleteSupplierLink'){

        global $wpdb;

        $table = $wpdb->prefix .'wc_order_tracker_supplier';

        $wpdb->delete( $table, array( 'id' => $_POST['sid'] ) );

        echo json_encode(1);
    }

    wp_die();
}

add_action( 'wp_ajax_nopriv_updateSupplierLink', 'updateSupplierLink' );
add_action( 'wp_ajax_updateSupplierLink', 'updateSupplierLink' );

function updateSupplierLink(){
    
    if($_POST['action'] == 'updateSupplierLink'){

        global $wpdb;

        $table = $wpdb->prefix .'wc_order_tracker_supplier';

        $supplier_notes = isset($_POST['supplier_notes']) ? $_POST['supplier_notes'] : '';

        $supplier_link = isset($_POST['supplier_link']) ? $_POST['supplier_link'] : '';

        $sid = $_POST['sid'];

        $postdata = array(
            'supplier_link'     =>  $supplier_link,
            'supplier_notes'    =>  $supplier_notes,
            'updated_by'        =>  get_current_user_id(),
            'updated_date'      =>  date('Y-m-d H:i:s'),
        );


        $wpdb->update($table, $postdata, array( 'id' => $sid ) );

        echo json_encode(1);
    }
    wp_die();
}

add_action( 'wp_ajax_nopriv_getSupplierLink', 'getSupplierLink' );
add_action( 'wp_ajax_getSupplierLink', 'getSupplierLink' );

function getSupplierLink(){

    if($_POST['action'] == 'getSupplierLink'){

         global $wpdb;

        $table = $wpdb->prefix .'wc_order_tracker_supplier';

        $sid = isset($_POST['sid']) ? $_POST['sid'] : '';

        $response = $wpdb->get_row ( "SELECT * FROM  $table WHERE id = $sid" );

        if ($response) {
            echo '<div class="input-group">';
                echo '<input type="text" name="supplier_link" placeholder="Supplier Link" value="'. esc_url( $response->supplier_link ) .'">';
            echo '</div>';
            echo '<div class="input-group">';
                echo '<textarea name="supplier_notes" placeholder="Notes">'. esc_html( $response->supplier_notes ).'</textarea>';
            echo '</div>';
            echo '<button type="button" class="button button-primary" id="updateSupplier" data-sid="'. $response->id .'">Update</button>';
        }

    }

    wp_die();
}
add_action( 'wp_ajax_nopriv_addnewSupplierLink', 'addnewSupplierLink' );
add_action( 'wp_ajax_addnewSupplierLink', 'addnewSupplierLink' );

function addnewSupplierLink(){

    if($_POST['action'] == 'addnewSupplierLink'){

        global $wpdb;

        $table = $wpdb->prefix .'wc_order_tracker_supplier';

        $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
        $product_id = isset($_POST['product_id']) ? $_POST['product_id'] : '';
        $supplier_link = isset($_POST['supplier_link']) ? $_POST['supplier_link'] : '';
        $supplier_notes = isset($_POST['supplier_notes']) ? $_POST['supplier_notes'] : '';

        $postdata = array(
            'user_id'       =>  $user_id,
            'product_id'    =>  $product_id,
            'supplier_link' =>  $supplier_link,
            'supplier_notes'    =>  $supplier_notes,
            'updated_by'        =>  $user_id,
            'created_date'  =>  date('Y-m-d H:i:s'),
            'updated_date'  =>  date('Y-m-d H:i:s'),
            'status'        =>  1,
        );

        $res = $wpdb->insert( $table, $postdata);

        echo json_encode(1);
    }

    wp_die();
}
