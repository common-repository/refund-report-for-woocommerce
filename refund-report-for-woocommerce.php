<?php
/**
 * Plugin Name: Refund Report for WooCommerce
 * Description: Generates a report on product line-item refunds in WooCommerce for a specified time period.
 * Version: 1.0.1
 * Author: Potent Plugins
 * Author URI: http://potentplugins.com/?utm_source=refund-report-for-woocommerce&utm_medium=link&utm_campaign=wp-plugin-author-uri
 * License: GNU General Public License version 2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 */

if (!defined('ABSPATH')) exit;

// Add the Refund Report to the WordPress admin
add_action('admin_menu', 'pp_wcrr_admin_menu');
function pp_wcrr_admin_menu() {
	add_submenu_page('woocommerce', 'Refund Report', 'Refund Report', 'view_woocommerce_reports', 'pp_wcrr', 'pp_wcrr_page');
}

function pp_wcrr_default_report_settings() {
	return array(
		'report_time' => '30d',
		'report_start' => date('Y-m-d', current_time('timestamp') - (86400 * 31)),
		'report_end' => date('Y-m-d', current_time('timestamp') - 86400),
		'products' => 'all',
		'product_cats' => array(),
		'product_ids' => '',
		'orderby' => 'quantity',
		'orderdir' => 'desc',
		'fields' => array('product_id', 'product_sku', 'product_name', 'quantity', 'amount'),
		'limit_on' => 0,
		'limit' => 10,
		'include_header' => 1,
	);
}

