/**
 * Gestión de Composiciones de Materias Primas
 * Sistema optimizado con buscador inteligente de similitud
 */

// Variables globales
let contadorFilasPrincipales = 0;
let contadorFilasExtras = 0;
let materiasDisponibles = [];

// Unidades de medida comunes para componentes extras
const unidadesMedida = ["kilogramos", "unidades"];

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
 * Buscar los 10 componentes más similares (excluyendo la materia prima objetivo)
 */
function buscarComponentesSimilares(
  consulta,
  idMateriaObjetivo = null,
  tipoMateria = "ambos"
) {
  if (!consulta || consulta.trim().length < 1) {
    return [];
  }

  // Filtrar materias primas para excluir la materia prima objetivo
  let materiasFilltradas = materiasDisponibles.filter(
    (materia) => !idMateriaObjetivo || materia.id != idMateriaObjetivo
  );

  // FILTRAR SEGÚN EL TIPO
  if (tipoMateria === "principal") {
    // Para componentes principales: EXCLUIR las que tienen unidad = 'Unidad'
    materiasFilltradas = materiasFilltradas.filter(
      (materia) => materia.unidad !== "Unidad"
    );
  }
  // Para componentes extras no filtrar nada (tipoMateria === 'extra' o 'ambos')

  const resultados = materiasFilltradas.map((materia) => ({
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
 * Crear buscador inteligente de componentes
 */
function crearBuscadorInteligenteComponentes(
  namePrefix,
  idSuffix,
  tipo = "principal"
) {
  const searchId = `search_${namePrefix}_${idSuffix}`;
  const hiddenId = `hidden_${namePrefix}_${idSuffix}`;
  const suggestionsId = `suggestions_${namePrefix}_${idSuffix}`;

  return `
    <div class="search-container">
      <i class="fas fa-search search-icon"></i>
      <input type="text" 
             class="form-control form-control-sm search-input" 
             id="${searchId}"
             placeholder="Buscar componente..."
             autocomplete="off"
             onkeyup="manejarBusquedaComponente('${searchId}', '${hiddenId}', '${suggestionsId}', '${tipo}')"
             onkeydown="navegarSugerencias(event, '${suggestionsId}')"
             onfocus="mostrarSugerenciasComponente('${searchId}', '${hiddenId}', '${suggestionsId}', '${tipo}')"
             onblur="ocultarSugerenciasConDelay('${suggestionsId}')">
      <button type="button" class="clear-selection" id="clear_${searchId}" 
              onclick="limpiarSeleccionComponente('${searchId}', '${hiddenId}', '${suggestionsId}')"
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
 * Manejar búsqueda de componentes en tiempo real
 */
function manejarBusquedaComponente(
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
  const idMateriaObjetivo = document.getElementById(
    "id_materia_prima_objetivo"
  ).value;

  if (!consulta) {
    hidden.value = "";
    suggestionsDiv.style.display = "none";
    clearBtn.style.display = "none";
    input.classList.remove("selected-value");
    return;
  }

  // PASAR EL TIPO A LA BÚSQUEDA
  const resultados = buscarComponentesSimilares(
    consulta,
    idMateriaObjetivo,
    tipoMateria
  );

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
             onclick="seleccionarComponente('${searchId}', '${hiddenId}', '${suggestionsId}', ${
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
      '<div class="no-results">No se encontraron componentes similares</div>';
    suggestionsDiv.style.display = "block";
  }

  clearBtn.style.display = consulta ? "inline-block" : "none";
}

/**
 * Mostrar sugerencias de componentes al hacer focus
 */
function mostrarSugerenciasComponente(
  searchId,
  hiddenId,
  suggestionsId,
  tipoMateria = "principal"
) {
  const input = document.getElementById(searchId);
  if (input.value.trim()) {
    manejarBusquedaComponente(searchId, hiddenId, suggestionsId, tipoMateria);
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
 * Seleccionar componente de las sugerencias
 */
function seleccionarComponente(
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

  // PRESELECCIONAR UNIDAD PARA COMPONENTES EXTRAS
  if (tipoMateria === "extra") {
    const fila = input.closest("tr");
    const selectUnidad = fila.querySelector(
      'select[name*="unidad_medida_extra"]'
    );

    if (selectUnidad && unidad) {
      let unidadSeleccionada = "";
      let bloquearCambio = false;

      switch (unidad) {
        case "Unidad":
          unidadSeleccionada = "unidades";
          bloquearCambio = true;
          break;
        case "Kilos":
          unidadSeleccionada = "kilogramos";
          bloquearCambio = true;
          break;
        default:
          unidadSeleccionada = unidad.toLowerCase();
          bloquearCambio = false;
          break;
      }

      const opcionExiste = Array.from(selectUnidad.options).some(
        (option) => option.value === unidadSeleccionada
      );

      if (opcionExiste) {
        selectUnidad.value = unidadSeleccionada;
        selectUnidad.disabled = bloquearCambio;

        if (bloquearCambio) {
          selectUnidad.style.backgroundColor = "#f8f9fa";
          selectUnidad.title = `Unidad fija para este componente: ${unidad}`;
        } else {
          selectUnidad.style.backgroundColor = "";
          selectUnidad.title = "";
        }
      } else {
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
 * Limpiar selección de componente
 */
function limpiarSeleccionComponente(searchId, hiddenId, suggestionsId) {
  const input = document.getElementById(searchId);
  const hidden = document.getElementById(hiddenId);
  const suggestionsDiv = document.getElementById(suggestionsId);
  const clearBtn = document.getElementById(`clear_${searchId}`);

  input.value = "";
  hidden.value = "";
  input.classList.remove("selected-value");
  suggestionsDiv.style.display = "none";
  clearBtn.style.display = "none";

  // RESETEAR SELECT DE UNIDAD SI ES COMPONENTE EXTRA
  const fila = input.closest("tr");
  const selectUnidad = fila
    ? fila.querySelector('select[name*="unidad_medida_extra"]')
    : null;

  if (selectUnidad) {
    selectUnidad.disabled = false;
    selectUnidad.style.backgroundColor = "";
    selectUnidad.title = "";
    selectUnidad.selectedIndex = 0;
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
function inicializarSistema(materias) {
  materiasDisponibles = materias;
  actualizarContadores();
  actualizarSumaTotal();
}

/**
 * Ver todas las versiones de una materia prima
 */
async function verTodasLasVersiones(idMateriaPrimaObjetivo, nombreMateria) {
  const modal = new bootstrap.Modal(document.getElementById("modalVersiones"));
  document.getElementById(
    "tituloVersiones"
  ).textContent = `Composiciones: ${nombreMateria}`;
  document.getElementById("loadingVersiones").style.display = "block";
  document.getElementById("contenidoVersiones").style.display = "none";

  modal.show();

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_versiones&id_materia_prima_objetivo=${idMateriaPrimaObjetivo}`,
    });

    const data = await response.json();

    if (data.success && data.versiones) {
      let html = `<div class="row mb-4">
                <div class="col-md-12">
                    <h6><i class="fas fa-layer-group text-primary me-2"></i>Total de Composiciones: ${data.total_versiones}</h6>
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
                                            <strong>Composición ${
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
                                            <button class="btn btn-info btn-sm me-2" onclick="verDetalleVersion(${idMateriaPrimaObjetivo}, ${
            version.version_receta
          })">
                                                <i class="fas fa-eye me-1"></i>Ver Detalle
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarVersion(${idMateriaPrimaObjetivo}, ${
            version.version_receta
          }, '${version.nombre_receta}')">
                                                <i class="fas fa-trash me-1"></i>Eliminar
                                            </button>
                                        </div>
                                    </div>
                                    <div id="detalleVersion${
                                      version.version_receta
                                    }">
                                        <p class="text-muted"><i class="fas fa-click me-1"></i>Haz clic en "Ver Detalle" para cargar los componentes</p>
                                    </div>
                                </div>
                            </div>
                        </div>`;
        });

        html += `</div>`;
      } else {
        html += `<div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay composiciones configuradas para esta materia prima.
                </div>`;
      }

      document.getElementById("contenidoVersiones").innerHTML = html;
      document.getElementById("btnAgregarComposicion").onclick = () =>
        agregarNuevaComposicion(idMateriaPrimaObjetivo);
    } else {
      document.getElementById("contenidoVersiones").innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.error || "No se pudieron cargar las composiciones"}
                </div>`;
    }
  } catch (error) {
    document.getElementById("contenidoVersiones").innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error al cargar las composiciones: ${error.message}
            </div>`;
  }

  document.getElementById("loadingVersiones").style.display = "none";
  document.getElementById("contenidoVersiones").style.display = "block";
}

