<?php

namespace BOILERPLATE\Inc;

use BOILERPLATE\Inc\Traits\Program_Logs;
use BOILERPLATE\Inc\Traits\Singleton;

class Cleanup_Scheduler {

	use Singleton;
	use Program_Logs;

	public function __construct() {
		$this->setup_hooks();
	}

	public function setup_hooks() {
		// Register custom interval if needed
		add_filter( 'cron_schedules', [ $this, 'add_weekly_schedule' ] );

		// Schedule cron event on plugin activation (optional — add if needed)
		add_action( 'init', [ $this, 'maybe_schedule_weekly_cleanup' ] );

		// Hook callback for the cleanup task
		add_action( 'wasp_weekly_retry_table_cleanup', [ $this, 'truncate_retry_table' ] );
	}

	/**
	 * Add weekly schedule if not exists
	 */
	public function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'boilerplate' ),
			];
		}
		return $schedules;
	}

	/**
	 * Schedule the weekly cleanup event if not already scheduled
	 */
	public function maybe_schedule_weekly_cleanup() {
		if ( ! wp_next_scheduled( 'wasp_weekly_retry_table_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'wasp_weekly_retry_table_cleanup' );
		}
	}

	/**
	 * Callback: Truncate retry table
	 */
	public function truncate_retry_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'sync_wasp_retry_items';

		// Truncate the table safely
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		// Optional: Log the cleanup
		$this->put_program_logs( "✅ Weekly cleanup completed for table: {$table}" );
	}

	/**
	 * Optional helper: clear scheduled event manually (if needed)
	 */
	public function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( 'wasp_weekly_retry_table_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wasp_weekly_retry_table_cleanup' );
		}
	}
}
