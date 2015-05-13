<?php
/*
Plugin Name: BetterImages
Plugin URI: https://github.com/ethercreative/BetterImages/
Description: Easy manipulation of images through code
Author: Ether Creative
Version: 1.0.0
Author URI: http://ethercreative.co.uk/
*/

define( 'BI_VERSION', '1.0.0' );

require_once( plugin_dir_path( __FILE__ ) . 'class.bi.php' );

/**
 * Pass image through BetterImages
 *
 * @param mixed $image The image URL, ID, or Array
 * @return bi
 */
function bi($image)
{

	return new bi($image);

}

// ADMIN

function register_bi_tools_page()
{
	add_submenu_page( 'tools.php', 'BetterImages', 'BetterImages', 'manage_options', 'betterimages', 'bi_tools_page' );
}

add_action('admin_menu', 'register_bi_tools_page');

function bi_tools_page()
{ ?>
	<style>
		.bi_res {
			margin-left: 5px;
			color: #666;
		}
		.bi_spinner {
			position: relative;
			display: inline-block;
			width: 15px;
			height: 15px;
			margin-left: 5px;
			margin-bottom: -6px;

			border: 3px solid #0074a2;
			border-radius: 50px;

			animation: biSpinner 1s infinite linear;
			-webkit-animation: biSpinner 1s infinite linear;
		}
		.bi_spinner:before {
			content: '';
			position: absolute;
			top: -4px;
			left: -2px;

			border: 4px solid #0074a2;
			border-radius: 50px;
		}
		@keyframes biSpinner {
			from {
				-webkit-transform: rotate(0deg);
				-moz-transform: rotate(0deg);
				-ms-transform: rotate(0deg);
				-o-transform: rotate(0deg);
				transform: rotate(0deg);
			}
			to {
				-webkit-transform: rotate(360deg);
				-moz-transform: rotate(360deg);
				-ms-transform: rotate(360deg);
				-o-transform: rotate(360deg);
				transform: rotate(360deg);
			}
		}
		@-webkit-keyframes biSpinner {
			from {
				-webkit-transform: rotate(0deg);
				-moz-transform: rotate(0deg);
				-ms-transform: rotate(0deg);
				-o-transform: rotate(0deg);
				transform: rotate(0deg);
			}
			to {
				-webkit-transform: rotate(360deg);
				-moz-transform: rotate(360deg);
				-ms-transform: rotate(360deg);
				-o-transform: rotate(360deg);
				transform: rotate(360deg);
			}
		}
	</style>

	<div class="wrap">
		<h2>BetterImages <small><small><small><small><?php echo BI_VERSION ?></small></small></small></small></h2>
		<p>Easy manipulation of images through code</p>

		<hr/>

		<h3>Clear Cache</h3>
		<p>Clear all cached images. This will <strong><em>permanently delete</em></strong> all images made by BetterImages, forcing them to be re-created the next time their page is loaded.</p>
		<p><button class="button button-primary" id="bi_clear_cache">Clear Cached Images</button> <span class="bi_spinner" style="display: none;"></span></p>
		<div id="bi_console"></div>
	</div>

	<script>
		(function ($) {

			var b = $('#bi_clear_cache'),
				isDisabled = b.attr('disabled'),
				spinner = $('.bi_spinner'),
				c = $('#bi_console'),
				url = '<?php echo plugins_url('clear_bi_cache.php', __FILE__); ?>';

			b.click(function () {
				if (typeof isDisabled === typeof undefined || isDisabled === false) {
					spinner.show();
					b.attr('disabled', true);

					c.html('');

					c.append('<p>Scanning directories...</p>');

					$.post(url, { scan: true, base: '<?php $upload_dir = wp_upload_dir(); echo $upload_dir['basedir']; ?>' })
						.done(function (data) {
							if (data === '[]') {
								spinner.hide();
								c.append('<p>No BetterImages directories found. The cache is already clear.</p>');
								b.attr('disabled', false);
							} else {
								deleteDirs(JSON.parse(data));
							}
						});
				}
			});

			function deleteDirs(dirs) {
				$.each(dirs, function (id, dir) {

					$.post(url, { del: true, dir: dir, base: '<?php echo ABSPATH ?>' })
						.done(function (data) {
							c.append('<p>'+data+'</p>');
						});

				});

				spinner.hide();
				b.attr('disabled', false);
			}

		})(jQuery);
	</script>
<?php }


// Updating ---- https://github.com/jeremyclark13/automatic-theme-plugin-update
$api_url = 'http://webtestbed.co.uk/updater/';
$plugin_slug = basename(dirname(__FILE__));

/*
// TEMP: Enable update check on every request. Normally you don't need this! This is for testing only!
// NOTE: The
//	if (empty($checked_data->checked))
//		return $checked_data;
// lines will need to be commented in the check_for_plugin_update function as well.
set_site_transient('update_plugins', null);
// TEMP: Show which variables are being requested when query plugin API
add_filter('plugins_api_result', 'aaa_result', 10, 3);
function aaa_result($res, $action, $args) {
	print_r($res);
	return $res;
}
// NOTE: All variables and functions will need to be prefixed properly to allow multiple plugins to be updated
*/

// Take over the update check
add_filter('pre_set_site_transient_update_plugins', 'bi_check_for_plugin_update');
function bi_check_for_plugin_update($checked_data) {
	global $api_url, $plugin_slug, $wp_version;

	//Comment out these two lines during testing.
	if (empty($checked_data->checked))
		return $checked_data;

	$args = array(
		'slug' => $plugin_slug,
		'version' => $checked_data->checked[$plugin_slug .'/'. strtolower($plugin_slug) .'.php'],
	);
	$request_string = array(
		'body' => array(
			'action' => 'basic_check',
			'request' => serialize($args),
			'api-key' => md5(get_bloginfo('url'))
		),
		'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
	);

	// Start checking for an update
	$raw_response = wp_remote_post($api_url, $request_string);

	if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
		$response = unserialize($raw_response['body']);

	if (is_object($response) && !empty($response)) // Feed the update data into WP updater
		$checked_data->response[$plugin_slug .'/'. strtolower($plugin_slug) .'.php'] = $response;

	return $checked_data;
}
// Take over the Plugin info screen
add_filter('plugins_api', 'bi_plugin_api_call', 10, 3);
function bi_plugin_api_call($def, $action, $args) {
	global $plugin_slug, $api_url, $wp_version;

	if (!isset($args->slug) || ($args->slug != $plugin_slug))
		return false;

	// Get the current version
	$plugin_info = get_site_transient('update_plugins');
	$current_version = $plugin_info->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
	$args->version = $current_version;

	$request_string = array(
		'body' => array(
			'action' => $action,
			'request' => serialize($args),
			'api-key' => md5(get_bloginfo('url'))
		),
		'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
	);

	$request = wp_remote_post($api_url, $request_string);

	if (is_wp_error($request)) {
		$res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
	} else {
		$res = unserialize($request['body']);

		if ($res === false)
			$res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
	}

	return $res;
}