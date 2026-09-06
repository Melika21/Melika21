<?php
/**
 * WooCommerce Card-to-Card Gateway.
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

require_once WOO_C2C_PLUGIN_DIR . 'includes/class-wc-card-to-card-upload-handler.php';

/**
 * Class WC_Gateway_Card_To_Card
 */
class WC_Gateway_Card_To_Card extends WC_Payment_Gateway {

	const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB.

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'card_to_card';
		$this->icon               = '';
		$this->has_fields         = true;
		$this->method_title       = __( 'کارت به کارت', 'woo-card-to-card' );
		$this->method_description = __( 'پرداخت دستی از طریق انتقال وجه کارت به کارت. مشتری پس از واریز، تصویر رسید پرداخت را آپلود می‌کند و مدیر فروشگاه آن را تأیید می‌کند.', 'woo-card-to-card' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->supports    = array( 'products' );

		// Settings save.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Force multipart on checkout form so file inputs work.
		add_action( 'woocommerce_after_checkout_form', array( $this, 'output_multipart_fix' ) );

		// Enqueue assets on checkout.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
	}

	/**
	 * Is this gateway available for the current cart?
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		if ( 'yes' === $this->get_option( 'hide_if_free' ) ) {
			$cart_total = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0;
			if ( $cart_total <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Force multipart form encoding so file inputs can be submitted.
	 */
	public function output_multipart_fix() {
		echo "<script>
			jQuery(function ($) {
				$('form.checkout').attr('enctype', 'multipart/form-data');
			});
		</script>";
	}

