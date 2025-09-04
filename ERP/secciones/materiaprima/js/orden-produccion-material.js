/**
 * Gestión de Órdenes de Producción de Materiales
 * Sistema para crear órdenes de producción usando recetas de materias primas
 * CON FORMATEO INTELIGENTE DE NÚMEROS
 */

// Variables globales
let materiasDisponibles = [];
let versionesDisponibles = [];
let componentesCalculados = [];

/**
 * FUNCIONES DE FORMATEO INTELIGENTE DE NÚMEROS
 */

/**
 * Formatea un número eliminando decimales innecesarios (solo ceros)
 * @param {number|string} numero - El número a formatear
 * @param {number} decimales - Máximo número de decimales a mostrar (default: 3)
 * @returns {string} - Número formateado
 */
function formatearNumero(numero, decimales = 3) {
  if (numero === null || numero === undefined || numero === "") {
    return "0";
  }

  const num = parseFloat(numero);
  if (isNaN(num)) {
    return "0";
  }

  // Si es un número entero, no mostrar decimales
  if (num % 1 === 0) {
    return num.toString();
  }

  // Formatear con decimales y eliminar ceros finales
  return num.toFixed(decimales).replace(/\.?0+$/, "");
}

/**
 * Formatea un número con separadores de miles y decimales inteligentes
 * @param {number|string} numero - El número a formatear
 * @param {number} decimales - Máximo número de decimales (default: 3)
 * @returns {string} - Número formateado con separadores
 */
function formatearNumeroConSeparadores(numero, decimales = 3) {
  if (numero === null || numero === undefined || numero === "") {
    return "0";
  }

  const num = parseFloat(numero);
  if (isNaN(num)) {
    return "0";
  }

  // Si es un número entero, usar formato con separadores sin decimales
  if (num % 1 === 0) {
    return num.toLocaleString("es-ES");
  }

  // Formatear con decimales y eliminar ceros finales
  const formatted = num.toFixed(decimales).replace(/\.?0+$/, "");
  const parts = formatted.split(".");

  // Agregar separadores de miles a la parte entera
  parts[0] = parseInt(parts[0]).toLocaleString("es-ES");

  return parts.join(".");
}

/**
 * Actualizar todos los números en la página con formato inteligente
 */
function formatearNumerosExistentes() {
  // Formatear badges con cantidades
  document.querySelectorAll(".badge").forEach((badge) => {
    const texto = badge.textContent.trim();
    const matchCantidad = texto.match(/^(\d+\.?\d*)\s+([A-Z]+)$/);
    const matchPorcentaje = texto.match(/^(\d+\.?\d*)%$/);

    if (matchCantidad) {
      const numero = matchCantidad[1];
      const unidad = matchCantidad[2];
      badge.textContent = formatearNumero(numero) + " " + unidad;
    } else if (matchPorcentaje) {
      const numero = matchPorcentaje[1];
      badge.textContent = formatearNumero(numero) + "%";
    }
  });

  // Formatear números en celdas de tabla que contengan solo números
  document.querySelectorAll("td strong").forEach((elemento) => {
    const texto = elemento.textContent.trim();
    if (/^\d+\.?\d*$/.test(texto)) {
      elemento.textContent = formatearNumero(texto);
    }
  });
}

/**
 * FUNCIONES PRINCIPALES DEL SISTEMA
 */

/**
 * Inicialización del sistema
 */
function inicializarSistema(materias) {
  materiasDisponibles = materias;
  inicializarEventListeners();

  // Formatear números existentes
  setTimeout(formatearNumerosExistentes, 100);
}

/**
 * Preparar modal para nueva orden
 */
function prepararModalNuevo() {
  limpiarFormulario();
  document.getElementById("tituloModal").textContent =
    "Nueva Orden de Producción";

  // Configurar fecha actual
  document.getElementById("fecha_orden").value = new Date()
    .toISOString()
    .split("T")[0];
}

/**
 * Limpiar formulario
 */
