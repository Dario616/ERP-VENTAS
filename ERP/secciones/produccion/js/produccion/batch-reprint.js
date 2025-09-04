/**
 * batch-reprint.js - Reimpresión en Lote
 * Maneja la funcionalidad de reimpresión masiva de etiquetas
 */

/**
 * Abrir modal de reimpresión en lote
 */
function abrirModalReimpresionLote() {
  console.log("📋 Abriendo modal de reimpresión en lote...");

  try {
    // Mostrar el modal
    const modal = new bootstrap.Modal(
      document.getElementById("modalReimpresionLote")
    );
    modal.show();

    // Limpiar formulario
    limpiarFormularioLote();

    console.log("✅ Modal de reimpresión en lote abierto");
  } catch (error) {
    console.error("💥 Error abriendo modal de lote:", error);
    alert("Error al abrir el modal de reimpresión en lote: " + error.message);
  }
}

/**
 * Limpiar formulario de lote
 */
function limpiarFormularioLote() {
  document.getElementById("item_desde").value = "";
  document.getElementById("item_hasta").value = "";
  document.getElementById("preview-rango").style.display = "none";
  document.getElementById("btnConfirmarReimpresionLote").disabled = true;
}

/**
 * Actualizar rango seleccionado
 */
function actualizarRangoSeleccionado() {
  const itemDesde = parseInt(document.getElementById("item_desde").value) || 0;
  const itemHasta = parseInt(document.getElementById("item_hasta").value) || 0;

  const previewElement = document.getElementById("preview-rango");
  const previewTexto = document.getElementById("preview-texto");
  const previewCantidad = document.getElementById("preview-cantidad");
  const previewAdvertencias = document.getElementById("preview-advertencias");
  const btnConfirmar = document.getElementById("btnConfirmarReimpresionLote");

  // Limpiar advertencias
  previewAdvertencias.innerHTML = "";

  if (itemDesde > 0 && itemHasta > 0) {
    const cantidad = Math.max(0, itemHasta - itemDesde + 1);

    // Mostrar vista previa
    previewTexto.textContent = `Items #${itemDesde} al #${itemHasta}`;
    previewCantidad.textContent = `${cantidad} etiqueta${
      cantidad !== 1 ? "s" : ""
    }`;
    previewElement.style.display = "block";

    // Validaciones
    let esValido = true;
    const advertencias = [];

    if (itemDesde > itemHasta) {
      advertencias.push(
        '<i class="fas fa-exclamation-triangle text-danger me-1"></i>El item inicial no puede ser mayor al final'
      );
      esValido = false;
    }

    if (itemDesde < 1) {
      advertencias.push(
        '<i class="fas fa-exclamation-triangle text-warning me-1"></i>El item inicial debe ser mayor a 0'
      );
      esValido = false;
    }

    if (cantidad > 100) {
      advertencias.push(
        '<i class="fas fa-exclamation-triangle text-warning me-1"></i>Máximo 100 etiquetas por lote (seleccionadas: ' +
          cantidad +
          ")"
      );
      esValido = false;
    }

    // Mostrar advertencias
    if (advertencias.length > 0) {
      previewAdvertencias.innerHTML = advertencias
        .map((adv) => `<small>${adv}</small>`)
        .join("<br>");
    }

    // Habilitar/deshabilitar botón
    btnConfirmar.disabled = !esValido || cantidad === 0;

    if (esValido && cantidad > 0) {
      previewCantidad.className = "badge bg-success";
    } else {
      previewCantidad.className = "badge bg-danger";
    }
  } else {
    previewElement.style.display = "none";
    btnConfirmar.disabled = true;
  }

  console.log(
    `📊 Rango actualizado: ${itemDesde}-${itemHasta} (${cantidad || 0} items)`
  );
}

/**
 * Confirmar reimpresión en lote
 */
function confirmarReimpresionLote() {
  const itemDesde = parseInt(document.getElementById("item_desde").value);
  const itemHasta = parseInt(document.getElementById("item_hasta").value);
  const cantidad = itemHasta - itemDesde + 1;

  if (!itemDesde || !itemHasta || cantidad <= 0) {
    alert("Por favor, especifique un rango válido de items.");
    return;
  }

  // Confirmación final
  const tipoProducto = document.querySelector(
    'input[name="tipo_producto_lote"]'
  ).value;
  const numeroOrden = document.querySelector(
    'input[name="numero_orden_lote"]'
  ).value;

  const confirmMessage =
    `🖨️ ¿Confirma la reimpresión en lote?\n\n` +
    `Orden: #${numeroOrden}\n` +
    `Tipo: ${tipoProducto}\n` +
    `Rango: Items #${itemDesde} al #${itemHasta}\n` +
    `Total: ${cantidad} etiqueta${cantidad !== 1 ? "s" : ""}\n\n` +
    `Se generará un PDF con todas las etiquetas seleccionadas.`;

  if (!confirm(confirmMessage)) {
    console.log("❌ Reimpresión en lote cancelada por el usuario");
    return;
  }

  try {
    const btnConfirmar = document.getElementById("btnConfirmarReimpresionLote");
    const originalText = btnConfirmar.innerHTML;

    // Cambiar estado del botón
    btnConfirmar.innerHTML =
      '<i class="fas fa-spinner fa-spin me-2"></i>Generando PDF...';
    btnConfirmar.disabled = true;

    // Restaurar después de un tiempo
    setTimeout(() => {
      btnConfirmar.innerHTML = originalText;
      btnConfirmar.disabled = false;
    }, 5000);

    // Enviar formulario
    const form = document.getElementById("formReimpresionLote");
    console.log("🚀 Enviando reimpresión en lote:", {
      itemDesde,
      itemHasta,
      cantidad,
      tipoProducto,
    });

    form.submit();

    // Cerrar modal después de enviar
    setTimeout(() => {
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("modalReimpresionLote")
      );
      if (modal) {
        modal.hide();
      }
    }, 500);
  } catch (error) {
    console.error("💥 Error enviando reimpresión en lote:", error);
    alert("Error al procesar la reimpresión en lote: " + error.message);
  }
}