	/**
	 * Enqueue checkout assets.
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'woo-c2c-checkout',
			WOO_C2C_PLUGIN_DIR . 'assets/css/checkout.css',
			array(),
			WOO_C2C_VERSION
		);

		wp_enqueue_script(
			'woo-c2c-checkout',
			WOO_C2C_PLUGIN_DIR . 'assets/js/checkout.js',
			array( 'jquery' ),
			WOO_C2C_VERSION,
			true
		);

		wp_localize_script( 'woo-c2c-checkout', 'wooC2C', array(
			'max_size_bytes' => self::MAX_FILE_SIZE,
			'max_size_text'  => __( '۵ مگابایت', 'woo-card-to-card' ),
			'copy_feedback'  => __( 'کپی شد', 'woo-card-to-card' ),
		) );
	}

	/**
	 * Admin settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'فعال‌سازی / غیرفعال‌سازی', 'woo-card-to-card' ),
				'type'    => 'checkbox',
				'label'   => __( 'فعال‌سازی درگاه کارت به کارت', 'woo-card-to-card' ),
				'default' => 'yes',
			),
			'title'        => array(
				'title'       => __( 'عنوان', 'woo-card-to-card' ),
				'type'        => 'text',
				'description' => __( 'عنوانی که مشتری در صفحه تسویه حساب می‌بیند.', 'woo-card-to-card' ),
				'default'     => __( 'پرداخت کارت به کارت', 'woo-card-to-card' ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => __( 'توضیحات', 'woo-card-to-card' ),
				'type'        => 'textarea',
				'description' => __( 'توضیحاتی که مشتری در صفحه تسویه حساب می‌بیند.', 'woo-card-to-card' ),
				'default'     => __( 'پرداخت از طریق انتقال وجه کارت به کارت', 'woo-card-to-card' ),
			),
			'card_number'  => array(
				'title'       => __( 'شماره کارت', 'woo-card-to-card' ),
				'type'        => 'text',
				'description' => __( 'شماره کارت مقصد (بدون خط تیره).', 'woo-card-to-card' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'card_holder'  => array(
				'title'       => __( 'نام صاحب کارت', 'woo-card-to-card' ),
				'type'        => 'text',
				'description' => __( 'نام و نام خانوادگی صاحب حساب.', 'woo-card-to-card' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'bank_name'    => array(
				'title'       => __( 'نام بانک', 'woo-card-to-card' ),
				'type'        => 'text',
				'description' => __( 'مثال: ملت، سامان، پاسارگاد و غیره.', 'woo-card-to-card' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'shaba_number' => array(
				'title'       => __( 'شماره شبا (اختیاری)', 'woo-card-to-card' ),
				'type'        => 'text',
				'description' => __( 'اگر شماره شبا دارید، درج کنید. مشتری می‌تواند آن را هم کپی کند.', 'woo-card-to-card' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'راهنمای پرداخت', 'woo-card-to-card' ),
				'type'        => 'textarea',
				'description' => __( 'متنی که بالای فرم آپلود رسید نمایش داده می‌شود.', 'woo-card-to-card' ),
				'default'     => __( 'لطفاً مبلغ سفارش را به شماره کارت زیر واریز کنید و عکس رسید پرداخت را در فرم زیر آپلود نمایید.', 'woo-card-to-card' ),
			),
			'hide_if_free' => array(
				'title'   => __( 'مخفی کردن در سفارش رایگان', 'woo-card-to-card' ),
				'type'    => 'checkbox',
				'label'   => __( 'اگر سفارش رایگان باشد این روش پرداخت نمایش داده نشود.', 'woo-card-to-card' ),
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
		$shaba_number = $this->get_option( 'shaba_number' );
		$instructions = $this->get_option( 'instructions' );

		$display_number = $card_number;
		if ( $card_number && 16 === strlen( $card_number ) ) {
			$display_number = implode( '-', str_split( $card_number, 4 ) );
		}

		$display_shaba = $shaba_number;
		if ( $shaba_number && 24 === strlen( $shaba_number ) ) {
			$display_shaba = implode( '-', str_split( $shaba_number, 4 ) );
		}

		echo '<div class="woo-c2c-payment-fields">';

		if ( $instructions ) {
			echo '<p class="woo-c2c-instructions">' . esc_html( $instructions ) . '</p>';
		}

		if ( $card_number || $card_holder || $bank_name || $shaba_number ) {
			echo '<div class="woo-c2c-card-info">';
			echo '<div class="woo-c2c-card-info-header">شماره حساب و شماره شبا</div>';
			echo '<table class="woo-c2c-card-table">';

			if ( $card_number ) {
				echo '<tr class="woo-c2c-card-row">';
				echo '<th>شماره کارت:</th>';
				echo '<td>';
				printf(
					'<span class="woo-c2c-copy-target" id="woo-c2c-card-number" dir="ltr">%s</span>',
					esc_html( $display_number )
				);
				echo '<button type="button" class="woo-c2c-copy-btn" data-target="woo-c2c-card-number">';
				esc_html_e( 'کپی کردن', 'woo-card-to-card' );
				echo '</button>';
				echo '</td>';
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

			if ( $shaba_number ) {
				echo '<tr class="woo-c2c-card-row">';
				echo '<th>شماره شبا:</th>';
				echo '<td>';
				printf(
					'<span class="woo-c2c-copy-target" id="woo-c2c-shaba" dir="ltr">%s</span>',
					esc_html( $display_shaba )
				);
				echo '<button type="button" class="woo-c2c-copy-btn" data-target="woo-c2c-shaba">';
				esc_html_e( 'کپی کردن', 'woo-card-to-card' );
				echo '</button>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</table>';
			echo '</div>';
		}

		echo '<p class="woo-c2c-form-label">' . __( 'پس از واریز، تصویر رسید پرداخت را آپلود کنید:', 'woo-card-to-card' ) . '</p>';

		echo '<div class="woo-c2c-upload-wrapper">';
		echo '<label for="c2c_receipt_image" class="woo-c2c-upload-label">' . __( 'تصویر رسید (اجباری) *', 'woo-card-to-card' ) . '</label>';
		echo '<input type="file" id="c2c_receipt_image" name="c2c_receipt_image" accept="image/jpeg,image/png,image/webp" class="woo-c2c-file-input" />';
		echo '<div class="woo-c2c-upload-preview" id="woo-c2c-preview"></div>';
		printf(
			'<p class="woo-c2c-upload-hint">%s: JPG، PNG، WebP — حداکثر حجم: %s</p>',
			esc_html__( 'فرمت‌های مجاز', 'woo-card-to-card' ),
			esc_html( wooC2C.max_size_text )
		);
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Validate payment fields on checkout.
	 */
	public function validate_fields() {
		if ( empty( $_FILES['c2c_receipt_image']['name'] ) ) {
			wc_add_notice( __( 'لطفاً تصویر رسید پرداخت را آپلود کنید.', 'woo-card-to-card' ), 'error' );
			return false;
		}

		$file = $_FILES['c2c_receipt_image'];

		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wc_add_notice( __( 'خطا در آپلود فایل. لطفاً دوباره تلاش کنید.', 'woo-card-to-card' ), 'error' );
			return false;
		}

		$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp' );
		$file_type     = wp_check_filetype( $file['name'], array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
		) );

		if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed_types, true ) ) {
			wc_add_notice( __( 'فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP آپلود کنید.', 'woo-card-to-card' ), 'error' );
			return false;
		}

		if ( (int) $file['size'] > self::MAX_FILE_SIZE ) {
			wc_add_notice( __( 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.', 'woo-card-to-card' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'error',
				'redirect' => '',
			);
		}

		$upload_handler = new WC_Card_To_Card_Upload_Handler();
		$attachment_id  = $upload_handler->handle_receipt_upload( $order_id );

		$order->update_meta_data( '_c2c_receipt_attachment_id', $attachment_id );
		$order->update_meta_data( '_c2c_status', 'pending_review' );

		$note = $attachment_id
			? __( 'رسید پرداخت آپلود شد.', 'woo-card-to-card' ) . ' (شناسه پیوست: ' . (int) $attachment_id . ')'
			: __( 'سفارش ثبت شد، tetapi رسید پرداخت آپلود نشد.', 'woo-card-to-card' );

		$order->add_order_note( $note );

		$order->set_status( 'on-hold' );
		$order->save();

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
