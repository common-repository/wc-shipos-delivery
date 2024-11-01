<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://matat.co.il
 * @since      1.0.0
 *
 * @package    Deliver_Via_Shipos_For_Woocommerce_2
 * @subpackage Deliver_Via_Shipos_For_Woocommerce_2/admin/partials
 */
?>

<?php

$url_parts = wp_parse_url( home_url() );
$domain    = $url_parts['host'];
$app_url   = get_option( 'dvsfw_dev_mode' ) == 'yes' ? 'https://stg.shipos.co.il' : 'https://app.shipos.co.il';

// Override the app_url for local testing if defined (in wp-config.php)
$app_url = defined( 'DVSFW_APP_URL' ) ? DVSFW_APP_URL : $app_url;

$query = http_build_query( array(
	"store"      => $domain,
	"token"      => get_option( 'dvsfw_shipos_token' ),
	"return_url" => admin_url() . 'admin.php?page=deliver-via-shipos',
) );

$spa_query_arr = [];

$select_ids = $_GET['dvsfw_select_ids'] ?? [];
foreach ( $select_ids as $id ) {
	$spa_query_arr[] = "select_ids=$id";
}

if ( isset( $_GET['dvsfw_action'] ) ) {
	$spa_query_arr[] = "action=" . $_GET['dvsfw_action'];
}
$spa_query = implode( "&", $spa_query_arr );
?>

<div class="wrap woocommerce" style="margin: 10px 15px;">
    <iframe src="<?php esc_html_e( $app_url, 'wc-shipos-delivery' ) ?>/wp/app? <?php esc_html_e( $query, 'wc-shipos-delivery' ) ?>#/orders?<?php esc_html_e( $spa_query, 'wc-shipos-delivery' ) ?>"
            style="width: 100%; height: 90vh"></iframe>
    <script>
        window.history.pushState({}, document.title, window.location.pathname + '?page=deliver-via-shipos');
    </script>
</div>

<style>
    #wpcontent, #wpbody-content {
        padding: 0;
    }

    .wrap {
        margin: 0
    }
</style>
