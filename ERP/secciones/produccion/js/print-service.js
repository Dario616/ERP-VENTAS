/**
 * print-service.js - Servicio de Impresión
 * Maneja auto-impresión, PDFs y reimpresión de etiquetas
 */

/**
 * Función de autoprint mejorada con debugging completo
 */
function autoPrintUnificado() {
  console.log("🔥 ==> autoPrintUnificado() INICIADA");
  console.log("🔍 appConfigUnificado:", appConfigUnificado);

  // Verificar elementos DOM
  const indicator = document.getElementById("print-indicator-unificado");
  const printFrame = document.getElementById("print-frame-unificado");

  console.log("🔍 Indicador encontrado:", !!indicator);
  console.log("🔍 iFrame encontrado:", !!printFrame);

  if (printFrame) {
    console.log("🔍 iFrame src:", printFrame.src);
    console.log("🔍 iFrame readyState:", printFrame.readyState);
    console.log("🔍 iFrame contentWindow:", !!printFrame.contentWindow);
  }

  if (indicator) {
    indicator.style.display = "block";
    console.log("✅ Indicador mostrado");
  }

  // Aumentar timeout y agregar más debugging
  setTimeout(function () {
    console.log("⏰ Timeout ejecutado - intentando imprimir...");

    try {
      if (printFrame && printFrame.contentWindow) {
        console.log("🖨️ Método 1: Intentando imprimir iframe...");

        // Verificar si el contenido está cargado
        try {
          const doc =
            printFrame.contentDocument || printFrame.contentWindow.document;
          console.log("📄 Documento del iframe:", !!doc);
          console.log("📄 readyState del documento:", doc.readyState);

          if (doc.readyState === "complete") {
            console.log("✅ Documento completamente cargado");
            printFrame.contentWindow.print();
            console.log("🖨️ Comando print() ejecutado en iframe");
          } else {
            console.log("⚠️ Documento no completamente cargado, esperando...");
            throw new Error("Documento no cargado completamente");
          }
        } catch (accessError) {
          console.log(
            "❌ Error accediendo al documento del iframe:",
            accessError
          );
          throw accessError;
        }

        setTimeout(() => {
          if (indicator) {
            indicator.style.display = "none";
            console.log("🫥 Indicador ocultado después de impresión");
          }
        }, 3000);
      } else {
        throw new Error("iframe o contentWindow no disponible");
      }
    } catch (e) {
      console.log("🚨 Error con iframe, usando método fallback:", e);

      // Método fallback con nueva ventana
      const autoPrintUrl = appConfigUnificado.autoPrintUrl || "";
      console.log("🔗 URL para fallback:", autoPrintUrl);

      if (autoPrintUrl) {
        console.log("🚀 Abriendo nueva ventana para impresión...");

        const printWindow = window.open(
          autoPrintUrl,
          "_blank",
          "width=800,height=600,scrollbars=yes"
        );

        if (printWindow) {
          console.log("✅ Nueva ventana abierta");

          printWindow.onload = function () {
            console.log("📄 Nueva ventana cargada, imprimiendo...");
            setTimeout(function () {
              try {
                printWindow.print();
                console.log("🖨️ Print ejecutado en nueva ventana");

                setTimeout(function () {
                  printWindow.close();
                  console.log("🚪 Nueva ventana cerrada");
                }, 2000);
              } catch (printError) {
                console.error(
                  "❌ Error imprimiendo en nueva ventana:",
                  printError
                );
              }
            }, 500);
          };

          // Timeout de seguridad por si onload no se ejecuta
          setTimeout(() => {
            if (printWindow && !printWindow.closed) {
              console.log("⏰ Timeout de seguridad - intentando imprimir...");
              try {
                printWindow.print();
              } catch (timeoutError) {
                console.error(
                  "❌ Error en timeout de seguridad:",
                  timeoutError
                );
              }
            }
          }, 3000);
        } else {
          console.error(
            "❌ No se pudo abrir nueva ventana (bloqueador de popups?)"
          );
          alert(
            "🚫 No se pudo abrir la ventana de impresión. Verifique que no esté bloqueando popups."
          );
        }
      } else {
        console.error("❌ No hay URL configurada para autoprint");
        alert("❌ Error: No se configuró la URL de impresión");
      }

      if (indicator) {
        indicator.style.display = "none";
        console.log("🫥 Indicador ocultado después de error");
      }
    }
  }, 2000); // Aumentar timeout a 2 segundos

  console.log("🔚 ==> autoPrintUnificado() FINALIZADA");
}

/**
 * Abrir PDF seleccionado según tipo de producto - ACTUALIZADO con soporte para PAÑOS
 */
