<?php
/**
 * WooCommerce Card-to-Card Gateway.
 *
 * Provides a manual card-to-card (inter-bank) payment method for Iranian stores.
 * The customer sees the shop card details, transfers funds, uploads the payment
 * receipt image, and the shop admin verifies it manually.
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Card_To_Card
 */
class WC_Gateway_Card_To_Card extends WC_Payment_Gateway {

	/**
	 * Maximum allowed receipt file size in bytes (5 MB).
	 *
	 * @var int
	 */
	const MAX_FILE_SIZE = 5242880;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'card_to_card';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = 'کارت به کارت';
		$this->method_description = 'پرداخت دستی از طریق انتقال وجه کارت به کارت. مشتری پس از واریز، تصویر رسید پرداخت را آپلود می‌کند و مدیر فروشگاه آن را تأیید می‌کند.';

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->supports    = array( 'products' );

		// Save admin settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Allow file uploads through the checkout form.
		add_action( 'woocommerce_after_checkout_form', array( $this, 'output_multipart_fix' ) );

		// Enqueue checkout styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Admin order meta box for reviewing transactions.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_meta_box' ) );
	}

	/**
	 * Force multipart/form-data encoding on the checkout form so file inputs work.
	 *
	 * @param string $checkout_title Checkout shortcode output (unused).
	 */
	public function output_multipart_fix( $checkout_title ) {
		unset( $checkout_title );
		echo "<script>
			jQuery(function($){
				$('form.checkout').attr('enctype','multipart/form-data');
			});
		</script>";
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
			$cart_total = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0;
			if ( $cart_total <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Enqueue styles and scripts on the checkout page.
	 */
	public function enqueue_checkout_assets() {
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
	}

	/**
	 * Gateway settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => 'فعال‌سازی / غیرفعال‌سازی',
				'type'    => 'checkbox',
				'label'   => 'فعال‌سازی درگاه کارت به کارت',
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => 'عنوان',
				'type'        => 'text',
				'description' => 'عنوانی که مشتری در صفحه تسویه حساب می‌بیند.',
				'default'     => 'پرداخت کارت به کارت',
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => 'توضیحات',
				'type'        => 'textarea',
				'description' => 'توضیحاتی که مشتری در صفحه تسویه حساب می‌بیند.',
				'default'     => 'پرداخت از طریق انتقال وجه کارت به کارت',
			),
			'card_number'  => array(
				'title'       => 'شماره کارت',
				'type'        => 'text',
				'description' => 'شماره کارت مقصد (بدون خط تیره).',
				'default'     => '',
				'desc_tip'    => true,
			),
			'card_holder'  => array(
				'title'       => 'نام صاحب کارت',
				'type'        => 'text',
				'description' => 'نام و نام خانوادگی صاحب حساب.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'bank_name'    => array(
				'title'       => 'نام بانک',
				'type'        => 'text',
				'description' => 'مثال: ملت، سامان، پاسارگاد و غیره.',
				'default'     => '',
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => 'راهنمای پرداخت',
				'type'        => 'textarea',
				'description' => 'متنی که بالای فرم آپلود رسید نمایش داده می‌شود.',
				'default'     => 'لطفاً مبلغ سفارش را به شماره کارت زیر واریز کنید و عکس رسید پرداخت را در فرم زیر آپلود نمایید.',
			),
			'hide_if_free' => array(
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
		if ( $card_number && 16 === strlen( $card_number ) ) {
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

		// File upload field (custom, WC doesn't support file inputs natively).
		echo '<div class="woo-c2c-upload-wrapper">';
		echo '<label for="c2c_receipt_image" class="woo-c2c-upload-label">تصویر رسید (اجباری) *</label>';
		echo '<input type="file" id="c2c_receipt_image" name="c2c_receipt_image" accept="image/jpeg,image/png,image/webp" class="woo-c2c-file-input" />';
		echo '<div class="woo-c2c-upload-preview" id="woo-c2c-preview"></div>';
		echo '<p class="woo-c2c-upload-hint">فرمت‌های مجاز: JPG، PNG، WebP — حداکثر حجم: ۵ مگابایت</p>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Validate payment fields on checkout.
	 */
	public function validate_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce checkout.
		if ( empty( $_FILES['c2c_receipt_image']['name'] ) ) {
			wc_add_notice( 'لطفاً تصویر رسید پرداخت را آپلود کنید.', 'error' );
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file = $_FILES['c2c_receipt_image'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wc_add_notice( 'خطا در آپلود فایل. لطفاً دوباره تلاش کنید.', 'error' );
			return false;
		}

		// Check file type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		$file_type     = wp_check_filetype( $file['name'], array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		) );

		if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed_types, true ) ) {
			wc_add_notice( 'فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP آپلود کنید.', 'error' );
			return false;
		}

		// Check file size.
		if ( (int) $file['size'] > self::MAX_FILE_SIZE ) {
			wc_add_notice( 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.', 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Process the payment — save the receipt and mark the order on-hold.
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
		$note = $attachment_id
			? 'رسید پرداخت آپلود شد. (شناسه پیوست: ' . (int) $attachment_id . ')'
			: 'سفارش ثبت شد اما رسید پرداخت آپلود نشد.';

		$order->add_order_note( $note );

		$order->set_status( 'on-hold' );
		$order->save();

		WC()->cart->empty_cart();

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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce checkout.
		if ( empty( $_FILES['c2c_receipt_image']['name'] ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file = $_FILES['c2c_receipt_image'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Move the uploaded file into the uploads directory via WP API.
		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			),
		);

		$move_result = wp_handle_upload( $file, $overrides );

		if ( ! is_array( $move_result ) || isset( $move_result['error'] ) ) {
			return 0;
		}

		$file_path = $move_result['file'];
		$file_url  = $move_result['url'];

		// Create the attachment.
		$attachment = array(
			'post_mime_type' => $move_result['type'],
			'post_title'     => sanitize_file_name( basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $file_url,
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $order_id );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		// Generate and store attachment metadata (image sizes etc.).
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}

	/**
	 * Admin order meta box — shows the uploaded receipt image.
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
			$img_url = wp_get_attachment_url( (int) $attachment_id );
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
}
