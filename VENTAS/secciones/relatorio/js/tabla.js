/**
 * tabla.js - Tabla de Ventas Detalladas y Modales
 * Relatorio de Ventas USD - Sistema de tablas y modales de productos
 */

/**
 * ========================================
 * TABLA DE VENTAS DETALLADAS
 * ========================================
 */
function cargarTablaDetallada() {
  $("#loadingTabla").show();
  const params = obtenerParametrosFiltros();

  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      ...params,
      action: "ventas_detalladas",
    },
    dataType: "json",
    success: function (response) {
      $("#loadingTabla").hide();
      if (response.success) {
        if (response.datos && response.datos.length > 0) {
          actualizarTablaVentasDetalladas(response.datos);
          $("#totalRegistros").text(`${response.datos.length} registros`);
        } else {
          mostrarTablaVacia(
            "No se encontraron ventas para los filtros seleccionados"
          );
          $("#totalRegistros").text("0 registros");
        }
      } else {
        console.error("Error cargando ventas detalladas:", response.error);
        mostrarTablaVacia(
          "Error al cargar las ventas: " +
            (response.error || "Error desconocido")
        );
        $("#totalRegistros").text("Error");
      }
    },
    error: function (xhr, status, error) {
      $("#loadingTabla").hide();
      console.error("Error en petición ventas detalladas:", error);
      mostrarTablaVacia("Error de conexión al cargar las ventas");
      $("#totalRegistros").text("Error");
    },
  });
}

function actualizarTablaVentasDetalladas(ventas) {
  ventasData = ventas;
  paginaActual = 1;
  mostrarPagina();
  crearControlesPaginacion();
  $("#totalRegistros").text(`${ventas.length} registros`);
}

function mostrarPagina() {
  const inicio = (paginaActual - 1) * registrosPorPagina;
  const fin = inicio + registrosPorPagina;
  const ventasPagina = ventasData.slice(inicio, fin);

  let html = "";
  ventasPagina.forEach(function (venta, index) {
    const fecha = formatearFecha(venta.fecha_venta);
    const vendedor = venta.nombre_vendedor || "Sin asignar";
    const estado = venta.estado || "Sin estado";
    const total = formatearMoneda(venta.monto_total || 0);
    const tipoPago =
      (venta.cond_pago || "") +
      (venta.tipo_pago ? " - " + venta.tipo_pago : "");
    const productos = venta.cantidad_productos || 0;

    html += `<tr>
            <td><strong>#${venta.id}</strong></td>
            <td>${fecha}</td>
            <td>${venta.cliente || "Sin cliente"}</td>`;

    if (PUEDE_VER_TODOS) {
      html += `<td><i class="fas fa-user-tie me-1"></i>${vendedor}</td>`;
    }

    html += `
            <td><span class="badge bg-${obtenerColorEstado(
              estado
            )}">${estado}</span></td>
            <td class="text-end"><strong>${total}</strong></td>
            <td>${tipoPago}</td>
            <td class="text-center"><span class="badge bg-secondary">${productos}</span></td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary" 
                        onclick="verProductosVenta(${venta.id}, '${(
      venta.cliente || "Sin cliente"
    ).replace(/'/g, "\\'")}', '${total}')"
                        title="Ver productos de esta venta">
                    <i class="fas fa-eye me-1"></i>Ver
                </button>
            </td>
        </tr>`;
  });

  $("#tablaVentasDetalladas tbody").html(html);
}

