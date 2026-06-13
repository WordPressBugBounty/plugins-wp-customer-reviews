<?php
if (!defined('ABSPATH')) {
	exit;
}

if (function_exists("current_user_can") === false || current_user_can('manage_options') !== true) {
	die("Access Denied");
}

// Included from admin options form; nonce verified before include.
// phpcs:ignore WordPress.Security.NonceVerification.Missing
if (!isset($_POST['wpcr3_confirm']) || $_POST['wpcr3_confirm'] !== 'YES') {
	?>
	This will permanently delete all of your reviews.<br /><br />
	To continue the deletion process, type YES in all caps below.<br /><br />
	<input name="wpcr3_confirm" type="text" value="" />&nbsp;&nbsp;
	<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Resubmit of admin options form; nonce verified before include. ?>
	<input name="wpcr3_debug_code" type="hidden" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['wpcr3_debug_code'] ?? ''))); ?>" />
	<input type="submit" value="Confirm" />
	<?php
	die();
}

$wpcr3_query_opts = array(
	'nopaging' => true,
	'post_type' => 'wpcr3_review',
	'post_status' => 'publish,pending,draft,future,private,trash'
);
$wpcr3_posts = new WP_Query($wpcr3_query_opts);
foreach ($wpcr3_posts->posts as $post) {
	printf(
		'Deleting review %1$s<br />',
		esc_html((string) $post->ID)
	);
	wp_delete_post($post->ID, true);
}
