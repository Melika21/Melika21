/**
 * Card-to-Card Checkout — Receipt Upload
 *
 * Handles file preview and client-side validation for the receipt image field.
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    var $input = $("#c2c_receipt_image");
    var $preview = $("#woo-c2c-preview");

    if (!$input.length) return;

    $input.on("change", function () {
      $preview.empty();

      var file = this.files[0];
      if (!file) return;

      // Validate file type.
      var allowedTypes = ["image/jpeg", "image/png", "image/webp"];
      if (allowedTypes.indexOf(file.type) === -1) {
        alert("فرمت فایل مجاز نیست. لطفاً فقط تصویر JPG، PNG یا WebP انتخاب کنید.");
        $input.val("");
        return;
      }

      // Validate file size (5 MB).
      if (file.size > 5 * 1024 * 1024) {
        alert("حجم فایل نباید بیشتر از ۵ مگابایت باشد.");
        $input.val("");
        return;
      }

      // Show preview.
      var reader = new FileReader();
      reader.onload = function (e) {
        $preview.html(
          '<img src="' +
            e.target.result +
            '" alt="پیش‌نمایش رسید" class="woo-c2c-preview-img" />' +
            '<p class="woo-c2c-file-name">' +
            file.name +
            "</p>"
        );
      };
      reader.readAsDataURL(file);
    });
  });
})(jQuery);
