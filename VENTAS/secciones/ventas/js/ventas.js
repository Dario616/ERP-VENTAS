// ===============================
// VARIABLES GLOBALES
// ===============================
let contadorProductos = 0;
let productosData = {};
let config = {}; // Se inicializa desde PHP

// ===============================
// SISTEMA DE NOTIFICACIONES TOAST
// ===============================

/**
 * Crea el contenedor de toasts si no existe
 */
function crearContenedorToasts() {
  if (!document.getElementById("toast-container")) {
    const toastContainer = document.createElement("div");
    toastContainer.id = "toast-container";
    toastContainer.className = "toast-container position-fixed top-0 end-0 p-3";
    toastContainer.style.zIndex = "9999";
    document.body.appendChild(toastContainer);
  }
}

/**
 * Muestra una notificación toast
 */
function mostrarToast(mensaje, tipo = "info", duracion = 4000) {
  crearContenedorToasts();

  const iconos = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-circle",
    warning: "fas fa-exclamation-triangle",
    info: "fas fa-info-circle",
  };

  const colores = {
    success: "text-success",
    error: "text-danger",
    warning: "text-warning",
    info: "text-primary",
  };

  const fondos = {
    success: "bg-success-subtle border-success",
    error: "bg-danger-subtle border-danger",
    warning: "bg-warning-subtle border-warning",
    info: "bg-primary-subtle border-primary",
  };

  const toastId = "toast-" + Date.now();

  const toastHTML = `
        <div id="${toastId}" class="toast ${fondos[tipo]} border" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${fondos[tipo]} border-0">
                <i class="${iconos[tipo]} ${colores[tipo]} me-2"></i>
                <strong class="me-auto ${colores[tipo]}">Sistema de Ventas</strong>
                <small class="text-muted">ahora</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${mensaje}
            </div>
        </div>
    `;

  document
    .getElementById("toast-container")
    .insertAdjacentHTML("beforeend", toastHTML);

  const toastElement = document.getElementById(toastId);
  const toast = new bootstrap.Toast(toastElement, {
    delay: duracion,
    autohide: true,
  });

  toast.show();

  toastElement.addEventListener("hidden.bs.toast", function () {
    toastElement.remove();
  });
}

function notificarExito(mensaje, duracion = 4000) {
  mostrarToast(mensaje, "success", duracion);
}

function notificarError(mensaje, duracion = 6000) {
  mostrarToast(mensaje, "error", duracion);
}

function notificarAdvertencia(mensaje, duracion = 5000) {
  mostrarToast(mensaje, "warning", duracion);
}

function notificarInfo(mensaje, duracion = 4000) {
  mostrarToast(mensaje, "info", duracion);
}

function notificarProductosAgregados(
  productosAgregados,
  productosDuplicados,
  productosConError
) {
  let mensaje = "";
  let tipo = "info";

  if (productosAgregados > 0) {
    mensaje += `<strong>✅ ${productosAgregados} producto(s) agregado(s)</strong><br>`;
    tipo = "success";
  }

  if (productosDuplicados > 0) {
    mensaje += `⚠️ ${productosDuplicados} ya estaban en la lista<br>`;
    if (tipo !== "error") tipo = "warning";
  }

  if (productosConError > 0) {
    mensaje += `❌ ${productosConError} producto(s) con error`;
    tipo = "error";
  }

  if (mensaje) {
    mostrarToast(mensaje, tipo, 5000);
  }
}

// ===============================
// FUNCIONES UTILITARIAS
// ===============================

function formatearNumero(numero) {
  if (!numero) return "0";
  const num = parseFloat(numero);
  if (isNaN(num)) return "0";

  let [entera, decimal] = num.toFixed(4).split(".");
  entera = entera.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  decimal = decimal.replace(/0+$/, "");

  return decimal ? `${entera},${decimal}` : entera;
}

function parseDecimal(valor) {
  if (!valor) return 0;
  return parseFloat(valor.toString().replace(",", ".")) || 0;
}

function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// ===============================
// FUNCIONES DE GESTIÓN DE PRODUCTOS
// ===============================