function abrirPDFSeleccionada() {
  console.log("📄 Abriendo PDF específico...");

  if (!registroSeleccionado) {
    alert("Por favor, seleccione un registro antes de abrir el PDF.");
    return;
  }

  try {
    const tipoProducto = registroSeleccionado.tipo;
    let pdfUrl;

    if (tipoProducto === "TOALLITAS") {
      // 🏷️ TOALLITAS: usar etiquetaToallitas.php
      pdfUrl = `pdf/etiquetaToallitas.php?id_orden=${registroSeleccionado.orden}&id_stock=${registroSeleccionado.id}`;
      console.log(`🏷️ PDF para TOALLITAS - ID: ${registroSeleccionado.id}`);
    } else if (tipoProducto === "PAÑOS") {
      // 🧽 PAÑOS: usar etiquetaPanos.php - NUEVO
      pdfUrl = `pdf/etiquetaPanos.php?id_orden=${registroSeleccionado.orden}&id_stock=${registroSeleccionado.id}`;
      console.log(`🧽 PDF para PAÑOS - ID: ${registroSeleccionado.id}`);
    } else {
      // 📦 TNT/SPUNLACE/LAMINADORA: usar pdf.php o pdf_1.php según largura
      const larguraInput = document.getElementById("largura");
      const larguraValue = larguraInput
        ? parseFloat(larguraInput.value) || 0
        : 0;

      let archivoPDF;
      if (larguraValue > 0 && larguraValue < 1.0) {
        archivoPDF = "pdf/etiquetatntAngosto.php";
        console.log(
          `📦 PDF para producto angosto (${larguraValue}m) - Tipo: ${tipoProducto}`
        );
      } else {
        archivoPDF = "pdf/etiquetatntAncho.php";
        console.log(
          `📏 PDF para producto ancho (${larguraValue}m) - Tipo: ${tipoProducto}`
        );
      }

      // Para LAMINADORA, usar tipo TNT en el PDF
      const tipoParaPdf = tipoProducto === "LAMINADORA" ? "TNT" : tipoProducto;

      pdfUrl = `${archivoPDF}?id_orden=${registroSeleccionado.orden}&id_stock=${
        registroSeleccionado.id
      }&tipo=${encodeURIComponent(tipoParaPdf)}`;
    }

    // Abrir en nueva pestaña
    window.open(
      pdfUrl,
      "_blank",
      "width=1000,height=700,scrollbars=yes,resizable=yes"
    );

    console.log(
      "✅ PDF abierto para registro específico:",
      registroSeleccionado.id,
      "- Tipo:",
      tipoProducto,
      "- SIN vinculación a venta"
    );
  } catch (error) {
    console.error("💥 Error al abrir PDF:", error);
    alert("Error al abrir el PDF: " + error.message);
  }
}

/**
 * Reimprimir etiqueta seleccionada
 */
function reimprimirSeleccionada() {
  console.log("🔍 Iniciando reimpresión específica...");

  if (!registroSeleccionado) {
    alert("Por favor, seleccione un registro de la tabla antes de reimprimir.");
    return;
  }

  const formReimprimir = document.getElementById("formReimprimirUnificado");
  if (!formReimprimir) {
    alert(
      "Error crítico: Formulario de reimpresión no encontrado. Recargue la página."
    );
    return;
  }

  try {
    // Incluir todos los datos necesarios
    document.getElementById("orden_reimprimir_unificado").value =
      registroSeleccionado.orden;
    document.getElementById("tipo_reimprimir_unificado").value =
      registroSeleccionado.tipo;
    document.getElementById("stock_reimprimir_unificado").value =
      registroSeleccionado.id;

    const btnReimprimir = document.getElementById("btnReimprimirUnificado");
    if (btnReimprimir) {
      const originalText = btnReimprimir.innerHTML;
      btnReimprimir.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Reimprimiendo...';
      btnReimprimir.disabled = true;

      setTimeout(() => {
        btnReimprimir.innerHTML = originalText;
        btnReimprimir.disabled = false;
      }, 5000);
    }

    console.log(
      "🚀 Enviando formulario con ID específico:",
      registroSeleccionado.id,
      ""
    );
    formReimprimir.submit();
  } catch (error) {
    console.error("💥 Error al procesar reimpresión:", error);
    alert("Error inesperado al procesar la reimpresión: " + error.message);
  }
}

/**
 * Finalizar orden de producción
 */
