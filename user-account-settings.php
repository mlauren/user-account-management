<?php
/*
  Plugin Name: User Account Settings
  Description: Customizations to User Account Stuff -- logged in actions
	Author: Lauren
	Author URI: https://github.com/mlauren
 */
 
add_action( 'plugins_loaded', array( 'AccoutEditHooks', 'get_instance' ) );

class AccoutEditHooks {
	public static $instance = null;
	
	public $nonce = '';
	
	// name of scripts
	public $name = 'accounteditajax';
	public $loggedin = 'loggedin';

	function __construct() {
		// Account Delete	
		add_action("wp_ajax_delete_user", array( $this, 'ajaxDeleteUser') );
		// non priv action for deletion
		add_action("wp_ajax_nopriv_delete_user", array( $this, 'ajaxDeleteUser') );

		// Save Search
		add_action("wp_ajax_saved_searches", array( $this, 'ajaxSaveSearches') );
		add_action("wp_ajax_nopriv_saved_searches", array( $this, 'ajaxSaveSearches') );
		// Save Individual Listing
		add_action("wp_ajax_saved_listings", array( $this, 'ajaxSaveListings') );
		// Send notification email
		add_action("wp_ajax_saved_searches", array( $this, 'send_saved_search_notification_email') );

		add_action("wp_ajax_check_all_saved_listings", array( $this, 'check_all_saved_listings') );

		// Remove Search
		add_action("wp_ajax_rm_search", array( $this, 'ajaxRmSearch') );
		// Remove Listing
		add_action('wp_ajax_rm_listings', array( $this, 'ajaxRmListings') );
		add_action('wp_ajax_remove_listing_by_id', array( $this, 'remove_listing_by_id') );

		// Save listings output from search
		add_action("wp_ajax_save_search_listings", array( $this, 'axaxSaveJsonOutput') );

		add_action( 'wp_loaded', array( $this, 'scriptsRegister' ) );
		// Could as well be: wp_enqueue_scripts or login_enqueue_scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'scriptsEnqueue' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'scriptsEnqueue' ) );
		// add php variables to script
		add_action( 'wp_enqueue_scripts', array( $this, 'scriptsLocalize' ) );
	}

	/**
	 * Initiates the Class within Wordpress.
	 *
	 * @return AccoutEditHooks|null
	 */
	public static function get_instance()
	{
		// create an object
		NULL === self::$instance and self::$instance = new self;
		return self::$instance; // return the object
	}

	/**
	 * Register the JavaScripts
	 *
	 * @param $page
	 */
	public function scriptsRegister( $page )
	{
		$file = 'user-account-settings.js';
		wp_register_script(
			$this->name,
			plugins_url( $file, __FILE__ ),
			array(
				'jquery',
			),
			filemtime( plugin_dir_path( __FILE__ )."/{$file}" ),
			true
		);
	}

	/**
	 * Enqueue JavaScripts
	 *
	 * @param $page
	 */
	public function scriptsEnqueue( $page )
	{
		wp_enqueue_script( $this->name );
	}

	/**
	 * Localize actions and variables used in the JavaScripts
	 *
	 * @param $page
	 */
	public function scriptsLocalize( $page )
	{
		$this->nonce = wp_create_nonce( "{$this->name}_nonce" );
		
		wp_localize_script( $this->name, "{$this->name}Object", array(
			'url' => admin_url( 'admin-ajax.php' ),
			'nonce' => $this->nonce,
			'action_delete_user' => 'delete_user',
			'action_saved_searches' => 'saved_searches',
			'action_rm_search' => 'rm_search',
			'action_save_search_listings' => 'user_saved_listings',
			'action_save_individual_listing' => 'saved_listings',
			'action_check_saved_listings' => 'check_all_saved_listings',
			'action_rm_ind_listing' => 'rm_listings',
			'action_remove_listing_by_id' => 'remove_listing_by_id'
		) );
	}

