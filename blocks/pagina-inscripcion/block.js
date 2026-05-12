( function( blocks, element, serverSideRender ) {
    var el = element.createElement;

    blocks.registerBlockType( 'biodevas-enroll/pagina-inscripcion', {
        edit: function() {
            return el( serverSideRender, {
                block: 'biodevas-enroll/pagina-inscripcion',
                attributes: {}
            } );
        },
        save: function() { return null; }
    } );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
