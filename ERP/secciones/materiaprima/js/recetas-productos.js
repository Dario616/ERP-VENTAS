/**
 * Gestión de Recetas con Materias Principales y Extras
 * Sistema optimizado con buscador inteligente de similitud
 */

// Variables globales
let contadorFilasPrincipales = 0;
let contadorFilasExtras = 0;
let materiasDisponibles = [];
let tiposProducto = [];

// Unidades de medida comunes para materias extras
const unidadesMedida = ["unidades", "kilogramos"];

/**
 * Función para formatear números eliminando decimales innecesarios
 */
function formatearNumero(numero, decimales = 3) {
  const num = parseFloat(numero);
  if (isNaN(num)) return "0";

  // Si es un número entero, no mostrar decimales
  if (num % 1 === 0) {
    return num.toString();
  }

  // Si tiene decimales, mostrar solo los necesarios
  const fixed = num.toFixed(decimales);
  return parseFloat(fixed).toString();
}

/**
 * Calcular similitud entre dos strings usando múltiples algoritmos
 */
function calcularSimilitud(texto1, texto2) {
  texto1 = texto1.toLowerCase().trim();
  texto2 = texto2.toLowerCase().trim();

  // Coincidencia exacta
  if (texto1 === texto2) return 1000;

  // Coincidencia al inicio
  if (texto2.startsWith(texto1)) return 900;

  // Texto1 contenido en texto2
  if (texto2.includes(texto1)) return 800;

  // Calcular similitud por palabras
  const palabras1 = texto1.split(/\s+/);
  const palabras2 = texto2.split(/\s+/);

  let coincidenciasPalabras = 0;
  palabras1.forEach((palabra1) => {
    palabras2.forEach((palabra2) => {
      if (palabra2.includes(palabra1) || palabra1.includes(palabra2)) {
        coincidenciasPalabras++;
      }
    });
  });

  const porcentajePalabras =
    coincidenciasPalabras / Math.max(palabras1.length, palabras2.length);

  // Calcular distancia de Levenshtein simplificada
  const maxLen = Math.max(texto1.length, texto2.length);
  const distancia = distanciaLevenshtein(texto1, texto2);
  const similitudLevenshtein = (maxLen - distancia) / maxLen;

  // Combinar métricas
  return porcentajePalabras * 400 + similitudLevenshtein * 200;
}

/**
 * Calcular distancia de Levenshtein (algoritmo simplificado)
 */
function distanciaLevenshtein(a, b) {
  const matriz = [];

  for (let i = 0; i <= b.length; i++) {
    matriz[i] = [i];
  }

  for (let j = 0; j <= a.length; j++) {
    matriz[0][j] = j;
  }

  for (let i = 1; i <= b.length; i++) {
    for (let j = 1; j <= a.length; j++) {
      if (b.charAt(i - 1) === a.charAt(j - 1)) {
        matriz[i][j] = matriz[i - 1][j - 1];
      } else {
        matriz[i][j] = Math.min(
          matriz[i - 1][j - 1] + 1,
          matriz[i][j - 1] + 1,
          matriz[i - 1][j] + 1
        );
      }
    }
  }

  return matriz[b.length][a.length];
}

/**
 * Buscar las 10 materias más similares CON FILTRO POR TIPO
 */
function buscarMateriasSimilares(consulta, tipoMateria = "ambos") {
  if (!consulta || consulta.trim().length < 1) {
    return [];
  }

  // FILTRAR SEGÚN EL TIPO
  let materiasFiltradas = materiasDisponibles;

  if (tipoMateria === "principal") {
    // Para materias principales: EXCLUIR las que tienen unidad = 'Unidad'
    materiasFiltradas = materiasDisponibles.filter(
      (materia) => materia.unidad !== "Unidad"
    );
  }
  // Para materias extras no filtrar nada (tipoMateria === 'extra' o 'ambos')

  const resultados = materiasFiltradas.map((materia) => ({
    ...materia,
    similitud: calcularSimilitud(consulta, materia.descripcion),
  }));

  return resultados
    .filter((item) => item.similitud > 0)
    .sort((a, b) => b.similitud - a.similitud)
    .slice(0, 10);
}

/**
 * Resaltar texto coincidente
 */
function resaltarCoincidencias(texto, consulta) {
  if (!consulta) return texto;

  const regex = new RegExp(
    `(${consulta.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`,
    "gi"
  );
  return texto.replace(regex, '<span class="suggestion-highlight">$1</span>');
}

/**
 * Crear buscador inteligente CON TIPO
 */
