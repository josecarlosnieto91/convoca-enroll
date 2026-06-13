/**
 * Gutenberg block: Formulario de Inscripción Convoca
 * Editor-only — server-side rendered.
 */
(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components || !wp.i18n) {
        return;
    }
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, ToggleControl, TextControl, Placeholder, Spinner } = wp.components;
    const { useState, useEffect } = wp.element;
    const { apiFetch } = wp;

    registerBlockType('convoca-enroll/formulario-inscripcion', {
        apiVersion: 3,
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { actividadId, mostrarPlazas, mostrarPrecios } = attributes;
            const blockProps = useBlockProps();

            const [activities, setActivities] = useState([]);
            const [loading, setLoading] = useState(true);
            const [selected, setSelected] = useState(null);

            // Fetch activities.
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

            // Update selected when ID changes.
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
                        ? el(Placeholder, { icon: 'clipboard', label: 'Cargando actividades…' }, el(Spinner))
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
                            : el(Placeholder, {
                                icon: 'clipboard',
                                label: 'Formulario de Inscripción Convoca',
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
                            )
                )
            );
        },

        save: function () {
            return null; // Server-side rendered.
        }
    });
})(window.wp);