// This function generates the Refund Report page
function pp_wcrr_page() {

	$savedReportSettings = get_option('pp_wcrr_report_settings');
	$reportSettings = (empty($savedReportSettings) ?
						pp_wcrr_default_report_settings() :
						array_merge(pp_wcrr_default_report_settings(),
								$savedReportSettings[
									isset($_POST['r']) && isset($savedReportSettings[$_POST['r']]) ? $_POST['r'] : 0
								]
						));
	
	$fieldOptions = array(
		'product_id' => 'Product ID',
		'product_sku' => 'Product SKU',
		'product_name' => 'Product Name',
		'product_categories' => 'Product Categories',
		'quantity' => 'Quantity Refunded',
		'amount' => 'Amount Refunded',
	);
		
	
	// Print header
	echo('
		<div class="wrap">
			<h2>Refund Report</h2>
	');
	
	// Check for WooCommerce
	if (!class_exists('WooCommerce')) {
		echo('<div class="error"><p>This plugin requires that WooCommerce is installed and activated.</p></div></div>');
		return;
	}
	
	// Print form
	
	echo('<div id="poststuff">
			<div id="post-body" class="columns-2">
				<div id="post-body-content" style="position: relative;">
					<form action="#pp_wcrr_table" method="post">
						<input type="hidden" name="pp_wcrr_do_export" value="1" />
		');
	wp_nonce_field('pp_wcrr_do_export');
	echo('
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="pp_wcrr_field_report_time">Report Period:</label>
						</th>
						<td>
							<select name="report_time" id="pp_wcrr_field_report_time">
								<option value="0d"'.($reportSettings['report_time'] == '0d' ? ' selected="selected"' : '').'>Today</option>
								<option value="1d"'.($reportSettings['report_time'] == '1d' ? ' selected="selected"' : '').'>Yesterday</option>
								<option value="7d"'.($reportSettings['report_time'] == '7d' ? ' selected="selected"' : '').'>Last 7 days</option>
								<option value="30d"'.($reportSettings['report_time'] == '30d' ? ' selected="selected"' : '').'>Last 30 days</option>
								<option value="all"'.($reportSettings['report_time'] == 'all' ? ' selected="selected"' : '').'>All time</option>
								<option value="custom"'.($reportSettings['report_time'] == 'custom' ? ' selected="selected"' : '').'>Custom date range</option>
							</select>
						</td>
					</tr>
					<tr valign="top" class="pp_wcrr_custom_time">
						<th scope="row">
							<label for="pp_wcrr_field_report_start">Start Date:</label>
						</th>
						<td>
							<input type="date" name="report_start" id="pp_wcrr_field_report_start" value="'.$reportSettings['report_start'].'" />
						</td>
					</tr>
					<tr valign="top" class="pp_wcrr_custom_time">
						<th scope="row">
							<label for="pp_wcrr_field_report_end">End Date:</label>
						</th>
						<td>
							<input type="date" name="report_end" id="pp_wcrr_field_report_end" value="'.$reportSettings['report_end'].'" />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label>Include Products:</label>
						</th>
						<td>
							<label><input type="radio" name="products" value="all"'.($reportSettings['products'] == 'all' ? ' checked="checked"' : '').' /> All products</label><br />
							<label><input type="radio" name="products" value="cats"'.($reportSettings['products'] == 'cats' ? ' checked="checked"' : '').' /> Products in categories:</label><br />
							<div style="padding-left: 20px; width: 300px; max-height: 200px; overflow-y: auto;">
						');
	foreach (get_terms('product_cat', array('hierarchical' => false)) as $term) {
		echo('<label><input type="checkbox" name="product_cats[]"'.(in_array($term->term_id, $reportSettings['product_cats']) ? ' checked="checked"' : '').' value="'.$term->term_id.'" /> '.htmlspecialchars($term->name).'</label><br />');
	}
				echo('
							</div>
							<label><input type="radio" name="products" value="ids"'.($reportSettings['products'] == 'ids' ? ' checked="checked"' : '').' /> Product ID(s):</label> 
							<input type="text" name="product_ids" style="width: 400px;" placeholder="Use commas to separate multiple product IDs" value="'.htmlspecialchars($reportSettings['product_ids']).'" /><br />
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="pp_wcrr_field_orderby">Sort By:</label>
						</th>
						<td>
							<select name="orderby" id="pp_wcrr_field_orderby">
								<option value="product_id"'.($reportSettings['orderby'] == 'product_id' ? ' selected="selected"' : '').'>Product ID</option>
								<option value="quantity"'.($reportSettings['orderby'] == 'quantity' ? ' selected="selected"' : '').'>Quantity Refunded</option>
								<option value="amount"'.($reportSettings['orderby'] == 'amount' ? ' selected="selected"' : '').'>Amount Refunded</option>
							</select>
							<select name="orderdir">
								<option value="asc"'.($reportSettings['orderdir'] == 'asc' ? ' selected="selected"' : '').'>ascending</option>
								<option value="desc"'.($reportSettings['orderdir'] == 'desc' ? ' selected="selected"' : '').'>descending</option>
							</select>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label>Report Fields:</label>
						</th>
						<td id="pp_wcrr_report_field_selection">');
	$fieldOptions2 = $fieldOptions;
	foreach ($reportSettings['fields'] as $fieldId) {
		if (!isset($fieldOptions2[$fieldId]))
			continue;
		echo('<label><input type="checkbox" name="fields[]" checked="checked" value="'.$fieldId.'"'.(in_array($fieldId, array('variation_id', 'variation_attributes')) ? ' class="variation-field"' : '').' /> '.$fieldOptions2[$fieldId].'</label>');
		unset($fieldOptions2[$fieldId]);
	}
	foreach ($fieldOptions2 as $fieldId => $fieldDisplay) {
		echo('<label><input type="checkbox" name="fields[]" value="'.$fieldId.'"'.(in_array($fieldId, array('variation_id', 'variation_attributes')) ? ' class="variation-field"' : '').' /> '.$fieldDisplay.'</label>');
	}
	unset($fieldOptions2);
				echo('</td>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2" class="th-full">
							<label>
								<input type="checkbox" name="limit_on"'.(empty($reportSettings['limit_on']) ? '' : ' checked="checked"').' />
								Show only the first
								<input type="number" name="limit" value="'.$reportSettings['limit'].'" min="0" step="1" class="small-text" />
								products
							</label>
						</th>
					</tr>
					<tr valign="top">
						<th scope="row" colspan="2" class="th-full">
							<label>
								<input type="checkbox" name="include_header"'.(empty($reportSettings['include_header']) ? '' : ' checked="checked"').' />
								Include header row
							</label>
						</th>
					</tr>
				</table>');
				
				echo('<p class="submit">
					<button type="submit" class="button-primary" onclick="jQuery(this).closest(\'form\').attr(\'target\', \'\'); return true;">View Report</button>
					<button type="submit" class="button-primary" name="pp_wcrr_download" value="1" onclick="jQuery(this).closest(\'form\').attr(\'target\', \'_blank\'); return true;">Download Report as CSV</button>
				</p>
			</form>
			
			</div> <!-- /post-body-content -->
			
			<div id="postbox-container-1" class="postbox-container">
				<div id="side-sortables" class="meta-box-sortables">
				
					<div class="postbox">
						<h2><a href="http://potentplugins.com/downloads/product-sales-report-pro-wordpress-plugin/?utm_source=refund-report-for-woocommerce&amp;utm_medium=link&amp;utm_campaign=wp-plugin-upgrade-link" target="_blank">Need more options?</a></h2>
						<div class="inside">
							<p>
								<strong>For more advanced product refund and sales reporting, check out <a href="http://potentplugins.com/downloads/product-sales-report-pro-wordpress-plugin/?utm_source=refund-report-for-woocommerce&amp;utm_medium=link&amp;utm_campaign=wp-plugin-upgrade-link" target="_blank">Product Sales Report Pro</a>!</strong>
							</p>
						</div>
					</div>
					
				</div> <!-- /side-sortables-->
			</div><!-- /postbox-container-1 -->
			
			</div> <!-- /post-body -->
			<br class="clear" />
			</div> <!-- /poststuff -->
			
			');
			
			
			if (!empty($_POST['pp_wcrr_do_export'])) {
				echo('<table id="pp_wcrr_table">');
				if (!empty($_POST['include_header'])) {
					echo('<thead><tr>');
					foreach (pp_wcrr_export_header(null, true) as $rowItem)
						echo('<th>'.htmlspecialchars($rowItem).'</th>');
					echo('</tr></thead>');
				}
				echo('<tbody>');
				foreach (pp_wcrr_export_body(null, true) as $row) {
					echo('<tr>');
					foreach ($row as $rowItem) {
						echo('<td>'.htmlspecialchars($rowItem).'</td>');
					}
					echo('</tr>');
				}
				echo('</tbody></table>');
				
			}
			
			$potent_slug = 'refund-report-for-woocommerce';
			include(__DIR__.'/plugin-credit.php');
			
			echo('
				<h4>More <strong style="color: #f00;">free</strong> plugins for WooCommerce:</h4>
				<a href="https://wordpress.org/plugins/product-sales-report-for-woocommerce/" target="_blank" style="margin-right: 10px;"><img src="'.plugins_url('images/psr-icon.png', __FILE__).'" alt="Product Sales Report" /></a>
				<a href="https://wordpress.org/plugins/export-order-items-for-woocommerce/" target="_blank" style="margin-right: 10px;"><img src="'.plugins_url('images/xoiwc-icon.png', __FILE__).'" alt="Export Order Items" /></a>
				<a href="https://wordpress.org/plugins/stock-export-and-import-for-woocommerce/" target="_blank" style="margin-right: 10px;"><img src="'.plugins_url('images/sxiwc-icon.png', __FILE__).'" alt="Stock Export and Import" /></a>
				<a href="https://wordpress.org/plugins/sales-trends-for-woocommerce/" target="_blank" style="margin-right: 10px;"><img src="'.plugins_url('images/wcst-icon.png', __FILE__).'" alt="Sales Trends" /></a>
			');

			
	echo('
		</div>
		
		<script type="text/javascript" src="'.plugins_url('js/pp-refund-report.js', __FILE__).'"></script>
	');
	
	


}

// Hook into WordPress init; this function performs report generation when
// the admin form is submitted
add_action('init', 'pp_wcrr_on_init');
function pp_wcrr_on_init() {
	global $pagenow;
	
	// Check if we are in admin and on the report page
	if (!is_admin())
		return;
	if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'pp_wcrr' && !empty($_POST['pp_wcrr_do_export'])) {
		
		// Verify the nonce
		check_admin_referer('pp_wcrr_do_export');
		
		$newSettings = array_intersect_key($_POST, pp_wcrr_default_report_settings());
		foreach ($newSettings as $key => $value)
			if (!is_array($value))
				$newSettings[$key] = htmlspecialchars($value);
		
		// Update the saved report settings
		$savedReportSettings = get_option('pp_wcrr_report_settings');
		$savedReportSettings[0] = array_merge(pp_wcrr_default_report_settings(), $newSettings);
		

		update_option('pp_wcrr_report_settings', $savedReportSettings);
		
		// Check if no fields are selected or if not downloading
		if (empty($_POST['fields']) || empty($_POST['pp_wcrr_download']))
			return;
		
		// Assemble the filename for the report download
		$filename =  'Product Refunds - ';
		if (!empty($_POST['cat']) && is_numeric($_POST['cat'])) {
			$cat = get_term($_POST['cat'], 'product_cat');
			if (!empty($cat->name))
				$filename .= addslashes(html_entity_decode($cat->name)).' - ';
		}
		$filename .= date('Y-m-d', current_time('timestamp')).'.csv';
		
		// Send headers
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		
		// Output the report header row (if applicable) and body
		$stdout = fopen('php://output', 'w');
		if (!empty($_POST['include_header']))
			pp_wcrr_export_header($stdout);
		pp_wcrr_export_body($stdout);
		
		exit;
	}
}

// This function outputs the report header row
function pp_wcrr_export_header($dest, $return=false) {
	$header = array();
	
	foreach ($_POST['fields'] as $field) {
		switch ($field) {
			case 'product_id':
				$header[] = 'Product ID';
				break;
			case 'product_sku':
				$header[] = 'Product SKU';
				break;
			case 'product_name':
				$header[] = 'Product Name';
				break;
			case 'quantity':
				$header[] = 'Quantity Refunded';
				break;
			case 'amount':
				$header[] = 'Amount Refunded';
				break;
			case 'product_categories':
				$header[] = 'Product Categories';
				break;
		}
	}
	
	if ($return)
		return $header;
	fputcsv($dest, $header);
}

// This function generates and outputs the report body rows
function pp_wcrr_export_body($dest, $return=false) {
	global $woocommerce, $wpdb;
	
	$product_ids = array();
	if ($_POST['products'] == 'cats') {
		$cats = array();
		foreach ($_POST['product_cats'] as $cat)
			if (is_numeric($cat))
				$cats[] = $cat;
		$product_ids = get_objects_in_term($cats, 'product_cat');
	} else if ($_POST['products'] == 'ids') {
		foreach (explode(',', $_POST['product_ids']) as $productId) {
			$productId = trim($productId);
			if (is_numeric($productId))
				$product_ids[] = $productId;
		}
	}
	
	// Calculate report start and end dates (timestamps)
	switch ($_POST['report_time']) {
		case '0d':
			$end_date = strtotime('midnight', current_time('timestamp'));
			$start_date = $end_date;
			break;
		case '1d':
			$end_date = strtotime('midnight', current_time('timestamp')) - 86400;
			$start_date = $end_date;
			break;
		case '7d':
			$end_date = strtotime('midnight', current_time('timestamp')) - 86400;
			$start_date = $end_date - (86400 * 7);
			break;
		case 'custom':
			$end_date = strtotime('midnight', strtotime($_POST['report_end']));
			$start_date = strtotime('midnight', strtotime($_POST['report_start']));
			break;
		default: // 30 days is the default
			$end_date = strtotime('midnight', current_time('timestamp')) - 86400;
			$start_date = $end_date - (86400 * 30);
	}
	
	// Assemble order by string
	$orderby = (in_array($_POST['orderby'], array('product_id', 'amount')) ? $_POST['orderby'] : 'quantity');
	$orderby .= ' '.($_POST['orderdir'] == 'asc' ? 'ASC' : 'DESC');
	
	// Create a new WC_Admin_Report object
	include_once($woocommerce->plugin_path().'/includes/admin/reports/class-wc-admin-report.php');
	$wc_report = new WC_Admin_Report();
	$wc_report->start_date = $start_date;
	$wc_report->end_date = $end_date;
	
	$where_meta = array();
	if ($_POST['products'] != 'all') {
		$where_meta[] = array(
			'type' => 'order_item_meta',
			'meta_key' => '_product_id',
			'operator' => 'in',
			'meta_value' => $product_ids
		);
	}
	
	// Get report data
	
	// Avoid max join size error
	$wpdb->query('SET SQL_BIG_SELECTS=1');

	// Based on woocoommerce/includes/admin/reports/class-wc-report-sales-by-product.php
	$reportParams = array(
		'data' => array(
			'_product_id' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => '',
				'name' => 'product_id'
			),
			'_qty' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => 'SUM',
				'name' => 'quantity'
			),
			'_line_subtotal' => array(
				'type' => 'order_item_meta',
				'order_item_type' => 'line_item',
				'function' => 'SUM',
				'name' => 'amount'
			)
		),
		'query_type' => 'get_results',
		'group_by' => 'product_id',
		'where_meta' => $where_meta,
		'order_by' => $orderby,
		'limit' => (!empty($_POST['limit_on']) && is_numeric($_POST['limit']) ? $_POST['limit'] : ''),
		'filter_range' => ($_POST['report_time'] != 'all'),
		'order_types' => array('shop_order_refund'),
		'order_status' => array()
	);
	$refunded_products = $wc_report->get_order_report_data($reportParams);
	
	if ($return)
		$rows = array();

	// Output report rows
	foreach ($refunded_products as $product) {
		if ($product->quantity == 0 && $product->amount == 0)
			continue;
	
		$row = array();
		
		foreach ($_POST['fields'] as $field) {
			switch ($field) {
				case 'product_id':
					$row[] = $product->product_id;
					break;
				case 'product_sku':
					$row[] = get_post_meta($product->product_id, '_sku', true);
					break;
				case 'product_name':
					$row[] = html_entity_decode(get_the_title($product->product_id));
					break;
				case 'quantity':
					$row[] = abs($product->quantity);
					break;
				case 'amount':
					$row[] = number_format($product->amount * -1, 2);
					break;
				case 'product_categories':
					$terms = get_the_terms($product->product_id, 'product_cat');
					if (empty($terms)) {
						$row[] = '';
					} else {
						$categories = array();
						foreach ($terms as $term)
							$categories[] = $term->name;
						$row[] = implode(', ', $categories);
					}
					break;
			}
		}
			
		if ($return)
			$rows[] = $row;
		else
			fputcsv($dest, $row);
	}
	if ($return)
		return $rows;
}

add_action('admin_enqueue_scripts', 'pp_wcrr_admin_enqueue_scripts');
function pp_wcrr_admin_enqueue_scripts() {
	wp_enqueue_style('pp_wcrr_admin_style', plugins_url('css/pp-refund-report.css', __FILE__));
	wp_enqueue_style('pikaday', plugins_url('css/pikaday.css', __FILE__));
	wp_enqueue_script('moment', plugins_url('js/moment.min.js', __FILE__));
	wp_enqueue_script('pikaday', plugins_url('js/pikaday.js', __FILE__));
}

// Schedulable email report hook
add_filter('pp_wc_get_schedulable_email_reports', 'pp_wcrr_add_schedulable_email_reports');
function pp_wcrr_add_schedulable_email_reports($reports) {
	$reports['pp_wcrr'] = array(
		'name' => 'Refund Report',
		'callback' => 'pp_wcrr_run_scheduled_report',
		'reports' => array(
			'last' => 'Last used settings'
		)
	);
	return $reports;
}

function pp_wcrr_run_scheduled_report($reportId, $start, $end, $args=array(), $output=false) {
	$savedReportSettings = get_option('pp_wcrr_report_settings');
	if (!isset($savedReportSettings[0]))
		return false;
	$prevPost = $_POST;
	$_POST = $savedReportSettings[0];
	$_POST['report_time'] = 'custom';
	$_POST['report_start'] = date('Y-m-d', $start);
	$_POST['report_end'] = date('Y-m-d', $end);
	$_POST = array_merge($_POST, array_intersect_key($args, $_POST));
	
	if ($output) {
		echo('<table><thead><tr>');
		foreach (pp_wcrr_export_header(null, true) as $heading) {
			echo("<th>$heading</th>");
		}
		echo('</tr></thead><tbody>');
		foreach (pp_wcrr_export_body(null, true) as $row) {
			echo('<tr>');
			foreach ($row as $cell)
				echo('<td>'.htmlspecialchars($cell).'</td>');
			echo('</tr>');
		}
		echo('</tbody></table>');
		$_POST = $prevPost;
		return;
	}
	
	$filename = get_temp_dir().'/Refund Report.csv';
	$out = fopen($filename, 'w');
	if (!empty($_POST['include_header']))
		pp_wcrr_export_header($out);
	pp_wcrr_export_body($out);
	fclose($out);
	
	$_POST = $prevPost;
	
	return $filename;
}

/* Review/donate notice */

register_activation_hook(__FILE__, 'pp_wcrr_first_activate');
function pp_wcrr_first_activate() {
	$pre = 'pp_wcrr';
	$firstActivate = get_option($pre.'_first_activate');
	if (empty($firstActivate)) {
		update_option($pre.'_first_activate', time());
	}
}
if (is_admin() && get_option('pp_wcrr_rd_notice_hidden') != 1 && time() - get_option('pp_wcrr_first_activate') >= (14*86400)) {
	add_action('admin_notices', 'pp_wcrr_rd_notice');
	add_action('wp_ajax_pp_wcrr_rd_notice_hide', 'pp_wcrr_rd_notice_hide');
}
function pp_wcrr_rd_notice() {
	$pre = 'pp_wcrr';
	$slug = 'refund-report-for-woocommerce';
	echo('
		<div id="'.$pre.'_rd_notice" class="updated notice is-dismissible"><p>Do you use the <strong>Refund Report</strong> plugin?
		Please support our free plugin by <a href="https://wordpress.org/support/view/plugin-reviews/'.$slug.'" target="_blank">writing a review</a> and/or <a href="https://potentplugins.com/donate/?utm_source='.$slug.'&amp;utm_medium=link&amp;utm_campaign=wp-plugin-notice-donate-link" target="_blank">making a donation</a>!
		Thanks!</p></div>
		<script>jQuery(document).ready(function($){$(\'#'.$pre.'_rd_notice\').on(\'click\', \'.notice-dismiss\', function(){jQuery.post(ajaxurl, {action:\'pp_wcrr_rd_notice_hide\'})});});</script>
	');
}
function pp_wcrr_rd_notice_hide() {
	$pre = 'pp_wcrr';
	update_option($pre.'_rd_notice_hidden', 1);
}
?>