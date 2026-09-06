<?php
/**
 * Card-to-Card Receipt Upload Handler.
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles moving the uploaded receipt file into the media library and linking
 * it to the WooCommerce order.
 */
class WC_Card_To_Card_Upload_Handler {

	/**
	 * Allowed image mime types.
	 *
	 * @var array
	 */
	private $allowed_types = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	);

	/**
	 * Handle receipt image upload for a given order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function handle_receipt_upload( $order_id ) {
		if ( empty( $_FILES['c2c_receipt_image']['name'] ) ) {
			return 0;
		}

		$file = $_FILES['c2c_receipt_image'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$type_check = wp_check_filetype( $file['name'], $this->allowed_types );
		$mime_type  = $type_check['type'];

		if ( empty( $mime_type ) || ! in_array( $mime_type, array_keys( $this->allowed_types ), true ) ) {
			return 0;
		}

		$ext        = $this->allowed_types[ $mime_type ];
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return 0;
		}

		$filename = 'receipt-order-' . (int) $order_id . '-' . time() . '.' . $ext;
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Move with WordPress file API.
		$overrides = array(
			'test_form' => false,
			'mimes'     => $this->allowed_types,
		);

		$move_result = wp_handle_upload( $file, $overrides );

		if ( ! is_array( $move_result ) || isset( $move_result['error'] ) ) {
			return 0;
		}

		$final_file_path = $move_result['file'];
		$final_file_url  = $move_result['url'];

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => sanitize_file_name( basename( $final_file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $final_file_url,
		);

		$attachment_id = wp_insert_attachment( $attachment, $final_file_path, (int) $order_id );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $final_file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}
}
