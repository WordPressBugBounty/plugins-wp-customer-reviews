<?php
/*
 * Plugin Name: WP Customer Reviews
 * Plugin URI: https://wordpress.org/plugins/wp-customer-reviews/
 * Description: Allows your visitors to leave business / product reviews. Testimonials are in Microdata / Microformat and may display star ratings in search results.
 * Version: 3.8.1
 * Requires PHP: 7.4
 * Author: Aaron Queen
 * Author URI: https://wordpress.org/plugins/wp-customer-reviews/
 * Text Domain: wp-customer-reviews
 * License: MIT
 *
 * Copyright (c) 2026 Aaron Queen
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */


if (!defined('ABSPATH')) {
  exit;
}

class WPCustomerReviews3
{
  var $prefix = "wpcr3";
  var $dashname = "wp-customer-reviews-3";
  var $url = "https://wordpress.org/plugins/wp-customer-reviews/";
  var $support_link = "https://wordpress.org/support/plugin/wp-customer-reviews/";
  var $prolink = "https://wordpress.org/plugins/wp-customer-reviews/";
  var $plugin_info = false;
  var $plugin_version = "0.0.0";
  var $adminClass = false;
  var $proClass = false;
  var $pro = false;
  var $force_active_page = false;
  var $options = [];
  var $options_name = "wpcr3_options";
  var $options_url_slug = "wpcr3_options";
  var $p = "";
  var $all_templates = [
    "frontend_review_holder" => "html",
    "frontend_review_form" => "html",
    "frontend_review_form_text_field" => "html",
    "frontend_review_form_rating_field" => "html",
    "frontend_review_form_review_field" => "html",
    "frontend_review_item" => "html",
    "frontend_review_item_aggregate" => "html",
    "frontend_review_item_reviews" => "html",
    "frontend_review_pagination" => "html",
    "frontend_review_rating_stars" => "html",
  ];
  var $allowedFieldTags = [
    "i" => true,
    "em" => true,
    "b" => true,
    "strong" => true,
  ];
  var $allowedContentTags = [
    "br" => true,
    "p" => true,
    "hr" => true,
    "i" => true,
    "em" => true,
    "b" => true,
    "strong" => true,
    "a" => [
      "href" => [],
      "title" => [],
      "target" => [],
      "rel" => [],
      "style" => [],
      "class" => [],
    ],
    "img" => [
      "src" => [],
      "alt" => [],
      "width" => [],
      "height" => [],
      "style" => [],
      "class" => [],
    ],
  ];

  function __construct() {}

  function start()
  {
    register_activation_hook(__FILE__, [&$this, "activate"]);
    register_deactivation_hook(__FILE__, [&$this, "deactivate"]);

    // we use priority 11 to allow v2 and v3 to coexist
    add_action("init", [&$this, "init"], 11);
  }

  function plugin_get_info()
  {
    include_once ABSPATH . "wp-admin/includes/plugin.php";
    return get_plugin_data(__FILE__);
  }

  function include_goatee()
  {
    if (!defined("WPCR3_HAS_GOATEE")) {
      define("WPCR3_HAS_GOATEE", 1);
      include_once $this->getplugindir() . "include/goatee-php/wpcr-goatee.php"; // include Goatee templating functions
    }
  }

  function include_pro()
  {
    if (!defined("WPCR3_HAS_PRO")) {
      $pro_file =
        $this->getplugindir() .
        "../wp-customer-reviews-pro-activation/wp-customer-reviews-3-pro-inc.php";
      $pro_exists = file_exists($pro_file);
      if ($pro_exists === false) {
        return;
      }

      include_once ABSPATH . "wp-admin/includes/plugin.php";
      if (
        is_plugin_active(
          "wp-customer-reviews-pro-activation/wp-customer-reviews-3-pro.php",
        ) === false
      ) {
        return;
      }

      define("WPCR3_HAS_PRO", 1);
      include_once $pro_file; // include pro functions
      $this->proClass = new WPCustomerReviews3Pro();
      $this->proClass->start_pro($this);
    }
  }

  // forward to pro class
  function pro_function()
  {
    if (!$this->pro) {
      die("Pro Function called without pro loaded.");
    }
    $args = func_get_args();
    $function = array_shift($args);
    return call_user_func_array([$this->proClass, $function], $args);
  }

  function include_admin()
  {
    if (!defined("WPCR3_HAS_ADMIN")) {
      define("WPCR3_HAS_ADMIN", 1);
      include_once $this->getplugindir() .
        "include/admin/wp-customer-reviews-3-admin.php"; // include admin functions
      $this->adminClass = new WPCustomerReviewsAdmin3();
      $this->adminClass->start_admin($this);
    }
  }

  // forward to admin class
  function admin_function()
  {
    $this->include_admin();
    $args = func_get_args();
    $function = array_shift($args);
    return call_user_func_array([$this->adminClass, $function], $args);
  }

  function admin_menu()
  {
    $this->admin_function("real_admin_menu");
  }

  function admin_init()
  {
    $this->admin_function("real_admin_init");
  }

  // if $this->p->$key does not exist, set it to empty string
  function param($keys, &$object = "")
  {
    if (!is_array($keys)) {
      $keys = [$keys];
    }
    if ($object === "") {
      $object = $this->p;
    }
    foreach ($keys as $key) {
      if (is_object($object)) {
        if (!isset($object->$key)) {
          $object->$key = "";
        }
      } elseif (is_array($object)) {
        if (!array_key_exists($key, $object)) {
          $object[$key] = "";
        }
      }
    }
  }

  function get_options()
  {
    $this->options = get_option($this->options_name);
  }

  function sanitize_p_obj_array($valArr)
  {
    foreach ($valArr as $k => $v) {
      if (is_array($v)) {
        $valArr[$k] = $this->sanitize_p_obj_array($v);
        continue;
      }

      $valArr[$k] = trim((string) $v);
    }

    return $valArr;
  }

