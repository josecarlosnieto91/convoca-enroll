( function( blocks, element, blockEditor, components, i18n, serverSideRender ) {
    var el = element.createElement;
    var __ = i18n.__;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;

    blocks.registerBlockType( 'biodevas-enroll/evaluacion', {
        apiVersion: 3,
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps = useBlockProps();

            return el( 'div', blockProps,
                el( InspectorControls, {},
                    el( PanelBody, { title: __( 'Ajustes de Evaluación', 'biodevas-enroll' ) },
                        el( TextControl, {
                            label: __( 'ID de la Actividad', 'biodevas-enroll' ),
                            value: attributes.actividadId || '',
                            onChange: function( val ) {
                                setAttributes( { actividadId: parseInt( val, 10 ) || 0 } );
                            },
                            type: 'number'
                        } )
                    )
                ),
                el( 'div', { style: { padding: '20px', border: '1px dashed #ccc', background: '#f9f9f9' } },
                    el( 'h3', {}, __( 'Formulario de Evaluación (Biodevas)', 'biodevas-enroll' ) ),
                    attributes.actividadId ? 
                        el( 'p', {}, 
                            __( 'Mostrando evaluación para la actividad ID:', 'biodevas-enroll' ),
                            ' ',
                            el( 'strong', {}, attributes.actividadId )
                        ) :
                        el( 'p', { style: { color: 'red' } }, __( 'Por favor, configura el ID de la actividad en el panel lateral.', 'biodevas-enroll' ) ),
                    el( 'p', {}, el( 'em', {}, __( 'Este bloque se renderizará dinámicamente en el frontend según los permisos del usuario.', 'biodevas-enroll' ) ) )
                )
            );
        },
        save: function() {
            return null; // Dynamic block
        }
    } );
} )( 
    window.wp.blocks, 
    window.wp.element, 
    window.wp.blockEditor, 
    window.wp.components, 
    window.wp.i18n,
    window.wp.serverSideRender
);
