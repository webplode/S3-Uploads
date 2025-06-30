<?php

namespace S3_Uploads;

use Imagick;
use WP_Error;
use WP_Image_Editor_Imagick;

class Image_Editor_Imagick extends WP_Image_Editor_Imagick {

	/**
	 * @var ?Imagick
	 */
	protected $image;

	/**
	 * @var ?string
	 */
	protected $file;

	/**
	 * @var ?array{width: int, height: int}
	 */
	protected $size;

	/**
	 * @var ?string
	 */
	protected $remote_filename = null;

	/**
	 * Hold on to a reference of all temp local files.
	 *
	 * These are cleaned up on __destruct.
	 *
	 * @var array
	 */
	protected $temp_files_to_cleanup = [];

	/**
	 * Loads image from $this->file into new Imagick Object.
	 *
	 * @return true|WP_Error True if loaded; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image instanceof Imagick ) {
			return true;
		}

		if ( $this->file && ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File doesn&#8217;t exist?' ), $this->file );
		}

		$upload_dir = wp_upload_dir();

		if ( ! $this->file || strpos( $this->file, $upload_dir['basedir'] ) !== 0 ) {
			return parent::load();
		}

		$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );
		$this->temp_files_to_cleanup[] = $temp_filename;

		copy( $this->file, $temp_filename );
		$this->remote_filename = $this->file;
		$this->file = $temp_filename;

		$result = parent::load();

		$this->file = $this->remote_filename;
		return $result;
	}

	/**
	 * Imagick by default can't handle s3:// paths
	 * for saving images. We have instead save it to a file file,
	 * then copy it to the s3:// path as a workaround.
	 *
	 * @param Imagick $image
	 * @param ?string $filename
	 * @param ?string $mime_type
	 * @return WP_Error|array{path: string, file: string, width: int, height: int, mime-type: string}
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		/**
		 * @var ?string $filename
		 * @var string $extension
		 * @var string $mime_type
		 */
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		$original_mime_type = $mime_type;
		$original_extension = $extension;
		$original_filename = $filename;

		// Check if we should convert to WebP based on original mime type
		$should_convert_to_webp = $this->should_convert_to_webp( $original_mime_type );

		// Convert PNG, JPG, JPEG to WebP - only upload WebP version to S3
		if ( $should_convert_to_webp ) {
			$this->log_debug( "Starting WebP conversion for file: {$filename}, original mime type: {$original_mime_type}" );
			
			$mime_type = 'image/webp';
			$extension = 'webp';
			
			// Update filename to include .webp extension
			if ( $filename ) {
				$filename = $this->add_webp_extension( $filename );
				$this->log_debug( "Converted filename from '{$original_filename}' to '{$filename}'" );
			}
		} else {
			$this->log_debug( "Skipping WebP conversion for mime type: {$original_mime_type}" );
		}

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		$upload_dir = wp_upload_dir();

		if ( strpos( $filename, $upload_dir['basedir'] ) === 0 ) {
			/** @var false|string */
			$temp_filename = tempnam( get_temp_dir(), 's3-uploads' );
		} else {
			$temp_filename = false;
		}

		// Convert to WebP if needed
		if ( $should_convert_to_webp ) {
			$this->log_debug( "Applying WebP format conversion with quality: " . apply_filters( 's3_uploads_webp_quality', 85 ) );
			
			try {
				$image->setImageFormat( 'webp' );
				$image->setImageCompressionQuality( apply_filters( 's3_uploads_webp_quality', 85 ) );
				$this->log_debug( "Successfully applied WebP format and quality settings" );
			} catch ( \Exception $e ) {
				$this->log_debug( "Error applying WebP format: " . $e->getMessage() );
				return new WP_Error( 'webp_conversion_failed', 'Failed to convert image to WebP: ' . $e->getMessage() );
			}
		}

		/**
		 * @var WP_Error|array{path: string, file: string, width: int, height: int, mime-type: string}
		 */
		$parent_call = parent::_save( $image, $temp_filename ?: $filename, $mime_type );