  function sanitize_p_obj()
  {
    foreach ($this->p as $c => $val) {
      if (is_array($val)) {
        $this->p->$c = $this->sanitize_p_obj_array($val);
        continue;
      }

      $this->p->$c = trim((string) $val);
    }
  }

  function make_p_obj()
  {
    $this->p = new stdClass();

    if (is_admin()) {
      // $_GET is used mainly by filters for admin pages, but no intended use case for this in frontend
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin GET for list filters/settings UI.
      foreach ($_GET as $c => $val) {
        $this->p->$c = wp_unslash($val);
      }
    }

    // Admin settings/tools and admin-ajax (review submit, pager) only.
    if (is_admin() || wp_doing_ajax()) {
      // phpcs:ignore WordPress.Security.NonceVerification.Missing -- POST ingested only in admin/AJAX; AJAX verified via check_ajax_referer.
      foreach ($_POST as $c => $val) {
        $this->p->$c = wp_unslash($val);
      }
    }

    $this->sanitize_p_obj();
  }

  function is_active_page()
  {
    global $post;

    // if using WPCR_INSERT, we always force the page active so reviews will output
    if ($this->force_active_page === "shortcode_insert") {
      return $this->force_active_page;
    }

    // not on a single post/page, do not output
    if (!is_singular()) {
      return 0;
    }

    $enabled_post = get_post_meta($post->ID, "wpcr3_enable", true);
    if ($enabled_post == "1") {
      return "enabled";
    }

    return 0;
  }

  // run after each shortcode has finished
  function reset_active_page()
  {
    $this->force_active_page = false;
  }

  function rand_string($length)
  {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $str = "";

    $size = strlen($chars);
    for ($i = 0; $i < $length; $i++) {
      $str .= $chars[wp_rand(0, $size - 1)];
    }

    return $str;
  }

  function get_aggregate_reviews($postid)
  {
    global $wpdb;

    /*
		Removed below INNER JOIN for 3.2.2 to allow in-text shortcode (and do_shortcode) to display correct review count even when WPCR-enabled checkbox is not checked on the page. (loc 1/2)
		... FROM {$wpdb->prefix}posts p1 ...
		>> INNER JOIN {$wpdb->prefix}postmeta pm1 ON pm1.meta_key = 'wpcr3_enable' AND pm1.meta_value = '1' AND pm1.post_id = p1.id
		... pm2 ON pm2.meta_key ...
		*/

    $query = $wpdb->prepare(
      "
			SELECT
			COUNT(*) AS aggregate_count, AVG(tmp2.rating) AS aggregate_rating
			FROM (
				SELECT pm4.meta_value AS rating
				FROM (
					SELECT DISTINCT pm2.post_id
					FROM {$wpdb->prefix}posts p1
					INNER JOIN {$wpdb->prefix}postmeta pm2 ON pm2.meta_key = 'wpcr3_review_post' AND pm2.meta_value = p1.id
					WHERE p1.id = %d
				) tmp1
				INNER JOIN {$wpdb->prefix}posts p2 ON p2.id = tmp1.post_id AND p2.post_status = 'publish' AND p2.post_type = 'wpcr3_review'
				INNER JOIN {$wpdb->prefix}postmeta pm4 ON pm4.post_id = p2.id AND pm4.meta_key = 'wpcr3_review_rating' AND pm4.meta_value IS NOT NULL AND pm4.meta_value != '0'
				GROUP BY p2.id
			) tmp2
		",
      intval($postid),
    );

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Aggregate rating query; prepared SQL, no core API equivalent.
    $results = $wpdb->get_results($query);

    $rtn = new stdClass();

    if (count($results)) {
      $rtn->aggregate_count = intval($results[0]->aggregate_count);
      $rtn->aggregate_rating =
        $rtn->aggregate_count === 0
          ? 0
          : number_format(floatval($results[0]->aggregate_rating), 2);
      $rtn->aggregate_count_valid = $rtn->aggregate_count > 0;
      $rtn->stars = $this->get_rating_template($rtn->aggregate_rating, false);
    }

    return $rtn;
  }

  // filters show reviews only display for posts that have wpcr enabled on them AND are published
  function reviews_attached_to_enabled_published_posts($where)
  {
    global $wpdb;

    /*
		Removed below INNER JOIN for 3.2.2 to allow in-text shortcode (and do_shortcode) to display correct review count even when WPCR-enabled checkbox is not checked on the page. (loc 2/2)
		... pm2 ON pm2.meta_key ...
		> INNER JOIN {$wpdb->prefix}postmeta pm3 ON pm3.meta_key = 'wpcr3_enable' AND pm3.meta_value = '1'
		... p3 ON p3.id = pm2.meta_value ...
		*/

    $where .= "
			AND {$wpdb->prefix}posts.id IN (
				SELECT DISTINCT p2.id FROM {$wpdb->prefix}posts p2
				INNER JOIN {$wpdb->prefix}postmeta pm2 ON pm2.meta_key = 'wpcr3_review_post' AND pm2.post_id = p2.id
				INNER JOIN {$wpdb->prefix}posts p3 ON p3.id = pm2.meta_value AND p3.post_status = 'publish'
				WHERE p2.post_type = 'wpcr3_review'
			)
		";
    return $where;
  }