function crearBuscadorInteligente(namePrefix, idSuffix, tipo = "principal") {
  const searchId = `search_${namePrefix}_${idSuffix}`;
  const hiddenId = `hidden_${namePrefix}_${idSuffix}`;
  const suggestionsId = `suggestions_${namePrefix}_${idSuffix}`;

  return `
    <div class="search-container">
      <i class="fas fa-search search-icon"></i>
      <input type="text" 
             class="form-control form-control-sm search-input" 
             id="${searchId}"
             placeholder="Buscar materia prima..."
             autocomplete="off"
             onkeyup="manejarBusqueda('${searchId}', '${hiddenId}', '${suggestionsId}', '${tipo}')"
             onkeydown="navegarSugerencias(event, '${suggestionsId}')"
             onfocus="mostrarSugerencias('${searchId}', '${hiddenId}', '${suggestionsId}', '${tipo}')"
             onblur="ocultarSugerenciasConDelay('${suggestionsId}')">
      <button type="button" class="clear-selection" id="clear_${searchId}" 
              onclick="limpiarSeleccion('${searchId}', '${hiddenId}', '${suggestionsId}')"
              style="display: none;">
        <i class="fas fa-times"></i>
      </button>
      <input type="hidden" 
             name="${namePrefix}[id_materia_prima]" 
             id="${hiddenId}"
             required>
      <input type="hidden" name="${namePrefix}[es_materia_extra]" value="${
    tipo === "extra" ? "true" : "false"
  }">
      <div class="suggestions-list" id="${suggestionsId}">
        <!-- Sugerencias dinámicas -->
      </div>
    </div>
  `;
}

/**
 * Manejar búsqueda en tiempo real CON TIPO
 */
function manejarBusqueda(
  searchId,
  hiddenId,
  suggestionsId,
  tipoMateria = "principal"
) {
  const input = document.getElementById(searchId);
  const hidden = document.getElementById(hiddenId);
  const suggestionsDiv = document.getElementById(suggestionsId);
  const clearBtn = document.getElementById(`clear_${searchId}`);

  const consulta = input.value.trim();

  if (!consulta) {
    hidden.value = "";
    suggestionsDiv.style.display = "none";
    clearBtn.style.display = "none";
    input.classList.remove("selected-value");
    return;
  }

  // PASAR EL TIPO A LA BÚSQUEDA
  const resultados = buscarMateriasSimilares(consulta, tipoMateria);

  if (resultados.length > 0) {
    let html = "";
    resultados.forEach((materia, index) => {
      const textoResaltado = resaltarCoincidencias(
        materia.descripcion,
        consulta
      );
      html += `
        <div class="suggestion-item ${index === 0 ? "selected" : ""}" 
             data-id="${materia.id}" 
             data-text="${materia.descripcion}"
             data-unidad="${materia.unidad || ""}"
             onclick="seleccionarMateria('${searchId}', '${hiddenId}', '${suggestionsId}', ${
        materia.id
      }, '${materia.descripcion.replace(/'/g, "\\'")}', '${
        materia.unidad || ""
      }', '${tipoMateria}')">
          ${textoResaltado}
        </div>
      `;
    });

    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = "block";
  } else {
    suggestionsDiv.innerHTML =
      '<div class="no-results">No se encontraron materias similares</div>';
    suggestionsDiv.style.display = "block";
  }

  clearBtn.style.display = consulta ? "inline-block" : "none";
}

/**
 * Mostrar sugerencias CON TIPO
 */
function mostrarSugerencias(
  searchId,
  hiddenId,
  suggestionsId,
  tipoMateria = "principal"
) {
  const input = document.getElementById(searchId);
  if (input.value.trim()) {
    manejarBusqueda(searchId, hiddenId, suggestionsId, tipoMateria);
  }
}
/**
 * Ocultar sugerencias con delay para permitir clics
 */
function ocultarSugerenciasConDelay(suggestionsId) {
  setTimeout(() => {
    const suggestionsDiv = document.getElementById(suggestionsId);
    if (suggestionsDiv) {
      suggestionsDiv.style.display = "none";
    }
  }, 200);
}

/**
 * Seleccionar materia CON PRESELECCIÓN AUTOMÁTICA DE UNIDAD
 */