function finalizarOrden() {
  console.log("🏁 Iniciando finalización de orden...");

  // Verificar que hay una orden cargada
  const tipoProductoInput = document.getElementById("tipo_producto_actual");
  if (!tipoProductoInput) {
    alert("No hay ninguna orden cargada para finalizar.");
    return;
  }

  // Obtener el número de orden actual
  const numeroOrdenInput = document.querySelector('input[name="numero_orden"]');
  if (!numeroOrdenInput || !numeroOrdenInput.value) {
    alert("No se puede determinar el número de orden a finalizar.");
    return;
  }

  const numeroOrden = numeroOrdenInput.value;

  // Confirmación con advertencia actualizada
  const confirmMessage =
    `🏁 ¿Está seguro de que desea FINALIZAR la Orden de Producción #${numeroOrden}?\n\n` +
    `⚠️ Al finalizar la orden:\n` +
    `• Se marcará como completada en el sistema y ya no sera una orden Pendiente\n` +
    `¿Desea continuar?`;

  if (!confirm(confirmMessage)) {
    console.log("❌ Finalización cancelada por el usuario");
    return;
  }

  const formFinalizar = document.getElementById("formFinalizarOrden");
  if (!formFinalizar) {
    alert(
      "Error crítico: Formulario de finalización no encontrado. Recargue la página."
    );
    return;
  }

  try {
    // Configurar el formulario
    document.getElementById("numero_orden_finalizar").value = numeroOrden;

    const btnFinalizar = document.getElementById("btnFinalizarOrden");
    if (btnFinalizar) {
      const originalText = btnFinalizar.innerHTML;
      btnFinalizar.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Finalizando...';
      btnFinalizar.disabled = true;

      // Restaurar botón después de 5 segundos en caso de error
      setTimeout(() => {
        btnFinalizar.innerHTML = originalText;
        btnFinalizar.disabled = false;
      }, 5000);
    }

    console.log(
      `🚀 Enviando formulario de finalización - Orden: ${numeroOrden} (productos NO vinculados)`
    );
    formFinalizar.submit();
  } catch (error) {
    console.error("💥 Error al procesar finalización:", error);
    alert("Error inesperado al procesar la finalización: " + error.message);
  }
}

/**
 * Eliminar registro seleccionado
 */
function eliminarRegistroSeleccionado() {
  console.log("🗑️ Iniciando eliminación de registro...");

  if (!registroSeleccionado) {
    alert("Por favor, seleccione un registro de la tabla antes de eliminar.");
    return;
  }

  // Confirmación con detalles del registro (incluyendo tara para todos los tipos)
  const tipoProducto = registroSeleccionado.tipo;
  const numeroItem = registroSeleccionado.numero;
  const pesoInfo = `Peso Bruto: ${registroSeleccionado.pesoBruto} | Tara: ${registroSeleccionado.tara} | Peso Líquido: ${registroSeleccionado.pesoLiquido}`;

  const confirmMessage =
    `⚠️ ¿Está seguro de que desea ELIMINAR este registro?\n\n` +
    `Registro: #${numeroItem}\n` +
    `Tipo: ${tipoProducto}\n` +
    `${pesoInfo}\n` +
    `⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER`;

  if (!confirm(confirmMessage)) {
    console.log("❌ Eliminación cancelada por el usuario");
    return;
  }

  const formEliminar = document.getElementById("formEliminarUnificado");
  if (!formEliminar) {
    alert(
      "Error crítico: Formulario de eliminación no encontrado. Recargue la página."
    );
    return;
  }

  try {
    // Configurar los datos del formulario
    document.getElementById("id_registro_eliminar").value =
      registroSeleccionado.id;
    document.getElementById("numero_orden_eliminar").value =
      registroSeleccionado.orden;

    const btnEliminar = document.getElementById("btnEliminarUnificado");
    if (btnEliminar) {
      const originalText = btnEliminar.innerHTML;
      btnEliminar.innerHTML =
        '<i class="fas fa-spinner fa-spin me-2"></i>Eliminando...';
      btnEliminar.disabled = true;

      // Restaurar botón después de 5 segundos en caso de error
      setTimeout(() => {
        btnEliminar.innerHTML = originalText;
        btnEliminar.disabled = false;
      }, 5000);
    }

    console.log(
      "🚀 Enviando formulario de eliminación - ID:",
      registroSeleccionado.id,
      "- Producto NO vinculado a venta"
    );
    formEliminar.submit();
  } catch (error) {
    console.error("💥 Error al procesar eliminación:", error);
    alert("Error inesperado al procesar la eliminación: " + error.message);
  }
}

// Exponer funciones globalmente
window.autoPrintUnificado = autoPrintUnificado;
window.abrirPDFSeleccionada = abrirPDFSeleccionada;
window.reimprimirSeleccionada = reimprimirSeleccionada;
window.finalizarOrden = finalizarOrden;
window.eliminarRegistroSeleccionado = eliminarRegistroSeleccionado;
