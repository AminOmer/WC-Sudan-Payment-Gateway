jQuery(document).ready(function ($) {
    // input receipt and change the preview image
    $(document).on('change', "#bank_payment_receipt", function () {
        var fd = new FormData();
        fd.append('file', $('#bank_payment_receipt')[0].files[0]);
        fd.append('action', 'invoice_response');
        $('.payment-receipt-btn').addClass('loading');
        $('.receipt-preview').addClass('loading');

        // upload receipt and getting attachment id
        $.ajax({
            type: 'POST',
            url: the_ajax_script.ajaxurl,
            data: fd,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response == '0') {
                    alert('Invalid File, please upload correct file');
                    $('.attach_id').val('');
                    document.getElementById('receiptPreview').src = '';
                } else {
                    $('.receipt-preview').removeClass('loading');
                    $('.payment-receipt-btn').removeClass('loading');
                    $('.attach_id').val(response);
                }
            }
        });
    });

    // upload receipt on click the preview image

    $(document).on('click', '#receiptPreview', function () {
        $('#bank_payment_receipt').trigger('click');
    });

    // copy account number on click

    $(document).on('click', '.account-number', function () {
        var copyText = $(this).find(".copy").text();
        var tempInput = document.createElement("input");
        tempInput.value = copyText;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);

        var this_ = $(this);
        this_.find('.copied').fadeIn();
        setTimeout(() => {
            this_.find('.copied').fadeOut();
        }, 500);
    });
});