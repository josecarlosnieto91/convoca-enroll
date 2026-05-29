jQuery(function($) {
  // Generate poster
  $('.convoca-generate-poster').on('click', function() {
    var btn = $(this);
    var postId = btn.data('post-id');
    var template = $('#convoca-template-select').val();
    var msgBox = btn.closest('.convoca-media-metabox').find('.convoca-media-message');
    
    msgBox.removeClass('success error').addClass('loading').text('Generando cartel...');
    btn.prop('disabled', true);
    
    $.post(convocaMedia.ajax_url, {
      action: 'convoca_render_poster',
      post_id: postId,
      template: template,
      format: 'square',
      nonce: convocaMedia.nonce
    }, function(resp) {
      if (resp.success) {
        msgBox.removeClass('loading').addClass('success').html('Cartel generado! <a href="' + resp.data.url + '" download>Descargar</a> (' + Math.round(resp.data.size/1024) + 'KB)');
        // Reload preview
        location.reload();
      } else {
        msgBox.removeClass('loading').addClass('error').text('Error: ' + resp.data.message);
      }
    }).fail(function() {
      msgBox.removeClass('loading').addClass('error').text('Error de conexión.');
    }).always(function() {
      btn.prop('disabled', false);
    });
  });
  
  // Create blog post
  $('.convoca-create-blog-post').on('click', function() {
    var btn = $(this);
    var postId = btn.data('post-id');
    var msgBox = btn.closest('.convoca-media-metabox').find('.convoca-media-message');
    
    msgBox.removeClass('success error').addClass('loading').text('Creando entrada...');
    btn.prop('disabled', true);
    
    $.post(convocaMedia.ajax_url, {
      action: 'convoca_create_blog_post',
      post_id: postId,
      status: 'draft',
      nonce: convocaMedia.nonce
    }, function(resp) {
      if (resp.success) {
        msgBox.removeClass('loading').addClass('success').html('Entrada creada! <a href="' + resp.data.edit_url + '">Editar</a>');
      } else {
        msgBox.removeClass('loading').addClass('error').text('Error: ' + resp.data.message);
      }
    }).fail(function() {
      msgBox.removeClass('loading').addClass('error').text('Error de conexión.');
    }).always(function() {
      btn.prop('disabled', false);
    });
  });
});
