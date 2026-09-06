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

		// Admin order meta box for reviewing transactions.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_meta_box' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_admin_order_meta' ) );
	}

	/**
	 * Enqueue front-end styles on the checkout page.
	 */
	public function enqueue_styles() {
		if ( is_checkout() ) {
			wp_enqueue_style(
				'woo-c2c-checkout',
				WOO_C2C_PLUGIN_URL . 'assets/css/checkout.css',
				array(),
				WOO_C2C_VERSION
			);
		}
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
				'description' => 'متنی که بالای فرم وارد کردن کد پیگیری نمایش داده می‌شود.',
				'default'     => 'لطفاً مبلغ سفارش را به شماره کارت زیر واریز کنید و کد پیگیری را در فرم زیر وارد نمایید.',
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

		echo '<p class="woo-c2c-form-label">پس از واریز، کد پیگیری را وارد کنید:</p>';

		woocommerce_form_field( 'c2c_transaction_id', array(
			'type'        => 'text',
			'class'       => array( 'form-row-wide' ),
			'label'       => 'کد پیگیری (شماره پیگیری / RRN)',
			'placeholder' => 'مثال: 123456789012',
			'required'    => true,
		) );

		woocommerce_form_field( 'c2c_card_number', array(
			'type'        => 'text',
			'class'       => array( 'form-row-wide' ),
			'label'       => 'شماره کارت مبدأ (اختیاری)',
			'placeholder' => 'شماره کارتی که با آن واریز کردید',
			'required'    => false,
		) );

		woocommerce_form_field( 'c2c_payer_name', array(
			'type'        => 'text',
			'class'       => array( 'form-row-wide' ),
			'label'       => 'نام واریزکننده (اختیاری)',
			'placeholder' => 'نام کامل واریزکننده',
			'required'    => false,
		) );

		echo '</div>';
	}

	/**
	 * Validate payment fields on checkout.
	 */
	public function validate_fields() {
		$transaction_id = isset( $_POST['c2c_transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['c2c_transaction_id'] ) ) : '';

		if ( empty( $transaction_id ) ) {
			wc_add_notice( 'لطفاً کد پیگیری را وارد کنید.', 'error' );
			return false;
		}

		if ( strlen( $transaction_id ) < 6 ) {
			wc_add_notice( 'کد پیگیری باید حداقل ۶ رقم باشد.', 'error' );
			return false;
		}

		// Check for duplicate transaction ID.
		global $wpdb;
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}postmeta
				 WHERE meta_key = '_c2c_transaction_id' AND meta_value = %s
				 LIMIT 1",
				$transaction_id
			)
		);

		if ( $existing ) {
			wc_add_notice( 'این کد پیگیری قبلاً استفاده شده است. لطفاً کد صحیح را وارد کنید.', 'error' );
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
		$order          = wc_get_order( $order_id );
		$transaction_id = isset( $_POST['c2c_transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['c2c_transaction_id'] ) ) : '';
		$source_card    = isset( $_POST['c2c_card_number'] ) ? sanitize_text_field( wp_unslash( $_POST['c2c_card_number'] ) ) : '';
		$payer_name     = isset( $_POST['c2c_payer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['c2c_payer_name'] ) ) : '';

		// Save transaction data to the order.
		$order->update_meta_data( '_c2c_transaction_id', $transaction_id );
		$order->update_meta_data( '_c2c_source_card', $source_card );
		$order->update_meta_data( '_c2c_payer_name', $payer_name );
		$order->update_meta_data( '_c2c_status', 'pending_review' );

		// Add order note.
		$order->add_order_note(
			sprintf(
				'کد پیگیری اعلام شده: %s%s',
				$transaction_id,
				$source_card ? ' | شماره کارت مبدأ: ' . $source_card : ''
			)
		);

		$order->set_status( 'on-hold' );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
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

		$transaction_id = $order->get_meta( '_c2c_transaction_id' );
		$source_card    = $order->get_meta( '_c2c_source_card' );
		$payer_name     = $order->get_meta( '_c2c_payer_name' );
		$c2c_status     = $order->get_meta( '_c2c_status' );

		echo '<div class="woo-c2c-admin-meta">';
		echo '<h3>اطلاعات پرداخت کارت به کارت</h3>';

		if ( $transaction_id ) {
			echo '<p><strong>کد پیگیری:</strong> ' . esc_html( $transaction_id ) . '</p>';
		}
		if ( $source_card ) {
			echo '<p><strong>شماره کارت مبدأ:</strong> ' . esc_html( $source_card ) . '</p>';
		}
		if ( $payer_name ) {
			echo '<p><strong>واریزکننده:</strong> ' . esc_html( $payer_name ) . '</p>';
		}

		if ( 'pending_review' === $c2c_status ) {
			echo '<p><strong>وضعیت بررسی:</strong> در انتظار بررسی ⏳</p>';
			echo '<p style="color:#999;font-size:12px;">پس از تأیید واریز وجه، وضعیت سفارش را به «تکمیل‌شده» تغییر دهید.</p>';
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
}
