function esc(v) {
    if (v === null || v === undefined) return '';
    return String(v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function mostrarBannerClavesFabrica(info) {
    if (document.getElementById('banner-seguridad-claves')) return;
    const banner = document.createElement('div');
    banner.id = 'banner-seguridad-claves';
    banner.style.cssText = 'background: #fffbeb; border-bottom: 2px solid #f59e0b; color: #92400e; padding: 10px 20px; font-size: 0.875rem; display: flex; align-items: center; justify-content: space-between; gap: 12px; z-index: 9999; box-shadow: 0 1px 3px rgba(0,0,0,0.05);';
    
    let texto = '⚠️ <strong>Atención de seguridad:</strong> El sistema tiene activas contraseñas predeterminadas de fábrica (';
    const partes = [];
    if (info.admin) partes.push('Acceso general "ana2"');
    if (info.config) partes.push('Configuraciones "ana3"');
    texto += (partes.length > 0 ? partes.join(' y ') : 'claves por defecto') + '). ';
    texto += 'Por favor cámbialas en el panel de <a href="/public/html/access_config.html" style="color: #b45309; font-weight: bold; text-decoration: underline;">Configuraciones</a> para proteger tus datos.';

    banner.innerHTML = `
        <div style="display:flex; align-items:center; gap:8px;">
            <span>${texto}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" style="background:transparent; border:none; color:#92400e; font-size:1.1rem; cursor:pointer; padding:0 4px;" title="Cerrar aviso">&times;</button>
    `;

    document.body.insertBefore(banner, document.body.firstChild);
}

async function verificarSesion(redireccionar = true) {
    if (window.location.pathname.includes('access.html')) {
        return;
    }

    try {
        const res = await fetch('/backend/auth_check.php');
        const data = await res.json();
        if (!data.autenticado && redireccionar) {
            window.location.href = '/public/html/access.html';
            return;
        }

        if (data.claves_fabrica && data.claves_fabrica.alerta) {
            mostrarBannerClavesFabrica(data.claves_fabrica);
        }
    } catch (err) {
        console.warn(err);
    }
}

async function cerrarSesion() {
    if (!confirm('¿Deseas cerrar tu sesión actual?')) return;
    try {
        await fetch('/backend/logout.php');
    } catch (e) { }
    window.location.href = '/public/html/access.html';
}

function inicializarNavegacion() {
    const navContenedor = document.getElementById('links');
    if (!navContenedor) return;

    const ruta = decodeURIComponent(window.location.pathname).toLowerCase();

    const esRegistro = ruta.includes('index.html') || ruta.endsWith('/html/') || ruta.endsWith('/public/') || ruta === '/' || ruta === '';
    const esEnEvaluacion = ruta.includes('en evaluacion.html') || ruta.includes('en_evaluacion.html');
    const esFinalizados = (ruta.includes('finalizados.html') || ruta.includes('finalizados')) &&
                          !ruta.includes('no finalizados.html') && !ruta.includes('no_finalizados.html');
    const esNoFinalizados = ruta.includes('no finalizados.html') || ruta.includes('no_finalizados.html');
    const esConfig = ruta.includes('configuracion') || ruta.includes('access_config');

    const linksExistentes = navContenedor.querySelectorAll('.nav-link');
    if (linksExistentes.length > 0) {
        linksExistentes.forEach(enlace => {
            if (enlace.classList.contains('btn-logout')) return;
            const href = enlace.getAttribute('href') ? decodeURIComponent(enlace.getAttribute('href')).toLowerCase() : '';

            let activo = false;
            if (esRegistro && href.includes('index.html')) activo = true;
            else if (esEnEvaluacion && (href.includes('en evaluacion.html') || href.includes('en_evaluacion.html'))) activo = true;
            else if (esFinalizados && href.includes('finalizados.html') && !href.includes('no finalizados.html') && !href.includes('no_finalizados.html')) activo = true;
            else if (esNoFinalizados && (href.includes('no finalizados.html') || href.includes('no_finalizados.html'))) activo = true;
            else if (esConfig && (href.includes('access_config.html') || href.includes('configuracion'))) activo = true;

            enlace.classList.toggle('active', activo);
        });
    } else {
        navContenedor.className = 'links';
        navContenedor.innerHTML = `
            <a class="nav-link ${esRegistro ? 'active' : ''}" href="/public/html/index.html">
                <span>📝</span> Registro
            </a>
            <a class="nav-link ${esEnEvaluacion ? 'active' : ''}" href="/public/html/en evaluacion.html">
                <span>⏳</span> En Evaluación
            </a>
            <a class="nav-link ${esFinalizados ? 'active' : ''}" href="/public/html/Finalizados.html">
                <span>✅</span> Finalizados
            </a>
            <a class="nav-link ${esNoFinalizados ? 'active' : ''}" href="/public/html/No Finalizados.html">
                <span>⛔</span> No Finalizados
            </a>
            <a class="nav-link btn-logout" id="navBtnLogout" href="javascript:void(0)">
                <span>🚪</span> Cerrar Sesión
            </a>
            <a class="nav-link nav-link-icon ${esConfig ? 'active' : ''}" id="navBtnConfig" href="/public/html/access_config.html" title="Configuraciones del Sistema" aria-label="Configuraciones del Sistema">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </a>
        `;
    }

    const btnLogout = navContenedor.querySelector('#navBtnLogout');
    if (btnLogout && !btnLogout.dataset.bound) {
        btnLogout.dataset.bound = 'true';
        btnLogout.addEventListener('click', cerrarSesion);
    }
}

function crearElementoEmpleado(persona, tipoVista = 'en_evaluacion', onActualizar = null) {
    const div = document.createElement('div');
    div.className = 'empleado';

    let badgeHtml = '';
    if (tipoVista === 'en_evaluacion') {
        const prox = persona.proxima_evaluacion;
        if (prox) {
            if (prox.vencida) {
                div.classList.add('alerta-vencido');
                badgeHtml = `<span class="badge badge-danger" title="Evaluación de ${esc(prox.etiqueta)} esperada el ${esc(prox.fecha_esperada)} (vencida hace ${prox.dias_vencida} Dias)">⚠️ Eval ${esc(prox.etiqueta)} vencida (${prox.dias_vencida} Dias)</span>`;
            } else if (prox.es_hoy) {
                div.classList.add('alerta-vencimiento');
                badgeHtml = `<span class="badge badge-warning" title="Evaluación de ${esc(prox.etiqueta)} programada para HOY (${esc(prox.fecha_esperada)})">🔔 Eval ${esc(prox.etiqueta)} es HOY</span>`;
            } else if (prox.urgente) {
                div.classList.add('alerta-vencimiento');
                badgeHtml = `<span class="badge badge-warning" title="Evaluación de ${esc(prox.etiqueta)} esperada el ${esc(prox.fecha_esperada)}">⏳ Eval ${esc(prox.etiqueta)} en ${prox.dias_restantes} Dias</span>`;
            } else if (prox.dias_restantes <= 15) {
                badgeHtml = `<span class="badge badge-info" title="Evaluación de ${esc(prox.etiqueta)} esperada el ${esc(prox.fecha_esperada)}">📅 Eval ${esc(prox.etiqueta)} en ${prox.dias_restantes} Dias</span>`;
            } else {
                badgeHtml = `<span class="badge badge-info" title="Evaluación de ${esc(prox.etiqueta)} esperada el ${esc(prox.fecha_esperada)}">Eval ${esc(prox.etiqueta)} (${prox.dias_restantes} Dias)</span>`;
            }
        } else if (persona.vencido) {
            div.classList.add('alerta-vencido');
            badgeHtml = `<span class="badge badge-danger">⚠️ Vencido (${Math.abs(persona.dias_restantes)} Dias)</span>`;
        } else if (persona.proximo_a_vencer) {
            div.classList.add('alerta-vencimiento');
            badgeHtml = `<span class="badge badge-warning">⏳ Vence en ${persona.dias_restantes} Dias</span>`;
        } else if (persona.dias_restantes !== null) {
            badgeHtml = `<span class="badge badge-info">${persona.dias_restantes} Dias restantes</span>`;
        } else {
            badgeHtml = `<span class="badge badge-success">✓ Evaluaciones listas</span>`;
        }
    } else if (tipoVista === 'finalizado') {
        const califTexto = persona.promedio_calificaciones !== null
            ? `Promedio: ${persona.promedio_calificaciones}`
            : 'Finalizado';
        badgeHtml = `<span class="badge badge-success">✓ ${esc(califTexto)}</span>`;
    } else if (tipoVista === 'interrumpido') {
        badgeHtml = `<span class="badge badge-danger">✕ Interrumpido</span>`;
    }

    const resumen = document.createElement('div');
    resumen.className = 'empleado-resumen';
    resumen.innerHTML = `
        <span class="col-codigo"><code>${esc(persona.codigo_empleado || '—')}</code></span>
        <span class="col-nombre"><strong>${esc(persona.nombre)}</strong></span>
        <span class="col-apellido">${esc(persona.apellido)}</span>
        <span class="col-puesto" title="${esc(persona.puesto || '')}">${esc(persona.puesto || '—')}</span>
        <span class="col-supervisor" title="${esc(persona.supervisor || 'No especificado')}">${esc(persona.supervisor || '—')}</span>
        <div class="col-badge">${badgeHtml}</div>
        <span class="flecha">&#9660;</span>
    `;

    const detalle = document.createElement('div');
    detalle.className = 'empleado-detalle';

    let detalleHtml = `
        <div class="detalle-grid">
            ${tipoVista === 'en_evaluacion' && persona.proxima_evaluacion ? `
            <div class="detalle-item" style="grid-column: 1 / -1; background: var(--bg-surface-subtle); padding: 10px 14px; border-radius: var(--radius-sm); border-left: 4px solid ${persona.proxima_evaluacion.vencida ? 'var(--danger)' : (persona.proxima_evaluacion.urgente || persona.proxima_evaluacion.es_hoy ? 'var(--warning)' : 'var(--primary)')};">
                <strong>🎯 Próxima Evaluación Prioritaria:</strong>
                Evaluación de ${esc(persona.proxima_evaluacion.etiqueta)} — Fecha programada: <code>${esc(persona.proxima_evaluacion.fecha_esperada)}</code>
                (${persona.proxima_evaluacion.vencida ? `<span style="color:var(--danger); font-weight:700;">⚠️ Vencida hace ${persona.proxima_evaluacion.dias_vencida} Dias</span>` : (persona.proxima_evaluacion.es_hoy ? '<span style="color:#b45309; font-weight:700;">🔔 ¡Programada para el día de hoy!</span>' : `Faltan ${persona.proxima_evaluacion.dias_restantes} Dias`)})
            </div>
            ` : ''}
            <div class="detalle-item">
                <strong>Supervisor / Jefe Inmediato</strong>
                ${esc(persona.supervisor || 'No especificado')}
            </div>
            <div class="detalle-item">
                <strong>Demografía / Ubicación</strong>
                ${esc(persona.demografia || 'No especificada')}
            </div>
            <div class="detalle-item">
                <strong>Nivel Académico</strong>
                ${esc(persona.nivel_academico || 'No especificado')}
            </div>
            <div class="detalle-item">
                <strong>Edad</strong>
                ${esc(persona.edad ? persona.edad + ' años' : '—')}
            </div>
            <div class="detalle-item">
                <strong>Fecha de Entrada</strong>
                ${esc(persona.fecha_entrada || '—')}
            </div>
            <div class="detalle-item">
                <strong>Término de Contrato</strong>
                ${esc(persona.termino_contrato || '—')}
            </div>
            <div class="detalle-item" style="grid-column: 1 / -1;">
                <strong>Observaciones Registradas</strong>
                ${esc(persona.observaciones || 'Sin observaciones registradas.')}
            </div>
        </div>
    `;

    detalleHtml += `
        <div class="evaluaciones-section">
            <div class="evaluaciones-titulo">
                <span>📋 Evaluaciones del Período de Prueba</span>
                ${persona.promedio_calificaciones !== null
                    ? `<span class="badge badge-info">Promedio Actual: ${persona.promedio_calificaciones} / 100</span>`
                    : ''}
            </div>
            <div style="overflow-x: auto;">
                <table class="tabla-evaluaciones">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Evaluación</th>
                            <th style="width: 140px;">Fecha Esperada</th>
                            <th style="width: 160px;">Fecha Realizada</th>
                            <th style="width: 120px;">Calificación (0-100)</th>
                            <th>Observaciones</th>
                            <th style="width: 100px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
    `;

    const evals = persona.evaluaciones || [];
    for (let i = 1; i <= 3; i++) {
        const ev = evals.find(e => parseInt(e.numero_evaluacion, 10) === i) || {
            numero_evaluacion: i,
            fecha_esperada: '—',
            fecha_realizada: '',
            calificacion: '',
            observaciones: ''
        };

        const etiquetaEval = i === 1 ? '1ra (+30 días)' : (i === 2 ? '2da (+60 días)' : '3ra (Término)');

        detalleHtml += `
            <tr data-num-eval="${i}">
                <td><strong>${etiquetaEval}</strong></td>
                <td>${esc(ev.fecha_esperada)}</td>
                <td>
                    <input type="date" class="eval-input-date" id="eval_fecha_${persona.id}_${i}" value="${esc(ev.fecha_realizada || '')}">
                </td>
                <td>
                    <input type="number" min="0" max="100" step="0.5" class="eval-input-calif" id="eval_calif_${persona.id}_${i}" placeholder="0-100" value="${esc(ev.calificacion !== null ? ev.calificacion : '')}">
                </td>
                <td>
                    <input type="text" class="eval-input-obs" id="eval_obs_${persona.id}_${i}" placeholder="Comentarios sobre desempeño..." value="${esc(ev.observaciones || '')}">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn btn-primary btn-sm btn-guardar-eval" data-empleado-id="${persona.id}" data-num-eval="${i}">
                        Guardar
                    </button>
                </td>
            </tr>
        `;
    }

    detalleHtml += `
                    </tbody>
                </table>
            </div>
        </div>
    `;

    const nombreCompletoAttr = esc(`${persona.nombre} ${persona.apellido}`);

    detalleHtml += `<div class="acciones-estado-bar">`;
    detalleHtml += `<span class="acciones-label">Gestión de Estado:</span>`;

    if (tipoVista === 'en_evaluacion') {
        detalleHtml += `
            <button type="button" class="btn btn-success btn-sm btn-cambiar-estado"
                data-empleado-id="${persona.id}"
                data-nuevo-estado="finalizado"
                data-nombre="${nombreCompletoAttr}">
                ✓ Aprobar y Finalizar
            </button>
            <button type="button" class="btn btn-danger btn-sm btn-cambiar-estado"
                data-empleado-id="${persona.id}"
                data-nuevo-estado="interrumpido"
                data-nombre="${nombreCompletoAttr}">
                ✕ Interrumpir / No Finalizado
            </button>
        `;
    } else {
        detalleHtml += `
            <button type="button" class="btn btn-primary btn-sm btn-cambiar-estado"
                data-empleado-id="${persona.id}"
                data-nuevo-estado="en_evaluacion"
                data-nombre="${nombreCompletoAttr}">
                ↺ Reabrir a 'En Evaluación'
            </button>
        `;
    }

    detalleHtml += `</div>`;

    detalle.innerHTML = detalleHtml;

    detalle.querySelectorAll('.btn-guardar-eval').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const empId = parseInt(this.dataset.empleadoId, 10);
            const numEval = parseInt(this.dataset.numEval, 10);
            guardarEvaluacionFila(empId, numEval, this);
        });
    });

    detalle.querySelectorAll('.btn-cambiar-estado').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const empId = parseInt(this.dataset.empleadoId, 10);
            const nuevoEstado = this.dataset.nuevoEstado;
            const nombreCompleto = this.dataset.nombre;
            solicitarCambioEstado(empId, nuevoEstado, nombreCompleto);
        });
    });

    resumen.addEventListener('click', function () {
        div.classList.toggle('abierto');
    });

    div.appendChild(resumen);
    div.appendChild(detalle);

    return div;
}