function crearControlesPaginacion() {
  const totalPaginas = Math.ceil(ventasData.length / registrosPorPagina);

  if (totalPaginas <= 1) {
    $("#controlesPaginacion").html("");
    return;
  }

  let html = `
        <nav aria-label="Paginación de ventas">
            <ul class="pagination justify-content-center">
                <li class="page-item ${paginaActual === 1 ? "disabled" : ""}">
                    <button class="page-link" onclick="cambiarPagina(${
                      paginaActual - 1
                    })">Anterior</button>
                </li>`;

  // Mostrar páginas
  for (let i = 1; i <= totalPaginas; i++) {
    if (
      i === paginaActual ||
      i === 1 ||
      i === totalPaginas ||
      (i >= paginaActual - 1 && i <= paginaActual + 1)
    ) {
      html += `<li class="page-item ${i === paginaActual ? "active" : ""}">
                        <button class="page-link" onclick="cambiarPagina(${i})">${i}</button>
                     </li>`;
    } else if (i === paginaActual - 2 || i === paginaActual + 2) {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    }
  }

  html += `
                <li class="page-item ${
                  paginaActual === totalPaginas ? "disabled" : ""
                }">
                    <button class="page-link" onclick="cambiarPagina(${
                      paginaActual + 1
                    })">Siguiente</button>
                </li>
            </ul>
        </nav>
        <div class="text-center text-muted">
            Mostrando ${
              (paginaActual - 1) * registrosPorPagina + 1
            } - ${Math.min(
    paginaActual * registrosPorPagina,
    ventasData.length
  )} de ${ventasData.length} registros
        </div>`;

  $("#controlesPaginacion").html(html);
}

function cambiarPagina(nuevaPagina) {
  const totalPaginas = Math.ceil(ventasData.length / registrosPorPagina);
  if (nuevaPagina >= 1 && nuevaPagina <= totalPaginas) {
    paginaActual = nuevaPagina;
    mostrarPagina();
    crearControlesPaginacion();
  }
}

function mostrarTablaVacia(mensaje) {
  const colspan = PUEDE_VER_TODOS ? "9" : "8";
  $("#tablaVentasDetalladas tbody").html(`
        <tr>
            <td colspan="${colspan}" class="text-center py-5">
                <div class="text-muted">
                    <i class="fas fa-info-circle me-2" style="font-size: 2rem;"></i>
                    <br><br>
                    <h5>${mensaje}</h5>
                    <p>Ajuste los filtros para obtener resultados</p>
                </div>
            </td>
        </tr>
    `);
}

/**
 * ========================================
 * MODAL DE PRODUCTOS DE VENTA
 * ========================================
 */
function verProductosVenta(ventaId, cliente, total) {
  // Actualizar información de la venta en el modal
  $("#ventaId").text("#" + ventaId);
  $("#ventaCliente").text(cliente);
  $("#totalVentaModal").text(total);

  // Mostrar loading y ocultar contenido
  $("#loadingProductosModal").show();
  $("#tablaProductosContainer").hide();
  $("#noProductosMessage").hide();

  // Abrir modal
  const modal = new bootstrap.Modal(
    document.getElementById("modalProductosVenta")
  );
  modal.show();

  // Hacer petición AJAX para obtener productos
  $.ajax({
    url: "relatorio.php",
    method: "GET",
    data: {
      action: "productos_venta",
      venta_id: ventaId,
    },
    dataType: "json",
    success: function (response) {
      $("#loadingProductosModal").hide();

      if (response.success && response.datos && response.datos.length > 0) {
        mostrarProductosEnModal(response.datos);
        $("#tablaProductosContainer").show();
      } else {
        $("#noProductosMessage").show();
        console.log("No se encontraron productos para la venta:", ventaId);

        // Mostrar mensaje más descriptivo
        const mensajeError =
          response.error || "No hay productos registrados para esta venta";
        $("#noProductosMessage h5").text(mensajeError);
      }
    },
    error: function (xhr, status, error) {
      $("#loadingProductosModal").hide();
      $("#noProductosMessage").show();
      console.error("Error al cargar productos de la venta:", error);
      mostrarToast("Error al cargar los productos de la venta", "error");

      // Mostrar error específico
      $("#noProductosMessage h5").text("Error de conexión");
      $("#noProductosMessage p").text(
        "No se pudieron cargar los productos. Intente nuevamente."
      );
    },
  });
}