  function get_reviews($postid, $thispage, $opts)
  {
    $queryOpts = [
      "orderby" => "date",
      "order" => "DESC",
      "showposts" => min($opts->perpage, $opts->num),
      "post_type" => $this->prefix . "_review",
      "post_status" => "publish",
      "paged" => $thispage,
    ];

    if ($postid != -1) {
      // if $postid is not -1 (all reviews from all posts), need to filter by meta value for post id
      $meta_query = ["relation" => "AND"];
      $meta_query[] = [
        "key" => "{$this->prefix}_review_post",
        "value" => $postid,
        "compare" => "=",
      ];
      $queryOpts["meta_query"] = $meta_query;
    }

    add_filter("posts_where", [
      &$this,
      "reviews_attached_to_enabled_published_posts",
    ]);
    $reviews = new WP_Query($queryOpts);
    remove_filter("posts_where", [
      &$this,
      "reviews_attached_to_enabled_published_posts",
    ]);

    $rtn = new stdClass();
    $rtn->reviews = [];
    $rtn->found_posts = $reviews->found_posts;

    foreach ($reviews->posts as $post) {
      $review = $this->get_post_custom_single($post->ID);

      $review["id"] = $post->ID;

      $params = [
        $this->prefix . "_review_name",
        $this->prefix . "_review_title",
        $this->prefix . "_review_website",
        $this->prefix . "_review_admin_response",
      ];
      $this->param($params, $review);

      if (
        $this->options["standard_fields"]["fname"]["show"] == 0 ||
        $review[$this->prefix . "_review_name"] == ""
      ) {
        $review[$this->prefix . "_review_name"] = "Anonymous";
      }

      if ($this->options["standard_fields"]["ftitle"]["show"] == 0) {
        $review[$this->prefix . "_review_title"] = "";
      }

      if ($this->options["standard_fields"]["fwebsite"]["show"] == 0) {
        $review[$this->prefix . "_review_website"] = "";
      }

      $review[$this->prefix . "_custom_fields"] = [];

      if ($opts->hidecustom == 0) {
        foreach ($this->options["custom_fields"] as $name => $fieldArr) {
          $params = [$this->prefix . "_" . $name];
          $this->param($params, $review);
          $value = trim($review[$this->prefix . "_" . $name]);
          if ($fieldArr["show"] == 1 && strlen($value)) {
            $review[$this->prefix . "_custom_fields"][] = [
              "label" => $fieldArr["label"],
              "value" => $value,
            ];
          }
        }
      }

      $review[$this->prefix . "_review_admin_response"] = nl2br(
        $review[$this->prefix . "_review_admin_response"],
      );

      if ($opts->hideresponse == 1) {
        unset($review[$this->prefix . "_review_admin_response"]);
      }

      // BEG: xss protect
      $review_name_key = $this->prefix . "_review_name";
      $review_title_key = $this->prefix . "_review_title";
      $review_website_key = $this->prefix . "_review_website";
      $review_rating_key = $this->prefix . "_review_rating";
      $review_admin_response_key = $this->prefix . "_review_admin_response";

      if (isset($review[$review_website_key]) && $review[$review_website_key] !== "") {
        $review[$review_website_key] = esc_url($review[$review_website_key]);
      }

      if (isset($review[$review_name_key])) {
        $review[$review_name_key] = esc_html($review[$review_name_key]);
      }

      if (isset($review[$review_title_key])) {
        $review[$review_title_key] = esc_html($review[$review_title_key]);
      }

      if (isset($review[$review_rating_key])) {
        $review[$review_rating_key] = esc_attr($review[$review_rating_key]);
      }

      if (isset($review[$review_admin_response_key])) {
        $review[$review_admin_response_key] = wp_kses(
          $review[$review_admin_response_key],
          $this->allowedContentTags,
        );
      }

      $review["content"] = wp_kses(
        $review["content"],
        $this->allowedContentTags,
      );

      foreach ($review[$this->prefix . "_custom_fields"] as $k => $r) {
        $review[$this->prefix . "_custom_fields"][$k]["label"] = wp_kses(
          $r["label"],
          $this->allowedFieldTags,
        );
        $review[$this->prefix . "_custom_fields"][$k]["value"] = esc_html(
          $r["value"],
        );
      }
      // END: xss protect

      $review["stars"] = $this->get_rating_template(
        $review[$this->prefix . "_review_rating"],
        false,
      );

      $rtn->reviews[] = $review;
    }

    return $rtn;
  }

  function iso8601($time = false)
  {
    if ($time === false) {
      $time = time();
    }
    $date = gmdate("Y-m-d\TH:i:sO", $time);
    return substr($date, 0, strlen($date) - 2) . ":" . substr($date, -2);
  }

  function pagination($opts, $thispage, $total_results)
  {
    $rtn = false;
    $thispage = intval($thispage);
    if ($thispage == 0) {
      $thispage = 1;
    }
    $pages = intval(ceil($total_results / $opts->perpage));

    if ($pages > 1) {
      $rtn = [];
      $rtn["pageOpts"] = htmlspecialchars(json_encode($opts), ENT_QUOTES);
      $rtn["onPage"] = $thispage;
      $rtn["numPages"] = $pages;
      $rtn["pages"] = [];
      $rtn["prevPage"] = max(1, $thispage - 1);
      $rtn["nextPage"] = min($pages, $thispage + 1);

      $range = 2;
      $showitems = $range * 2 + 1;

      $rtn["hasPrev"] = false;
      if ($thispage !== 1) {
        $rtn["hasPrev"] = true;
      }

      for ($i = 1; $i <= $pages; $i++) {
        if ($i === $thispage) {
          $tmp = [];
          $tmp["pageNum"] = $i;
          $tmp["current"] = true;
          $rtn["pages"][] = $tmp;
        } elseif (
          !($i >= $thispage + $range + 1 || $i <= $thispage - $range - 1) ||
          $pages <= $showitems
        ) {
          $tmp = [];
          $tmp["pageNum"] = $i;
          $tmp["current"] = false;
          $rtn["pages"][] = $tmp;
        }
      }

      $rtn["hasNext"] = false;
      if ($thispage !== $pages) {
        $rtn["hasNext"] = true;
      }
    }

    return $rtn;
  }