async function guardarEvaluacionFila(empleadoId, numeroEvaluacion, btnElement) {
    const fechaInput = document.getElementById(`eval_fecha_${empleadoId}_${numeroEvaluacion}`);
    const califInput = document.getElementById(`eval_calif_${empleadoId}_${numeroEvaluacion}`);
    const obsInput   = document.getElementById(`eval_obs_${empleadoId}_${numeroEvaluacion}`);

    const formData = new FormData();
    formData.append('empleado_id', empleadoId);
    formData.append('numero_evaluacion', numeroEvaluacion);
    formData.append('fecha_realizada', fechaInput ? fechaInput.value : '');
    formData.append('calificacion', califInput ? califInput.value : '');
    formData.append('observaciones', obsInput ? obsInput.value : '');

    const textoOriginal = btnElement.textContent;
    btnElement.disabled = true;
    btnElement.textContent = 'Guardando...';

    try {
        const resp = await fetch('/backend/guardar_evaluacion.php', {
            method: 'POST',
            body: formData
        });

        if (resp.status === 401) {
            alert('Sesión no autorizada o expirada. Redirigiendo al inicio de sesión...');
            window.location.href = '/public/html/access.html';
            return;
        }

        const data = await resp.json();

        if (data.exito) {
            btnElement.textContent = '✓ Guardado';
            btnElement.style.backgroundColor = 'var(--success)';
            setTimeout(() => {
                btnElement.textContent = textoOriginal;
                btnElement.style.backgroundColor = '';
                btnElement.disabled = false;
            }, 1800);
        } else {
            alert('Error: ' + data.mensaje);
            btnElement.textContent = textoOriginal;
            btnElement.disabled = false;
        }
    } catch (err) {
        alert('Error de conexión al guardar evaluación.');
        btnElement.textContent = textoOriginal;
        btnElement.disabled = false;
    }
}