function mostrarProductosEnModal(productos) {
  let html = "";
  let totalCalculado = 0;
  let cantidadTotal = 0;

  productos.forEach(function (producto, index) {
    const cantidad = parseInt(producto.cantidad) || 0;
    const precioUnitario = parseFloat(producto.precio_unitario) || 0;
    const totalUsd = parseFloat(producto.total_usd) || 0;
    const subtotal = cantidad * precioUnitario;

    totalCalculado += totalUsd; // Usar el total convertido
    cantidadTotal += cantidad;

    // Alternar colores de fila
    const rowClass = index % 2 === 0 ? "" : "table-light";

    // Determinar si hay diferencia entre total guardado y calculado
    const diferencia = Math.abs(totalUsd - subtotal);
    const hayDiferencia = diferencia > 0.01;
    const claseDiferencia = hayDiferencia ? "text-warning" : "";

    html += `
        <tr class="${rowClass}">
            <td>
                <div>
                    <strong>${
                      producto.nombre_producto || "Producto sin nombre"
                    }</strong>
                    ${
                      producto.descripcion &&
                      producto.descripcion !== "Sin categoría"
                        ? `<br><small class="text-muted"><i class="fas fa-tag me-1"></i>${producto.descripcion}</small>`
                        : ""
                    }
                    ${
                      producto.codigo && producto.codigo !== "Sin código"
                        ? `<br><small class="text-info"><i class="fas fa-barcode me-1"></i>${producto.codigo}</small>`
                        : ""
                    }
                    ${
                      producto.unidad_medida &&
                      producto.unidad_medida !== "Unidad"
                        ? `<br><small class="text-secondary"><i class="fas fa-ruler me-1"></i>${producto.unidad_medida}</small>`
                        : ""
                    }
                </div>
            </td>
            <td class="text-center">
                <span class="badge bg-primary fs-6">${cantidad}</span>
                ${
                  producto.unidad_medida && producto.unidad_medida !== "Unidad"
                    ? `<br><small class="text-muted">${producto.unidad_medida}</small>`
                    : ""
                }
            </td>
            <td class="text-end">
                <strong>${formatearMoneda(precioUnitario)}</strong>
                ${
                  producto.precio_original_formateado &&
                  producto.moneda_original !== "USD"
                    ? `<br><small class="text-muted">Original: ${producto.precio_original_formateado}</small>`
                    : ""
                }
            </td>
            <td class="text-end">
                <strong class="text-success ${claseDiferencia}">${formatearMoneda(
      totalUsd
    )}</strong>
                ${
                  hayDiferencia
                    ? `<br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Dif: ${formatearMoneda(
                        diferencia
                      )}</small>`
                    : ""
                }
                ${
                  producto.total_original_formateado &&
                  producto.moneda_original !== "USD"
                    ? `<br><small class="text-muted">Original: ${producto.total_original_formateado}</small>`
                    : ""
                }
            </td>
        </tr>
    `;
  });

  $("#tablaProductosModal").html(html);
  $("#totalVentaModal").text(formatearMoneda(totalCalculado));

  // Mostrar información detallada
  const infoDetallada = `${productos.length} productos (${cantidadTotal} unidades)`;
  $("#cantidadProductosModal").text(infoDetallada);

  // Agregar información adicional si hay conversiones
  const hayConversiones = productos.some((p) => p.moneda_original !== "USD");
  if (hayConversiones) {
    const tasasUsadas = Object.keys(tasasCache)
      .filter((k) => k !== "USD")
      .map((k) => {
        const simbolos = { PYG: "₲", BRL: "R$" };
        return `${simbolos[k] || ""} ${tasasCache[k]}`;
      })
      .join(" | ");

    const notaConversion = `
            <small class="text-info d-block mt-1">
                <i class="fas fa-exchange-alt me-1"></i>Valores convertidos a USD
                <br>Tasas aplicadas: ${tasasUsadas}
            </small>
        `;
    $("#cantidadProductosModal").html(infoDetallada + notaConversion);
  }
}

/**
 * ========================================
 * FUNCIONES AUXILIARES DE ESTADO
 * ========================================
 */
function obtenerColorEstado(estado) {
  switch (estado?.toLowerCase()) {
    case "completado":
    case "aprobado":
    case "finalizado":
    case "confirmado":
      return "success";
    case "pendiente":
    case "en revision":
    case "en proceso":
      return "warning";
    case "rechazado":
    case "cancelado":
      return "danger";
    case "pagado":
      return "primary";
    default:
      return "secondary";
  }
}
