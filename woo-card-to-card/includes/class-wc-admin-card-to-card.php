<?php
/**
 * Card-to-Card Admin Order Meta Box and Approval Controller.
 *
 * @package Woo_Card_To_Card
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Order' ) ) {
	return;
}

/**
 * Renders the card-to-card transaction info panel on the admin order screen
 * and provides approve/reject actions.
 */
class WC_Admin_Card_To_Card {

	/**
	 * Payment gateway ID.
	 *
	 * @var string
	 */
	const GATEWAY_ID = 'card_to_card';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'order_meta_box' ) );
		add_action( 'admin_post_wpcard2c_approve_order', array( $this, 'handle_approval' ) );
		add_action( 'admin_post_wpcard2c_reject_order', array( $this, 'handle_rejection' ) );
	}

	/**
	 * Render order meta box.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function order_meta_box( $order ) {
		if ( self::GATEWAY_ID !== $order->get_payment_method() ) {
			return;
		}

		$attachment_id = $order->get_meta( '_c2c_receipt_attachment_id' );
		$status        = $order->get_meta( '_c2c_status' );

		echo '<div class="woo-c2c-admin-meta">';
		echo '<h3>' . esc_html__( 'اطلاعات پرداخت کارت به کارت', 'woo-card-to-card' ) . '</h3>';

		if ( $attachment_id ) {
			$img_url = wp_get_attachment_url( (int) $attachment_id );
			if ( $img_url ) {
				echo '<p><strong>' . esc_html__( 'رسید پرداخت:', 'woo-card-to-card' ) . '</strong></p>';
				echo '<a href="' . esc_url( $img_url ) . '" target="_blank">';
				echo '<img src="' . esc_url( $img_url ) . '" style="max-width:300px;max-height:400px;border:1px solid #ddd;border-radius:4px;" />';
				echo '</a>';
				echo '<p style="font-size:12px;color:#888;margin-top:5px;">' . esc_html__( 'برای مشاهده در اندازه کامل روی تصویر کلیک کنید.', 'woo-card-to-card' ) . '</p>';
			} else {
				echo '<p><strong>' . esc_html__( 'رسید پرداخت:', 'woo-card-to-card' ) . '</strong> ';
				echo '<span style="color:red;">' . esc_html__( 'فایل یافت نشد', 'woo-card-to-card' ) . '</span></p>';
			}
		} else {
			echo '<p><strong>' . esc_html__( 'رسید پرداخت:', 'woo-card-to-card' ) . '</strong> ';
			echo '<span style="color:orange;">' . esc_html__( 'آپلود نشده', 'woo-card-to-card' ) . '</span></p>';
		}

		$this->render_status_badge( $status );

		// Approve / reject controls only when pending_review.
		if ( 'pending_review' === $status ) {
			echo '<div class="woo-c2c-admin-actions">';
			$approve_url  = wp_nonce_url(
				add_query_arg( array(
					'post'   => $order->get_id(),
					'action' => 'wpcard2c_approve_order',
				), admin_url( 'admin-post.php' ) ),
				'woo_c2c_approve_' . $order->get_id()
			);
			$reject_url   = wp_nonce_url(
				add_query_arg( array(
					'post'   => $order->get_id(),
					'action' => 'wpcard2c_reject_order',
				), admin_url( 'admin-post.php' ) ),
				'woo_c2c_reject_' . $order->get_id()
			);

			echo '<a href="' . esc_url( $approve_url ) . '" class="button button-primary woo-c2c-approve-btn">';
			esc_html_e( '✅ تأیید رسید و تغییر به «در حال انجام»', 'woo-card-to-card' );
			echo '</a>';

			echo '<a href="' . esc_url( $reject_url ) . '" class="button woo-c2c-reject-btn">';
			esc_html_e( '❌ رد رسید و بازگشت به «در انتظار بررسی»', 'woo-card-to-card' );
			echo '</a>';

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render a small status badge.
	 *
	 * @param string $status Internal status string.
	 */
	protected function render_status_badge( $status ) {
		echo '<div class="woo-c2c-status-badge" style="margin-top:10px;padding:6px 10px;border-radius:4px;font-weight:600;font-size:13px;">';

		switch ( $status ) {
			case 'pending_review':
				echo '<span style="color:#b7791f;">⏳ در انتظار بررسی</span>';
				break;
			case 'processing':
				echo '<span style="color:#2f855a;">🟢 در حال انجام</span>';
				break;
			case 'approved':
				echo '<span style="color:#2f855a;">✅ تأیید شده</span>';
				break;
			case 'rejected':
				echo '<span style="color:#c53030;">❌ رد شده</span>';
				break;
			default:
				echo '<span>' . esc_html( $status ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Approve action: set status to processing, update meta, add note.
	 */
	public function handle_approval() {
		$order_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0;

		check_admin_referer( 'woo_c2c_approve_' . $order_id );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( __( 'دسترسی غیرمجاز.', 'woo-card-to-card' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( __( 'سفارش یافت نشد.', 'woo-card-to-card' ) );
		}

		if ( self::GATEWAY_ID !== $order->get_payment_method() ) {
			wp_die( __( 'این سفارش با روش پرداخت کارت به کارت انجام نشده است.', 'woo-card-to-card' ) );
		}

		if ( $order->get_status() === 'processing' || $order->get_status() === 'completed' ) {
			wp_safe_redirect( remove_query_arg( array( 'action', 'post' ), wp_get_referer() ) );
			exit;
		}

		$order->update_meta_data( '_c2c_status', 'processing' );
		$order->set_status( 'processing' );
		$order->add_order_note( __( 'رسید پرداخت تأیید شد. وضعیت سفارش به «در حال انجام» تغییر یافت.', 'woo-card-to-card' ) );
		$order->save();

		wp_safe_redirect( remove_query_arg( array( 'action', 'post' ), wp_get_referer() ) );
		exit;
	}

	/**
	 * Reject action: reset to pending_review, add note.
	 */
	public function handle_rejection() {
		$order_id = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : 0;

		check_admin_referer( 'woo_c2c_reject_' . $order_id );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( __( 'دسترسی غیرمجاز.', 'woo-card-to-card' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( __( 'سفارش یافت نشد.', 'woo-card-to-card' ) );
		}

		if ( self::GATEWAY_ID !== $order->get_payment_method() ) {
			wp_die( __( 'این سفارش با روش پرداخت کارت به کارت انجام نشده است.', 'woo-card-to-card' ) );
		}

		if ( $order->get_status() === 'processing' || $order->get_status() === 'completed' ) {
			wp_safe_redirect( remove_query_arg( array( 'action', 'post' ), wp_get_referer() ) );
			exit;
		}

		$order->update_meta_data( '_c2c_status', 'pending_review' );
		$order->set_status( 'pending' );
		$order->add_order_note( __( 'رسید پرداخت رد شد. سفارش به «در انتظار بررسی» بازگردانده شد.', 'woo-card-to-card' ) );
		$order->save();

		wp_safe_redirect( remove_query_arg( array( 'action', 'post' ), wp_get_referer() ) );
		exit;
	}
}