	/**
	 * Delete user ajax request.
	 *
	 * @param $data
	 */
	public function ajaxDeleteUser($data) {
		// Get current user logged in info
		$user = wp_get_current_user();
		if ($_POST['senddata'] && $_POST['senddata'] == true) {
			$delete = wp_delete_user($user->ID);	
			if (!is_wp_error($delete)) {
				wp_send_json_success( array(
					'response' => 'success',
					'message' => 'You have successfully deleted your account. Redirecting...'
				));	
			}
			else {
				wp_send_json_error( array(
					'response' => 'error',
					'error_code' => 'deletion_problem',
					'message' => $delete->get_error_message
				));	
			}
		}
	}

  /**
   * @param $user_id
   */
  public function send_saved_listing_notification_email( $user_id ) {

	    if (!current_user_can('edit_posts')) {
	      wp_mail( 'jesse@reapmarketing.com', 'The subject', "THIS USER SAVED A LISTING");
	    }

	}

	
	// Basically the same thing but different
  /**
   * AJAX request - Allow Wordpress user to save listings.
   */
  public function ajaxSaveListings() {
		$user = wp_get_current_user();
		$prev_value = get_user_meta( $user->ID, 'user_saved_listings', true);

		// convert value of user meta to array
		if ($prev_value) {
			$listings = unserialize($prev_value);
			// Makes sure that search doesn't exist already
			foreach ($listings as $index => $value) {
				if ($value['searchURL'] == $_POST['searchURL']) {
					wp_send_json_error( array(
						'error_code' => 'update_user_meta',
						'message' => 'Listing already saved!'
					));
				}
			}
		}
		else {
			$listings = [];
		}

		$listingsPosted = $_POST;
		$listings[] = $listingsPosted;
		$listings = serialize($listings);
		$serialized_listing_posted = serialize($listingsPosted);

		// Update user meta
		$usermet = update_user_meta( $user->ID, 'user_saved_listings', $listings, false);

		if (!$usermet) {
			wp_send_json_error( array(
				'error_code' => 'update_user_meta',
				'message' => 'Something went wrong!'
			));
    }

		wp_send_json_success( array(
			'message' => 'Listing Saved!'
		));

		die();
	}

	/**
   * AJAX request - Check for saved listings.
   */
	public function check_all_saved_listings() {
		$user = wp_get_current_user();
		$saved_listings = get_user_meta( $user->ID, 'user_saved_listings', true);

		if ( $saved_listings ) {
			wp_send_json(unserialize($saved_listings));
		}
		else {
			wp_send_json_error();
		}
		die();
	}

	public function remove_listing_by_id($data) {
		$user = wp_get_current_user();
		$saved_listings = get_user_meta( $user->ID, 'user_saved_listings', true);

		if ($saved_listings) {
			$listings_new_array = [];
			$listings = unserialize($saved_listings);

			foreach ($listings as $index => $listing) {
				if ( $listing['listing']['listing_id'] !== $_POST['listing']['listing_id'] ) {
					$listings_new_array[] = $listing;
				}
			}
			//if (())

			// wp_send_json($searches);
			// Remove from array
			$listingsnew = serialize($listings_new_array);

			$usermet = update_user_meta($user->ID, 'user_saved_listings', $listingsnew);
			if ( $usermet ) {
				wp_send_json_success();
			}
			else {
				wp_send_json_error();
			}
			
		}
		die();
	}

