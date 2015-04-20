<?php
/*
Plugin Name: Better Images
Plugin URI: https://github.com/ethercreative/BetterImages/
Description: Easy manipulation of images through code
Author: Ether Creative
Version: 0.1.2
Author URI: http://ethercreative.co.uk/
*/

define( 'BI_VERSION', '0.1.2' );

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