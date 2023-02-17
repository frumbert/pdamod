<?php

/*
Plugin Name: PDAcademy Edwiser Brige Extension
Description: Hooks into Edwiser to adjust my_course listing page and provides shortcodes for Token access
Author: tim st.clair <tim.stclair@gmail.com>
Author URI: https://www.frumbert.org/
Plugin URI: https://github.com/frumbert/pdamod
Version: 1.0.0
*/

$pdadebug = (strpos(home_url( $wp->request ), ".test") !== false);

if ($pdadebug) {
	add_filter( 'https_ssl_verify', '__return_false' );
}

function pdadump(...$value) {
global $pdadebug;
	if ($pdadebug) echo "<pre>", print_r($value, true), "</pre>";
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
register_activation_hook(__FILE__, 'pda_activate');
function pda_activate() {
	add_option('pda_moodle', 'https://set.path.to/moodle');
	add_option('pda_token', 'not-set');
}
register_deactivation_hook(__FILE__, 'pda_deactivate');
function pda_deactivate() {
	delete_option('pda_moodle');
	delete_option('pda_token');
}
register_uninstall_hook(__FILE__, 'pda_uninstall');
function pda_uninstall() {
	pda_deactivate();
	delete_option('pda_moodle');
	delete_option('pda_token');
}

/* BEFORE the my_courses list begins, see if we need to modify the data based on external enrolments */
add_action( 'eb_before_my_courses_wrapper', 'pda_before_my_courses_wrapper' );
function pda_before_my_courses_wrapper() {
	global $wpdb;
	$userid = get_current_user_id();
	$moodleid = get_user_meta($userid, 'moodle_user_id', true);

	// if the web service token was set
	if (!empty(get_option('pda_token')) && $moodleid > 0) {

		// get the Moodle courses that I'm enrolled in
		$result = pda_webservice_call("core_enrol_get_users_courses", [
			"userid" => $moodleid
		]);

		// if courses were returned, result will be set
		if (!empty($result)) {

			// get the Edwiser courses that I'm enrolled in
			$eb_courses = \app\wisdmlabs\edwiserBridge\eb_get_user_enrolled_courses( $userid );

			// $all_courses = \app\wisdmlabs\edwiserBridge\wdm_eb_get_all_eb_sourses();
			foreach ($result as $course) {

				// find out the edwiser course id based on the moodle course id
				$eb_courseid = \app\wisdmlabs\edwiserBridge\wdm_eb_get_wp_course_id_from_moodle_course_id($course->id);

				// tell Edwiser about any missing Moodle courses
				if ($eb_courseid && !in_array($eb_courseid, $eb_courses)) {
					$wpdb->insert( $wpdb->prefix . 'moodle_enrollment', [
						"user_id" => $userid,
						"course_id" => $eb_courseid,
						"role_id" => 5,
						"time" => current_time( 'mysql' ),
						"act_cnt" => 1,
						"suspended" => 0,
						"membership_id" => null,
					] );
				}
			}
		}

	}
}

/* register a shortcode [pdaverify] */
add_action ( 'init', 'pda_register_shortcode');
function pda_register_shortcode() {
	add_shortcode('pdaverify', 'pda_handler');
}

/* consume the [pdaverify] shortcode and produce a form for verifying the token */
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
		$userid = get_current_user_id();
		$moodleid = get_user_meta($userid, 'moodle_user_id', true);
		$result = pda_webservice_call("st_validate_token", [
			"token" => $_REQUEST["token"],
		]);
		if ($result->valid) {
			$result = pda_webservice_call("st_apply_token", [
				"token" => $_REQUEST["token"],
				"userid" => $moodleid
			]);
		}
		if ($result == false) {
			$result = [
				"message" => "Sorry an error occurred."
			];
		}
		$result = json_encode($result);
		echo $result;
	} else {
		header("Location: ".$_SERVER["HTTP_REFERER"]);
	}
	wp_die();
}

/* perform a webservice call to moodle using the function-name and data */
function pda_webservice_call($function_name, $data) {
global $pdadebug;
	$moodle_url = get_option('pda_moodle', '');
	$moodle_token = get_option('pda_token', '');
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
			'body' => $data,
			'timeout' => ($pdadebug) ? 300 : 15,
			'sslverify' => ($pdadebug) ? false : true,
		]);
		if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
			$responseBody = json_decode($response['body']);
			if( json_last_error() === JSON_ERROR_NONE ) {
				$return = $responseBody;
			}
		}
	} catch (Exception $ex) {
		throw new Exception(print_r($ex, true));
	}
	return $return;
}