function seleccionarMateria(
  searchId,
  hiddenId,
  suggestionsId,
  id,
  descripcion,
  unidad = "",
  tipoMateria = "principal"
) {
  const input = document.getElementById(searchId);
  const hidden = document.getElementById(hiddenId);
  const suggestionsDiv = document.getElementById(suggestionsId);
  const clearBtn = document.getElementById(`clear_${searchId}`);

  input.value = descripcion;
  hidden.value = id;
  input.classList.add("selected-value");
  suggestionsDiv.style.display = "none";
  clearBtn.style.display = "inline-block";

  // PRESELECCIONAR UNIDAD PARA MATERIAS EXTRAS
  if (tipoMateria === "extra") {
    // Buscar el select de unidad en la misma fila
    const fila = input.closest("tr");
    const selectUnidad = fila.querySelector(
      'select[name*="unidad_medida_extra"]'
    );

    if (selectUnidad && unidad) {
      let unidadSeleccionada = "";
      let bloquearCambio = false;

      // Mapear unidades de la base de datos a opciones del select
      switch (unidad) {
        case "Unidad":
          unidadSeleccionada = "unidades";
          bloquearCambio = true; // Bloquear cambio para unidades fijas
          break;
        case "Kilos":
          unidadSeleccionada = "kilogramos";
          bloquearCambio = true; // Bloquear cambio para unidades fijas
          break;
        default:
          // Para otras unidades, intentar mapear directamente
          unidadSeleccionada = unidad.toLowerCase();
          bloquearCambio = false;
          break;
      }

      // Verificar si la opción existe en el select
      const opcionExiste = Array.from(selectUnidad.options).some(
        (option) => option.value === unidadSeleccionada
      );

      if (opcionExiste) {
        selectUnidad.value = unidadSeleccionada;
        selectUnidad.disabled = bloquearCambio;

        // Añadir indicador visual si está bloqueado
        if (bloquearCambio) {
          selectUnidad.style.backgroundColor = "#f8f9fa";
          selectUnidad.title = `Unidad fija para esta materia: ${unidad}`;
        } else {
          selectUnidad.style.backgroundColor = "";
          selectUnidad.title = "";
        }
      } else {
        // Si no existe la opción, no bloquear el select
        selectUnidad.disabled = false;
        selectUnidad.style.backgroundColor = "";
        selectUnidad.title = "";
      }
    }
  }

  input.dispatchEvent(new Event("change"));
  hidden.dispatchEvent(new Event("change"));
}

/**
 * Limpiar selección CON RESET DE UNIDADES
 */
function limpiarSeleccion(searchId, hiddenId, suggestionsId) {
  const input = document.getElementById(searchId);
  const hidden = document.getElementById(hiddenId);
  const suggestionsDiv = document.getElementById(suggestionsId);
  const clearBtn = document.getElementById(`clear_${searchId}`);

  input.value = "";
  hidden.value = "";
  input.classList.remove("selected-value");
  suggestionsDiv.style.display = "none";
  clearBtn.style.display = "none";

  // RESETEAR SELECT DE UNIDAD SI ES MATERIA EXTRA
  const fila = input.closest("tr");
  const selectUnidad = fila
    ? fila.querySelector('select[name*="unidad_medida_extra"]')
    : null;

  if (selectUnidad) {
    selectUnidad.disabled = false;
    selectUnidad.style.backgroundColor = "";
    selectUnidad.title = "";
    selectUnidad.selectedIndex = 0; // Volver a la primera opción
  }

  input.focus();
}

/**
 * Navegación por teclado en sugerencias
 */
function navegarSugerencias(event, suggestionsId) {
  const suggestionsDiv = document.getElementById(suggestionsId);
  const items = suggestionsDiv.querySelectorAll(
    ".suggestion-item:not(.no-results)"
  );

  if (items.length === 0) return;

  let selectedIndex = Array.from(items).findIndex((item) =>
    item.classList.contains("selected")
  );

  if (event.key === "ArrowDown") {
    event.preventDefault();
    selectedIndex = (selectedIndex + 1) % items.length;
  } else if (event.key === "ArrowUp") {
    event.preventDefault();
    selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
  } else if (event.key === "Enter") {
    event.preventDefault();
    if (selectedIndex >= 0 && items[selectedIndex]) {
      items[selectedIndex].click();
    }
  } else if (event.key === "Escape") {
    suggestionsDiv.style.display = "none";
  } else {
    return; // No manejar otras teclas
  }

  // Actualizar selección visual
  items.forEach((item, index) => {
    item.classList.toggle("selected", index === selectedIndex);
  });
}

/**
 * Inicialización del sistema
 */
function inicializarSistema(materias, tipos) {
  materiasDisponibles = materias;
  tiposProducto = tipos;
  actualizarContadores();
  actualizarSumaTotal();
}

/**
 * Ver todas las versiones de un tipo de producto
 */