async function solicitarCambioEstado(empleadoId, nuevoEstado, nombreCompleto) {
    let motivo = '';
    const etiquetas = {
        'en_evaluacion': 'En Evaluación',
        'finalizado': 'Finalizado con Éxito',
        'interrumpido': 'No Finalizado / Interrumpido'
    };

    if (nuevoEstado === 'interrumpido') {
        motivo = prompt(`Ingresa el motivo por el cual el empleado ${nombreCompleto} no finalizó o interrumpió el proceso:`, '');
        if (motivo === null) return;
    } else {
        if (!confirm(`¿Confirmas cambiar el estado de ${nombreCompleto} a '${etiquetas[nuevoEstado]}'?`)) {
            return;
        }
    }

    const formData = new FormData();
    formData.append('empleado_id', empleadoId);
    formData.append('nuevo_estado', nuevoEstado);
    if (motivo) {
        formData.append('motivo', motivo);
    }

    try {
        const res = await fetch('/backend/cambiar_estado.php', {
            method: 'POST',
            body: formData
        });

        if (res.status === 401) {
            alert('Sesión no autorizada o expirada. Redirigiendo al inicio de sesión...');
            window.location.href = '/public/html/access.html';
            return;
        }

        const data = await res.json();

        if (data.exito) {
            alert(data.mensaje);
            if (typeof window.recargarListaActual === 'function') {
                window.recargarListaActual();
            } else {
                window.location.reload();
            }
        } else {
            alert('Error: ' + data.mensaje);
        }
    } catch (err) {
        alert('Error de conexión con el servidor al cambiar estado.');
    }
}

