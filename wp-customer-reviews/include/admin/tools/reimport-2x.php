<?php
if (!defined('ABSPATH')) {
	exit;
}

if (function_exists("current_user_can") === false || current_user_can('manage_options') !== true) {
	die("Access Denied");
}

// imports 2x reviews
// Included from admin options form; nonce verified before include.
// phpcs:ignore WordPress.Security.NonceVerification.Missing
if (!isset($_POST['wpcr3_confirm']) || $_POST['wpcr3_confirm'] !== 'YES') {
	?>
	This will re-import all reviews AND settings from v2.x, even if they were previously imported.<br /><br />
	To continue the importing process, type YES in all caps below.<br /><br />
	<input name="wpcr3_confirm" type="text" value="" />&nbsp;&nbsp;
	<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Resubmit of admin options form; nonce verified before include. ?>
	<input name="wpcr3_debug_code" type="hidden" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['wpcr3_debug_code'] ?? ''))); ?>" />
	<input type="submit" value="Confirm" />
	<?php
	die();
}

// remove upgraded bit from 2x settings
$wpcr3_old_2x_options = get_option("wpcr_options");
$wpcr3_old_2x_options['migrated_to_3x'] = 0;
update_option('wpcr_options', $wpcr3_old_2x_options);

// remove 3x upgraded bit for all wp posts
$wpcr3_query_opts = array(
	'nopaging' => true,
	'post_type' => 'any',
	'post_status' => 'publish,pending,draft,future,private,trash',
	'meta_query' => array(
		array(
			'key' => 'wpcr_migrated_to_3x',
			'value' => '1',
			'compare' => '='
		)
	)
);
$wpcr3_migrated_posts = new WP_Query($wpcr3_query_opts);
foreach ($wpcr3_migrated_posts->posts as $post) {
	delete_post_meta($post->ID, 'wpcr_migrated_to_3x');
}

// run 2x-3x migrate script
include($this->getplugindir().'include/migrate/2x-3x.php');
$wpcr3_migrate_ok = wpcr3_migrate_2x_3x($this, 248);
