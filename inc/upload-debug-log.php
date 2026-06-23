<?php

namespace S3_Uploads;

/**
 * Verbose logging for upload / thumbnail failures (see README).
 *
 * @param string               $message
 * @param array<string, mixed> $context
 */
function debug_log( string $message, array $context = [] ) : void {
	if ( ! is_debug_logging_enabled() ) {
		return;
	}

	$line = '[S3-Uploads] ' . $message;
	if ( $context !== [] ) {
		$line .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	error_log( $line );
}

/**
 * @return bool
 */
function is_debug_logging_enabled() : bool {
	if ( defined( 'S3_UPLOADS_DEBUG_LOG' ) ) {
		return (bool) S3_UPLOADS_DEBUG_LOG;
	}

	$filtered = apply_filters( 's3_uploads_debug_log', null );
	if ( $filtered !== null ) {
		return (bool) $filtered;
	}

	return defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
}

/**
 * Register hooks that trace metadata / thumbnail generation.
 */
function register_upload_debug_hooks() : void {
	if ( ! is_debug_logging_enabled() ) {
		return;
	}

	add_action( 'add_attachment', __NAMESPACE__ . '\\debug_log_add_attachment', 1, 1 );
	add_filter( 'wp_generate_attachment_metadata', __NAMESPACE__ . '\\debug_log_attachment_metadata_end', 999, 2 );
	add_filter( 'wp_handle_upload', __NAMESPACE__ . '\\debug_log_handle_upload', 20, 2 );
	add_action( 'shutdown', __NAMESPACE__ . '\\debug_log_shutdown_after_upload', 999 );
}

/**
 * Fires when the attachment post exists; thumbnails are generated next in the same request.
 *
 * @param int $attachment_id
 */
function debug_log_add_attachment( int $attachment_id ) : void {
	$file = get_attached_file( $attachment_id );
	$info = is_string( $file ) && is_readable( $file ) ? @getimagesize( $file ) : false;

	debug_log(
		'add_attachment (before thumbnail generation)',
		[
			'attachment_id'  => $attachment_id,
			'file'           => $file,
			'width'          => is_array( $info ) ? $info[0] : null,
			'height'         => is_array( $info ) ? $info[1] : null,
			'mime'           => is_array( $info ) ? ( $info['mime'] ?? null ) : null,
			'filesize'       => is_string( $file ) && file_exists( $file ) ? filesize( $file ) : null,
			'memory_mb'      => round( memory_get_usage( true ) / 1048576, 1 ),
			'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		]
	);
}

/**
 * @param array<string, mixed>|false $metadata
 * @param int                        $attachment_id
 * @return array<string, mixed>|false
 */
function debug_log_attachment_metadata_end( $metadata, int $attachment_id ) {
	if ( empty( $metadata ) || ! is_array( $metadata ) ) {
		debug_log(
			'generate_attachment_metadata FAILED (empty metadata)',
			[
				'attachment_id'  => $attachment_id,
				'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
			]
		);
		return $metadata;
	}

	$size_count = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? count( $metadata['sizes'] ) : 0;
	debug_log(
		'generate_attachment_metadata OK',
		[
			'attachment_id'   => $attachment_id,
			'sizes_generated' => $size_count,
			'size_names'      => $size_count ? array_keys( $metadata['sizes'] ) : [],
			'peak_memory_mb'  => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		]
	);

	return $metadata;
}

/**
 * @param array  $upload
 * @param string $context
 * @return array
 */
function debug_log_handle_upload( array $upload, string $context ) : array {
	if ( ! empty( $upload['error'] ) ) {
		debug_log( 'wp_handle_upload returned error', [ 'context' => $context, 'error' => $upload['error'] ] );
	} elseif ( ! empty( $upload['file'] ) ) {
		debug_log( 'wp_handle_upload OK', [ 'context' => $context, 'file' => $upload['file'], 'type' => $upload['type'] ?? null ] );
	}
	return $upload;
}

/**
 * Log PHP fatals / last error after media AJAX (when metadata step dies mid-request).
 */
function debug_log_shutdown_after_upload() : void {
	if ( ! is_debug_logging_enabled() || ! is_media_upload_ajax_request() ) {
		return;
	}

	$err = error_get_last();
	if ( $err && in_array( $err['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
		debug_log(
			'PHP fatal/error on media AJAX shutdown',
			[
				'type'           => $err['type'],
				'message'        => $err['message'],
				'file'           => $err['file'],
				'line'           => $err['line'],
				'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
			]
		);
	}
}