function renderizarPaginacion(contenedorId, paginacion, onCambiarPagina) {
    const contenedor = document.getElementById(contenedorId);
    if (!contenedor) return;

    if (!paginacion || paginacion.total_paginas <= 1) {
        contenedor.innerHTML = paginacion && paginacion.total > 0
            ? `<div class="paginacion-contenedor"><span>Total: <strong>${paginacion.total}</strong> empleados registrados.</span></div>`
            : '';
        return;
    }

    const { pagina, total_paginas, total } = paginacion;

    let html = `
        <div class="paginacion-contenedor">
            <span>Página <strong>${pagina}</strong> de <strong>${total_paginas}</strong> (Total: ${total} empleados)</span>
            <div class="paginacion-botones">
                <button type="button" class="pag-btn" data-pagina="${pagina - 1}" ${pagina <= 1 ? 'disabled' : ''}>
                    &laquo; Anterior
                </button>
    `;

    for (let p = 1; p <= total_paginas; p++) {
        if (p === 1 || p === total_paginas || (p >= pagina - 2 && p <= pagina + 2)) {
            html += `
                <button type="button" class="pag-btn ${p === pagina ? 'activo' : ''}" data-pagina="${p}">
                    ${p}
                </button>
            `;
        } else if (p === pagina - 3 || p === pagina + 3) {
            html += `<span style="padding: 0 4px;">...</span>`;
        }
    }

    html += `
                <button type="button" class="pag-btn" data-pagina="${pagina + 1}" ${pagina >= total_paginas ? 'disabled' : ''}>
                    Siguiente &raquo;
                </button>
            </div>
        </div>
    `;

    contenedor.innerHTML = html;

    contenedor.querySelectorAll('.pag-btn[data-pagina]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.disabled) return;
            const p = parseInt(this.dataset.pagina, 10);
            if (!isNaN(p) && typeof window.cambiarPagina === 'function') {
                window.cambiarPagina(p);
            }
        });
    });
}