/**
 * Configurar event listeners para reimpresión en lote
 */
function setupReimpresionLoteListeners() {
  // Event listeners para campos de rango
  const itemDesdeInput = document.getElementById("item_desde");
  const itemHastaInput = document.getElementById("item_hasta");

  if (itemDesdeInput) {
    itemDesdeInput.addEventListener("input", actualizarRangoSeleccionado);
    itemDesdeInput.addEventListener("change", actualizarRangoSeleccionado);
  }

  if (itemHastaInput) {
    itemHastaInput.addEventListener("input", actualizarRangoSeleccionado);
    itemHastaInput.addEventListener("change", actualizarRangoSeleccionado);
  }

  // Event listener para Enter en los campos
  [itemDesdeInput, itemHastaInput].forEach((input) => {
    if (input) {
      input.addEventListener("keypress", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          const btnConfirmar = document.getElementById(
            "btnConfirmarReimpresionLote"
          );
          if (!btnConfirmar.disabled) {
            confirmarReimpresionLote();
          }
        }
      });
    }
  });

  console.log("🔧 Event listeners de reimpresión en lote configurados");
}

/**
 * Validar rango de items antes de procesar
 * @param {number} desde - Item inicial
 * @param {number} hasta - Item final
 * @returns {Object} Resultado de validación
 */
function validarRangoItems(desde, hasta) {
  const resultado = {
    valido: true,
    errores: [],
    advertencias: [],
    cantidad: 0
  };

  // Convertir a números
  desde = parseInt(desde) || 0;
  hasta = parseInt(hasta) || 0;
  
  resultado.cantidad = Math.max(0, hasta - desde + 1);

  // Validaciones básicas
  if (desde <= 0) {
    resultado.errores.push("El item inicial debe ser mayor a 0");
    resultado.valido = false;
  }

  if (hasta <= 0) {
    resultado.errores.push("El item final debe ser mayor a 0");
    resultado.valido = false;
  }

  if (desde > hasta) {
    resultado.errores.push("El item inicial no puede ser mayor al final");
    resultado.valido = false;
  }

  // Advertencias
  if (resultado.cantidad > 50) {
    resultado.advertencias.push(`Se imprimirán ${resultado.cantidad} etiquetas. Esto puede tomar varios minutos.`);
  }

  if (resultado.cantidad > 100) {
    resultado.errores.push(`Máximo 100 etiquetas por lote. Seleccionadas: ${resultado.cantidad}`);
    resultado.valido = false;
  }

  return resultado;
}

/**
 * Mostrar preview del lote con validaciones mejoradas
 * @param {number} desde - Item inicial  
 * @param {number} hasta - Item final
 */
function mostrarPreviewLote(desde, hasta) {
  const validacion = validarRangoItems(desde, hasta);
  const previewElement = document.getElementById("preview-rango");
  const previewTexto = document.getElementById("preview-texto");
  const previewCantidad = document.getElementById("preview-cantidad");
  const previewAdvertencias = document.getElementById("preview-advertencias");
  const btnConfirmar = document.getElementById("btnConfirmarReimpresionLote");

  if (validacion.cantidad > 0) {
    // Mostrar preview
    previewTexto.textContent = `Items #${desde} al #${hasta}`;
    previewCantidad.textContent = `${validacion.cantidad} etiqueta${validacion.cantidad !== 1 ? "s" : ""}`;
    previewElement.style.display = "block";

    // Mostrar errores y advertencias
    const mensajes = [];
    
    validacion.errores.forEach(error => {
      mensajes.push(`<small class="text-danger"><i class="fas fa-times-circle me-1"></i>${error}</small>`);
    });
    
    validacion.advertencias.forEach(advertencia => {
      mensajes.push(`<small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>${advertencia}</small>`);
    });

    previewAdvertencias.innerHTML = mensajes.join("<br>");

    // Configurar botón y estilos
    btnConfirmar.disabled = !validacion.valido;
    previewCantidad.className = validacion.valido ? "badge bg-success" : "badge bg-danger";

  } else {
    previewElement.style.display = "none";
    btnConfirmar.disabled = true;
  }
}

// Exponer funciones globalmente
window.abrirModalReimpresionLote = abrirModalReimpresionLote;
window.limpiarFormularioLote = limpiarFormularioLote;
window.actualizarRangoSeleccionado = actualizarRangoSeleccionado;
window.confirmarReimpresionLote = confirmarReimpresionLote;
window.setupReimpresionLoteListeners = setupReimpresionLoteListeners;
window.validarRangoItems = validarRangoItems;
window.mostrarPreviewLote = mostrarPreviewLote;