  function get_meta_or_default($metaArr, $key, $default)
  {
    return isset($metaArr[$key]) ? $metaArr[$key] : $default;
  }

  function get_post_custom_single($postid)
  {
    $post = get_post($postid);
    if (!$post instanceof WP_Post) {
      return [];
    }

    $meta = get_post_custom($postid);

    $out = [];
    foreach ($meta as $key => $valArr) {
      if ($key === "") {
        continue;
      }

      $out[$key] = $valArr[0];
    }

    if ($post->post_type !== $this->prefix . "_review") {
      $format = $this->get_meta_or_default(
        $out,
        $this->prefix . "_format",
        "Blank Format",
      );
      if ($format === "business") {
        $out["is_business"] = true;
      } elseif ($format === "product") {
        $out["is_product"] = true;
      }
    }

    $out["content"] = nl2br($post->post_content);

    $post_date = explode(" ", $post->post_date);
    $out["post_date"] = $post_date[0];
    $out["post_date"] = wp_date("M j, Y", strtotime($out["post_date"]));

    return $out;
  }

  function inject_parent_info($reviews, $postid, $parentData, $opts)
  {
    $format = $this->get_meta_or_default(
      $parentData,
      $this->prefix . "_format",
      "Blank Format",
    );
    $business_name = $parentData[$this->prefix . "_business_name"];
    $product_name = $parentData[$this->prefix . "_product_name"];
    $postLink = esc_url(get_permalink($postid));

    foreach ($reviews as &$review) {
      if ($format === "business") {
        $review["is_business"] = true;
        $review["item_name"] = $business_name;
      } elseif ($format === "product") {
        $review["is_product"] = true;
        $review["item_name"] = $product_name;
      }
      $review["postLink"] = $postLink;
      $review["on_same_page"] = $opts->on_postid == $postid;

      if ($opts->snippet > 0) {
        $review["content"] = $this->trim_text_to_word($review, $opts);
      }
    }

    return $reviews;
  }

  function default_parentData($parentData)
  {
    // default some fields for those too lazy to use the plugin properly
    $blog_name = get_bloginfo("name");
    $blog_url = get_bloginfo("url");

    $business_name = $this->get_meta_or_default(
      $parentData,
      $this->prefix . "_business_name",
      $blog_name,
    );
    $product_name = $this->get_meta_or_default(
      $parentData,
      $this->prefix . "_product_name",
      $blog_name,
    );
    $parentData[$this->prefix . "_business_name_meta"] = esc_attr($business_name);
    $parentData[$this->prefix . "_product_name_meta"] = esc_attr($product_name);
    $parentData[$this->prefix . "_business_name"] = wp_kses(
      $business_name,
      $this->allowedFieldTags,
    );
    $parentData[$this->prefix . "_product_name"] = wp_kses(
      $product_name,
      $this->allowedFieldTags,
    );
    $business_url = $this->get_meta_or_default(
      $parentData,
      $this->prefix . "_business_url",
      $blog_url,
    );
    $parentData[$this->prefix . "_business_url"] = esc_url($business_url);

    // todo: replace with provided image in future
    $parentData[$this->prefix . "_business_image"] =
      $this->getpluginurl_abs() . "css/1x1.png";
    $parentData[$this->prefix . "_product_image"] =
      $this->getpluginurl_abs() . "css/1x1.png";

    return $parentData;
  }