function renumerarProductos() {
  const productosVisibles = $(".product-row:visible");
  const nuevosProductosData = {};

  productosVisibles.each(function (nuevoIndice) {
    const $row = $(this);
    const indiceAnterior = $row.data("index");

    $row.attr("data-index", nuevoIndice);
    $row.data("index", nuevoIndice);
    $row.find(".badge").text(nuevoIndice + 1);

    $row.find("input, select").each(function () {
      const $input = $(this);
      const nameAnterior = $input.attr("name");
      if (nameAnterior && nameAnterior.includes("[")) {
        const nuevoName = nameAnterior.replace(/\[\d+\]/, `[${nuevoIndice}]`);
        $input.attr("name", nuevoName);
      }
    });

    $row.find(".carga-por-bobinas-switch").each(function () {
      const nuevoId = `switch-bobinas-${nuevoIndice}`;
      $(this).attr("id", nuevoId);
      $(this).next("label").attr("for", nuevoId);
    });

    if (productosData[indiceAnterior]) {
      nuevosProductosData[nuevoIndice] = productosData[indiceAnterior];
    }
  });

  productosData = nuevosProductosData;
  contadorProductos = productosVisibles.length;

  console.log(`Productos renumerados. Nuevo contador: ${contadorProductos}`);
}

// ===============================
// 🚀 FUNCIONES DE CARGA POR BOBINAS (SOLO ESTAS)
// ===============================

function obtenerPesoPorBobina(idProducto, callback) {
  $.ajax({
    url: "./config/obtener_peso_bobina.php",
    method: "GET",
    data: { id_producto: idProducto },
    dataType: "json",
    success: function (response) {
      if (response.success) {
        callback(parseFloat(response.peso_bobina) || 0);
      } else {
        callback(0);
      }
    },
    error: function () {
      callback(0);
    },
  });
}

function puedeUsarCargaPorBobinas(tipoProducto, unidadMedida) {
  const tiposConBobinas = ["TNT", "Spunlace", "Laminadora"];
  const esProductoConBobinas = tiposConBobinas.some((tipo) =>
    tipoProducto.toLowerCase().includes(tipo.toLowerCase())
  );
  const esEnKilos = unidadMedida && unidadMedida.toLowerCase().includes("kilo");

  return esProductoConBobinas && esEnKilos;
}

function actualizarModosCarga(productoRow) {
  const tipoProducto = productoRow.find('input[name*="[tipo_producto]"]').val();
  const unidadMedida = productoRow
    .find('select[name*="[unidad_medida]"]')
    .val();

  if (puedeUsarCargaPorBobinas(tipoProducto, unidadMedida)) {
    mostrarOpcionCargaPorBobinas(productoRow);
    // ✅ NUEVA FUNCIONALIDAD: Mostrar equivalencia de bobinas en modo normal
    mostrarEquivalenciaBobinas(productoRow);
  } else {
    ocultarOpcionCargaPorBobinas(productoRow);
    ocultarEquivalenciaBobinas(productoRow);
  }
}

function mostrarEquivalenciaBobinas(productoRow) {
  const cantidad =
    parseFloat(productoRow.find(".producto-cantidad").val()) || 0;
  const idProducto = productoRow.find('input[name*="[id_producto]"]').val();
  const tipoProducto = productoRow.find('input[name*="[tipo_producto]"]').val();
  const unidadMedida = productoRow
    .find('select[name*="[unidad_medida]"]')
    .val();
  const switchActivo = productoRow
    .find(".carga-por-bobinas-switch")
    .is(":checked");

  // ✅ NUEVA VALIDACIÓN: Solo mostrar para productos TNT, Spunlace y Laminadora
  if (!puedeUsarCargaPorBobinas(tipoProducto, unidadMedida)) {
    ocultarEquivalenciaBobinas(productoRow);
    return;
  }

  // Función helper para formatear números con máximo 2 decimales sin ceros innecesarios
  function formatearNumero(numero) {
    return parseFloat(numero.toFixed(2)).toString();
  }

  // Solo mostrar si NO está activado el modo carga por bobinas
  if (switchActivo || cantidad <= 0) {
    ocultarEquivalenciaBobinas(productoRow);
    return;
  }

  // Buscar o crear el mensaje de equivalencia
  let mensajeEquivalencia = productoRow.find(".equivalencia-bobinas-mensaje");
  if (mensajeEquivalencia.length === 0) {
    // ✅ CAMBIO PRINCIPAL: Crear el mensaje como un div separado que va DEBAJO
    const mensajeHTML = `
      <div class="equivalencia-bobinas-mensaje mt-2">
        <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.875rem; border-left: 3px solid #17a2b8;">
          <i class="fas fa-calculator me-2"></i>
          <strong>Equivalencia:</strong> 
          <span class="equivalencia-texto" style="font-family: monospace;">Calculando...</span>
        </div>
      </div>
    `;

    // ✅ CAMBIO: Insertar después del input-group, no como hijo del parent
    productoRow
      .find(".producto-cantidad")
      .closest(".input-group")
      .after(mensajeHTML);
    mensajeEquivalencia = productoRow.find(".equivalencia-bobinas-mensaje");
  }

  // Obtener peso por bobina y calcular equivalencia
  obtenerPesoPorBobina(idProducto, function (pesoPorBobina) {
    if (pesoPorBobina > 0) {
      const cantidadBobinas = cantidad / pesoPorBobina;

      // ✅ MOSTRAR EL CÁLCULO COMPLETO CON FORMATO VISUAL Y MÁXIMO 2 DECIMALES
      const textoCalculo = `
        <span class="text-primary fw-bold">${formatearNumero(
          cantidad
        )} kg</span> 
        <span class="text-muted">÷</span> 
        <span class="text-warning fw-bold">${formatearNumero(
          pesoPorBobina
        )} kg/bobina</span> 
        <span class="text-muted">=</span> 
        <span class="text-success fw-bold">${formatearNumero(
          cantidadBobinas
        )} bobinas</span>
      `;

      mensajeEquivalencia.find(".equivalencia-texto").html(textoCalculo);
      mensajeEquivalencia.show();
    } else {
      ocultarEquivalenciaBobinas(productoRow);
    }
  });
}