function limpiarFormulario() {
  document.getElementById("formOrden").reset();
  document.getElementById("version_receta").disabled = true;
  document.getElementById("version_receta").innerHTML =
    '<option value="">Primero seleccione materia prima</option>';
  document.getElementById("seccionComponentes").style.display = "none";
  versionesDisponibles = [];
  componentesCalculados = [];
}

/**
 * Cargar versiones de receta disponibles
 */
async function cargarVersionesReceta() {
  const materiaPrimaId = document.getElementById("id_materia_prima").value;
  const selectVersiones = document.getElementById("version_receta");

  if (!materiaPrimaId) {
    selectVersiones.disabled = true;
    selectVersiones.innerHTML =
      '<option value="">Primero seleccione materia prima</option>';
    document.getElementById("seccionComponentes").style.display = "none";
    return;
  }

  selectVersiones.disabled = true;
  selectVersiones.innerHTML = '<option value="">Cargando versiones...</option>';

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_versiones_receta&id_materia_prima=${materiaPrimaId}`,
    });

    const data = await response.json();

    if (data.success && data.versiones) {
      selectVersiones.innerHTML =
        '<option value="">Seleccionar versión de receta</option>';

      if (data.versiones.length > 0) {
        versionesDisponibles = data.versiones;

        data.versiones.forEach((version) => {
          const option = document.createElement("option");
          option.value = version.version_receta;
          option.textContent = `Versión ${version.version_receta}: ${version.nombre_receta} (${version.porcentaje_formateado}% - ${version.estado_completitud})`;
          option.dataset.esCompleta = version.es_completa;

          // Deshabilitar versiones incompletas
          if (!version.es_completa) {
            option.disabled = true;
            option.textContent += " - INCOMPLETA";
          }

          selectVersiones.appendChild(option);
        });

        selectVersiones.disabled = false;
      } else {
        selectVersiones.innerHTML =
          '<option value="">No hay recetas disponibles</option>';
      }
    } else {
      selectVersiones.innerHTML =
        '<option value="">Error cargando versiones</option>';
    }
  } catch (error) {
    console.error("Error cargando versiones:", error);
    selectVersiones.innerHTML =
      '<option value="">Error cargando versiones</option>';
  }
}

/**
 * Calcular componentes necesarios para la producción
 */
async function calcularComponentes() {
  const materiaPrimaId = document.getElementById("id_materia_prima").value;
  const versionReceta = document.getElementById("version_receta").value;
  const cantidadSolicitada = document.getElementById(
    "cantidad_solicitada"
  ).value;

  const seccionComponentes = document.getElementById("seccionComponentes");
  const listaComponentes = document.getElementById("listaComponentes");

  if (
    !materiaPrimaId ||
    !versionReceta ||
    !cantidadSolicitada ||
    parseFloat(cantidadSolicitada) <= 0
  ) {
    seccionComponentes.style.display = "none";
    return;
  }

  listaComponentes.innerHTML =
    '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Calculando componentes...</div>';
  seccionComponentes.style.display = "block";

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=calcular_componentes&id_materia_prima=${materiaPrimaId}&version_receta=${versionReceta}&cantidad_solicitada=${cantidadSolicitada}`,
    });

    const data = await response.json();

    if (data.success && data.componentes) {
      componentesCalculados = data.componentes;
      mostrarComponentesCalculados(data.componentes);
    } else {
      listaComponentes.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>${
        data.error || "No se pudieron calcular los componentes"
      }</div>`;
    }
  } catch (error) {
    console.error("Error calculando componentes:", error);
    listaComponentes.innerHTML =
      '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error al calcular componentes</div>';
  }
}

/**
 * Mostrar componentes calculados en una tabla (CON FORMATEO INTELIGENTE)
 */
function mostrarComponentesCalculados(componentes) {
  const listaComponentes = document.getElementById("listaComponentes");

  if (!componentes || componentes.length === 0) {
    listaComponentes.innerHTML =
      '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No hay componentes para esta receta</div>';
    return;
  }

  // Separar principales de extras
  const principales = componentes.filter((c) => !c.es_extra);
  const extras = componentes.filter((c) => c.es_extra);

  let html = "";

  // Tabla de componentes principales
  if (principales.length > 0) {
    html += `
            <div class="mb-3">
                <h6 class="text-success">
                    <i class="fas fa-percentage me-1"></i>Componentes Principales (${principales.length})
                </h6>
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Componente</th>
                            <th>Porcentaje</th>
                            <th>Cantidad Necesaria</th>
                            <th>Unidad</th>
                        </tr>
                    </thead>
                    <tbody>`;

    principales.forEach((componente) => {
      html += `
                <tr>
                    <td><strong>${componente.descripcion}</strong></td>
                    <td><span class="badge bg-success">${formatearNumero(
                      componente.cantidad_original
                    )}%</span></td>
                    <td><strong>${formatearNumero(
                      componente.cantidad_necesaria
                    )}</strong></td>
                    <td>${componente.unidad}</td>
                </tr>`;
    });

    html += `
                    </tbody>
                </table>
            </div>`;
  }

  // Tabla de componentes extras
  if (extras.length > 0) {
    html += `
            <div class="mb-3">
                <h6 class="text-warning">
                    <i class="fas fa-plus-circle me-1"></i>Componentes Extras (${extras.length})
                </h6>
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Componente Extra</th>
                            <th>Cantidad/KG</th>
                            <th>Cantidad Necesaria</th>
                            <th>Unidad</th>
                        </tr>
                    </thead>
                    <tbody>`;

    extras.forEach((componente) => {
      html += `
                <tr>
                    <td><strong>${componente.descripcion}</strong></td>
                    <td><span class="badge bg-warning">${formatearNumero(
                      componente.cantidad_original
                    )}</span></td>
                    <td><strong>${formatearNumero(
                      componente.cantidad_necesaria
                    )}</strong></td>
                    <td>${componente.unidad}</td>
                </tr>`;
    });

    html += `
                    </tbody>
                </table>
            </div>`;
  }

  // Resumen
  const totalPrincipales = principales.reduce(
    (sum, c) => sum + parseFloat(c.cantidad_necesaria),
    0
  );
  const totalExtras = extras.length;

  html += `
        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle me-1"></i>Resumen:</strong> 
            ${principales.length} componentes principales (${formatearNumero(
    totalPrincipales
  )} KG total) + 
            ${totalExtras} componentes extras
        </div>`;

  listaComponentes.innerHTML = html;
}

/**
 * Ver detalle completo de una orden
 */
async function verDetalleOrden(idOrden) {
  const modal = new bootstrap.Modal(document.getElementById("modalDetalle"));
  document.getElementById("loadingDetalle").style.display = "block";
  document.getElementById("contenidoDetalle").style.display = "none";

  // Configurar botón de PDF
  document.getElementById("btnDescargarPDF").onclick = () => {
    window.open(`generar_pdf_orden_material.php?id_orden=${idOrden}`, "_blank");
  };

  modal.show();

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_detalle_orden&id_orden=${idOrden}`,
    });

    const data = await response.json();

    if (data.success && data.orden) {
      mostrarDetalleOrden(data.orden, data.componentes_calculados || []);
    } else {
      document.getElementById("contenidoDetalle").innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error || "No se pudo cargar el detalle"}
                </div>`;
    }
  } catch (error) {
    document.getElementById("contenidoDetalle").innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error al cargar detalle: ${error.message}
            </div>`;
  }

  document.getElementById("loadingDetalle").style.display = "none";
  document.getElementById("contenidoDetalle").style.display = "block";
}

