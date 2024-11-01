<?php
/*
Plugin Name: Term meta API
Plugin URI: mailto: karev.n@gmail.com
Description: Adds API that allows to manage terms meta the same way it is done to posts and users.
Version: 1.0
Author: Nikolay Karev
Author URI: mailto: karev.n@gmail.com
*/

global $wpdb;
$wpdb->termmeta = $wpdb->prefix . "termmeta";

register_activation_hook(__FILE__, 'tma_activation_hook');
function tma_activation_hook(){
	global $wpdb;
	$wpdb->termmeta = $wpdb->prefix . "termmeta";
	if($wpdb->get_var("SHOW TABLES LIKE '$wpdb->termmeta'") != $wpdb->termmeta) {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$sql = "CREATE TABLE `$wpdb->termmeta` (
			`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`meta_key` varchar(255) DEFAULT NULL,
			`meta_value` longtext,
			PRIMARY KEY (`meta_id`),
			KEY `term_id` (`term_id`),
			KEY `meta_key` (`meta_key`)
		);";
		dbDelta($sql);
	}
}

function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = ''){
	return update_metadata('term', $term_id, $meta_key, $meta_value, $prev_value);
}

function add_term_meta($term_id, $meta_key, $meta_value, $unique = false){
	return add_metadata('term', $term_id, $meta_key, $meta_value, $unique);
}

function delete_term_meta($term_id, $meta_key, $meta_value = '', $delete_all = false){
	return delete_metadata('term', $term_id, $meta_key, $meta_value, $delete_all);
}

function get_term_meta($term_id, $key, $single = true){
	return  get_metadata('term', $term_id, $key, $single);
}

add_filter('list_terms_exclusions', 'tma_filter_list_term_exclusions', 10, 2);

function tma_filter_list_term_exclusions($exclusions, $args){
	global $wpdb;
	if (isset($args['meta_compare']) && is_array($args['meta_compare'])) {
		foreach($args['meta_compare'] as $var){
			if ($var['value'] && in_array($var['operation'], array('>', '<', '>=', '<=', '='))) {
				$op = $var['operation'];
				$val = $var['value'];
				if (is_string($val)) $val = "'" . addslashes($val) . "'";
				if (in_array($op, array('>', '<', '>=', '<=', '='))) {
					$exclusions .= $wpdb->prepare(
						" AND t.term_id IN (
							SELECT tm.term_id FROM $wpdb->termmeta tm 
							WHERE tm.term_id = term_id AND meta_key = %s AND meta_value $op $val )", 
						$var['key']);
				}
			}
		}
	}
	return $exclusions;
}

add_filter('get_terms', 'tma_filter_get_terms', 10, 3);

function tma_filter_get_terms($terms, $taxonomies, $args){
	global $wpdb;
	if (isset($args['orderby_meta']) && $args['orderby_meta'] && count($terms)){
		$ids = array();
		foreach($terms as $term) $ids []= $term->term_id;
		$ids = implode(',', $ids);
		$ordered = $wpdb->get_col($wpdb->prepare("SELECT t.term_id FROM $wpdb->terms t LEFT OUTER JOIN $wpdb->termmeta tm ON t.term_id = tm.term_id WHERE t.term_id IN ($ids) AND (tm.meta_key = %s OR tm.meta_key IS NULL) ORDER BY CAST(tm.meta_value AS SIGNED) ASC", $args['orderby_meta']));
		$newterms = array();
		$termhash = array();
		foreach($terms as $term) $termhash[$term->term_id] = $term;
		foreach($ordered as $id) $newterms []= $termhash[$id];
		$terms = $newterms;
	}
	return $terms;
}