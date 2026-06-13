<?php
if (!defined('ABSPATH')) {
	exit;
}

if (function_exists("current_user_can") === false || current_user_can('manage_options') !== true) {
	die("Access Denied");
}

// removes duplicate 3x reviews
// determines duplicate by name + email + reviewed page id + date
// keeps lowest page ids found
// Included from admin options form; nonce verified before include.
// phpcs:ignore WordPress.Security.NonceVerification.Missing
if (!isset($_POST['wpcr3_confirm']) || $_POST['wpcr3_confirm'] !== 'YES') {
	?>
	This will attempt to de-dupe reviews. Duplicates are determined by comparing title + content + timestamp + reviewed page id<br /><br />
	To continue the duplicate removal process, type YES in all caps below.<br /><br />
	<input name="wpcr3_confirm" type="text" value="" />&nbsp;&nbsp;
	<?php // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Resubmit of admin options form; nonce verified before include. ?>
	<input name="wpcr3_debug_code" type="hidden" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_POST['wpcr3_debug_code'] ?? ''))); ?>" />
	<input type="submit" value="Confirm" />
	<?php
	die();
}

$wpcr3_query_opts = array(
	'nopaging' => true,
	'post_type' => $this->prefix.'_review',
	'post_status' => 'publish,pending,draft,future,private' // no trash
);
$wpcr3_posts = new WP_Query($wpcr3_query_opts);

$wpcr3_unique_hashes = array();

foreach ($wpcr3_posts->posts as $post) {
	$wpcr3_reviewed_post_id = get_post_meta($post->ID, $this->prefix.'_review_post' ,true);
	$wpcr3_hash = "'{$post->post_date}'__'{$post->post_title}'__'{$post->post_content}'__'{$wpcr3_reviewed_post_id}'";
	$wpcr3_hash = md5($wpcr3_hash);

	if (!in_array($wpcr3_hash, $wpcr3_unique_hashes, true)) {
		$wpcr3_unique_hashes[] = $wpcr3_hash;
		continue;
	}

	printf(
		'Deleting duplicate review %1$s %2$s<br />',
		esc_html((string) $post->ID),
		esc_html($post->post_title)
	);
	wp_delete_post($post->ID, true);
}