  function output_reviews_show($opts)
  {
    global $post;

    // required: showform, num, postid, classes, showsupport
    // optional: hidecustom, hideresponse, snippet, morelink, thispage, ajax, hidereviews, on_postid (internal), wrapper (internal)

    $params = ["morelink", "classes", "on_postid", "wrapper"];
    $this->param($params, $opts);

    $intArr = [
      "postid",
      "num",
      "paginate",
      "perpage",
      "hidecustom",
      "snippet",
      "showform",
      "hidereviews",
      "hideresponse",
      "ajax",
      "thispage",
      "on_postid",
      "wrapper",
    ];
    foreach ($intArr as $key) {
      $opts->$key = isset($opts->$key)
        ? $this->strip_trim_intval($opts->$key)
        : 0;
    }

    if ($opts->num < 1) {
      $opts->num = 9999;
    }
    if ($opts->thispage < 1) {
      $opts->thispage = 1;
    }
    if ($opts->perpage < 1) {
      $opts->perpage = intval($this->options["reviews_per_page"]);
    }
    if ($opts->perpage < 1) {
      $opts->perpage = 10;
    }

    $postid = $opts->postid;
    $thispage = $opts->thispage;

    if ($opts->num > 0 && $opts->num < $opts->perpage) {
      $opts->perpage = $opts->num;
    }

    if ($opts->ajax == 1) {
      $opts->showform = 0;
    }

    $this->include_goatee();

    $tmp = $this->get_reviews($postid, $thispage, $opts);
    $found_posts = $tmp->found_posts;
    $reviews = $tmp->reviews;
    $page_count = count($reviews);

    $ajaxurl_arr = json_encode(
      explode(".", str_replace("/", "|", $this->getAjaxURL())),
    );

    $on_postid = $opts->on_postid;
    if (isset($post)) {
      $opts->on_postid = $post->ID;
      $on_postid = $opts->on_postid;
    }

    $main_data = [
      "classes" => $opts->classes,
      "review_form" => "",
      "reviews" => "",
      "power" => "",
      "hidereviews" => $opts->hidereviews == 1,
      "ajaxurl" => $ajaxurl_arr,
      "postid" => $postid,
      "on_postid" => $on_postid,
    ];

    $got_post_meta = [];

    $pagination = "";
    if ($opts->hidereviews == 0) {
      if ($found_posts > $opts->perpage && $opts->paginate === 1) {
        $pagination = $this->pagination($opts, $thispage, $found_posts);
        $pagination = wpcr_Goatee::fill(
          $this->options["templates"]["frontend_review_pagination"],
          $pagination,
        );
      }
    }

    if ($opts->showsupport == 1 && $this->options["support_us"] == 1) {
      $main_data["power"] =
        'Powered by <strong><a target="_blank" rel="noopener" href="' .
        $this->url .
        '">' .
        $this->plugin_info["Name"] .
        "</a></strong>";
    }

    $main_data["pagination"] = $pagination;

    if ($postid > 0) {
      // if viewing reviews of a single post id
      $got_post_meta[$postid] = $this->get_post_custom_single($postid);
      $data = &$got_post_meta[$postid];
      $data = $this->default_parentData($data);

      $params = [$this->prefix . "_hideform"];
      $this->param($params, $data);

      $showform =
        $opts->showform == 1 && $data[$this->prefix . "_hideform"] != 1;
      $main_data["review_form"] = $this->show_reviews_form(
        $postid,
        $found_posts,
        $showform,
      );

      $reviews = $this->inject_parent_info($reviews, $postid, $data, $opts);

      $data["postLink"] = esc_url(get_permalink($postid));
      $data["on_same_page"] = $opts->on_postid == $postid;

      $data["reviews"] = [
        "template" =>
          $this->options["templates"]["frontend_review_item_reviews"],
        "data" => [
          "reviews" => $reviews,
        ],
      ];

      $data["aggregate"] = [
        "template" =>
          $this->options["templates"]["frontend_review_item_aggregate"],
        "data" => [
          "aggregate" => $this->get_aggregate_reviews($postid),
        ],
      ];

      $main_data["reviews"] .= wpcr_Goatee::fill(
        $this->options["templates"]["frontend_review_item"],
        $data,
      );
    } else {
      // we are in a shortcode/widget that is showing reviews for multiple posts
      // so we need to fill $got_post_meta with the post meta related to each $review
      foreach ($reviews as $review) {
        $review_of_postid = intval($review[$this->prefix . "_review_post"]);

        if (!array_key_exists($review_of_postid, $got_post_meta)) {
          $got_post_meta[$review_of_postid] = $this->get_post_custom_single(
            $review_of_postid,
          );
        }

        $data = &$got_post_meta[$review_of_postid];
        $data = $this->default_parentData($data);

        $tmpReviews = [$review];
        $tmpReview = $this->inject_parent_info(
          $tmpReviews,
          $review_of_postid,
          $data,
          $opts,
        );
        $review = $tmpReview[0];

        // 1. no aggregate shown for multiple businesses/products as it would confuse crawlers which one to show in SERPs
        // 2. no form shown for showing "all" reviews because we don't know what post to bind the form to

        if ($opts->hidereviews == 0) {
          $data["reviews"] = [
            "template" =>
              $this->options["templates"]["frontend_review_item_reviews"],
            "data" => [
              "reviews" => [$review],
            ],
          ];
        }

        $main_data["reviews"] .= wpcr_Goatee::fill(
          $this->options["templates"]["frontend_review_item"],
          $data,
        );
      }
    }

    // Useful info: http://wordpress.stackexchange.com/a/39928 ( faster way to get meta values for many posts )
    // Useful info: http://stackoverflow.com/a/18422969 ( outputting multiple ratings for one item )

    $reviews_content = wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_holder"],
      $main_data,
    );
    $reviews_content = preg_replace(
      '/\n\r|\r\n|\n|\r|\t/',
      "",
      $reviews_content,
    ); // minify to prevent automatic line breaks, not removing double spaces

    if ($opts->wrapper === 1) {
      $data_attr = $this->get_data_attr_wrapper($postid);
      $reviews_content = "<div {$data_attr}>" . $reviews_content . "</div>";
    }

