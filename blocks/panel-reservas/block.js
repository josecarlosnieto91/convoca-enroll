( function( blocks, element, serverSideRender ) {
    var el = element.createElement;

    blocks.registerBlockType( 'biodevas-enroll/panel-reservas', {
        edit: function() {
            return el( 'div', { className: 'bde-block-preview', style: { padding: '20px', background: '#f0fdf4', border: '2px dashed #059669', borderRadius: '8px', textAlign: 'center' } },
                el( 'span', { className: 'dashicons dashicons-tickets-alt', style: { fontSize: '36px', color: '#059669' } } ),
                el( 'p', { style: { fontWeight: 600, marginTop: '10px' } }, 'Panel de Reservas' ),
                el( 'p', { style: { fontSize: '12px', color: '#6b7280' } }, 'Los usuarios podrán consultar y cancelar reservas con su email y código.' )
            );
        },
        save: function() { return null; }
    } );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
