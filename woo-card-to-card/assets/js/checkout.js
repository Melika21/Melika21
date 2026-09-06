/**
 * Card-to-Card Checkout — Receipt Upload + Copy-to-Clipboard.
 *
 * @package Woo_Card_To_Card
 */
(function ($) {
	"use strict";

	var MAX_FILE_SIZE = wooC2C.max_size_bytes || 5242880;
	var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

	$(function () {
		// Copy-to-clipboard.
		$(document).on('click', '.woo-c2c-copy-btn', function () {
			var targetId = $(this).data('target');
			var $target  = $('#' + targetId);
			var text     = $target.text().trim();

			if (!text) {
				return;
			}

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					showCopyFeedback($(this));
				}).catch(function () {
					fallbackCopy(text, $(this));
				});
			} else {
				fallbackCopy(text, $(this));
			}
		});

		function fallbackCopy(text, $btn) {
			var $textarea = $('<textarea style="position:absolute;left:-9999px;"></textarea>');
			$textarea.val(text).appendTo('body');
			$textarea[0].select();
			try {
				document.execCommand('copy');
				showCopyFeedback($btn);
			} catch (e) {
				window.alert('کپی با خطا مواجه شد.');
			}
			$textarea.remove();
		}

		function showCopyFeedback($btn) {
			var $orig = $btn.clone();
			$btn.hide();
			var $label = $('<span class="woo-c2c-copy-feedback">' + wooC2C.copy_feedback + '</span>');
			$label.insertAfter($btn);
			setTimeout(function () {
				$label.fadeOut(800, function () { $label.remove(); });
				$btn.show();
			}, 1200);
		}

		// File input: preview + validation.
		var $input   = $('#c2c_receipt_image');
		var $preview = $('#woo-c2c-preview');

		if (!$input.length) {
			return;
		}

		$input.on('change', function () {
			$preview.empty();
			var file = this.files[0];
			if (!file) return;

			if (ALLOWED_TYPES.indexOf(file.type) === -1) {
				window.alert('فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP انتخاب کنید.');
				$input.val('');
				return;
			}

			if (file.size > MAX_FILE_SIZE) {
				window.alert('حجم فایل نباید بیشتر از ' + wooC2C.max_size_text + ' باشد.');
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

		// WooCommerce AJAX checkout can't transmit files — force native POST.
		$(document.body).on('checkout_place_order', function (e) {
			if ($('.payment_method_card_to_card input[name="payment_method"]').is(':checked')) {
				if (!$input.val()) {
					window.alert('لطفاً تصویر رسید پرداخت را آپلود کنید.');
					e.preventDefault();
					return false;
				}

				var $form = $('form.checkout');
				$form.attr('enctype', 'multipart/form-data');
				$form.removeClass('processing');
				$form.submit();
				e.preventDefault();
				return false;
			}
		});
	});
})(jQuery);