    return $reviews_content;
  }

  // stripts html then trims text, but does not break up a word
  function trim_text_to_word($review, $opts)
  {
    $text = $review["content"];
    $text = str_replace("<br", " <br", $text);
    $text = trim(wp_strip_all_tags($text));
    $len = $opts->snippet;

    if (strlen($text) > $len) {
      preg_match("/^.{0," . $len . "}(?:.*?)\b/siu", $text, $matches);
      $text = $matches[0] . "... ";
      if (strlen(trim($opts->morelink)) > 0) {
        $postLink = $review["postLink"] . "#wpcr3_id_" . $review["id"];
        $text .= "<a href='{$postLink}'>$opts->morelink</a>";
      }
    }

    return $text;
  }

  function get_data_attr_wrapper($postid)
  {
    return "data-wpcr3-content=\"{$postid}\"";
  }

  function do_the_content($original_content)
  {
    global $post;

    if (!isset($post) || !isset($post->ID) || intval($post->ID) == 0) {
      // we need a post object to do anything useful

      return $original_content;
    }

    $postid = $post->ID;
    $data_attr = $this->get_data_attr_wrapper($postid);
    $already_ran = strpos($original_content, $data_attr) !== false ? 1 : 0;

    // return original content if reviews should not display for this post
    $is_active_page = $this->is_active_page();

    if ($already_ran === 1 || $is_active_page === 0) {
      return $original_content;
    }

    $this->reset_active_page();

    $hideform = get_post_meta($post->ID, $this->prefix . "_hideform", true);
    $showform = $hideform !== "1";

    $opts = new stdClass();
    $opts->showform = $showform;
    $opts->showsupport = $this->options["support_us"];
    $opts->postid = $postid;
    $opts->perpage = $this->options["reviews_per_page"];
    $opts->paginate = 1;
    $opts->classes = $this->prefix . "_in_content";
    $opts->wrapper = 1;

    $reviews_content = $this->output_reviews_show($opts);
    $original_content .= $reviews_content;
    return $original_content;
  }

  function get_rating_template($rating, $enable_hover)
  {
    $data = [
      "hoverable" => $enable_hover,
      "stars" => $rating,
      "rating_width" => 20 * $rating, // 20% for each star if having 5 stars
    ];
    return wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_rating_stars"],
      $data,
    );
  }

  function get_form_field($name, $fieldArr)
  {
    $required = $fieldArr["require"] == 1;

    $data = [
      "name" => $this->prefix . "_" . $name,
      "label" => wp_kses($fieldArr["label"], $this->allowedFieldTags),
      "required" => $required ? "*" : "",
      "class" => $required ? $this->prefix . "_required" : "",
      "value" => "",
    ];
    $field = wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_form_text_field"],
      $data,
    );
    return $field;
  }

  function get_rating_field()
  {
    $data = [
      "rating_stars" => $this->get_rating_template(0, true),
    ];
    $field = wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_form_rating_field"],
      $data,
    );
    return $field;
  }

  function get_review_field()
  {
    $data = [
      "value" => "",
    ];
    $field = wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_form_review_field"],
      $data,
    );
    return $field;
  }

  // currently, because of how JS "wpcr3" object works, we can only display one form on a single page. To fix, we would need to make "wpcr3" a "new wpcr3();"
  function show_reviews_form($postid, $found_posts, $showform)
  {
    $input_fields = "";

    foreach ($this->options["standard_fields"] as $name => $fieldArr) {
      if ($fieldArr["ask"] == 1) {
        $input_fields .= $this->get_form_field($name, $fieldArr);
      }
    }

    foreach ($this->options["custom_fields"] as $name => $fieldArr) {
      if ($fieldArr["ask"] == 1) {
        $input_fields .= $this->get_form_field($name, $fieldArr);
      }
    }

    $has_required_fields =
      strpos($input_fields, $this->prefix . "_required") !== false;
    $rating_field = $this->get_rating_field();
    $review_field = $this->get_review_field();

    $data = [
      "input_fields" => $input_fields,
      "rating_field" => $rating_field,
      "review_field" => $review_field,
      "has_required_fields" => $has_required_fields,
      "postid" => $postid,
      "found_posts" => $found_posts,
      "showform" => $showform,
    ];

    return wpcr_Goatee::fill(
      $this->options["templates"]["frontend_review_form"],
      $data,
    );
  }

  function generateTitle($fname)
  {
    $fname = strlen($fname) === 0 ? "Anonymous" : $fname;
    $datetime = wp_date("m/d/Y h:i");
    return "{$fname} @ {$datetime}";
  }

  function normalize_xss_probe_string(string $s): string
  {
    $s = str_replace("\0", '', $s);
    for ($i = 0; $i < 4; $i++) {
      $next = html_entity_decode(urldecode($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($next === $s) {
        break;
      }
      $s = $next;
    }

    $s = strtolower($s);
    $normalized = preg_replace('/[\s\x00-\x1F\x7F]/u', '', $s);

    return $normalized ?? '';
  }

  function isXssAttempt($s, string $field = ''): bool
  {
    $s = $this->normalize_xss_probe_string((string) $s);
    if ($s === '') {
      return false;
    }

    // Tag substrings and explicit on* handlers omitted: '<' and on*= regex cover them.
    $needles = [
      'javascript:',
      'vbscript:',
      'data:text/html',
      'data:application',
      '<',
      '>',
      'srcdoc',
      '%3c',
      '%3e',
      '\x3c',
      '\u003c',
    ];

    // JS execution needles on short identity fields only; ftext may contain prose like "function(ality)".
    if ($field !== 'ftext') {
      $needles = array_merge($needles, [
        'eval(',
        'atob(',
        'alert(',
        'settimeout(',
        'setinterval(',
        'function(',
      ]);
    }

    foreach ($needles as $needle) {
      if (strpos($s, $needle) !== false) {
        return true;
      }
    }

    if (preg_match('/\bon[a-z]{2,}\s*=/', $s) === 1) {
      return true;
    }

    return false;
  }

  function sanitize_pager_classes($classes)
  {
    $classes = trim(wp_strip_all_tags((string) $classes));
    if ($classes === "") {
      return $this->prefix . "_in_content";
    }

    return preg_replace('/[^a-zA-Z0-9_\- ]/', "", $classes);
  }

  function sanitize_pager_morelink($morelink)
  {
    $morelink = trim(wp_strip_all_tags((string) $morelink));
    if ($morelink === "") {
      return "";
    }

    $url = esc_url_raw($morelink);
    if ($url === "") {
      return "";
    }

    return substr($url, 0, 2048);
  }

  function ajax()
  {
    check_ajax_referer('wpcr3_ajax', '_wpnonce');

    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Content-type: application/json");

    $rtn = new stdClass();
    $rtn->err = [];
    $rtn->success = false;

    $posted = new stdClass();
    foreach ($this->p as $k => $v) {
      $k = str_replace($this->prefix . "_", "", $k);
      $posted->$k = $v;
    }

    $params = [
      "ajaxAct2",
      "postid",
      "checkid2",
      "fconfirm1",
      "fconfirm2",
      "fconfirm3",
      "url",
      "website",
      "femail",
      "fname",
      "frating",
      "ftext",
      "ftitle",
      "fwebsite",
      "pageOpts",
      "page",
      "on_postid",
    ];

    foreach ($this->options["custom_fields"] as $name => $fieldArr) {
      $params[] = $name;
    }

    $this->param($params, $posted);

    // isXssAttempt rejects encoded markup/handlers/JS before strip; wp_strip_all_tags is the storage sanitizer.
    // Spambot honeypots (fconfirm*, url, website, checkid2) are checked separately below.
    // Only expected params are checked; servers sometimes inject extra keys into $_POST.
    foreach ($params as $k) {
      $v = $posted->$k;

      if ($k !== "pageOpts" && $this->isXssAttempt($v, $k) === true) {
        $rtn->err[] = "You have failed the spambot check. Code 0";
        die(json_encode($rtn));
      }

      if ($k !== "pageOpts") {
        $posted->$k = trim(wp_strip_all_tags($v));
      }
    }

    $ajaxAct = $posted->ajaxAct2;
    $postid = (int) $posted->postid;
    $checkId = (int) $posted->checkid2;
    $checkIdExpect = $postid * 42 - $postid;

    if ($ajaxAct === "form") {
      if ($checkId != $checkIdExpect) {
        $rtn->err[] = "You have failed the spambot check. Code 1";
      }
      if ($posted->fconfirm1 != "0") {
        $rtn->err[] = "You have failed the spambot check. Code 2";
      }
      if ($posted->fconfirm2 != "1") {
        $rtn->err[] = "You have failed the spambot check. Code 3";
      }
      if ($posted->fconfirm3 != "1") {
        $rtn->err[] = "You have failed the spambot check. Code 4";
      }
      if ($posted->url != "") {
        $rtn->err[] = "You have failed the spambot check. Code 5";
      }
      if ($posted->website != "") {
        $rtn->err[] = "You have failed the spambot check. Code 6";
      }
      if (
        $posted->femail != "" &&
        filter_var($posted->femail, FILTER_VALIDATE_EMAIL) == false
      ) {
        $rtn->err[] = "Please enter a valid email address.";
      }

      if (count($rtn->err)) {
        die(json_encode($rtn));
      } // die here if we failed any spambot checks

      $rating = (int) $posted->frating;
      if ($rating < 1 || $rating > 5) {
        $rtn->err[] = "Please select a valid rating.";
        die(json_encode($rtn));
      }
      $posted->frating = (string) $rating;

      // passed all spambot checks, continue

      $title = $this->generateTitle($posted->fname);

      $newpost = [
        "post_author" => 1,
        "post_date" => current_time("mysql"),
        "post_content" => nl2br($posted->ftext),
        "post_status" => "pending",
        "post_title" => $title,
        "post_type" => $this->prefix . "_review",
      ];
      $newpostid = wp_insert_post($newpost, true);

      $review_ip = isset($_SERVER["REMOTE_ADDR"])
        ? sanitize_text_field(wp_unslash($_SERVER["REMOTE_ADDR"]))
        : "";
      update_post_meta(
        $newpostid,
        $this->prefix . "_review_ip",
        $review_ip,
      );

      if (isset($posted->postid)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_post",
          $posted->postid,
        );
      }
      if (isset($posted->fname)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_name",
          $posted->fname,
        );
      }
      if (isset($posted->femail)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_email",
          $posted->femail,
        );
      }
      if (isset($posted->frating)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_rating",
          $posted->frating,
        );
      }
      if (isset($posted->ftitle)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_title",
          $posted->ftitle,
        );
      }
      if (isset($posted->fwebsite)) {
        update_post_meta(
          $newpostid,
          $this->prefix . "_review_website",
          $posted->fwebsite,
        );
      }

      foreach ($this->options["custom_fields"] as $name => $fieldArr) {
        if ($fieldArr["ask"] == 1 && isset($posted->$name)) {
          update_post_meta(
            $newpostid,
            $this->prefix . "_" . $name,
            $posted->$name,
          );
        }
      }

      $datetime = wp_date("m/d/Y h:i");
      @wp_mail(
        get_bloginfo("admin_email"),
        "WP Customer Reviews: New Review Posted on {$datetime}",
        "A new review has been posted on " .
          get_bloginfo("name") .
          " via WP Customer Reviews. \n\nYou will need to approve this review before it will appear on your site.",
      );
    } elseif ($ajaxAct === "pager") {
      $opts = json_decode($posted->pageOpts, false);
      if (!is_object($opts)) {
        $rtn->err[] = "You have failed the spambot check. Code 8";
        die(json_encode($rtn));
      }

      if (isset($opts->classes)) {
        $opts->classes = $this->sanitize_pager_classes($opts->classes);
      }
      if (isset($opts->morelink)) {
        $opts->morelink = $this->sanitize_pager_morelink($opts->morelink);
      }

      $opts->thispage = $posted->page;
      $opts->ajax = 1;
      $opts->showsupport = 0;
      $opts->on_postid = $posted->on_postid;
      $rtn->output = $this->output_reviews_show($opts);
    } else {
      $rtn->err[] = "You have failed the spambot check. Code 7";
      die(json_encode($rtn));
    }

    $rtn->success = true;
    die(json_encode($rtn));
  }

  function getAjaxURL()
  {
    return admin_url("admin-ajax.php") . "?action=wpcr3-ajax";
  }

  // used in extended classes to grab already-set information we care about from main class
  function setSharedVars($parentClass)
  {
    $this->plugin_info = &$parentClass->plugin_info; // array by &reference
    $this->plugin_version = $this->plugin_info["Version"];
    $this->pro = $parentClass->pro;

    $this->options = &$parentClass->options; // array by &reference
    $this->p = $parentClass->p; // object is already by &reference
  }

  function init()
  {
    $this->include_pro();

    add_action("admin_menu", [&$this, "admin_menu"]); // adding menu items to admin must be done in admin_menu which gets executed BEFORE admin_init
    add_action("admin_init", [&$this, "admin_init"]);
    add_filter("the_content", [&$this, "do_the_content"], 15); // prio 15 makes sure this hits after wptexturize and other garbage that likes to destroy our output

    // "Wordpress SEO - Yoast" hijacks the_content and breaks all kinds of plugins
    // luckily they provide a filter to fix it
    // add_filter('wpseo_pre_analysis_post_content', array(&$this, 'do_the_content_wpseo'), 15);

    add_action("wp_ajax_" . $this->prefix . "-ajax", [&$this, "ajax"]);
    add_action("wp_ajax_nopriv_" . $this->prefix . "-ajax", [&$this, "ajax"]);

    $this->plugin_info = $this->plugin_get_info();
    $this->plugin_version = $this->plugin_info["Version"];

    $this->make_p_obj(); // make P variables object
    $this->get_options(); // populate the options array
    $this->create_post_type();

    // remove any existing shortcode to allow v2 and v3 to coexist
    remove_shortcode("WPCR_INSERT");
    remove_shortcode("WPCR_SHOW");

    add_shortcode("WPCR_INSERT", [&$this, "shortcode_insert"]);
    add_shortcode("WPCR_SHOW", [&$this, "shortcode_show"]);
    add_shortcode("WPCR_HCARD", [&$this, "shortcode_hcard"]); // deprecated, returns blank

    // we insert styles/scripts in init because some themes are horrible
    wp_register_style(
      "wp-customer-reviews-3-frontend",
      $this->getpluginurl() . "css/wp-customer-reviews.css",
      [],
      $this->plugin_version,
    );
    wp_register_script(
      "wp-customer-reviews-3-frontend",
      $this->getpluginurl() . "js/wp-customer-reviews.js",
      ["jquery"],
      $this->plugin_version,
    );
    wp_enqueue_style("wp-customer-reviews-3-frontend");
    wp_enqueue_script("wp-customer-reviews-3-frontend");
    wp_localize_script('wp-customer-reviews-3-frontend', 'wpcr3Ajax', [
      'nonce' => wp_create_nonce('wpcr3_ajax'),
    ]);
  }

  function create_post_type()
  {
    $defaults1 = [
      "labels" => [],
      "public" => false,
      "exclude_from_search" => true,
      "publicly_queryable" => false,
      "show_in_nav_menus" => false,
      "show_ui" => true,
      "show_in_menu" => $this->prefix . "_view_reviews",
      "menu_position" => 25,
      "show_in_admin_bar" => false,
      "has_archive" => false,
      "rewrite" => false,
      "supports" => ["title"],
      "map_meta_cap" => true,
    ];

    $defaults2 = $defaults1;
    $defaults2["labels"] = [
      "name" => "WP Customer Reviews",
      "singular_name" => "Review",
      "menu_name" => "All Reviews",
      "add_new_item" => "Add New Customer Review",
      "edit_item" => "Edit Customer Review",
      "new_item" => "New Customer Review",
      "view_item" => "View Customer Review",
      "search_items" => "Search Customer Reviews",
      "not_found" => "No Reviews Found",
      "not_found_in_trash" => "No Reviews Found in Trash",
    ];
    $defaults2["supports"] = ["title", "editor"];
    $err = register_post_type($this->prefix . "_review", $defaults2);
  }

  function shortcode_insert()
  {
    $this->force_active_page = "shortcode_insert";
    return $this->do_the_content("");
  }

  function strip_trim($val)
  {
    return trim(wp_strip_all_tags($val));
  }

  function strip_trim_intval($val)
  {
    return intval($this->strip_trim($val));
  }

  function shortcode_show($atts)
  {
    $attArr = shortcode_atts(
      [
        "postid" => "all",
        "num" => 5,
        "paginate" => 1,
        "perpage" => 5,
        "hidecustom" => 0,
        "snippet" => 0,
        "more" => "",
        "showform" => 1,
        "hidereviews" => 0,
        "hideresponse" => 0,
      ],
      $atts,
    );

    $opts = new stdClass();
    foreach ($attArr as $key => $val) {
      $opts->$key = $val;
    }

    $opts->postid = $this->strip_trim($opts->postid);

    if (strtolower($opts->postid) == "all") {
      $opts->postid = -1;
    } // -1 queries all reviews
    $opts->morelink = $opts->more;

    $intArr = [
      "postid",
      "num",
      "paginate",
      "perpage",
      "hidecustom",
      "snippet",
      "showform",
      "hidereviews",
      "hideresponse",
    ];
    foreach ($intArr as $key) {
      $opts->$key = isset($opts->$key)
        ? $this->strip_trim_intval($opts->$key)
        : 0;
    }

    if ($opts->postid === -1) {
      $opts->showform = 0;
    } // do not show form if postid is "all"
    $opts->showsupport = 0;
    if ($opts->showform == 1) {
      $opts->showsupport = 1;
    }

    $opts->wrapper = 1;

    return $this->output_reviews_show($opts);
  }

  // deprecated, returns blank
  function shortcode_hcard($atts)
  {
    return "";
  }

  function activate()
  {
    add_option($this->prefix . "_gotosettings", true); // used for redirecting to settings page upon initial activation
  }

  function deactivate()
  {
    // do not fire on upgrading plugin or upgrading WP - only on true manual deactivation
    if (isset($this->p->action) && $this->p->action == "deactivate") {
      $this->admin_function("notify_activate", 2);
    }
  }

  function template($name)
  {
    return $this->options["templates"][$name];
  }

  function getpluginurl()
  {
    return trailingslashit(plugins_url(basename(dirname(__FILE__))));
  }

  function getpluginurl_abs()
  {
    $url = $this->getpluginurl();
    if (strpos($url, "://") === false) {
      $url = home_url($url);
    }
    return $url;
  }

  function getplugindir()
  {
    return trailingslashit(
      WP_PLUGIN_DIR .
        "/" .
        str_replace(basename(__FILE__), "", plugin_basename(__FILE__)),
    );
  }
}

function wpcr3_start(): WPCustomerReviews3
{
  $wpcr3_plugin = new WPCustomerReviews3();
  $wpcr3_plugin->start();
  return $wpcr3_plugin;
}

$wpcr3_plugin = wpcr3_start();