		if ( is_wp_error( $parent_call ) ) {
			$this->log_debug( "Parent _save failed: " . $parent_call->get_error_message() );
			if ( $temp_filename ) {
				unlink( $temp_filename );
			}

			return $parent_call;
		} else {
			/**
			 * @var array{path: string, file: string, width: int, height: int, mime-type: string} $save
			 */
			$save = $parent_call;
			$this->log_debug( "Parent _save successful, temp file created at: " . $save['path'] );
		}

		$copy_result = copy( $save['path'], $filename );

		unlink( $save['path'] );
		if ( $temp_filename ) {
			unlink( $temp_filename );
		}

		if ( ! $copy_result ) {
			$this->log_debug( "Failed to copy temp file to S3 destination: {$filename}" );
			return new WP_Error( 'unable-to-copy-to-s3', 'Unable to copy the temp image to S3' );
		}

		$this->log_debug( "Successfully copied image to S3: {$filename}" );

		$response = [
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'] ?? 0,
			'height'    => $this->size['height'] ?? 0,
			'mime-type' => $mime_type,
		];

		if ( $should_convert_to_webp ) {
			$this->log_debug( "WebP conversion completed successfully. Final file: " . $response['file'] . ", mime-type: " . $response['mime-type'] );
		}

		return $response;
	}

	/**
	 * Override multi_resize to handle WebP conversion for all image sizes.
	 *
	 * @param array $sizes
	 * @return array|WP_Error
	 */
	public function multi_resize( $sizes ) {
		// First check if we need to convert to WebP
		$current_mime = $this->mime_type;
		$should_convert = $this->should_convert_to_webp( $current_mime );
		
		$this->log_debug( "Starting multi_resize for " . count( $sizes ) . " sizes. Should convert to WebP: " . ( $should_convert ? 'yes' : 'no' ) );
		
		if ( $should_convert ) {
			// Temporarily change the mime type for processing
			$this->mime_type = 'image/webp';
			$this->log_debug( "Temporarily changed mime type from '{$current_mime}' to 'image/webp' for processing" );
		}

		$result = parent::multi_resize( $sizes );

		// Restore original mime type if it was changed
		if ( $should_convert ) {
			$this->mime_type = $current_mime;
			$this->log_debug( "Restored original mime type: {$current_mime}" );
			
			// Update the result array to reflect WebP filenames
			if ( ! is_wp_error( $result ) && is_array( $result ) ) {
				$converted_count = 0;
				foreach ( $result as $size_name => &$size_data ) {
					if ( isset( $size_data['file'] ) ) {
						$original_file = $size_data['file'];
						$size_data['file'] = $this->convert_filename_to_webp( $size_data['file'] );
						$size_data['mime-type'] = 'image/webp';
						$converted_count++;
						$this->log_debug( "Converted size '{$size_name}': '{$original_file}' -> '{$size_data['file']}'" );
					}
				}
				$this->log_debug( "Successfully converted {$converted_count} image sizes to WebP format" );
			} else if ( is_wp_error( $result ) ) {
				$this->log_debug( "Multi-resize failed: " . $result->get_error_message() );
			}
		}

		return $result;
	}

	/**
	 * Convert a filename to have .webp extension appended.
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function convert_filename_to_webp( string $filename ) : string {
		return $filename . '.webp';
	}

	/**
	 * Check if the image should be converted to WebP format.
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	protected function should_convert_to_webp( string $mime_type ) : bool {
		$convertible_types = [
			'image/png',
			'image/jpeg',
			'image/jpg'
		];

		$should_convert = in_array( $mime_type, $convertible_types, true );
		$this->log_debug( "Checking WebP conversion for mime type '{$mime_type}': " . ( $should_convert ? 'convertible' : 'not convertible' ) );
		
		return $should_convert;
	}

	/**
	 * Add .webp extension to filename (appends to existing extension).
	 *
	 * @param string $filename
	 * @return string
	 */
	protected function add_webp_extension( string $filename ) : string {
		return $filename . '.webp';
	}

	/**
	 * Log debug messages when WP_DEBUG is enabled.
	 *
	 * @param string $message
	 */
	protected function log_debug( string $message ) : void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[S3-Uploads WebP] ' . $message );
		}
	}

	public function __destruct() {
		array_map( 'unlink', $this->temp_files_to_cleanup );
		parent::__destruct();
	}
}
