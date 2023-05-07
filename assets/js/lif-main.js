"use strict";

jQuery(document).ready(function ($) {
    $('input').on('focus', function () {
        $(this).closest('.float-label-field').addClass('float focus');
    });

    $('input').on('blur', function () {
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


    $('.lif-form__form').on('submit', function (e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $('.lif-form__submit').prop('disabled', true);
        $(".lif-form__submit__loader").show();
        $(".lif-form__submit__label").hide();
        $('.lif-form__msg').text('');
        $('.lif-form__msg').removeClass(['lif-form__msg__success', 'lif-form__msg__error']);

        $.ajax({
            type: 'POST',
            url: lif_ajaxurl,
            data: {
                action: 'lif_record',
                formData: formData
            },
            success: function (response) {
                if (response.success && response.data.result) {
                    $('#lif_' + response.data.form_id)[0].reset();
                    let current_count = parseInt($('#lif_' + response.data.form_id + ' .lif-form__signed').text());
                    $('#lif_' + response.data.form_id + ' .lif-form__signed').text(++current_count);

                    // reset recaptcha
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.reset();
                    }

                    $('.lif-form__msg').append('Thanks for submitting!');
                    $('.lif-form__msg').show().delay(5000).fadeOut(500);
                    $('.lif-form__msg').addClass('lif-form__msg__success');
                } else {
                    $('.lif-form__msg').append(response.data[0].message);
                    $('.lif-form__msg').show();
                    $('.lif-form__msg').addClass('lif-form__msg__error');
                }
            },
            error: function (xhr, status, error) {
                console.log(xhr.responseText);
            }
        }).done(function() {
            $(".lif-form__submit__loader").hide();
            $('.lif-form__submit').prop('disabled', false);
            $(".lif-form__submit__label").show();
        });
    });

    function rescaleCaptcha(){
        var width = $('.lif-form__recaptpcha').parent().width();
        var scale;
        if (width < 302) {
            scale = width / 302;
        } else{
            scale = 1.0; 
        }
    
        $('.lif-form__recaptpcha').css('transform', 'scale(' + scale + ')');
        $('.lif-form__recaptpcha').css('-webkit-transform', 'scale(' + scale + ')');
        $('.lif-form__recaptpcha').css('transform-origin', '0 0');
        $('.lif-form__recaptpcha').css('-webkit-transform-origin', '0 0');
    }
    
    rescaleCaptcha();
    $( window ).resize(function() { rescaleCaptcha(); });
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