/**
 * Ver detalle de una versión específica
 */
async function verDetalleVersion(idMateriaPrimaObjetivo, versionReceta) {
  const contenedor = document.getElementById(`detalleVersion${versionReceta}`);
  contenedor.innerHTML =
    '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando...</div>';

  try {
    const response = await fetch("", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `ajax=true&accion=obtener_detalle_version&id_materia_prima_objetivo=${idMateriaPrimaObjetivo}&version_receta=${versionReceta}`,
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

      // Tab de componentes principales
      html += `<div class="tab-pane fade show active" id="principales${versionReceta}" role="tabpanel">`;

      if (data.materias_principales && data.materias_principales.length > 0) {
        html += `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Componente Principal</th>
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
                    No hay componentes principales configurados en esta composición.
                </div>`;
      }

      html += `</div>`; // Cierre tab principales

      // Tab de componentes extras
      html += `<div class="tab-pane fade" id="extras${versionReceta}" role="tabpanel">`;

      if (data.materias_extras && data.materias_extras.length > 0) {
        html += `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Componente Extra</th>
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
                    No hay componentes extras configurados en esta composición.
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
 * Agregar nueva composición
 */
function agregarNuevaComposicion(idMateriaPrimaObjetivo) {
  document.getElementById("id_materia_prima_objetivo").value =
    idMateriaPrimaObjetivo;
  document
    .getElementById("id_materia_prima_objetivo")
    .dispatchEvent(new Event("change"));

  const modalVersiones = bootstrap.Modal.getInstance(
    document.getElementById("modalVersiones")
  );
  if (modalVersiones) {
    modalVersiones.hide();
  }

  const modal = new bootstrap.Modal(
    document.getElementById("modalComposicion")
  );
  modal.show();
}

/**
 * Cargar versiones existentes
 */
async function cargarVersionesExistentes() {
  const materiaPrimaObjetivo = document.getElementById(
    "id_materia_prima_objetivo"
  ).value;
  const divVersiones = document.getElementById("composicionesExistentes");

  if (!materiaPrimaObjetivo) {
    divVersiones.innerHTML =
      "Seleccione una materia prima objetivo para ver composiciones";
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
      body: `ajax=true&accion=obtener_versiones&id_materia_prima_objetivo=${materiaPrimaObjetivo}`,
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
          html += `<span class="badge bg-${color} text-wrap">C${version.version_receta}: ${version.nombre_receta}${extraInfo}</span>`;
        });
        html += "</div>";
        html += `<small class="text-muted mt-2 d-block">Total: ${data.total_versiones} composición(es) existente(s)</small>`;
        divVersiones.innerHTML = html;
      } else {
        divVersiones.innerHTML =
          '<span class="text-success"><i class="fas fa-star me-1"></i>No hay composiciones existentes - Esta será la primera</span>';
      }
    } else {
      divVersiones.innerHTML =
        '<span class="text-muted">Error cargando composiciones</span>';
    }
  } catch (error) {
    divVersiones.innerHTML =
      '<span class="text-muted">Error cargando composiciones</span>';
  }

  activarAgregarComponente();
}

/**
 * Gestión de la interfaz
 */
function activarAgregarComponente() {
  const materiaPrimaObjetivo = document.getElementById(
    "id_materia_prima_objetivo"
  ).value;
  const seccion = document.getElementById("seccionComponentes");

  if (materiaPrimaObjetivo) {
    seccion.style.display = "block";
    document.getElementById("indicadorSuma").classList.remove("d-none");

    // Agregar primera fila de componente principal si no hay ninguna
    if (
      document.querySelectorAll("#tbodyComponentesPrincipales tr").length === 0
    ) {
      agregarFilaComponente("principal");
    }
  } else {
    seccion.style.display = "none";
    document.getElementById("indicadorSuma").classList.add("d-none");
    limpiarTablasComponentes();
  }
}

/**
 * Actualizar contadores de componentes
 */
function actualizarContadores() {
  const totalPrincipales = document.querySelectorAll(
    "#tbodyComponentesPrincipales tr"
  ).length;
  const totalExtras = document.querySelectorAll(
    "#tbodyComponentesExtras tr"
  ).length;

  document.getElementById("contadorPrincipales").textContent = totalPrincipales;
  document.getElementById("contadorExtras").textContent = totalExtras;
}

/**
 * Actualizar suma total (solo componentes principales) - OPTIMIZADA
 */
function actualizarSumaTotal() {
  let sumaTotal = 0;
  const inputs = document.querySelectorAll(
    '#tbodyComponentesPrincipales input[type="number"]'
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
 * Agregar fila de componente (principal o extra) con buscador inteligente
 */
function agregarFilaComponente(tipo) {
  const materiaPrimaObjetivo = document.getElementById(
    "id_materia_prima_objetivo"
  ).value;

  if (tipo === "principal") {
    contadorFilasPrincipales++;
    const tbody = document.getElementById("tbodyComponentesPrincipales");
    const fila = document.createElement("tr");

    const namePrefix = `materias_primas[${contadorFilasPrincipales}]`;
    const buscadorHTML = crearBuscadorInteligenteComponentes(
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
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaComponente(this, 'principal')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

    tbody.appendChild(fila);
  } else if (tipo === "extra") {
    contadorFilasExtras++;
    const tbody = document.getElementById("tbodyComponentesExtras");
    const fila = document.createElement("tr");

    const namePrefix = `materias_primas[extra_${contadorFilasExtras}]`;
    const buscadorHTML = crearBuscadorInteligenteComponentes(
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
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaComponente(this, 'extra')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

    tbody.appendChild(fila);
  }

  actualizarSumaTotal();
}

/**
 * Eliminar fila de componente
 */
function eliminarFilaComponente(btn, tipo) {
  btn.closest("tr").remove();
  if (tipo === "principal") {
    actualizarSumaTotal();
  } else {
    actualizarContadores();
  }
}

/**
 * Limpiar tablas de componentes
 */
function limpiarTablasComponentes() {
  document.getElementById("tbodyComponentesPrincipales").innerHTML = "";
  document.getElementById("tbodyComponentesExtras").innerHTML = "";
  contadorFilasPrincipales = 0;
  contadorFilasExtras = 0;
  actualizarSumaTotal();
}

/**
 * Limpiar formulario completo
 */
function limpiarFormularioMultiple() {
  document.getElementById("formComposicion").reset();
  document.getElementById("seccionComponentes").style.display = "none";
  document.getElementById("indicadorSuma").classList.add("d-none");
  document.getElementById("composicionesExistentes").innerHTML =
    "Seleccione una materia prima objetivo para ver composiciones";
  limpiarTablasComponentes();

  // Activar tab de principales
  const tabPrincipales = document.getElementById("tab-principales");
  if (tabPrincipales) {
    tabPrincipales.click();
  }
}

function prepararModalNuevo() {
  limpiarFormularioMultiple();
  document.getElementById("tituloModal").textContent =
    "Configurar Nueva Composición de Materia Prima";
}

/**
 * Funciones de eliminación
 */
function eliminarVersion(idMateriaPrimaObjetivo, version, nombreComposicion) {
  if (
    confirm(
      `¿Está seguro que desea eliminar la composición ${version} "${nombreComposicion}"?\n\nEsta acción eliminará TODOS los componentes de esta composición y no se puede deshacer.`
    )
  ) {
    document.getElementById("idMateriaPrimaObjetivoEliminarVersion").value =
      idMateriaPrimaObjetivo;
    document.getElementById("versionEliminar").value = version;
    document.getElementById("formEliminarVersion").submit();
  }
}

function eliminarTodasLasComposiciones(idMateriaPrimaObjetivo, nombreMateria) {
  if (
    confirm(
      `¿Está seguro que desea eliminar TODAS las composiciones de la materia prima "${nombreMateria}"?\n\nEsta acción eliminará TODAS las composiciones de TODAS las versiones y no se puede deshacer.`
    )
  ) {
    document.getElementById("idMateriaPrimaObjetivoEliminar").value =
      idMateriaPrimaObjetivo;
    document.getElementById("formEliminar").submit();
  }
}

function eliminarComponenteDelTipo(idReceta) {
  if (confirm(`¿Eliminar el componente de esta composición?`)) {
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

function generarPDFComposiciones(idMateriaPrimaObjetivo) {
  if (!idMateriaPrimaObjetivo || idMateriaPrimaObjetivo <= 0) {
    alert("ID de materia prima inválido");
    return;
  }

  try {
    // Crear URL para el PDF
    const urlPDF = `generar-pdf-composiciones.php?id_materia_prima_objetivo=${idMateriaPrimaObjetivo}`;

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
      "✅ Generando PDF de composiciones para materia prima:",
      idMateriaPrimaObjetivo
    );
  } catch (error) {
    console.error("💥 Error generando PDF:", error);
    alert(
      "Error al generar el PDF de composiciones. Por favor, inténtelo de nuevo."
    );
  }
}

/**
 * Validaciones y eventos
 */
function inicializarEventListeners() {
  // Validación del formulario
  document
    .getElementById("formComposicion")
    .addEventListener("submit", function (e) {
      const materiaPrimaObjetivo = document.getElementById(
        "id_materia_prima_objetivo"
      ).value;
      const filasPrincipales = document.querySelectorAll(
        "#tbodyComponentesPrincipales tr"
      );
      const filasExtras = document.querySelectorAll(
        "#tbodyComponentesExtras tr"
      );

      if (!materiaPrimaObjetivo) {
        e.preventDefault();
        alert("Debe seleccionar una materia prima objetivo");
        return;
      }

      if (filasPrincipales.length === 0) {
        e.preventDefault();
        alert("Debe agregar al menos un componente principal");
        return;
      }

      let errores = [];
      let sumaTotal = 0;

      // Validar componentes principales
      filasPrincipales.forEach((fila, index) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        const materia = hiddenInput ? hiddenInput.value : "";
        const porcentaje = fila.querySelector('input[type="number"]').value;

        if (!materia) {
          errores.push(
            `Componente Principal ${index + 1}: Seleccione una materia prima`
          );
        } else if (materia === materiaPrimaObjetivo) {
          errores.push(
            `Componente Principal ${
              index + 1
            }: Una materia prima no puede ser componente de sí misma`
          );
        }

        if (!porcentaje || parseFloat(porcentaje) <= 0) {
          errores.push(
            `Componente Principal ${index + 1}: Ingrese un porcentaje válido`
          );
        } else {
          sumaTotal += parseFloat(porcentaje);
        }
      });

      // Validar componentes extras
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
            `Componente Extra ${index + 1}: Seleccione una materia prima`
          );
        } else if (materia === materiaPrimaObjetivo) {
          errores.push(
            `Componente Extra ${
              index + 1
            }: Una materia prima no puede ser componente de sí misma`
          );
        }

        if (!cantidad || parseFloat(cantidad) <= 0) {
          errores.push(
            `Componente Extra ${index + 1}: Ingrese una cantidad válida`
          );
        }
        if (!unidad) {
          errores.push(
            `Componente Extra ${index + 1}: Seleccione una unidad de medida`
          );
        }
      });

      if (errores.length > 0) {
        e.preventDefault();
        alert("Errores encontrados:\n" + errores.join("\n"));
        return;
      }

      // Verificar duplicados en todos los componentes
      const todosLosComponentes = [];
      filasPrincipales.forEach((fila) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        if (hiddenInput && hiddenInput.value)
          todosLosComponentes.push(hiddenInput.value);
      });
      filasExtras.forEach((fila) => {
        const hiddenInput = fila.querySelector(
          'input[type="hidden"][name*="id_materia_prima"]'
        );
        if (hiddenInput && hiddenInput.value)
          todosLosComponentes.push(hiddenInput.value);
      });

      const componentesDuplicados = todosLosComponentes.filter(
        (item, index) => todosLosComponentes.indexOf(item) !== index
      );

      if (componentesDuplicados.length > 0) {
        e.preventDefault();
        alert(
          "Hay componentes duplicados entre principales y extras. Revise las tablas."
        );
        return;
      }

      // Verificar suma de principales
      if (Math.abs(sumaTotal - 100) > 0.001) {
        e.preventDefault();
        const sumaFormateada = formatearNumero(sumaTotal, 3);
        alert(
          `La suma de porcentajes de componentes principales debe ser exactamente 100%.\nSuma actual: ${sumaFormateada}%`
        );
        return;
      }
    });

  // Eventos del modal
  document
    .getElementById("modalComposicion")
    .addEventListener("shown.bs.modal", function () {
      document.getElementById("id_materia_prima_objetivo").focus();
    });

  document
    .getElementById("modalComposicion")
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