async function verTodasLasVersiones(idTipoProducto, nombreTipo) {
  const modal = new bootstrap.Modal(document.getElementById("modalVersiones"));
  document.getElementById(
    "tituloVersiones"
  ).textContent = `Versiones: ${nombreTipo}`;
  document.getElementById("loadingVersiones").style.display = "block";
  document.getElementById("contenidoVersiones").style.display = "none";

  modal.show();

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_versiones&id_tipo_producto=${idTipoProducto}`,
    });

    const data = await response.json();

    if (data.success && data.versiones) {
      let html = `<div class="row mb-4">
                <div class="col-md-12">
                    <h6><i class="fas fa-layer-group text-primary me-2"></i>Total de Versiones: ${data.total_versiones}</h6>
                </div>
            </div>`;

      if (data.versiones.length > 0) {
        html += `<div class="accordion" id="accordionVersiones">`;

        data.versiones.forEach((version, index) => {
          const porcentajeFormateado = formatearNumero(
            version.total_porcentaje,
            1
          );
          const esCompleto =
            Math.abs(parseFloat(version.total_porcentaje) - 100) <= 0.001;
          const badgeClass = esCompleto
            ? "success"
            : version.total_porcentaje > 0
            ? "warning"
            : "secondary";

          const totalPrincipales = version.materias_principales || 0;
          const totalExtras = version.materias_extras || 0;

          html += `
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="heading${
                              version.version_receta
                            }">
                                <button class="accordion-button ${
                                  index === 0 ? "" : "collapsed"
                                }" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapse${
                                      version.version_receta
                                    }">
                                    <div class="d-flex justify-content-between w-100 me-3">
                                        <div>
                                            <strong>Versión ${
                                              version.version_receta
                                            }: ${version.nombre_receta}</strong>
                                        </div>
                                        <div>
                                            <span class="badge bg-${badgeClass}">${porcentajeFormateado}%</span>
                                            <span class="badge bg-info ms-2">${totalPrincipales} principales</span>
                                            ${
                                              totalExtras > 0
                                                ? `<span class="badge bg-warning ms-1">${totalExtras} extras</span>`
                                                : ""
                                            }
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse${
                              version.version_receta
                            }" class="accordion-collapse collapse ${
            index === 0 ? "show" : ""
          }" 
                                data-bs-parent="#accordionVersiones">
                                <div class="accordion-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <div>
                                            <small class="text-muted">
                                                Última modificación: ${
                                                  version.fecha_formateada ||
                                                  "N/A"
                                                }
                                            </small>
                                        </div>
                                        <div>
                                            <button class="btn btn-info btn-sm me-2" onclick="verDetalleVersion(${idTipoProducto}, ${
            version.version_receta
          })">
                                                <i class="fas fa-eye me-1"></i>Ver Detalle
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarVersion(${idTipoProducto}, ${
            version.version_receta
          }, '${version.nombre_receta}')">
                                                <i class="fas fa-trash me-1"></i>Eliminar
                                            </button>
                                        </div>
                                    </div>
                                    <div id="detalleVersion${
                                      version.version_receta
                                    }">
                                        <p class="text-muted"><i class="fas fa-click me-1"></i>Haz clic en "Ver Detalle" para cargar las materias primas</p>
                                    </div>
                                </div>
                            </div>
                        </div>`;
        });

        html += `</div>`;
      } else {
        html += `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay versiones de recetas configuradas para este tipo de producto.
                </div>`;
      }

      document.getElementById("contenidoVersiones").innerHTML = html;
      document.getElementById("btnAgregarVersion").onclick = () =>
        agregarNuevaVersion(idTipoProducto);
    } else {
      document.getElementById("contenidoVersiones").innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error || "No se pudieron cargar las versiones"}
                </div>`;
    }
  } catch (error) {
    document.getElementById("contenidoVersiones").innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error al cargar las versiones: ${error.message}
            </div>`;
  }

  document.getElementById("loadingVersiones").style.display = "none";
  document.getElementById("contenidoVersiones").style.display = "block";
}

/**
 * Ver detalle de una versión específica
 */
async function verDetalleVersion(idTipoProducto, versionReceta) {
  const contenedor = document.getElementById(`detalleVersion${versionReceta}`);
  contenedor.innerHTML =
    '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando...</div>';

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_detalle_version&id_tipo_producto=${idTipoProducto}&version_receta=${versionReceta}`,
    });

    const data = await response.json();

    if (data.success) {
      let html = "";

      // Crear tabs para principales y extras
      html += `
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active btn-sm" data-bs-toggle="pill" data-bs-target="#principales${versionReceta}" type="button" role="tab">
                            <i class="fas fa-percentage me-1"></i>Principales (${
                              data.total_materias_principales || 0
                            })
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link btn-sm" data-bs-toggle="pill" data-bs-target="#extras${versionReceta}" type="button" role="tab">
                            <i class="fas fa-plus-circle me-1"></i>Extras (${
                              data.total_materias_extras || 0
                            })
                        </button>
                    </li>
                </ul>

                <div class="tab-content">`;

      // Tab de materias principales
      html += `<div class="tab-pane fade show active" id="principales${versionReceta}" role="tabpanel">`;

      if (data.materias_principales && data.materias_principales.length > 0) {
        html += `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Materia Prima</th>
                                    <th>Porcentaje</th>
                                </tr>
                            </thead>
                            <tbody>`;

        data.materias_principales.forEach((materia) => {
          const porcentajeFormateado = formatearNumero(
            materia.cantidad_por_kilo
          );
          html += `
                        <tr>
                            <td><strong>${materia.descripcion}</strong></td>
                            <td><span class="badge bg-success">${porcentajeFormateado}%</span></td>
                        </tr>`;
        });

        html += `</tbody></table></div>`;
      } else {
        html += `<div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No hay materias principales configuradas en esta versión.
                </div>`;
      }

      html += `</div>`; // Cierre tab principales

      // Tab de materias extras
      html += `<div class="tab-pane fade" id="extras${versionReceta}" role="tabpanel">`;

      if (data.materias_extras && data.materias_extras.length > 0) {
        html += `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Materia Prima Extra</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>`;

        data.materias_extras.forEach((materia) => {
          const cantidadFormateada = formatearNumero(materia.cantidad_por_kilo);
          html += `
                        <tr>
                            <td><strong>${materia.descripcion}</strong></td>
                            <td><span class="badge bg-warning">${cantidadFormateada} ${materia.unidad_medida}</span></td>
                        </tr>`;
        });

        html += `</tbody></table></div>`;
      } else {
        html += `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay materias extras configuradas en esta versión.
                </div>`;
      }

      html += `</div></div>`; // Cierre tab extras y tab-content

      // Estado de completitud
      html += `
                <div class="mt-3 p-3 ${
                  data.es_completo ? "bg-success" : "bg-warning"
                } text-white rounded">
                    <strong><i class="fas fa-info-circle me-1"></i>${
                      data.mensaje_completitud
                    }</strong>
                </div>`;

      contenedor.innerHTML = html;
    } else {
      contenedor.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error || "No se pudo cargar el detalle"}
                </div>`;
    }
  } catch (error) {
    contenedor.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error al cargar detalle: ${error.message}
            </div>`;
  }
}