/**
 * ✅ NUEVA FUNCIÓN: Oculta el mensaje de equivalencia de bobinas
 */
function ocultarEquivalenciaBobinas(productoRow) {
  productoRow.find(".equivalencia-bobinas-mensaje").hide();
}

function mostrarOpcionCargaPorBobinas(productoRow) {
  let cargaBobinasContainer = productoRow.find(".carga-bobinas-container");

  if (cargaBobinasContainer.length === 0) {
    const cargaBobinasHTML = `
            <div class="carga-bobinas-container mb-3">
                <div class="card bg-light border" style="border-left: 4px solid #28a745 !important; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);">
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input carga-por-bobinas-switch" type="checkbox" id="switch-bobinas-${productoRow.data(
                              "index"
                            )}" style="transform: scale(1.2);">
                            <label class="form-check-label fw-bold text-primary" for="switch-bobinas-${productoRow.data(
                              "index"
                            )}">
                                <i class="fas fa-tape me-2"></i>Cargar por cantidad de bobinas
                            </label>
                        </div>
                        
                        <div class="carga-bobinas-inputs" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold">Cantidad de Bobinas</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tape"></i></span>
                                        <input type="number" class="form-control cantidad-bobinas-input" 
                                               placeholder="" min="0.01" step="0.01">
                                        <span class="input-group-text">bobinas</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small fw-bold">Peso por Bobina</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-weight"></i></span>
                                        <input type="number" class="form-control peso-bobina-display" 
                                               placeholder="Obteniendo..." readonly style="background-color: #f8f9fa; font-weight: 500;">
                                        <span class="input-group-text">kg</span>
                                    </div>
                                </div>
                            </div>
                            <div class="resultado-calculo-bobinas mt-2">
                                <div class="alert alert-info mb-0 p-2" style="border-left: 3px solid #17a2b8;">
                                    <small>
                                        <i class="fas fa-calculator me-2"></i>
                                        <strong>Cálculo:</strong> 
                                        <span class="calculo-display" style="font-family: monospace;">Ingrese cantidad de bobinas</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

    const rowCantidadPrecio = productoRow
      .find(".row")
      .has(".producto-cantidad")
      .first();
    rowCantidadPrecio.before(cargaBobinasHTML);

    configurarEventosCargaPorBobinas(productoRow);
  }
}

function ocultarOpcionCargaPorBobinas(productoRow) {
  productoRow.find(".carga-bobinas-container").remove();
}

function configurarEventosCargaPorBobinas(productoRow) {
  const switchBobinas = productoRow.find(".carga-por-bobinas-switch");
  const inputsBobinas = productoRow.find(".carga-bobinas-inputs");
  const cantidadBobinasInput = productoRow.find(".cantidad-bobinas-input");
  const pesoBobinaDisplay = productoRow.find(".peso-bobina-display");
  const calculoDisplay = productoRow.find(".calculo-display");
  const cantidadPrincipal = productoRow.find(".producto-cantidad");

  switchBobinas.change(function () {
    if ($(this).is(":checked")) {
      inputsBobinas.slideDown();
      cantidadPrincipal.prop("readonly", true).addClass("bg-light").css({
        "background-color": "#e9ecef !important",
        "border-color": "#28a745",
        "font-weight": "600",
      });
      obtenerPesoBobinaParaCarga(productoRow);
      notificarInfo(
        '<i class="fas fa-tape me-2"></i>Modo carga por bobinas activado'
      );

      // ✅ Ocultar mensaje de equivalencia cuando se activa el modo carga
      ocultarEquivalenciaBobinas(productoRow);
    } else {
      inputsBobinas.slideUp();
      cantidadPrincipal.prop("readonly", false).removeClass("bg-light").css({
        "background-color": "",
        "border-color": "",
        "font-weight": "",
      });
      cantidadBobinasInput.val("");
      pesoBobinaDisplay.val("");
      calculoDisplay.text("Ingrese cantidad de bobinas");

      // ✅ Mostrar mensaje de equivalencia cuando se desactiva el modo carga
      mostrarEquivalenciaBobinas(productoRow);
    }
  });

  cantidadBobinasInput.on("input", function () {
    calcularPesoDesdeBobinas(productoRow);
  });
}

function obtenerPesoBobinaParaCarga(productoRow) {
  const idProducto = productoRow.find('input[name*="[id_producto]"]').val();
  const pesoBobinaDisplay = productoRow.find(".peso-bobina-display");

  pesoBobinaDisplay.val("Obteniendo...");

  obtenerPesoPorBobina(idProducto, function (pesoPorBobina) {
    if (pesoPorBobina > 0) {
      pesoBobinaDisplay.val(pesoPorBobina.toFixed(2));
      calcularPesoDesdeBobinas(productoRow);
    } else {
      pesoBobinaDisplay.val("No disponible");
      productoRow
        .find(".calculo-display")
        .text("Peso por bobina no disponible");
      notificarAdvertencia(
        "No se pudo obtener el peso por bobina para este producto"
      );
    }
  });
}

function calcularPesoDesdeBobinas(productoRow) {
  const cantidadBobinas =
    parseFloat(productoRow.find(".cantidad-bobinas-input").val()) || 0;
  const pesoPorBobina =
    parseFloat(productoRow.find(".peso-bobina-display").val()) || 0;
  const calculoDisplay = productoRow.find(".calculo-display");
  const cantidadPrincipal = productoRow.find(".producto-cantidad");

  if (cantidadBobinas > 0 && pesoPorBobina > 0) {
    const pesoTotal = cantidadBobinas * pesoPorBobina;

    cantidadPrincipal.val(pesoTotal.toFixed(2));

    calculoDisplay.html(`
            <strong>${cantidadBobinas.toFixed(2)} bobinas</strong> × 
            <strong>${pesoPorBobina.toFixed(2)} kg/bobina</strong> = 
            <strong class="text-success">${pesoTotal.toFixed(2)} kg</strong>
        `);

    cantidadPrincipal.trigger("input");
  } else if (cantidadBobinas > 0) {
    calculoDisplay.text("Esperando peso por bobina...");
  } else {
    calculoDisplay.text("Ingrese cantidad de bobinas");
    cantidadPrincipal.val("");
  }
}

// ===============================
// FUNCIONES DE PRODUCTOS
// ===============================

function createProductHTML(indice, producto) {
  const monedaActual = $("#moneda").val();
  let simboloMoneda = "₲";

  switch (monedaActual) {
    case "Dólares":
      simboloMoneda = "USD";
      break;
    case "Real brasileño":
      simboloMoneda = "R$";
      break;
    default:
      simboloMoneda = "₲";
  }

  let opcionesUnidades = "";
  if (producto.unidades_medida && producto.unidades_medida.length > 0) {
    opcionesUnidades = producto.unidades_medida
      .map(
        (um) => `<option value="${escapeHtml(um)}">${escapeHtml(um)}</option>`
      )
      .join("");
  } else {
    opcionesUnidades = '<option value="">Sin unidades definidas</option>';
  }

  const aplicarIva = $("#aplicar_iva").is(":checked");
  const etiquetaPrecio = aplicarIva ? "Precio (con IVA)" : "Precio";

  const html = `
        <div class="product-row card mb-1" data-index="${indice}" data-product-id="${
    producto.id
  }">
            <div class="col-1 d-flex align-items-center justify-content-center">
                <div class="badge bg-primary rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold; box-shadow: 0 2px 8px rgba(74, 108, 247, 0.3);">
                    ${indice + 1}
                </div>
            </div>
            <div class="card-body">
                <input type="hidden" name="productos[${indice}][id_producto]" value="${
    producto.id
  }">
                <input type="hidden" name="productos[${indice}][tipo_producto]" value="${escapeHtml(
    producto.tipo_producto
  )}">
                <input type="hidden" class="producto-imagen-base64" value="${escapeHtml(
                  producto.base64img
                )}">
                <input type="hidden" class="producto-imagen-tipo" value="${escapeHtml(
                  producto.tipoimg
                )}">
                
                <div class="col-md-13 mb-3">
                    <label class="form-label">Descripción</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-box"></i></span>
                        <input type="text" name="productos[${indice}][descripcion]" class="form-control" value="${escapeHtml(
    producto.descripcion
  )}" readonly>
                        ${
                          producto.base64img
                            ? `<button type="button" class="btn btn-outline-secondary btn-ver-imagen" title="Ver imagen"><i class="fas fa-eye"></i></button>`
                            : ""
                        }
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Unidad de Medida</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-ruler"></i></span>
                            <select name="productos[${indice}][unidad_medida]" class="form-select producto-unidad-medida" required>
                                ${opcionesUnidades}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">NCM</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            <input type="text" name="productos[${indice}][ncm]" class="form-control" value="${escapeHtml(
    producto.ncm
  )}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
    <label class="form-label">Detalles para Producción (Opcional)</label>
    <div class="input-group">
        <span class="input-group-text"><i class="fas fa-tools"></i></span>
        <textarea name="productos[${indice}][instruccion]" class="form-control" rows="2" placeholder="Instrucciones especiales para producción..." maxlength = "96"></textarea>
    </div>
        </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cantidad</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-balance-scale"></i></span>
                            <input type="number" name="productos[${indice}][cantidad]" class="form-control producto-cantidad" value="1" min="0.0001" step="0.0001" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label precio-label">${etiquetaPrecio}</label>
                        <div class="input-group">
                            <span class="input-group-text moneda-simbolo">${simboloMoneda}</span>
                            <input type="number" name="productos[${indice}][precio]" class="form-control producto-precio" value="" min="0" step="0.0001" required>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total</label>
                        <div class="input-group">
                            <span class="input-group-text moneda-simbolo">${simboloMoneda}</span>
                            <input type="text" class="form-control producto-total" value="0,0000" readonly>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-producto">
                        <i class="fas fa-trash me-1"></i> Eliminar Producto
                    </button>
                </div>
            </div>
        </div>
    `;

  return html;
}

function configurarEventosProductoCompleto(productoRow) {
  productoRow
    .find(".producto-cantidad, .producto-precio")
    .on("input", function () {
      calcularTotalProducto(productoRow);

      // ✅ NUEVA FUNCIONALIDAD: Actualizar equivalencia de bobinas al cambiar cantidad
      if ($(this).hasClass("producto-cantidad")) {
        mostrarEquivalenciaBobinas(productoRow);
      }
    });

  productoRow.find(".producto-unidad-medida").on("change", function () {
    actualizarModosCarga(productoRow);
  });

  actualizarModosCarga(productoRow);
}

function calcularTotalProducto(productoRow) {
  const cantidad = parseDecimal(productoRow.find(".producto-cantidad").val());
  const precio = parseDecimal(productoRow.find(".producto-precio").val());
  const subtotalProducto = cantidad * precio;

  productoRow.find(".producto-total").val(formatearNumero(subtotalProducto));
  calcularTotales();
}

// ===============================
// FUNCIONES DE CÁLCULOS TOTALES
// ===============================

function calcularTotales() {
  let totalGeneral = 0;
  const aplicarIva = $("#aplicar_iva").is(":checked");

  $(".product-row:visible").each(function () {
    const productoRow = $(this);
    const cantidad = parseDecimal(productoRow.find(".producto-cantidad").val());
    const precio = parseDecimal(productoRow.find(".producto-precio").val());
    const totalProducto = cantidad * precio;
    totalGeneral += totalProducto;
  });

  let subtotal = 0;
  let totalFinal = 0;

  if (aplicarIva) {
    subtotal = totalGeneral / 1.1;
    totalFinal = totalGeneral;
  } else {
    subtotal = totalGeneral;
    totalFinal = totalGeneral;
  }

  $("#subtotal-valor").text(formatearNumero(subtotal));
  $("#total-con-iva-valor").text(formatearNumero(totalFinal));

  if (aplicarIva) {
    $("#total-label").text("Total con IVA (10%):");
    $("#subtotal-label").text("Subtotal (sin IVA):");
    $("#iva-explanation").hide();
  } else {
    $("#total-label").text("Total:");
    $("#subtotal-label").text("Total productos:");
    $("#iva-explanation").show();
  }

  actualizarInfoCredito();
}

function actualizarInfoCredito() {
  if ($("#cond_pago").val() === "Crédito") {
    const tipoCredito = $("#tipocredito").val();
    const totalFinalTexto = $("#total-con-iva-valor").text();
    const totalFinal =
      parseFloat(totalFinalTexto.replace(/\./g, "").replace(",", ".")) || 0;

    const moneda = $("#moneda").val();
    let simbolo = "₲";
    switch (moneda) {
      case "Dólares":
        simbolo = "USD";
        break;
      case "Real brasileño":
        simbolo = "R$";
        break;
      default:
        simbolo = "₲";
    }

    if (tipoCredito && totalFinal > 0) {
      $("#info-credito").show();
      $("#credito-tipo-display").text("Crédito a " + tipoCredito + " días");
      $("#credito-total-display").text(
        simbolo + " " + formatearNumero(totalFinal)
      );
    } else {
      $("#info-credito").hide();
    }
  }
}

// ===============================
// FUNCIONES DE SELECCIÓN MÚLTIPLE
// ===============================

function abrirSeleccionMultiple() {
  const url =
    config.url_base + "secciones/ventas/seleccionar_productos_modal.php";
  const ventana = window.open(
    url,
    "seleccionProductos",
    "width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,location=no,status=no"
  );

  if (!ventana) {
    notificarError(
      "Por favor, permita las ventanas emergentes para usar esta función"
    );
    return;
  }

  ventana.focus();
}

function verificarProductoDuplicadoMultiple(productoId) {
  let productoExistente = null;

  $(".product-row:visible").each(function () {
    const idExistente = $(this).find('input[name*="[id_producto]"]').val();
    if (idExistente == productoId) {
      productoExistente = $(this).find('input[name*="[descripcion]"]').val();
      return false;
    }
  });

  return productoExistente;
}

function agregarProductosSeleccionados(productosSeleccionados) {
  console.log("Recibiendo productos:", productosSeleccionados);

  if (!productosSeleccionados || productosSeleccionados.length === 0) {
    notificarError("No se recibieron productos para agregar");
    return;
  }

  let productosAgregados = 0;
  let productosDuplicados = 0;
  let productosConError = 0;

  productosSeleccionados.forEach(function (producto) {
    try {
      const productoExistente = verificarProductoDuplicadoMultiple(producto.id);
      if (productoExistente) {
        console.log("Producto duplicado encontrado:", productoExistente);
        productosDuplicados++;
        return;
      }

      const indiceActual = contadorProductos;
      contadorProductos++;

      productosData[indiceActual] = {
        id: parseInt(producto.id),
        descripcion: String(producto.descripcion),
        ncm: String(producto.ncm || ""),
        tipo_producto: String(producto.tipo_producto),
        unidades_medida: Array.isArray(producto.unidades_medida)
          ? producto.unidades_medida.map((um) => String(um))
          : [],
        base64img: String(producto.base64img || ""),
        tipoimg: String(producto.tipoimg || ""),
      };

      const htmlProducto = createProductHTML(
        indiceActual,
        productosData[indiceActual]
      );
      $("#productos-container").append(htmlProducto);

      const productoRow = $(`.product-row[data-index="${indiceActual}"]`);

      configurarEventosProductoCompleto(productoRow);

      productosAgregados++;

      console.log(
        `Producto agregado con índice: ${indiceActual} (mostrado como #${
          indiceActual + 1
        })`
      );
    } catch (error) {
      console.error("Error al agregar producto:", error);
      productosConError++;
    }
  });

  notificarProductosAgregados(
    productosAgregados,
    productosDuplicados,
    productosConError
  );
  calcularTotales();

  if (productosAgregados > 0) {
    $("html, body").animate(
      {
        scrollTop: $("#productos-container").offset().top - 100,
      },
      800
    );
  }
}

// ===============================
// CONFIGURACIÓN DE SELECT2
// ===============================

function inicializarSelect2Clientes() {
  $.fn.select2.defaults.set("language", {
    errorLoading: function () {
      return "No se pudieron cargar los resultados";
    },
    inputTooLong: function (args) {
      return (
        "Por favor, elimine " +
        (args.input.length - args.maximum) +
        " caracteres"
      );
    },
    inputTooShort: function (args) {
      return (
        "Ingrese arriba ↑ " +
        (args.minimum - args.input.length) +
        " o más caracteres para buscar"
      );
    },
    loadingMore: function () {
      return "Cargando más resultados...";
    },
    maximumSelected: function (args) {
      return "Solo puede seleccionar " + args.maximum + " elementos";
    },
    noResults: function () {
      return "No se encontraron resultados";
    },
    searching: function () {
      return "Buscando...";
    },
    removeAllItems: function () {
      return "Eliminar todos los elementos";
    },
  });

  $("#selectCliente").select2({
    placeholder: "Buscar por nombre o RUC/CI",
    allowClear: true,
    minimumInputLength: 2,
    width: "100%",
    ajax: {
      url: "./config/buscar_clientes.php",
      dataType: "json",
      delay: 250,
      data: function (params) {
        return { q: params.term };
      },
      processResults: function (data) {
        return data;
      },
      cache: true,
    },
  });

  $("#selectCliente").on("select2:select", function (e) {
    const data = e.params.data;
    let infoHTML = "";

    if (data.telefono)
      infoHTML += `<i class="fas fa-phone me-1"></i>${data.telefono} `;
    if (data.ruc) infoHTML += `<i class="fas fa-id-card me-1"></i>${data.ruc} `;
    if (data.direccion)
      infoHTML += `<i class="fas fa-map-marker-alt me-1"></i>${data.direccion}`;
    if (data.nro) infoHTML += `<i class="fab fa-whatsapp me-1"></i>${data.nro}`;

    $("#clienteInfo").html(infoHTML);
  });

  $("#selectCliente").on("select2:clear", function () {
    $("#clienteInfo").html("");
  });
}

// ===============================
// EVENT LISTENERS COMUNES
// ===============================

function inicializarEventListeners() {
  $(document).on("input", ".producto-cantidad, .producto-precio", function () {
    const productoRow = $(this).closest(".product-row");
    calcularTotalProducto(productoRow);

    // ✅ NUEVA FUNCIONALIDAD: Actualizar equivalencia de bobinas al cambiar cantidad
    if ($(this).hasClass("producto-cantidad")) {
      mostrarEquivalenciaBobinas(productoRow);
    }
  });

  $(document).on("change", ".producto-unidad-medida", function () {
    const productoRow = $(this).closest(".product-row");
    actualizarModosCarga(productoRow);
  });

  $("#aplicar_iva").change(function () {
    calcularTotales();
    const aplicarIva = $(this).is(":checked");
    $(".product-row .precio-label").each(function () {
      $(this).text(aplicarIva ? "Precio (con IVA)" : "Precio");
    });
  });

  $("#moneda").change(function () {
    const moneda = $(this).val();
    let simbolo = "₲";

    if (moneda === "Dólares") {
      simbolo = "USD";
    } else if (moneda === "Real brasileño") {
      simbolo = "R$";
    }

    $(".moneda-simbolo").text(simbolo);
    $("#moneda-simbolo").text(simbolo);
    $("#total-con-iva-simbolo").text(simbolo);

    calcularTotales();
  });

  $("#cond_pago").change(function () {
    if ($(this).val() === "Crédito") {
      $("#campos-credito").show();
      actualizarInfoCredito();
    } else {
      $("#campos-credito").hide();
      $("#info-credito").hide();
    }
  });

  $("#tipocredito").change(function () {
    actualizarInfoCredito();
  });

  $("#btnSeleccionMultiple").click(function () {
    console.log("Abriendo selección múltiple...");
    abrirSeleccionMultiple();
  });

  $(document).on("click", ".btn-eliminar-producto", function () {
    const productoRow = $(this).closest(".product-row");
    const index = productoRow.data("index");
    const descripcionProducto = productoRow
      .find('input[name*="[descripcion]"]')
      .val();

    productoRow.remove();
    renumerarProductos();
    calcularTotales();

    notificarInfo(
      `Producto eliminado: ${descripcionProducto.substring(0, 40)}...`
    );

    console.log(
      `Producto eliminado. Productos restantes: ${contadorProductos}`
    );
  });

  $(document).on("click", ".btn-ver-imagen", function (e) {
    e.preventDefault();
    const productoRow = $(this).closest(".product-row");
    const base64img = productoRow.find(".producto-imagen-base64").val();
    const tipoimg = productoRow.find(".producto-imagen-tipo").val();
    const descripcion = productoRow.find('input[name*="[descripcion]"]').val();

    if (base64img && tipoimg) {
      $("#modal-producto-imagen").attr(
        "src",
        `data:${tipoimg};base64,${base64img}`
      );
      $("#modal-producto-titulo").text(descripcion);

      const modal = new bootstrap.Modal(
        document.getElementById("imagenProductoModal")
      );
      modal.show();
    } else {
      notificarAdvertencia("No hay imagen disponible para este producto");
    }
  });
}

// ===============================
// VALIDACIÓN DE FORMULARIO
// ===============================

function inicializarValidacionFormulario() {
  $("#formPresupuesto").submit(function (e) {
    if ($(".product-row:visible").length === 0) {
      notificarError("Debe agregar al menos un producto");
      e.preventDefault();
      return false;
    }

    const productosFinales = [];
    $(".product-row:visible").each(function () {
      const $row = $(this);
      const producto = {
        id_producto: $row.find('input[name*="[id_producto]"]').val(),
        descripcion: $row.find('input[name*="[descripcion]"]').val(),
        cantidad: $row.find('input[name*="[cantidad]"]').val(),
        precio: $row.find('input[name*="[precio]"]').val(),
      };
      productosFinales.push(producto);
    });

    const idsProductos = productosFinales.map((p) => p.id_producto);
    const idsUnicos = [...new Set(idsProductos)];

    if (idsProductos.length !== idsUnicos.length) {
      notificarError(
        "❌ Se detectaron productos duplicados en el formulario.<br><br>Por favor, elimine los duplicados antes de continuar."
      );
      e.preventDefault();
      return false;
    }

    const sinPrecio = productosFinales.filter(
      (p) => !p.precio || parseFloat(p.precio) <= 0
    );
    if (sinPrecio.length > 0) {
      let mensaje = "Algunos productos no tienen precio válido:<br>";
      mensaje += sinPrecio
        .map((p) => "• " + p.descripcion.substring(0, 50))
        .join("<br>");

      notificarError(mensaje);
      e.preventDefault();
      return false;
    }

    return true;
  });
}

// ===============================
// FUNCIÓN PRINCIPAL DE INICIALIZACIÓN
// ===============================

function inicializarVentas(configuracion) {
  config = configuracion;

  inicializarSelect2Clientes();
  inicializarEventListeners();
  inicializarValidacionFormulario();

  $("#moneda").trigger("change");
  $("#aplicar_iva").trigger("change");
  $("#cond_pago").trigger("change");

  calcularTotales();

  console.log("🚀 Sistema de ventas inicializado - SOLO carga por bobinas");
}

// Hacer las funciones globalmente accesibles
window.inicializarVentas = inicializarVentas;
window.agregarProductosSeleccionados = agregarProductosSeleccionados;
window.createProductHTML = createProductHTML;
window.calcularTotalProducto = calcularTotalProducto;
window.calcularTotales = calcularTotales;
window.mostrarToast = mostrarToast;
window.notificarExito = notificarExito;
window.notificarError = notificarError;
window.notificarAdvertencia = notificarAdvertencia;
window.notificarInfo = notificarInfo;
window.notificarProductosAgregados = notificarProductosAgregados;
window.renumerarProductos = renumerarProductos;
window.configurarEventosProductoCompleto = configurarEventosProductoCompleto;
window.actualizarModosCarga = actualizarModosCarga;
window.mostrarEquivalenciaBobinas = mostrarEquivalenciaBobinas;
window.ocultarEquivalenciaBobinas = ocultarEquivalenciaBobinas;