function inicializarVistaLista({
    endpoint,
    tipoVista,
    contenedorListaId,
    paginacionId,
    inputNombreId,
    inputCodigoId,
    btnBuscarId,
    btnLimpiarId
}) {
    let paginaActual = 1;

    async function cargarDatos(pagina = 1) {
        paginaActual = pagina;
        const nombreInput = document.getElementById(inputNombreId);
        const codigoInput = document.getElementById(inputCodigoId);
        const listaContenedor = document.getElementById(contenedorListaId);

        const nombre = nombreInput ? nombreInput.value.trim() : '';
        const codigo = codigoInput ? codigoInput.value.trim() : '';

        const params = new URLSearchParams();
        if (nombre) params.append('nombre', nombre);
        if (codigo) params.append('codigo', codigo);
        params.append('pagina', paginaActual);

        listaContenedor.innerHTML = '<div class="mensaje-lista"><p>Cargando registros...</p></div>';

        try {
            const resp = await fetch(`${endpoint}?${params.toString()}`);

            if (resp.status === 401) {
                window.location.href = '/public/html/access.html';
                return;
            }

            const data = await resp.json();

            if (!data.exito) {
                listaContenedor.innerHTML = `<div class="mensaje-lista"><p style="color:var(--danger);">${esc(data.mensaje || 'Error al cargar datos.')}</p></div>`;
                return;
            }

            listaContenedor.innerHTML = '';

            if (!data.datos || data.datos.length === 0) {
                listaContenedor.innerHTML = '<div class="mensaje-lista"><p>No se encontraron resultados con los filtros indicados.</p></div>';
            } else {
                data.datos.forEach(persona => {
                    const card = crearElementoEmpleado(persona, tipoVista, () => cargarDatos(paginaActual));
                    listaContenedor.appendChild(card);
                });
            }

            if (paginacionId) {
                renderizarPaginacion(paginacionId, data.paginacion, cargarDatos);
            }
        } catch (err) {
            listaContenedor.innerHTML = '<div class="mensaje-lista"><p style="color:var(--danger);">Error de conexión con el servidor.</p></div>';
        }
    }

    window.recargarListaActual = () => cargarDatos(paginaActual);
    window.cambiarPagina = (p) => cargarDatos(p);

    const btnBuscar = document.getElementById(btnBuscarId);
    if (btnBuscar) {
        btnBuscar.addEventListener('click', () => cargarDatos(1));
    }

    const btnLimpiar = document.getElementById(btnLimpiarId);
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', () => {
            const nombreInput = document.getElementById(inputNombreId);
            const codigoInput = document.getElementById(inputCodigoId);
            if (nombreInput) nombreInput.value = '';
            if (codigoInput) codigoInput.value = '';
            cargarDatos(1);
        });
    }

    [inputNombreId, inputCodigoId].forEach(id => {
        const inp = document.getElementById(id);
        if (inp) {
            inp.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    cargarDatos(1);
                }
            });
        }
    });

    cargarDatos(1);
}

document.addEventListener('DOMContentLoaded', () => {
    inicializarNavegacion();
    verificarSesion();
});