/**
 * Agregar nueva versión
 */
function agregarNuevaVersion(idTipoProducto) {
  document.getElementById("id_tipo_producto").value = idTipoProducto;
  document
    .getElementById("id_tipo_producto")
    .dispatchEvent(new Event("change"));

  const modalVersiones = bootstrap.Modal.getInstance(
    document.getElementById("modalVersiones")
  );
  if (modalVersiones) {
    modalVersiones.hide();
  }

  const modal = new bootstrap.Modal(document.getElementById("modalReceta"));
  modal.show();
}

/**
 * Cargar versiones existentes
 */
async function cargarVersionesExistentes() {
  const tipoProducto = document.getElementById("id_tipo_producto").value;
  const divVersiones = document.getElementById("versionesExistentes");

  if (!tipoProducto) {
    divVersiones.innerHTML =
      "Seleccione un tipo de producto para ver versiones";
    return;
  }

  divVersiones.innerHTML =
    '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando...</div>';

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_versiones&id_tipo_producto=${tipoProducto}`,
    });

    const data = await response.json();

    if (data.success) {
      if (data.versiones && data.versiones.length > 0) {
        let html = '<div class="d-flex flex-wrap gap-2">';
        data.versiones.forEach((version) => {
          const porcentajeFormateado = formatearNumero(
            version.total_porcentaje,
            1
          );
          const esCompleto =
            Math.abs(parseFloat(version.total_porcentaje) - 100) <= 0.001;
          const color = esCompleto ? "success" : "warning";
          const totalExtras = version.materias_extras || 0;
          const extraInfo = totalExtras > 0 ? ` (+${totalExtras}E)` : "";
          html += `<span class="badge bg-${color} text-wrap">V${version.version_receta}: ${version.nombre_receta}${extraInfo}</span>`;
        });
        html += "</div>";
        html += `<small class="text-muted mt-2 d-block">Total: ${data.total_versiones} versión(es) existente(s)</small>`;
        divVersiones.innerHTML = html;
      } else {
        divVersiones.innerHTML =
          '<span class="text-success"><i class="fas fa-star me-1"></i>No hay versiones existentes - Esta será la primera</span>';
      }
    } else {
      divVersiones.innerHTML =
        '<span class="text-muted">Error cargando versiones</span>';
    }
  } catch (error) {
    divVersiones.innerHTML =
      '<span class="text-muted">Error cargando versiones</span>';
  }

  activarAgregarMateria();
}

/**
 * Gestión de la interfaz
 */
function activarAgregarMateria() {
  const tipoProducto = document.getElementById("id_tipo_producto").value;
  const seccion = document.getElementById("seccionMateriasPrimas");

  if (tipoProducto) {
    seccion.style.display = "block";
    document.getElementById("indicadorSuma").classList.remove("d-none");

    // Agregar primera fila de materia principal si no hay ninguna
    if (
      document.querySelectorAll("#tbodyMateriasPrincipales tr").length === 0
    ) {
      agregarFilaMateria("principal");
    }
  } else {
    seccion.style.display = "none";
    document.getElementById("indicadorSuma").classList.add("d-none");
    limpiarTablasMaterias();
  }
}

/**
 * Actualizar contadores de materias
 */
function actualizarContadores() {
  const totalPrincipales = document.querySelectorAll(
    "#tbodyMateriasPrincipales tr"
  ).length;
  const totalExtras = document.querySelectorAll(
    "#tbodyMateriasExtras tr"
  ).length;

  document.getElementById("contadorPrincipales").textContent = totalPrincipales;
  document.getElementById("contadorExtras").textContent = totalExtras;
}

/**
 * Actualizar suma total (solo materias principales) - OPTIMIZADA
 */
function actualizarSumaTotal() {
  let sumaTotal = 0;
  const inputs = document.querySelectorAll(
    '#tbodyMateriasPrincipales input[type="number"]'
  );

  inputs.forEach((input) => {
    const valor = parseFloat(input.value) || 0;
    sumaTotal += valor;
  });

  // Formatear la suma sin decimales innecesarios
  const sumaFormateada = formatearNumero(sumaTotal, 3);
  document.getElementById("sumaTotal").textContent = sumaFormateada;

  const estadoSuma = document.getElementById("estadoSuma");
  const barraProgreso = document.getElementById("barraProgreso");
  const btnGuardar = document.getElementById("btnGuardarMultiple");

  if (Math.abs(sumaTotal - 100) <= 0.001) {
    estadoSuma.textContent = "Completo (100%)";
    estadoSuma.className = "badge bg-success";
    barraProgreso.className = "progress-bar bg-success";
    barraProgreso.style.width = "100%";
    btnGuardar.disabled = false;
  } else if (sumaTotal < 100) {
    const faltante = formatearNumero(100 - sumaTotal, 3);
    estadoSuma.textContent = `Faltan ${faltante}%`;
    estadoSuma.className = "badge bg-warning";
    barraProgreso.className = "progress-bar bg-warning";
    barraProgreso.style.width = `${Math.min(sumaTotal, 100)}%`;
    btnGuardar.disabled = true;
  } else {
    const exceso = formatearNumero(sumaTotal - 100, 3);
    estadoSuma.textContent = `Excede por ${exceso}%`;
    estadoSuma.className = "badge bg-danger";
    barraProgreso.className = "progress-bar bg-danger";
    barraProgreso.style.width = "100%";
    btnGuardar.disabled = true;
  }

  actualizarContadores();
}

/**
 * Agregar fila de materia (principal o extra) con buscador inteligente
 */
function agregarFilaMateria(tipo) {
  if (tipo === "principal") {
    contadorFilasPrincipales++;
    const tbody = document.getElementById("tbodyMateriasPrincipales");
    const fila = document.createElement("tr");

    const namePrefix = `materias_primas[${contadorFilasPrincipales}]`;
    const buscadorHTML = crearBuscadorInteligente(
      namePrefix,
      contadorFilasPrincipales,
      "principal"
    );

    fila.innerHTML = `
            <td>
                ${buscadorHTML}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm porcentaje-input" 
                       name="materias_primas[${contadorFilasPrincipales}][cantidad_por_kilo]" 
                       step="0.001" min="0.001" max="100" 
                       placeholder="0"
                       oninput="actualizarSumaTotal()"
                       required>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaMateria(this, 'principal')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

    tbody.appendChild(fila);
  } else if (tipo === "extra") {
    contadorFilasExtras++;
    const tbody = document.getElementById("tbodyMateriasExtras");
    const fila = document.createElement("tr");

    const namePrefix = `materias_primas[extra_${contadorFilasExtras}]`;
    const buscadorHTML = crearBuscadorInteligente(
      namePrefix,
      `extra_${contadorFilasExtras}`,
      "extra"
    );

    const opcionesUnidades = unidadesMedida
      .map((unidad) => `<option value="${unidad}">${unidad}</option>`)
      .join("");

    fila.innerHTML = `
            <td>
                ${buscadorHTML}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm" 
                       name="materias_primas[extra_${contadorFilasExtras}][cantidad_por_kilo]" 
                       step="0.001" min="0.001" 
                       placeholder="0"
                       oninput="actualizarContadores()"
                       required>
            </td>
            <td>
                <select class="form-control form-control-sm" name="materias_primas[extra_${contadorFilasExtras}][unidad_medida_extra]" required>
                    ${opcionesUnidades}
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaMateria(this, 'extra')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

    tbody.appendChild(fila);
  }

  actualizarSumaTotal();
}

/**
 * Eliminar fila de materia
 */
function eliminarFilaMateria(btn, tipo) {
  btn.closest("tr").remove();
  if (tipo === "principal") {
    actualizarSumaTotal();
  } else {
    actualizarContadores();
  }
}

/**
 * Limpiar tablas de materias
 */
function limpiarTablasMaterias() {
  document.getElementById("tbodyMateriasPrincipales").innerHTML = "";
  document.getElementById("tbodyMateriasExtras").innerHTML = "";
  contadorFilasPrincipales = 0;
  contadorFilasExtras = 0;
  actualizarSumaTotal();
}

/**
 * Limpiar formulario completo
 */
function limpiarFormularioMultiple() {
  document.getElementById("formReceta").reset();
  document.getElementById("seccionMateriasPrimas").style.display = "none";
  document.getElementById("indicadorSuma").classList.add("d-none");
  document.getElementById("versionesExistentes").innerHTML =
    "Seleccione un tipo de producto para ver versiones";
  limpiarTablasMaterias();

  // Activar tab de principales
  const tabPrincipales = document.getElementById("tab-principales");
  if (tabPrincipales) {
    tabPrincipales.click();
  }
}

function prepararModalNuevo() {
  limpiarFormularioMultiple();
  document.getElementById("tituloModal").textContent =
    "Configurar Nueva Versión de Receta";
}

/**
 * Funciones de eliminación
 */
function eliminarVersion(idTipoProducto, version, nombreReceta) {
  if (
    confirm(
      `¿Está seguro que desea eliminar la versión ${version} "${nombreReceta}"?\n\nEsta acción eliminará TODAS las materias primas de esta versión y no se puede deshacer.`
    )
  ) {
    document.getElementById("idTipoProductoEliminarVersion").value =
      idTipoProducto;
    document.getElementById("versionEliminar").value = version;
    document.getElementById("formEliminarVersion").submit();
  }
}

function eliminarTodasLasVersiones(idTipoProducto, nombreTipo) {
  if (
    confirm(
      `¿Está seguro que desea eliminar TODAS las versiones del tipo de producto "${nombreTipo}"?\n\nEsta acción eliminará TODAS las recetas de TODAS las versiones y no se puede deshacer.`
    )
  ) {
    document.getElementById("idTipoProductoEliminar").value = idTipoProducto;
    document.getElementById("formEliminar").submit();
  }
}

function eliminarMateriaDelTipo(idReceta) {
  if (confirm(`¿Eliminar la materia prima de esta receta?`)) {
    document.getElementById("idEliminarIndividual").value = idReceta;
    document.getElementById("formEliminarIndividual").submit();
  }
}

/**
 * Utilidades de interfaz
 */
function toggleBusqueda() {
  const panel = document.getElementById("panelBusqueda");
  panel.style.display = panel.style.display === "none" ? "block" : "none";
}

function mostrarAyuda() {
  const modal = new bootstrap.Modal(document.getElementById("modalAyuda"));
  modal.show();
}

function refrescarDatos() {
  window.location.reload();
}
function generarPDFRecetas(idTipoProducto) {
  if (!idTipoProducto || idTipoProducto <= 0) {
    alert("ID de tipo de producto inválido");
    return;
  }

  try {
    // Crear URL para el PDF
    const urlPDF = `generar-pdf-recetas.php?id_tipo_producto=${idTipoProducto}`;

    // Abrir en nueva ventana/pestaña
    const ventanaPDF = window.open(
      urlPDF,
      "_blank",
      "width=1000,height=700,scrollbars=yes,resizable=yes"
    );

    if (!ventanaPDF) {
      // Si el popup fue bloqueado, mostrar mensaje
      alert(
        "Por favor, permite las ventanas emergentes para ver el PDF.\n\nO accede manualmente a: " +
          urlPDF
      );
    } else {
      // Opcional: Enfocar la nueva ventana
      ventanaPDF.focus();
    }

    console.log(
      "✅ Generando PDF de recetas para tipo producto:",
      idTipoProducto
    );
  } catch (error) {
    console.error("💥 Error generando PDF:", error);
    alert("Error al generar el PDF de recetas. Por favor, inténtelo de nuevo.");
  }
}
/**
 * Validaciones y eventos
 */
function inicializarEventListeners() {
  // Validación del formulario
  document
    .getElementById("formReceta")
    .addEventListener("submit", function (e) {
      const tipoProducto = document.getElementById("id_tipo_producto").value;
      const filasPrincipales = document.querySelectorAll(
        "#tbodyMateriasPrincipales tr"
      );
      const filasExtras = document.querySelectorAll("#tbodyMateriasExtras tr");

      if (!tipoProducto) {
        e.preventDefault();
        alert("Debe seleccionar un tipo de producto");
        return;
      }

      if (filasPrincipales.length === 0) {
        e.preventDefault();
        alert("Debe agregar al menos una materia prima principal");
        return;
      }

      let errores = [];
      let sumaTotal = 0;

      // Validar materias principales
      filasPrincipales.forEach((fila, index) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        const materia = hiddenInput ? hiddenInput.value : "";
        const porcentaje = fila.querySelector('input[type="number"]').value;

        if (!materia) {
          errores.push(
            `Materia Principal ${index + 1}: Seleccione una materia prima`
          );
        }
        if (!porcentaje || parseFloat(porcentaje) <= 0) {
          errores.push(
            `Materia Principal ${index + 1}: Ingrese un porcentaje válido`
          );
        } else {
          sumaTotal += parseFloat(porcentaje);
        }
      });

      // Validar materias extras
      filasExtras.forEach((fila, index) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        const materia = hiddenInput ? hiddenInput.value : "";
        const cantidad = fila.querySelector('input[type="number"]').value;
        const unidadSelect = fila.querySelector(
          'select[name*="unidad_medida_extra"]'
        );
        const unidad = unidadSelect ? unidadSelect.value : "";

        if (!materia) {
          errores.push(
            `Materia Extra ${index + 1}: Seleccione una materia prima`
          );
        }
        if (!cantidad || parseFloat(cantidad) <= 0) {
          errores.push(
            `Materia Extra ${index + 1}: Ingrese una cantidad válida`
          );
        }
        if (!unidad) {
          errores.push(
            `Materia Extra ${index + 1}: Seleccione una unidad de medida`
          );
        }
      });

      if (errores.length > 0) {
        e.preventDefault();
        alert("Errores encontrados:\n" + errores.join("\n"));
        return;
      }

      // Verificar duplicados en todas las materias
      const todasLasMaterias = [];
      filasPrincipales.forEach((fila) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        if (hiddenInput && hiddenInput.value)
          todasLasMaterias.push(hiddenInput.value);
      });
      filasExtras.forEach((fila) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        if (hiddenInput && hiddenInput.value)
          todasLasMaterias.push(hiddenInput.value);
      });

      const materiasDuplicadas = todasLasMaterias.filter(
        (item, index) => todasLasMaterias.indexOf(item) !== index
      );

      if (materiasDuplicadas.length > 0) {
        e.preventDefault();
        alert(
          "Hay materias primas duplicadas entre principales y extras. Revise las tablas."
        );
        return;
      }

      // Verificar suma de principales
      if (Math.abs(sumaTotal - 100) > 0.001) {
        e.preventDefault();
        const sumaFormateada = formatearNumero(sumaTotal, 3);
        alert(
          `La suma de porcentajes de materias principales debe ser exactamente 100%.\nSuma actual: ${sumaFormateada}%`
        );
        return;
      }
    });

  // Eventos del modal
  document
    .getElementById("modalReceta")
    .addEventListener("shown.bs.modal", function () {
      document.getElementById("id_tipo_producto").focus();
    });

  document
    .getElementById("modalReceta")
    .addEventListener("hidden.bs.modal", function () {
      limpiarFormularioMultiple();
    });

  // Cerrar sugerencias al hacer clic fuera
  document.addEventListener("click", function (e) {
    const suggestionsLists = document.querySelectorAll(".suggestions-list");
    suggestionsLists.forEach((list) => {
      if (
        !list.contains(e.target) &&
        !list.previousElementSibling.contains(e.target)
      ) {
        list.style.display = "none";
      }
    });
  });
}

/**
 * Inicialización cuando se carga el DOM
 */
document.addEventListener("DOMContentLoaded", function () {
  inicializarEventListeners();
  actualizarContadores();
  actualizarSumaTotal();
});
