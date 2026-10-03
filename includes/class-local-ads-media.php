<?php
/**
 * Local advertisement image storage.
 *
 * Images are stored under wp_upload_dir()['basedir'] / {upload_folder}/ and tracked in
 * their own table so they stay separate from the WordPress Media Library.
 *
 * @package LocalAds
 */

defined( 'ABSPATH' ) || exit;

/**
 * Media storage and validation.
 */
class Local_Ads_Media {

	/**
	 * Folder used while a wp_handle_upload() call is in progress.
	 *
	 * @var string
	 */
	private static $active_folder = '';

	/**
	 * Allowed extension => MIME map.
	 *
	 * @return array
	 */
	public static function allowed_mimes() {
		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
		if ( Local_Ads_Settings::get( 'allow_gif' ) ) {
			$mimes['gif'] = 'image/gif';
		}
		return $mimes;
	}

	/**
	 * Maximum upload size in bytes (setting, capped by the server limit).
	 *
	 * @return int
	 */
	public static function max_bytes() {
		$setting = (int) Local_Ads_Settings::get( 'max_upload_mb' ) * MB_IN_BYTES;
		$server  = (int) wp_max_upload_size();
		return $server > 0 ? min( $setting, $server ) : $setting;
	}

	/**
	 * Absolute path and URL of the configured upload folder, creating it when needed.
	 *
	 * @return array|WP_Error { path, url, relative }
	 */
	public static function folder() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'uploads', __( 'The uploads directory is not available.', 'local-ads' ) );
		}
		$relative = Local_Ads_Security::sanitize_folder( Local_Ads_Settings::get( 'upload_folder' ) );
		if ( false === $relative ) {
			return new WP_Error( 'invalid_folder', __( 'The selected folder is invalid.', 'local-ads' ) );
		}
		$path = trailingslashit( $uploads['basedir'] ) . $relative;
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return new WP_Error( 'mkdir', __( 'The advertisement folder could not be created.', 'local-ads' ) );
		}
		if ( ! Local_Ads_Security::path_within( $path, $uploads['basedir'] ) ) {
			return new WP_Error( 'invalid_folder', __( 'The selected folder is invalid.', 'local-ads' ) );
		}
		self::protect_folder( $path );
		return array(
			'path'     => $path,
			'url'      => trailingslashit( $uploads['baseurl'] ) . $relative,
			'relative' => $relative,
		);
	}

	/**
	 * Adds an index file and an .htaccess that blocks script execution.
	 *
	 * @param string $path Folder path.
	 */
	private static function protect_folder( $path ) {
		$index = trailingslashit( $path ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$htaccess = trailingslashit( $path ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Local Ads: images only. Block script execution.\n"
				. "<FilesMatch \"\\.(?i:php[0-9]?|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|shtml|svg|html?)$\">\n"
				. "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
				. "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
				. "</FilesMatch>\n";
			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Validates an entry of $_FILES and stores it in the advertisement folder.
	 *
	 * @param array $file    One $_FILES entry.
	 * @param int   $replace Media ID to replace, or 0 to create a new record.
	 * @return int|WP_Error Media ID.
	 */
	public static function handle_upload( $file, $replace = 0 ) {
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! isset( $file['error'] ) ) {
			return new WP_Error( 'no_file', __( 'The image could not be uploaded.', 'local-ads' ) . ' ' . __( 'No file was received.', 'local-ads' ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$msg = in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
				? __( 'The file is larger than the allowed size.', 'local-ads' )
				: __( 'The upload did not complete.', 'local-ads' );
			return new WP_Error( 'upload_error', __( 'The image could not be uploaded.', 'local-ads' ) . ' ' . $msg );
		}
		if ( (int) $file['size'] > self::max_bytes() ) {
			/* translators: %s: maximum size, e.g. "5 MB". */
			return new WP_Error( 'too_large', sprintf( __( 'The image could not be uploaded. The maximum file size is %s.', 'local-ads' ), size_format( self::max_bytes() ) ) );
		}

		$validation = self::validate_image( $file['tmp_name'], $file['name'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$folder = self::folder();
		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		self::$active_folder = $folder['relative'];
		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::allowed_mimes(),
			)
		);
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );
		self::$active_folder = '';

		if ( ! is_array( $result ) || isset( $result['error'] ) ) {
			return new WP_Error( 'handle_upload', __( 'The image could not be uploaded.', 'local-ads' ) . ( isset( $result['error'] ) ? ' ' . $result['error'] : '' ) );
		}

		if ( ! Local_Ads_Security::path_within( $result['file'], $folder['path'] ) ) {
			wp_delete_file( $result['file'] );
			return new WP_Error( 'path', __( 'The image could not be uploaded.', 'local-ads' ) );
		}

		$size     = wp_getimagesize( $result['file'] );
		$uploads  = wp_upload_dir( null, false );
		$relative = ltrim( str_replace( wp_normalize_path( $uploads['basedir'] ), '', wp_normalize_path( $result['file'] ) ), '/' );
		$row      = array(
			'file'       => $relative,
			'filename'   => wp_basename( $result['file'] ),
			'mime'       => $validation,
			'width'      => $size ? (int) $size[0] : 0,
			'height'     => $size ? (int) $size[1] : 0,
			'filesize'   => (int) filesize( $result['file'] ),
			'updated_at' => Local_Ads_DB::now_gmt(),
		);

		global $wpdb;
		$table = Local_Ads_DB::table( 'media' );
		if ( $replace ) {
			$old = self::get( $replace );
			if ( ! $old ) {
				wp_delete_file( $result['file'] );
				return new WP_Error( 'missing', __( 'The image to replace was not found.', 'local-ads' ) );
			}
			$wpdb->update( $table, $row, array( 'id' => (int) $old->id ) );
			$old_path = self::path( $old );
			if ( $old_path && $old_path !== $result['file'] ) {
				self::delete_file_safely( $old_path );
			}
			return (int) $old->id;
		}

		$row['uploaded_by'] = get_current_user_id();
		$row['created_at']  = Local_Ads_DB::now_gmt();
		$wpdb->insert( $table, $row );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Checks real file content: extension, MIME sniffing and image decoding.
	 *
	 * @param string $tmp  Temporary path.
	 * @param string $name Original filename (untrusted).
	 * @return string|WP_Error Detected MIME type.
	 */
	public static function validate_image( $tmp, $name ) {
		$raw   = wp_basename( (string) $name );
		$name  = sanitize_file_name( $raw );
		$mimes = self::allowed_mimes();
		$check = wp_check_filetype_and_ext( $tmp, $name, $mimes );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ! in_array( $check['type'], $mimes, true ) ) {
			return new WP_Error( 'type', __( 'The image could not be uploaded. Only JPG, PNG, WEBP and GIF images are allowed.', 'local-ads' ) );
		}
		// Reject double extensions such as file.php.jpg.
		if ( preg_match( '/\.(php[0-9]?|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|exe|js|html?|svg)[._]/i', $raw ) ) {
			return new WP_Error( 'type', __( 'The image could not be uploaded. The filename is not allowed.', 'local-ads' ) );
		}
		$info = wp_getimagesize( $tmp );
		if ( ! $info || empty( $info['mime'] ) || ! in_array( $info['mime'], $mimes, true ) ) {
			return new WP_Error( 'type', __( 'The image could not be uploaded. The file is not a valid image.', 'local-ads' ) );
		}
		return $info['mime'];
	}

	/**
	 * upload_dir filter: put files in the advertisement folder without year/month subfolders.
	 *
	 * @param array $dirs Upload dirs.
	 * @return array
	 */
	public static function filter_upload_dir( $dirs ) {
		if ( '' === self::$active_folder ) {
			return $dirs;
		}
		$dirs['subdir'] = '/' . self::$active_folder;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}

	/**
	 * Fetches one media row.
	 *
	 * @param int $id Media ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}
		$table = Local_Ads_DB::table( 'media' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Lists media with assignment information.
	 *
	 * @return object[]
	 */
	public static function all() {
		global $wpdb;
		$media = Local_Ads_DB::table( 'media' );
		$ads   = Local_Ads_DB::table( 'ads' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT m.*, GROUP_CONCAT(CONCAT(a.id, ':', a.status) SEPARATOR ',') AS assigned FROM {$media} m LEFT JOIN {$ads} a ON a.media_id = m.id GROUP BY m.id ORDER BY m.created_at DESC, m.id DESC" );
		foreach ( $rows as $row ) {
			$row->ads = array();
			if ( $row->assigned ) {
				foreach ( explode( ',', $row->assigned ) as $pair ) {
					list( $ad_id, $status ) = array_pad( explode( ':', $pair, 2 ), 2, '' );
					$row->ads[ (int) $ad_id ]  = $status;
				}
			}
			$row->url = self::url( $row );
		}
		return $rows;
	}

	/**
	 * Public URL for a media row.
	 *
	 * @param object $media Media row.
	 * @return string
	 */
	public static function url( $media ) {
		if ( ! $media || empty( $media->file ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		$parts   = array_map( 'rawurlencode', explode( '/', $media->file ) );
		return trailingslashit( $uploads['baseurl'] ) . implode( '/', $parts );
	}

	/**
	 * Absolute path for a media row, or '' if it does not resolve inside uploads.
	 *
	 * @param object $media Media row.
	 * @return string
	 */
	public static function path( $media ) {
		if ( ! $media || empty( $media->file ) || false !== strpos( $media->file, '..' ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		$path    = trailingslashit( $uploads['basedir'] ) . $media->file;
		return file_exists( $path ) && Local_Ads_Security::path_within( $path, $uploads['basedir'] ) ? $path : '';
	}

	/**
	 * IDs and statuses of ads using a media item.
	 *
	 * @param int $id Media ID.
	 * @return object[]
	 */
	public static function usage( $id ) {
		global $wpdb;
		$table = Local_Ads_DB::table( 'ads' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, name, status FROM {$table} WHERE media_id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Deletes a media item unless a live (active/scheduled) ad uses it.
	 * Inactive ads using it are detached.
	 *
	 * @param int  $id    Media ID.
	 * @param bool $force Delete even if live ads use it (used by uninstall cleanup).
	 * @return true|WP_Error
	 */
	public static function delete( $id, $force = false ) {
		global $wpdb;
		$media = self::get( $id );
		if ( ! $media ) {
			return new WP_Error( 'missing', __( 'The image was not found.', 'local-ads' ) );
		}
		$usage = self::usage( $media->id );
		if ( ! $force ) {
			foreach ( $usage as $ad ) {
				if ( in_array( $ad->status, array( 'active', 'scheduled' ), true ) ) {
					/* translators: %s: advertisement name. */
					return new WP_Error( 'in_use', sprintf( __( 'This image is used by the live advertisement "%s". Assign another image or pause the advertisement first.', 'local-ads' ), $ad->name ) );
				}
			}
		}
		if ( $usage ) {
			$wpdb->update( Local_Ads_DB::table( 'ads' ), array( 'media_id' => 0 ), array( 'media_id' => (int) $media->id ) );
		}
		$path = self::path( $media );
		if ( $path ) {
			self::delete_file_safely( $path );
		}
		$wpdb->delete( Local_Ads_DB::table( 'media' ), array( 'id' => (int) $media->id ) );
		return true;
	}

	/**
	 * Deletes a file only when it is inside the uploads directory.
	 *
	 * @param string $path Absolute path.
	 */
	private static function delete_file_safely( $path ) {
		$uploads = wp_upload_dir( null, false );
		if ( file_exists( $path ) && Local_Ads_Security::path_within( $path, $uploads['basedir'] ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Removes every tracked image file (uninstall cleanup when enabled).
	 */
	public static function delete_all_files() {
		global $wpdb;
		$table = Local_Ads_DB::table( 'media' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$path = self::path( $row );
			if ( $path ) {
				self::delete_file_safely( $path );
			}
		}
	}

	/**
	 * Data needed by the admin media picker.
	 *
	 * @param object $row Media row.
	 * @return array
	 */
	public static function to_js( $row ) {
		return array(
			'id'       => (int) $row->id,
			'url'      => self::url( $row ),
			'filename' => $row->filename,
			'width'    => (int) $row->width,
			'height'   => (int) $row->height,
			'size'     => size_format( (int) $row->filesize ),
		);
	}
}
