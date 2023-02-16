<?php

/*
Plugin Name: PDAcademy Hooks
Description: Hooks into Edwiser to adjust my_course listing page and provides shortcodes for Token access
Author: tim st.clair <tim.stclair@gmail.com>
Author URI: https://www.frumbert.org/
*/

// routines for debugging
add_filter('https_ssl_verify', '__return_false'); // NOT FOR PRODUCTION
function pdadump($value) {
	echo "<pre>", print_r($value, true), "</pre>";
}

/* register and add a settings page */
add_action( 'admin_menu', 'pda_add_settings_page' );
function pda_add_settings_page() {
	add_options_page( 
		__( 'Options', 'pda' ),
		__( 'PDAcademy', 'pda' ),
		'manage_options',
		dirname(__FILE__).'/pda_settings_page.php'
	);
}
add_action( 'admin_init', 'pda_register_settings' );
function pda_register_settings() {
	register_setting( 'pda-settings-group', 'pda_moodle' );
	register_setting( 'pda-settings-group', 'pda_token' );
}

/* manage plugin add/deactivate/removal */
register_activation_hook(__FILE__, 'pdamod_activate');
function pdamod_activate() {
	add_option('pda_moodle', 'https://set.path.to/moodle');
	add_option('pda_token', 'not-set');
}
register_deactivation_hook(__FILE__, 'pdamod_deactivate');
function pdamod_deactivate() {
	delete_option('pda_moodle');
	delete_option('pda_token');
}
register_uninstall_hook(__FILE__, 'pdamod_uninstall');
function pdamod_uninstall() {
	pdamod_deactivate();
}

/* BEFORE the my_courses list begins, see if we need to modify the data based on external enrolments */
add_action( 'eb_before_my_courses_wrapper', 'pda_before_my_courses_wrapper' );
function pda_before_my_courses_wrapper() {
	$userid = get_current_user_id();
	$moodleid = get_user_meta($userid, 'moodle_user_id', true);

	$moodle_url = get_option('pdamod_moodle', '');
	$moodle_token = get_option('pdamod_token', '');

	if (!empty($moodle_url) && !empty($moodle_token)) {

		$usertoken = get_user_meta($userid, 'moodle_usertoken', true);
		if (empty($usertoken)) {
			// connect to the moodle service to generate a token for this user
			// $usertoken = 'webservice-return-value';
			// add_user_meta($userid, 'moodle_usertoken', $usertoken);
		}

		pdadump([$userid,$moodleid, $usertoken]);
	
		// look up the moodle web service for enrolled courses for this user

		// match the moodle course ids back to post ids - check for duplicate course ids!
		// [prefix_woo_moodle_course]
		// meta_id
		// product_id
		// moodle_post_id - it says moodle but it's really a wordpress post record of type 'eb_course' - an "EDWISER COURSE"
		// moodle_course_id - the course record from moodle


		// write these courses into the table will look up the courses to draw
		// [prefix_moodle_enrollment]
		// id
		// user_id
		// course_id (this is a POST of type 'eb_course' and status of 'publish')
		// role_id = 5 (configured somewhere)
		// time = a date
		// expire_time = zeroed date
		// act_cnt = the number of times the user has accessed the course (default: 1)
		// suspended = 0
		// membership_id = NULL - no idea


		// TODO: determine what happens if we leave these records in the table
		// does it muck up other systems?
		// there is no eb_after_my_courses_wrapper where we could otherwise clean up

	}

}

/* register a shortcode [pdaverify] */
add_action ( 'init', 'pda_register_shortcode');
function pda_register_shortcode() {
	add_shortcode('pdaverify', 'pda_handler');
}

/* consume the [pdaverify] shortcode and produce a form for verifying the token

	[pdaverify feedbackObj=".some-class" placeholder="Jeff Bezos" label="Who owns Amazon?" size="5"]Do It![/pdaverify]

	[pdaverify]Apply[/pdaverify]

*/
function pda_handler( $atts, $content = null ) {
	if (!is_user_logged_in()) return $content;

	$id = wp_unique_id('pda');
	$buttontext = do_shortcode($content);

	extract(shortcode_atts(array(
		"feedbackObj" => '#pdaFeedback',
		"placeholder" => 'Enter here ...',
		"label" => 'Token:',
		'size' => 8,
	), $atts));

	$nonce = wp_nonce_field('verifytoken', '_nonce', true, false);

	$form = <<<FORM
	<form method="post" action="{$posturl}" data-pda-feedback-obj="{$feedbackObj}" class="pda-verify-wrapper">
		{$nonce}
		<input type="hidden" name="action" value="verifytoken">
	    <span class="pda-verify-label"><label for="{$id}_input">{$label}</label></span>
		<span class="pda-verify-input"><input id="{$id}_input" name="token" type="text" size="{$size}" placeholder="{$placeholder}"></span>
		<span class="pda-verify-submit"><input type="submit" value="{$buttontext}"></span>
	</form>
	<output id="pdaFeedback" class="pda-verify-output"></output>
FORM;

	wp_register_script('pda_shortcode', plugin_dir_url( __FILE__ ) . 'verify.js', ['jquery']);
	wp_localize_script('pda_shortcode', 'pdaAjax', [
		'ajaxurl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('ajax-nonce','verifytoken'),
	]);
	wp_enqueue_script('jquery');
	wp_enqueue_script('pda_shortcode');

	return $form;
}

/* register an ajax callback function to be used by verify.js */
add_action('wp_ajax_verifytoken', 'pda_verifytokenjs');
function pda_verifytokenjs() {
	if ( !wp_verify_nonce( $_REQUEST['_nonce'],'verifytoken')) {
		exit("No soup for you!");
	}
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		$result = pda_webservice_call("st_apply_token", [
			"token" => $_REQUEST["token"]
		]);
		$result = json_encode($result);
		echo $result;
	} else {
		header("Location: ".$_SERVER["HTTP_REFERER"]);
	}
	wp_die();
}

/* perform a webservice call to moodle using the function-name and data */
function pda_webservice_call($function_name, $data) {
	$moodle_url = get_option('pdamod_moodle', '');
	$moodle_token = get_option('pdamod_token', '');
	$return = false;
	$params = http_build_query([
		"wstoken" => $moodle_token,
		"wsfunction" => $function_name,
		"moodlewsrestformat" => "json"
	],'', '&');
	try {
		$response = wp_remote_post("{$moodle_url}/webservice/rest/server.php?{$params}", [
			'headers' => [
				'Accept' => 'application/json'
			],
			'blocking' => true,
			'body' => $data
		]);
		if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
			$responseBody = json_decode($response['body']);
			if( json_last_error() === JSON_ERROR_NONE ) {
				$return = $responseBody;
			}
		}
	} catch (Exception $ex) {
		// TODO
	}
	return $return;
}