<?php

// Only run through WP CLI.
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

class Revslider_Search_Replace extends WP_CLI_Command {
	/**
	 * WP CLI Command to search replace the website URLs in the Revolution sliders
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 *      ID of the slider, also takes "all" as option where it will search accross all the sliders
	 *
	 * <source-url>
	 *      Source URL
	 *
	 * <destination-url>
	 *      destination URL
	 *
	 * [--network]
	 *      Search Replace the strings in Revolution sliders throughout all the sites in multisite network
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp rsr 2 <source-url> <destination-url>
	 *      - This will search replace the strings in the slider with is "2"
	 *  2. wp rsr all <source-url> <destination-url>
	 *      - This will search replace the strings on all the sliders on the site
	 *  3. wp rsr all <source-url> <destination-url> --network
	 *		- This command will will search replace the strings on all sliders accross all the sites in multisite network.
	 *
	*/

	public $slider;

	public function __invoke( $args, $assoc_args ) {

		$network = false;

		if ( ! class_exists( 'RevSliderSlider' ) ) {
			WP_CLI::error( "Revolution slider is not active" );

			return false;
		}

		$default = array(
			0 => '',
			1 => '',
			2 => '',
		);


		if ( ! isset( $args[0] ) ) {
			$args[0] = $default[0];
		}

		if ( ! isset( $args[1] ) ) {
			$args[1] = $default[1];
		}

		if ( ! isset( $args[2] ) ) {
			$args[2] = $default[2];
		}

		$id          = $args[0];
		$source      = $args[1];
		$destination = $args[2];

		if ( isset( $assoc_args['network'] ) && $assoc_args['network'] == true && is_multisite() ) {
			$network = true;
		}

		if ( $id == "" ) {
			WP_CLI::error( "Plese enter ID of the slider which you want to search-replace into or 'all' to select all the sliders" );

			return false;
		}

		if ( $source == "" ) {
			WP_CLI::error( "Please enter source URL" );

			return false;
		}

		if ( $destination == "" ) {
			WP_CLI::error( "Please enter destination URL" );

			return false;
		}

		$data = array(
			'url_from' => $source,
			'url_to'   => $destination
		);

		$this->slider = new RevSliderSlider();

		if ( $network == true ) {
			
			if ( function_exists( 'get_sites' ) ) {
				$blogs = get_sites();
			} else {
				$blogs = wp_get_sites();
			}

			foreach ( $blogs as $keys => $blog ) {

				// Cast $blog as an array instead of WP_Site object
				if ( is_object( $blog ) ) {
					$blog = (array) $blog;
				}

				$blog_id = $blog['blog_id'];
				switch_to_blog( $blog_id );
				WP_CLI::success( "Switched to the blog " . get_option( 'home' ) );
				$this->set_id_and_replace( $id, $data );
				restore_current_blog();
			}
		} else {
			$this->set_id_and_replace( $id, $data );
		}

	}

	public function set_id_and_replace( $id, $data ) {

		global $wpdb;
		$query = "Select * from ".RevSliderGlobals::$table_sliders;	
		
		if ( $id !== 'all' ) {
			$query = "Select * from ".RevSliderGlobals::$table_sliders." where id=".$id;
		}

		$sliders = $wpdb->get_results( $query );
		foreach ( $sliders as $key => $value ) {
			// var_dump($value->id);
			$data["id"] = $value->id;
			$this->replace_revslider_urls( $data );
		}
	}

	public function replace_revslider_urls( $data ) {
		// var_dump($data['id']);
		$sliderId = $data['id'];
		global $wpdb;
		$query = "Select * from ".RevSliderGlobals::$table_sliders." where id=".$sliderId;
		$sliders = $wpdb->get_results( $query );

		$encodedUrlFrom = json_encode($data['url_from']);
		$encodedUrlFrom = str_replace('"', '', $encodedUrlFrom);
		
		// $encodedUrlFrom = $encodedUrlFrom.replace(/\"/g, ""));
		$order   = array($data['url_from'], $encodedUrlFrom );
		$replace = json_encode($data['url_to']);
		$replace = str_replace('"', '', $replace);

		// var_dump($order);

		foreach ( $sliders as $key => $value ) {
			$count = 0;
			$data = [
				"params" => str_replace($order, $replace, $value->params, $count)
			];
			$where = [ 'id' => $sliderId ]; // NULL value in WHERE clause.
			// var_dump($where);
			$wpdb->update( RevSliderGlobals::$table_sliders, $data, $where );
			// echo RevSliderGlobals::$table_sliders.PHP_EOL;
			// echo "Update";

			WP_CLI::success( "Number of Urls replaced : " . $count );
			WP_CLI::success( "Search Replace complete for slider with id : " . $sliderId );
		}


		// Replace in slides
		$query = "Select * from ".RevSliderGlobals::$table_slides." where slider_id=".$sliderId;
		$slides = $wpdb->get_results( $query );
		foreach ( $slides as $key => $value ) {
			$count = 0;
			$count1 = 0;
			$data = [
				"params" => str_replace($order, $replace, $value->params, $count),
				"layers" => str_replace($order, $replace, $value->layers, $count1)
			];
			$where = [ 'id' => $value->id ];
			$wpdb->update( RevSliderGlobals::$table_slides, $data, $where );
			$totalCount = $count+$count1;
			WP_CLI::success( "Number of Urls replaced : " . $totalCount );
			WP_CLI::success( "Search Replace complete for slider with id {$sliderId} and slide with id : " . $value->id );
		}

		// Replace in static slides
		$query = "Select * from ".RevSliderGlobals::$table_static_slides." where slider_id=".$sliderId;
		$static_slides = $wpdb->get_results( $query );
		foreach ( $static_slides as $key => $value ) {
			$count = 0;
			$count1 = 0;
			$data = [
				"params" => str_replace($order, $replace, $value->params, $count),
				"layers" => str_replace($order, $replace, $value->layers, $count1)
			];
			$where = [ 'id' => $value->id ];
			$wpdb->update( RevSliderGlobals::$table_static_slides, $data, $where );

			$totalCount = $count+$count1;
			WP_CLI::success( "Number of Urls replaced : " . $totalCount );
			WP_CLI::success( "Search Replace complete for slider with id {$sliderId} and static slide with id : " . $value->id );
		}
	}

}

WP_CLI::add_command( 'rsr', 'Revslider_Search_Replace' );
