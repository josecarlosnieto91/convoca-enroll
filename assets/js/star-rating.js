jQuery(document).ready(function($) {
    // Star rating interaction
    $('.star-rating .star').on('mouseover', function() {
        var onStar = parseInt($(this).data('val'), 10);
        $(this).parent().children('.star').each(function(e) {
            if (e < onStar) {
                $(this).addClass('hover');
                $(this).text('★');
            } else {
                $(this).removeClass('hover');
                $(this).text('☆');
            }
        });
    }).on('mouseout', function() {
        $(this).parent().children('.star').each(function(e) {
            $(this).removeClass('hover');
            var rating = $(this).parent().find('input[type="hidden"]').val();
            if (e < rating) {
                $(this).text('★');
                $(this).addClass('selected');
            } else {
                $(this).text('☆');
                $(this).removeClass('selected');
            }
        });
    });

    $('.star-rating .star').on('click', function() {
        var onStar = parseInt($(this).data('val'), 10);
        var input = $(this).parent().find('input[type="hidden"]');
        input.val(onStar);

        $(this).parent().children('.star').each(function(e) {
            if (e < onStar) {
                $(this).text('★');
                $(this).addClass('selected');
            } else {
                $(this).text('☆');
                $(this).removeClass('selected');
            }
        });
    });

    // Keyboard accessibility for stars
    $('.star-rating .star').on('keypress', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // Form submission
    $('#bdv-evaluacion-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var responseDiv = $('#bdv-evaluacion-response');
        
        // Check if all star ratings have a value > 0
        var allRated = true;
        form.find('input[type="hidden"]').each(function() {
            if ($(this).attr('name') && ['gestion', 'instalaciones', 'participantes', 'comunicacion'].indexOf($(this).attr('name')) !== -1) {
                if (parseInt($(this).val()) === 0) {
                    allRated = false;
                }
            }
        });

        if (!allRated) {
            responseDiv.html('<div class="bdv-eval-error" style="color:red; margin-top:10px;">Por favor, valora todas las categorías numéricas con al menos 1 estrella.</div>');
            return;
        }

        submitBtn.prop('disabled', true).text('Enviando...');
        
        // Fetch fresh nonce before submitting
        $.ajax({
            url: bdv_eval_ajax.url,
            type: 'POST',
            data: { action: 'bdv_eval_get_nonce' },
            success: function(nonceResponse) {
                if (nonceResponse.success) {
                    // Update nonce in form
                    form.find('input[name="security"]').val(nonceResponse.data);
                    
                    var formData = form.serialize();
                    
                    $.ajax({
                        url: bdv_eval_ajax.url,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                form.slideUp();
                                responseDiv.html('<div class="bdv-eval-success" style="color:green; font-weight:bold; margin-top:20px; padding:15px; border:1px solid green; border-radius:5px;">' + response.data + '</div>');
                            } else {
                                responseDiv.html('<div class="bdv-eval-error" style="color:red; margin-top:10px;">' + response.data + '</div>');
                                submitBtn.prop('disabled', false).text('Enviar Evaluación');
                            }
                        },
                        error: function() {
                            responseDiv.html('<div class="bdv-eval-error" style="color:red; margin-top:10px;">Error de conexión. Inténtalo de nuevo.</div>');
                            submitBtn.prop('disabled', false).text('Enviar Evaluación');
                        }
                    });
                } else {
                    responseDiv.html('<div class="bdv-eval-error" style="color:red; margin-top:10px;">Error de seguridad al refrescar sesión.</div>');
                    submitBtn.prop('disabled', false).text('Enviar Evaluación');
                }
            },
            error: function() {
                responseDiv.html('<div class="bdv-eval-error" style="color:red; margin-top:10px;">Error al conectar con el servidor.</div>');
                submitBtn.prop('disabled', false).text('Enviar Evaluación');
            }
        });
    });
});