  /**
   * AJAX request - Wordpress User Removes their favorite listings.
   */
  public function ajaxRmListings() {
		$user = wp_get_current_user();
		$saved_listings = get_user_meta( $user->ID, 'user_saved_listings', true);

		if ($saved_listings) {
			$searches = unserialize($saved_listings);

			// wp_send_json($searches);
			// Lets remove based on listing ID
			foreach ( $searches as $index => $value ) {
				if ($value['listing']['listing_id'] == $_POST['index_rm']) {
					unset($searches[$index]);
				}
			}
			// wp_send_json($searches);
			// Remove from array
			$searches = serialize($searches);
			$usermet = update_user_meta( $user->ID, 'user_saved_listings', $searches, false);
			if ( $usermet ) {
				wp_send_json_success( array(
					'message' => 'Search has been removed.'
				));
			}
		}
		// If nothings happen return here
		wp_send_json_error( array(
			'error_code' => 'update_user_meta',
			'message' => 'Something went wrong!'
		));
		die();
	}

  /**
   * AJAX request - Wordpress user saves their favorite searches.
   */
  public function ajaxSaveSearches() {
		$user = wp_get_current_user();
		if ( $user->ID == 0 ) {
			wp_send_json_error( array(
				'message' => '<a href="#popup-registration" class="popup-opener">Create an Account</a> or <a href="#popup-login" class="popup-opener">Login</a> to save your searches and listings.'
			));
		}

		$prev_value = get_user_meta( $user->ID, 'user_saved_searches', true);
		// convert value of user meta to array
		if ($prev_value) {
			$searches = unserialize($prev_value);
			// Makes sure that search doesn't exist already
			foreach ($searches as $index => $value) {
				if (is_array($value) && $value['url'] == $_POST['searchURL']) {
					wp_send_json_error( array(
						'error_code' => 'update_user_meta',
						'message' => 'Search already saved!'
					));
				}
			}
		}
		else {
			$searches = array();
		}

		$searches[] = array('url' => $_POST['searchURL'], 'date_saved' => date('n/j/y'), 'id_first_entry' => $_POST['id_first_entry']);
		$searches = serialize($searches);
		// Update user meta
		$usermet = update_user_meta( $user->ID, 'user_saved_searches', $searches, false);

		if (!$usermet) {
			wp_send_json_error( array(
				'error_code' => 'update_user_meta',
				'message' => 'Something went wrong!'
			));
		}

		wp_send_json_success( array(
			'message' => 'Search Saved!'
		));
		die();
	}

  /**
   * AJAX request - Wordpress user removes their favorite searches.
   */
  public function ajaxRmSearch() {
		$user = wp_get_current_user();
		$saved_searches = get_user_meta( $user->ID, 'user_saved_searches', true);

		if ( $saved_searches ) {
			$searches = unserialize($saved_searches);
			$indexnum = (int)$_POST['index_rm'];
			// Remove from array
			unset($searches[$indexnum]);
			$searches = serialize($searches);
			$usermet = update_user_meta( $user->ID, 'user_saved_searches', $searches, false);
			if ( $usermet ) {
				wp_send_json_success( array(
					'message' => 'Search has been removed.'
				));
			}
		}
		// If nothings happen return here
		wp_send_json_error( array(
			'error_code' => 'update_user_meta',
			'message' => 'Something went wrong!'
		));
		// If nothings happen return here
		die();
	}

  /**
   * AJAX request - Save json output for listings returned from saved search.
   */
  public function axaxSaveJsonOutput() {
		$user = wp_get_current_user();
		// get the user meta
		$saved_searches_listings = get_user_meta( $user->ID, 'user_saved_searches_listings', true );
		$current_search_listings = $_POST['listings'];
		// convert value of user meta to array
		if (!empty($saved_searches_listings)) {
			$search_listings = unserialize($saved_searches_listings);
		}
		else {
			$search_listings = array();
		}
		// Add listings object to search listing array
		$search_listings[] = $current_search_listings;
		$search_listings = serialize($search_listings);
		// Update user meta
		$usermet = update_user_meta( $user->id, 'user_saved_searches_listings', $search_listings, false);

		if ($usermet !== false) {
			wp_send_json_success( array(
				'message' => 'Search Saved!'
			));
		}
		wp_send_json_error( array(
			'error_code' => 'update_user_meta',
			'message' => 'Something went wrong!'
		));
		die();
	}
}