/**
 * Mostrar detalle completo de una orden (CON FORMATEO INTELIGENTE)
 */
function mostrarDetalleOrden(orden, componentesCalculados) {
  const contenido = document.getElementById("contenidoDetalle");

  // Color del estado
  let colorEstado = "";
  switch (orden.estado) {
    case "PENDIENTE":
      colorEstado = "warning";
      break;
    case "EN_PROCESO":
      colorEstado = "info";
      break;
    case "COMPLETADA":
      colorEstado = "success";
      break;
    case "CANCELADA":
      colorEstado = "danger";
      break;
    default:
      colorEstado = "secondary";
  }

  let html = `
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Orden de Producción #${orden.id}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-box text-primary me-1"></i>Material a Producir:</h6>
                                <p class="fs-5"><strong>${
                                  orden.materia_prima_desc
                                }</strong></p>
                                
                                <h6><i class="fas fa-weight text-info me-1"></i>Cantidad Solicitada:</h6>
                                <p class="fs-4 text-info"><strong>${formatearNumero(
                                  orden.cantidad_solicitada
                                )} ${orden.unidad_medida}</strong></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-layer-group text-success me-1"></i>Versión de Receta:</h6>
                                <p><span class="badge bg-secondary fs-6">Versión ${
                                  orden.version_receta
                                }</span></p>
                                
                                <h6><i class="fas fa-flag text-secondary me-1"></i>Estado:</h6>
                                <p><span class="badge bg-${colorEstado} fs-6">${
    orden.estado
  }</span></p>
                                
                                <h6><i class="fas fa-calendar text-danger me-1"></i>Fecha de Orden:</h6>
                                <p>${orden.fecha_orden_formateada}</p>
                                
                                <h6><i class="fas fa-user text-secondary me-1"></i>Creado por:</h6>
                                <p>${orden.usuario_creacion}</p>
                            </div>
                        </div>
                        
                        ${
                          orden.observaciones
                            ? `
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <h6><i class="fas fa-sticky-note text-secondary me-1"></i>Observaciones:</h6>
                                    <div class="alert alert-light">
                                        ${orden.observaciones}
                                    </div>
                                </div>
                            </div>
                        `
                            : ""
                        }
                    </div>
                </div>
            </div>
        </div>`;

  // Mostrar componentes calculados si están disponibles
  if (componentesCalculados && componentesCalculados.length > 0) {
    html += `
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-list-ul me-2"></i>
                                Componentes Necesarios para la Producción
                            </h5>
                        </div>
                        <div class="card-body">`;

    // Separar principales de extras
    const principales = componentesCalculados.filter((c) => !c.es_extra);
    const extras = componentesCalculados.filter((c) => c.es_extra);

    // Componentes principales
    if (principales.length > 0) {
      html += `
                <h6 class="text-success mb-3">
                    <i class="fas fa-percentage me-1"></i>Componentes Principales (${principales.length})
                </h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Componente</th>
                                <th>Porcentaje Original</th>
                                <th>Cantidad Necesaria</th>
                                <th>Unidad</th>
                            </tr>
                        </thead>
                        <tbody>`;

      principales.forEach((componente) => {
        html += `
                    <tr>
                        <td><strong>${componente.descripcion}</strong></td>
                        <td><span class="badge bg-success">${formatearNumero(
                          componente.cantidad_original
                        )}%</span></td>
                        <td class="text-primary"><strong>${formatearNumero(
                          componente.cantidad_necesaria
                        )}</strong></td>
                        <td>${componente.unidad}</td>
                    </tr>`;
      });

      html += `
                        </tbody>
                    </table>
                </div>`;
    }

    // Componentes extras
    if (extras.length > 0) {
      html += `
                <h6 class="text-warning mb-3">
                    <i class="fas fa-plus-circle me-1"></i>Componentes Extras (${extras.length})
                </h6>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Componente Extra</th>
                                <th>Cantidad por KG</th>
                                <th>Cantidad Necesaria</th>
                                <th>Unidad</th>
                            </tr>
                        </thead>
                        <tbody>`;

      extras.forEach((componente) => {
        html += `
                    <tr>
                        <td><strong>${componente.descripcion}</strong></td>
                        <td><span class="badge bg-warning">${formatearNumero(
                          componente.cantidad_original
                        )}</span></td>
                        <td class="text-primary"><strong>${formatearNumero(
                          componente.cantidad_necesaria
                        )}</strong></td>
                        <td>${componente.unidad}</td>
                    </tr>`;
      });

      html += `
                        </tbody>
                    </table>
                </div>`;
    }

    html += `
                        </div>
                    </div>
                </div>
            </div>`;
  }

  contenido.innerHTML = html;
}

