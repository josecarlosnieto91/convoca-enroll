/**
 * Gutenberg blocks for Convoca Enroll.
 * Consolidated editor-only scripts.
 */
(function (wp) {
    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, ToggleControl, TextControl, Placeholder, Spinner, RangeControl } = wp.components;
    const { useState, useEffect } = wp.element;
    const ServerSideRender = wp.serverSideRender || null;

    // 1. Formulario de Inscripción
    registerBlockType('convoca/formulario-inscripcion', {
        apiVersion: 3,
        category: 'convoca-enroll',
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { actividadId, mostrarPlazas, mostrarPrecios } = attributes;
            const blockProps = useBlockProps();

            const [activities, setActivities] = useState([]);
            const [loading, setLoading] = useState(true);
            const [selected, setSelected] = useState(null);

            useEffect(function () {
                wp.apiFetch({ path: '/convoca-enroll/v1/actividades' })
                    .then(function (data) {
                        setActivities(data);
                        setLoading(false);
                        if (actividadId) {
                            var found = data.find(function (a) { return a.id === actividadId; });
                            setSelected(found || null);
                        }
                    })
                    .catch(function () { setLoading(false); });
            }, []);

            useEffect(function () {
                if (actividadId && activities.length) {
                    var found = activities.find(function (a) { return a.id === actividadId; });
                    setSelected(found || null);
                }
            }, [actividadId, activities]);

            return el(Fragment, null,
                el(InspectorControls, null,
                    el(PanelBody, { title: 'Opciones de inscripción' },
                        el(ToggleControl, {
                            label: 'Mostrar plazas restantes',
                            checked: mostrarPlazas,
                            onChange: function (v) { setAttributes({ mostrarPlazas: v }); }
                        }),
                        el(ToggleControl, {
                            label: 'Mostrar precios socio/general',
                            checked: mostrarPrecios,
                            onChange: function (v) { setAttributes({ mostrarPrecios: v }); }
                        })
                    )
                ),
                el('div', blockProps,
                    loading
                        ? (Placeholder ? el(Placeholder, { icon: 'clipboard', label: 'Cargando actividades…' }, Spinner ? el(Spinner) : null) : el('p', null, 'Cargando…'))
                        : actividadId && selected
                            ? el('div', { className: 'bde-block-preview' },
                                el('div', { className: 'bde-block-preview__header' },
                                    el('strong', null, '📋 Formulario de Inscripción'),
                                    el('br'),
                                    el('span', null, selected.titulo),
                                    el('br'),
                                    el('small', null,
                                        (selected.plazas_disponibles || 0) + '/' + (selected.plazas_totales || 0) + ' plazas'
                                    )
                                )
                            )
                            : (Placeholder ? el(Placeholder, {
                                icon: 'clipboard',
                                label: "Formulario de Inscripción Convoca',
                                instructions: 'Selecciona una actividad:'
                            },
                                el('select', {
                                    value: actividadId || '',
                                    onChange: function (e) {
                                        setAttributes({ actividadId: parseInt(e.target.value, 10) || 0 });
                                    },
                                    style: { width: '100%', padding: '8px' }
                                },
                                    el('option', { value: '' }, '— Seleccionar actividad —'),
                                    activities.map(function (a) {
                                        return el('option', { key: a.id, value: a.id }, a.titulo);
                                    })
                                )
                            ) : el('p', null, 'Selecciona una actividad en la barra lateral'))
                )
            );
        },
        save: function () { return null; }
    });

    // 2. Panel de Reservas
    registerBlockType('convoca-enroll/panel-reservas', {
        apiVersion: 3,
        category: 'convoca-enroll',
        edit: function () {
            const blockProps = useBlockProps();
            return el('div', { ...blockProps, className: blockProps.className + ' bde-block-preview', style: { ...blockProps.style, padding: '20px', background: '#f0fdf4', border: '2px dashed #059669', borderRadius: '8px', textAlign: 'center' } },
                el('span', { className: 'dashicons dashicons-tickets-alt', style: { fontSize: '36px', color: '#059669' } } ),
                el('p', { style: { fontWeight: 600, marginTop: '10px' } }, 'Panel de Reservas'),
                el('p', { style: { fontSize: '12px', color: '#6b7280' } }, 'Los usuarios podrán consultar y cancelar reservas con su email y código.')
            );
        },
        save: function () { return null; }
    });

    // 3. Página de Inscripciones
    registerBlockType('convoca-enroll/pagina-inscripcion', {
        apiVersion: 3,
        category: 'convoca-enroll',
        edit: function (props) {
            const blockProps = useBlockProps();
            if (!ServerSideRender) {
                return el('div', blockProps, 'Error: wp.serverSideRender no disponible.');
            }
            return el('div', blockProps, 
                el(ServerSideRender, {
                    block: 'convoca-enroll/pagina-inscripcion',
                    attributes: props.attributes
                })
            );
        },
        save: function () { return null; }
    });

    // 4. Formulario de Evaluación
    registerBlockType('convoca-enroll/evaluacion', {
        apiVersion: 3,
        category: 'convoca-enroll',
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();

            return el('div', blockProps,
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Ajustes de Evaluación' },
                        el(TextControl, {
                            label: 'ID de la Actividad',
                            value: attributes.actividadId || '',
                            onChange: function (val) {
                                setAttributes({ actividadId: parseInt(val, 10) || 0 });
                            },
                            type: 'number'
                        })
                    )
                ),
                el('div', { style: { padding: '20px', border: '1px dashed #ccc', background: '#f9f9f9' } },
                    el('h3', {}, 'Formulario de Evaluación (Convoca)'),
                    attributes.actividadId ?
                        el('p', {},
                            'Mostrando evaluación para la actividad ID: ',
                            el('strong', {}, attributes.actividadId)
                        ) :
                        el('p', { style: { color: 'red' } }, 'Configura el ID de la actividad en el panel lateral.'),
                    el('p', {}, el('em', {}, 'Este bloque se renderizará dinámicamente en el frontend.'))
                )
            );
        },
        save: function () { return null; }
    });

    // 5. Lista de Espera
    registerBlockType('convoca-enroll/lista-espera', {
        apiVersion: 3,
        category: 'convoca-enroll',
        title: 'Lista de espera de actividad',
        icon: 'groups',
        attributes: {
            actividadId: { type: 'number', default: 0 }
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();
            const [activities, setActivities] = useState([]);
            const [loading, setLoading] = useState(true);

            useEffect(function () {
                wp.apiFetch({ path: '/convoca-enroll/v1/actividades' })
                    .then(function (data) { setActivities(data); setLoading(false); })
                    .catch(function () { setLoading(false); });
            }, []);

            return el(Fragment, null,
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Actividad' },
                        el('select', {
                            value: attributes.actividadId || '',
                            onChange: function (e) { setAttributes({ actividadId: parseInt(e.target.value, 10) || 0 }); },
                            style: { width: '100%', padding: '8px' }
                        },
                            el('option', { value: '' }, '— Seleccionar actividad —'),
                            activities.map(function (a) {
                                return el('option', { key: a.id, value: a.id }, a.titulo);
                            })
                        )
                    )
                ),
                el('div', blockProps,
                    attributes.actividadId ?
                        el('div', { style: { padding: '20px', background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: '8px', textAlign: 'center' } },
                            el('p', { style: { fontSize: '24px', fontWeight: 800, margin: 0 } }, '📋'),
                            el('p', { style: { fontWeight: 600 } }, 'Lista de espera - Actividad #' + attributes.actividadId)
                        ) :
                        el('div', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } },
                            el('p', null, 'Selecciona una actividad en la barra lateral.')
                        )
                )
            );
        },
        save: function () { return null; }
    });

})(window.wp);
