jQuery(function($) {
  var editor = $('#convoca-editor-app');
  if (!editor.length) return;

  // Color pickers
  $('.convoca-color-picker').wpColorPicker();

  // Sortable layers
  $('#convoca-layer-list').sortable({
    placeholder: 'convoca-layer-placeholder',
    axis: 'y',
    handle: '.convoca-layer-item',
    cursor: 'move',
    opacity: 0.7
  });

  // Template switcher
  $('#convoca-editor-template-select').on('change', function() {
    window.location.href = 'admin.php?page=convoca-media-editor&template=' + $(this).val();
  });

  // Preview
  $('#convoca-editor-preview-btn').on('click', function() {
    var aid = $('#convoca-editor-preview-activity').val();
    var slug = editor.data('template-slug');
    $('#convoca-editor-preview-img').html('<p style="color:#999;">Generando...</p>');
    $.post(convocaEditor.ajax_url, {
      action: 'convoca_render_poster',
      post_id: aid,
      template: slug,
      format: 'square',
      nonce: convocaMedia ? convocaMedia.nonce : convocaEditor.nonce
    }, function(resp) {
      if (resp.success) {
        $('#convoca-editor-preview-img').html(
          '<img src="' + resp.data.url + '" style="width:100%;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">' +
          '<p style="font-size:11px;color:#666;margin-top:4px;">' + Math.round(resp.data.size/1024) + 'KB</p>'
        );
      } else {
        $('#convoca-editor-preview-img').html('<p style="color:red;">' + resp.data.message + '</p>');
      }
    });
  });

  // Save
  $('#convoca-editor-save-btn').on('click', function() {
    var slug = editor.data('template-slug');
    var layers = [];
    $('#convoca-layer-list .convoca-layer-item').each(function() {
      var item = $(this);
      var layer = {
        type: item.find('.layer-type').val(),
        x: parseFloat(item.find('.layer-x').val()) || 0,
        y: parseFloat(item.find('.layer-y').val()) || 0,
        w: parseFloat(item.find('.layer-w').val()) || 100,
        h: parseFloat(item.find('.layer-h').val()) || 100
      };
      var color = item.find('.layer-color').val();
      if (color) layer.color = color;
      var fs = item.find('.layer-font-size').val();
      if (fs) layer.font_size = parseInt(fs);
      layers.push(layer);
    });
    var btn = $(this);
    btn.prop('disabled', true).text('Guardando...');
    $.post(convocaEditor.ajax_url, {
      action: 'convoca_editor_save',
      slug: slug,
      layers: JSON.stringify(layers),
      nonce: convocaEditor.nonce
    }, function(resp) {
      if (resp.success) {
        alert('Plantilla guardada!');
      } else {
        alert('Error: ' + resp.data.message);
      }
    }).always(function() {
      btn.prop('disabled', false).text('💾 Guardar plantilla');
    });
  });

  // Export
  $('#convoca-editor-export-btn').on('click', function() {
    var slug = editor.data('template-slug');
    $.post(convocaEditor.ajax_url, {
      action: 'convoca_editor_export',
      slug: slug,
      nonce: convocaEditor.nonce
    }, function(resp) {
      if (resp.success) {
        var blob = new Blob([JSON.stringify(resp.data, null, 2)], {type: 'application/json'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = slug + '.json';
        a.click();
      } else {
        alert('Error: ' + resp.data.message);
      }
    });
  });

  // Import
  $('#convoca-editor-import-btn').on('click', function() {
    $('#convoca-editor-import-file').click();
  });
  $('#convoca-editor-import-file').on('change', function() {
    var file = this.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('action', 'convoca_editor_import');
    fd.append('json_file', file);
    fd.append('nonce', convocaEditor.nonce);
    $('#convoca-editor-import-status').text('Importando...');
    $.ajax({
      url: convocaEditor.ajax_url,
      type: 'POST',
      data: fd,
      processData: false,
      contentType: false,
      success: function(resp) {
        if (resp.success) {
          $('#convoca-editor-import-status').text('✅ Importada! Recargando...');
          location.reload();
        } else {
          $('#convoca-editor-import-status').text('❌ ' + resp.data.message);
        }
      },
      error: function() {
        $('#convoca-editor-import-status').text('❌ Error de conexión.');
      }
    });
  });
});