/**
 * Eliminar orden
 */
function eliminarOrden(idOrden, nombreMaterial) {
  if (
    confirm(
      `¿Está seguro que desea eliminar la orden #${idOrden} para "${nombreMaterial}"?\n\nEsta acción no se puede deshacer.`
    )
  ) {
    document.getElementById("idEliminar").value = idOrden;
    document.getElementById("formEliminar").submit();
  }
}

/**
 * Utilidades de interfaz
 */
function toggleBusqueda() {
  const panel = document.getElementById("panelBusqueda");
  panel.style.display = panel.style.display === "none" ? "block" : "none";
}

function refrescarDatos() {
  window.location.reload();
}

/**
 * Inicializar event listeners
 */
function inicializarEventListeners() {
  // Validación del formulario
  document.getElementById("formOrden").addEventListener("submit", function (e) {
    const materiaPrima = document.getElementById("id_materia_prima").value;
    const versionReceta = document.getElementById("version_receta").value;
    const cantidadSolicitada = document.getElementById(
      "cantidad_solicitada"
    ).value;
    const unidadMedida = document.getElementById("unidad_medida").value;
    const fechaOrden = document.getElementById("fecha_orden").value;

    let errores = [];

    if (!materiaPrima) {
      errores.push("Debe seleccionar una materia prima");
    }

    if (!versionReceta) {
      errores.push("Debe seleccionar una versión de receta");
    }

    if (!cantidadSolicitada || parseFloat(cantidadSolicitada) <= 0) {
      errores.push("Debe especificar una cantidad válida mayor a 0");
    }

    if (!unidadMedida) {
      errores.push("Debe seleccionar una unidad de medida");
    }

    if (!fechaOrden) {
      errores.push("Debe especificar una fecha de orden");
    }

    // Validar que la fecha no sea muy antigua (más de 30 días atrás)
    if (fechaOrden) {
      const fechaSeleccionada = new Date(fechaOrden);
      const fechaActual = new Date();
      const diferenciaDias =
        (fechaActual - fechaSeleccionada) / (1000 * 60 * 60 * 24);

      if (diferenciaDias > 30) {
        errores.push(
          "La fecha de orden no puede ser mayor a 30 días en el pasado"
        );
      }
    }

    // Validar que la versión de receta esté completa
    const versionSelect = document.getElementById("version_receta");
    const selectedOption = versionSelect.options[versionSelect.selectedIndex];
    if (selectedOption && selectedOption.dataset.esCompleta === "false") {
      errores.push("No se puede crear una orden con una receta incompleta");
    }

    if (errores.length > 0) {
      e.preventDefault();
      alert("Errores encontrados:\n\n" + errores.join("\n"));
      return false;
    }
  });

  // Eventos de los modales
  document
    .getElementById("modalOrden")
    .addEventListener("shown.bs.modal", function () {
      document.getElementById("id_materia_prima").focus();
    });

  document
    .getElementById("modalOrden")
    .addEventListener("hidden.bs.modal", function () {
      limpiarFormulario();
    });

  // Auto-calcular componentes cuando cambian los valores
  document
    .getElementById("version_receta")
    .addEventListener("change", calcularComponentes);
}

/**
 * Inicialización cuando se carga el DOM
 */
document.addEventListener("DOMContentLoaded", function () {
  inicializarEventListeners();

  // Formatear números existentes después de un breve delay
  setTimeout(formatearNumerosExistentes, 200);
});
