"use strict";

jQuery(document).ready(function($) {
    $('input').on('focus', function() {
        $(this).closest('.float-label-field').addClass('float focus');
      });
      
    $('input').on('blur', function() {
    var $parent = $(this).closest('.float-label-field');
    $parent.removeClass('focus');
    if (!$(this).val()) {
        $parent.removeClass('float');
    }
    });

    let total_signed = $('.lif-form__signed').text();
    let total_target = $('.lif-form__target').text();

    let percentage_raw = LIFcalculatePercentage(total_signed, total_target);

    $('.lif-form__progressbar div').width(percentage_raw + '%');
    
    
    $('.lif-form__form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: lif_ajaxurl,
            data: {
                action: 'lif_record',
                formData: formData
            },
            success: function(response) {
                $('#lif_' + response.data.form_id)[0].reset();
                let current_count = parseInt($('#lif_' + response.data.form_id + ' .lif-form__signed').text());
                $('#lif_' + response.data.form_id + ' .lif-form__signed').text(++current_count);
            },
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
            }
        });
    });
});

function LIFcalculatePercentage(numStr, totalStr) {
    let num = parseInt(numStr);
    let total = parseInt(totalStr);

    if (isNaN(num) || isNaN(total)) {
        return "Invalid input";
    }

    let percentage = (num / total) * 100;

    if (percentage > 0 && percentage < 3) {
        return 3;
    } else if (percentage > 100) {
        return percentage = 100;
    } else {
        return Math.round(percentage);
    }
}