/* global jQuery, wp, convocaMedia */
jQuery(function($) {
  let posterFrame = null;

  function getBox(el) {
    return $(el).closest('.convoca-media-metabox');
  }

  $('.convoca-select-poster-image').on('click', function(e) {
    e.preventDefault();
    const box = getBox(this);

    posterFrame = wp.media({
      title: 'Seleccionar imagen para el cartel',
      button: { text: 'Usar esta imagen' },
      library: { type: 'image' },
      multiple: false
    });

    posterFrame.on('select', function() {
      const attachment = posterFrame.state().get('selection').first().toJSON();
      const thumb = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
      box.find('#convoca-poster-image-id').val(attachment.id);
      box.find('.convoca-poster-image-preview')
        .removeClass('is-empty')
        .html('<img src="' + thumb + '" alt="Imagen seleccionada">');
      box.find('.convoca-clear-poster-image').prop('disabled', false);
    });

    posterFrame.open();
  });

  $('.convoca-clear-poster-image').on('click', function(e) {
    e.preventDefault();
    const box = getBox(this);
    box.find('#convoca-poster-image-id').val('0');
    box.find('.convoca-poster-image-preview')
      .addClass('is-empty')
      .html('<span>Usará imagen destacada o fondo automático.</span>');
    $(this).prop('disabled', true);
  });

  $('.convoca-generate-poster').on('click', function() {
    const btn = $(this);
    const box = getBox(btn);
    const postId = btn.data('post-id');
    const template = box.find('#convoca-template-select').val();
    const format = box.find('#convoca-format-select').val() || 'square';
    const imageId = box.find('#convoca-poster-image-id').val() || 0;
    const msgBox = box.find('.convoca-media-message');

    msgBox.removeClass('success error').addClass('loading').text('Generando cartel...');
    btn.prop('disabled', true);

    $.post(convocaMedia.ajax_url, {
      action: 'convoca_render_poster',
      post_id: postId,
      template: template,
      format: format,
      image_id: imageId,
      nonce: convocaMedia.nonce
    }, function(resp) {
      if (resp.success) {
        const url = resp.data.url + (resp.data.url.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();
        box.find('> img').first().attr('src', url);
        msgBox.removeClass('loading').addClass('success').html('Cartel generado. <a href="' + resp.data.url + '" download>Descargar</a> (' + Math.round(resp.data.size / 1024) + 'KB)');
      } else {
        msgBox.removeClass('loading').addClass('error').text('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'desconocido'));
      }
    }).fail(function() {
      msgBox.removeClass('loading').addClass('error').text('Error de conexión.');
    }).always(function() {
      btn.prop('disabled', false);
    });
  });

  $('.convoca-create-blog-post').on('click', function() {
    const btn = $(this);
    const postId = btn.data('post-id');
    const msgBox = getBox(btn).find('.convoca-media-message');

    msgBox.removeClass('success error').addClass('loading').text('Creando entrada...');
    btn.prop('disabled', true);

    $.post(convocaMedia.ajax_url, {
      action: 'convoca_create_blog_post',
      post_id: postId,
      status: 'draft',
      nonce: convocaMedia.nonce
    }, function(resp) {
      if (resp.success) {
        msgBox.removeClass('loading').addClass('success').html('Entrada creada. <a href="' + resp.data.edit_url + '">Editar</a>');
      } else {
        msgBox.removeClass('loading').addClass('error').text('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'desconocido'));
      }
    }).fail(function() {
      msgBox.removeClass('loading').addClass('error').text('Error de conexión.');
    }).always(function() {
      btn.prop('disabled', false);
    });
  });
});
