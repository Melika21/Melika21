<?php
/**
 * WooCommerce Card-to-Card Gateway.
 *
 * Provides a manual card-to-card (inter-bank) payment method for Iranian stores.
 * The customer sees the shop card details, transfers funds, and enters the
 * transaction code (RRN) on the checkout page.
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Card_To_Card
 */
class WC_Gateway_Card_To_Card extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'card_to_card';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = 'کارت به کارت';
		$this->method_description = 'پرداخت دستی از طریق انتقال وجه کارت به کارت. مشتری پس از واریز کد پیگیری (RRN) را وارد می‌کند و مدیر فروشگاه تأیید می‌کند.';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->supports    = array( 'products' );

		// Enqueue checkout styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Save admin settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Handle receipt image upload via AJAX.
		add_action( 'wp_ajax_woo_c2c_upload_receipt', array( $this, 'ajax_upload_receipt' ) );

		// Enqueue checkout scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Admin order meta box for reviewing transactions.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_admin_order_meta' ) );
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		// Hide for free orders if configured.
		if ( 'yes' === $this->get_option( 'hide_if_free' ) ) {
			$cart_total = WC()->cart ? WC()->cart->get_total( 'edit' ) : 0;
			if ( 0 === (float) $cart_total ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Enqueue styles and scripts on the checkout page.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'woo-c2c-checkout',
			WOO_C2C_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			WOO_C2C_VERSION
		);

		wp_enqueue_script(
			'woo-c2c-checkout',
			WOO_C2C_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			WOO_C2C_VERSION,
			true
		);

		wp_localize_script( 'woo-c2c-checkout', 'wooC2C', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'woo_c2c_receipt_upload' ),
			'max_size' => 5 * 1024 * 1024, // 5 MB.
			'max_size_text' => '۵ مگابایت',
		) );
	}

	/**
	 * Gateway settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'       => array(
				'title'   => 'فعال‌سازی / غیرفعال‌سازی',
				'type'    => 'checkbox',
				'label'   => 'فعال‌سازی درگاه کارت به کارت',
				'default' => 'yes',
			),
			'title'         => array(
				'title'       => 'عنوان',
				'type'        => 'text',
				'description' => 'عنوانی که مشتری در صفحه تسویه حساب می‌بیند.',
				'default'     => 'پرداخت کارت به کارت',
				'desc_tip'    => true,
			),
			'description'   => array(
				'title'       => 'توضیحات',
				'type'        => 'textarea',
				'description' => 'توضیحاتی که مشتری در صفحه تسویه حساب می‌بیند.',
				'default'     => 'پرداخت از طریق انتقال وجه کارت به کارت',
			),
			'card_number'   => array(
				'title'       => 'شماره کارت',
				'type'        => 'text',
				'description' => 'شماره کارت مقصد (بدون خط تیره).',
				'default'     => '',
				'desc_tip'    => true,
			),
			'card_holder'   => array(
				'title'       => 'نام صاحب کارت',
				'type'        => 'text',
				'description' => 'نام و نام خانوادگی صاحب حساب.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'bank_name'     => array(
				'title'       => 'نام بانک',
				'type'        => 'text',
				'description' => 'مثال: ملت، سامان، پاسارگاد و غیره.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'instructions'  => array(
				'title'       => 'راهنمای پرداخت',
				'type'        => 'textarea',
				'description' => 'متنی که بالای فرم آپلود رسید نمایش داده می‌شود.',
				'default'     => 'لطفاً مبلغ سفارش را به شماره کارت زیر واریز کنید و عکس رسید پرداخت را در فرم زیر آپلود نمایید.',
			),
			'hide_if_free'  => array(
				'title'   => 'مخفی کردن در سفارش رایگان',
				'type'    => 'checkbox',
				'label'   => 'اگر سفارش رایگان باشد این روش پرداخت نمایش داده نشود.',
				'default' => 'yes',
			),
		);
	}

	/**
	 * Payment fields shown on the checkout page.
	 */
	public function payment_fields() {
		$card_number  = $this->get_option( 'card_number' );
		$card_holder  = $this->get_option( 'card_holder' );
		$bank_name    = $this->get_option( 'bank_name' );
		$instructions = $this->get_option( 'instructions' );

		// Format card number for display: xxxx-xxxx-xxxx-xxxx.
		$display_number = $card_number;
		if ( $card_number && strlen( $card_number ) === 16 ) {
			$display_number = implode( '-', str_split( $card_number, 4 ) );
		}

		echo '<div class="woo-c2c-payment-fields">';

		if ( $instructions ) {
			echo '<p class="woo-c2c-instructions">' . esc_html( $instructions ) . '</p>';
		}

		if ( $card_number || $card_holder || $bank_name ) {
			echo '<div class="woo-c2c-card-info">';
			echo '<table class="woo-c2c-card-table">';

			if ( $card_number ) {
				echo '<tr>';
				echo '<th>شماره کارت:</th>';
				echo '<td class="woo-c2c-card-number" dir="ltr">' . esc_html( $display_number ) . '</td>';
				echo '</tr>';
			}
			if ( $card_holder ) {
				echo '<tr>';
				echo '<th>به نام:</th>';
				echo '<td>' . esc_html( $card_holder ) . '</td>';
				echo '</tr>';
			}
			if ( $bank_name ) {
				echo '<tr>';
				echo '<th>بانک:</th>';
				echo '<td>' . esc_html( $bank_name ) . '</td>';
				echo '</tr>';
			}

			echo '</table>';
			echo '</div>';
		}

		echo '<p class="woo-c2c-form-label">پس از واریز، تصویر رسید پرداخت را آپلود کنید:</p>';

		// File upload field (custom, not woocommerce_form_field — WC doesn't support file type natively).
		echo '<div class="woo-c2c-upload-wrapper">';
		echo '<label for="c2c_receipt_image" class="woo-c2c-upload-label">تصویر رسید (اجباری) *</label>';
		echo '<input type="file" id="c2c_receipt_image" name="c2c_receipt_image" accept="image/*" class="woo-c2c-file-input" />' ;
		echo '<div class="woo-c2c-upload-preview" id="woo-c2c-preview"></div>';
		echo '<p class="woo-c2c-upload-hint">فرمت‌های مجاز: JPG, PNG, WebP — حداکثر حجم: ۵ مگابایت</p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Validate payment fields on checkout.
	 */
	public function validate_fields() {
		// Validate receipt image upload.
		if ( empty( $_FILES['c2c_receipt_image'] ) || 0 !== $_FILES['c2c_receipt_image']['error'] ) {
			wc_add_notice( 'لطفاً تصویر رسید پرداخت را آپلود کنید.', 'error' );
			return false;
		}

		$file = $_FILES['c2c_receipt_image'];

		// Check file type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		$file_type     = wp_check_filetype( $file['name'], array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		) );

		if ( ! in_array( $file_type['type'], $allowed_types, true ) ) {
			wc_add_notice( 'فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP آپلود کنید.', 'error' );
			return false;
		}

		// Check file size (max 5 MB).
		if ( $file['size'] > 5 * 1024 * 1024 ) {
			wc_add_notice( 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.', 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Process the payment — create order and mark as on-hold.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Handle receipt image upload.
		$attachment_id = $this->handle_receipt_upload( $order_id );

		$order->update_meta_data( '_c2c_receipt_attachment_id', $attachment_id );
		$order->update_meta_data( '_c2c_status', 'pending_review' );

		// Add order note.
		$note = 'رسید پرداخت آپلود شده.';
		if ( $attachment_id ) {
			$note .= ' (شناسه پیوست: ' . $attachment_id . ')';
		}
		$order->add_order_note( $note );

		$order->set_status( 'on-hold' );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Handle receipt image upload and attach to the order.
	 *
	 * @param int $order_id The order ID.
	 * @return int Attachment ID or 0 on failure.
	 */
	private function handle_receipt_upload( $order_id ) {
		if ( empty( $_FILES['c2c_receipt_image'] ) || 0 !== $_FILES['c2c_receipt_image']['error'] ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = $_FILES['c2c_receipt_image'];

		// Determine file extension from MIME type.
		$extension_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);

		$mime_type = wp_check_filetype( $file['name'], array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		) );

		$ext = isset( $extension_map[ $mime_type['type'] ] ) ? $extension_map[ $mime_type['type'] ] : 'jpg';

		// Build a unique filename.
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['path'];
		$filename   = 'receipt-order-' . $order_id . '-' . time() . '.' . $ext;
		$file_path  = $dir . '/' . $filename;

		// Move the uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
			return 0;
		}

		// Generate attachment metadata.
		$wp_filetype = wp_check_filetype( $file_path, null );
		$attachment  = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Admin order meta box — shows transaction details and approve/reject buttons.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function admin_order_meta_box( $order ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		$attachment_id = $order->get_meta( '_c2c_receipt_attachment_id' );
		$c2c_status    = $order->get_meta( '_c2c_status' );

		echo '<div class="woo-c2c-admin-meta">';
		echo '<h3>اطلاعات پرداخت کارت به کارت</h3>';

		if ( $attachment_id ) {
			$img_url = wp_get_attachment_url( $attachment_id );
			if ( $img_url ) {
				echo '<p><strong>رسید پرداخت:</strong></p>';
				echo '<a href="' . esc_url( $img_url ) . '" target="_blank">';
				echo '<img src="' . esc_url( $img_url ) . '" style="max-width:300px;max-height:400px;border:1px solid #ddd;border-radius:4px;" />'; 
				echo '</a>';
				echo '<p style="font-size:12px;color:#888;margin-top:5px;">برای مشاهده در اندازه کامل روی تصویر کلیک کنید.</p>';
			} else {
				echo '<p><strong>رسید پرداخت:</strong> <span style="color:red;">فایل یافت نشد</span></p>';
			}
		} else {
			echo '<p><strong>رسید پرداخت:</strong> <span style="color:orange;">آپلود نشده</span></p>';
		}

		if ( 'pending_review' === $c2c_status ) {
			echo '<p><strong>وضعیت بررسی:</strong> در انتظار بررسی ⏳</p>';
			echo '<p style="color:#999;font-size:12px;">رسید را بررسی کنید و در صورت تأیید، وضعیت سفارش را به «تکمیل‌شده» تغییر دهید.</p>';
		} elseif ( 'approved' === $c2c_status ) {
			echo '<p><strong>وضعیت بررسی:</strong> <span style="color:green;">تأیید شده ✅</span></p>';
		} elseif ( 'rejected' === $c2c_status ) {
			echo '<p><strong>وضعیت بررسی:</strong> <span style="color:red;">رد شده ❌</span></p>';
		}

		echo '</div>';
	}

	/**
	 * Save meta from admin order edit.
	 *
	 * @param int $order_id The order ID.
	 */
	public function save_admin_order_meta( $order_id ) {
		// Placeholder for future admin transaction management.
	}

	/**
	 * AJAX handler for receipt image upload.
	 */
	public function ajax_upload_receipt() {
		check_ajax_referer( 'woo_c2c_receipt_upload', 'nonce' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			echo wp_send_json_error( array( 'message' => 'عدم دسترسی.' ) );
		}

		echo wp_send_json_success();
	}

}
