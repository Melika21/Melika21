/**
 * Card-to-Card Checkout — Receipt Upload
 *
 * - Shows a live preview of the chosen receipt image.
 * - Forces a native (non-AJAX) form submission when the card-to-card gateway
 *   is selected and a receipt file is attached, because WooCommerce's AJAX
 *   checkout cannot transmit file inputs.
 *
 * @package Woo_Card_To_Card
 */
(function ($) {
	"use strict";

	var MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB.
	var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

	$(function () {
		var $input = $('#c2c_receipt_image');
		var $preview = $('#woo-c2c-preview');

		if (!$input.length) {
			return;
		}

		// Live preview + client-side validation.
		$input.on('change', function () {
			$preview.empty();

			var file = this.files[0];
			if (!file) {
				return;
			}

			if (ALLOWED_TYPES.indexOf(file.type) === -1) {
				window.alert('فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP انتخاب کنید.');
				$input.val('');
				return;
			}

			if (file.size > MAX_FILE_SIZE) {
				window.alert('حجم فایل نباید بیشتر از ۵ مگابایت باشد.');
				$input.val('');
				return;
			}

			var reader = new FileReader();
			reader.onload = function (e) {
				$preview.html(
					'<img src="' + e.target.result + '" alt="پیش‌نمایش رسید" class="woo-c2c-preview-img" />' +
					'<p class="woo-c2c-file-name">' + file.name + '</p>'
				);
			};
			reader.readAsDataURL(file);
		});

		// WooCommerce checkout submits via AJAX by default, and AJAX cannot
		// transmit files. Force a native POST when this gateway is selected.
		$(document.body).on('checkout_place_order_card_to_card', function () {
			var $form = $('form.checkout');

			if (!$input.val()) {
				window.alert('لطفاً تصویر رسید پرداخت را آپلود کنید.');
				return false;
			}

			$form.attr('enctype', 'multipart/form-data');
			$form.removeClass('processing');
			$form.submit();
			return false;
		});
	});
})(jQuery);
