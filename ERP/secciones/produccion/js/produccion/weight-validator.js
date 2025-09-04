/**
 * weight-validator.js - Validador de Peso Teórico ±15%
 * VERSIÓN LIMPIA: Solo validación basada en peso teórico
 */

// Variables globales para confirmación
let esperandoConfirmacionPeso = false;
let datosConfirmacionPendiente = null;

/**
 * Recalcular peso teórico cuando cambien metragem o bobinas_pacote
 */
function recalcularPesoTeorico() {
  if (!datosPromedioOrden.success) {
    return;
  }

  const numeroOrden = window.ordenActual;
  const metragemInput = document.getElementById("metragem");
  const bobinasPacoteInput = document.getElementById("bobinas_pacote");

  if (!numeroOrden || !metragemInput || !bobinasPacoteInput) {
    return;
  }

  const metragem = parseInt(metragemInput.value) || datosPromedioOrden.metragem;
  const bobinasPacote = parseInt(bobinasPacoteInput.value) || 1;

  // Solo recalcular si los valores cambiaron
  if (
    metragem === datosPromedioOrden.metragem &&
    bobinasPacote === datosPromedioOrden.bobinas_pacote
  ) {
    return;
  }

  // Petición AJAX para recalcular peso teórico
  fetch(window.location.pathname, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: new URLSearchParams({
      numero_orden: numeroOrden,
      metragem: metragem,
      bobinas_pacote: bobinasPacote,
      obtener_peso_teorico: "1",
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        datosPromedioOrden = data;
        console.log("📊 Peso teórico actualizado:", data);

        // Revalidar peso bruto actual
        const pesoBrutoInput = document.getElementById("peso_bruto");
        if (pesoBrutoInput && pesoBrutoInput.value) {
          validarPesoBruto(pesoBrutoInput);
        }
      }
    })
    .catch((error) => {
      console.error("Error recalculando peso teórico:", error);
    });
}

/**
 * Validar peso bruto contra peso teórico ±15%
 */
function validarPesoBruto(input) {
  if (!datosPromedioOrden.success || !datosPromedioOrden.peso_teorico) {
    limpiarAdvertenciasPeso(input);
    return true;
  }

  const pesoIngresado = parseFloat(input.value) || 0;
  const contenedorAdvertencia =
    document.getElementById("advertencia-peso-container") ||
    crearContenedorAdvertencia(input);

  if (pesoIngresado <= 0) {
    limpiarAdvertenciasPeso(input);
    return true;
  }

  const dentroDelRango =
    pesoIngresado >= datosPromedioOrden.rango_15_inferior &&
    pesoIngresado <= datosPromedioOrden.rango_15_superior;

  if (!dentroDelRango) {
    mostrarAdvertenciaPeso(input, pesoIngresado, contenedorAdvertencia);
    return false;
  } else {
    mostrarPesoValido(input, contenedorAdvertencia);
    return true;
  }
}

/**
 * Crear contenedor de advertencias
 */
function crearContenedorAdvertencia(input) {
  const contenedor = document.createElement("div");
  contenedor.id = "advertencia-peso-container";
  contenedor.className = "mt-2";
  contenedor.style.maxHeight = "100px";
  contenedor.style.overflow = "hidden";

  const inputGroup = input.closest(".input-group");
  if (inputGroup && inputGroup.parentNode) {
    inputGroup.parentNode.insertBefore(contenedor, inputGroup.nextSibling);
  }

  return contenedor;
}

/**
 * Mostrar advertencia basada en peso teórico
 */
function mostrarAdvertenciaPeso(input, pesoIngresado, contenedor) {
  const diferenciaPorcentaje = Math.abs(
    ((pesoIngresado - datosPromedioOrden.peso_teorico) /
      datosPromedioOrden.peso_teorico) *
      100
  );

  const esAlto = pesoIngresado > datosPromedioOrden.rango_15_superior;
  const icono = esAlto ? "fas fa-arrow-up" : "fas fa-arrow-down";
  const color = esAlto ? "danger" : "warning";

  const textoBobinas =
    datosPromedioOrden.bobinas_pacote > 1
      ? ` (${datosPromedioOrden.bobinas_pacote} bobinas)`
      : "";

  contenedor.innerHTML = `
    <div class="alert alert-${color} py-1 px-2 mb-1 d-flex align-items-center justify-content-between" style="font-size: 0.85rem; border-radius: 6px;">
        <div class="d-flex align-items-center">
            <i class="${icono} me-2"></i>
            <span><strong>Peso fuera del rango teórico${textoBobinas}:</strong> ${pesoIngresado}kg vs ${
    datosPromedioOrden.peso_teorico
  }kg (±${diferenciaPorcentaje.toFixed(1)}%)</span>
        </div>
    </div>
    <small class="text-muted">Especificaciones: ${
      datosPromedioOrden.gramatura
    }g/m² × ${datosPromedioOrden.metragem}m × ${
    datosPromedioOrden.largura
  }m</small>
  `;

  input.style.borderColor = esAlto ? "#dc3545" : "#ffc107";
  input.style.borderWidth = "2px";
}

/**
 * Mostrar peso válido basado en peso teórico
 */
function mostrarPesoValido(input, contenedor) {
  const textoBobinas =
    datosPromedioOrden.bobinas_pacote > 1
      ? ` (${datosPromedioOrden.bobinas_pacote} bobinas)`
      : "";

  contenedor.innerHTML = `
    <div class="alert alert-success py-1 px-2 mb-1 d-flex align-items-center" style="font-size: 0.85rem; border-radius: 6px;">
        <i class="fas fa-check-circle me-2"></i>
        <span><strong>Peso teórico válido${textoBobinas}:</strong> Dentro del rango ±15% (${datosPromedioOrden.peso_teorico}kg teórico)</span>
    </div>
    <small class="text-muted">Especificaciones: ${datosPromedioOrden.gramatura}g/m² × ${datosPromedioOrden.metragem}m × ${datosPromedioOrden.largura}m</small>
  `;

  input.style.borderColor = "#28a745";
  input.style.borderWidth = "2px";
}

/**
 * Limpiar advertencias de peso
 */
function limpiarAdvertenciasPeso(input) {
  const contenedor = document.getElementById("advertencia-peso-container");
  if (contenedor) {
    contenedor.innerHTML = "";
  }

  input.style.borderColor = "";
  input.style.borderWidth = "";
}

/**
 * Crear modal de confirmación de peso
 */
function crearModalConfirmacionPeso() {
  if (document.getElementById("modalConfirmacionPeso")) {
    return;
  }

  const modalHTML = `
    <div class="modal fade" id="modalConfirmacionPeso" tabindex="-1" aria-labelledby="modalConfirmacionPesoLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="modalConfirmacionPesoLabel">
              <i class="fas fa-exclamation-triangle me-2"></i>Peso fuera del rango teórico
            </h5>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger" role="alert">
              <div class="row">
                <div class="col-md-6">
                  <strong>Peso ingresado:</strong><br>
                  <span id="pesoIngresadoModal" class="text-danger fs-5"></span>
                </div>
                <div class="col-md-6">
                  <strong>Peso teórico:</strong><br>
                  <span id="pesoPromedioModal" class="text-primary fs-5"></span>
                </div>
              </div>
              <hr>
              <div class="row">
                <div class="col-md-6">
                  <strong>Diferencia:</strong><br>
                  <span id="diferenciaPorcentajeModal" class="text-danger fs-6"></span>
                </div>
                <div class="col-md-6">
                  <strong>Rango válido ±15%:</strong><br>
                  <span id="rangoValidoModal" class="text-success fs-6"></span>
                </div>
              </div>
            </div>
            
            <div class="text-center">
              <p class="mb-2"><strong id="tipoProductoModal"></strong></p>
              <small class="text-muted" id="baseDatosModal"></small>
            </div>
            
            <div class="mt-3">
              <p class="text-center mb-3">
                <i class="fas fa-exclamation-circle text-danger fa-2x"></i>
              </p>
              <p class="text-center">
                <strong>Favor revisar el peso Bruto</strong>
              </p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="btnCancelarPeso">
              <i class="fas fa-times me-2"></i>Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHTML);
}

/**
 * Mostrar modal de confirmación con peso teórico
 */
function mostrarModalConfirmacionPeso(pesoIngresado, datosPromedio) {
  crearModalConfirmacionPeso();

  const diferenciaPorcentaje = Math.abs(
    ((pesoIngresado - datosPromedio.peso_teorico) /
      datosPromedio.peso_teorico) *
      100
  );

  const textoBobinas =
    datosPromedio.bobinas_pacote > 1
      ? `Paquetes de ${datosPromedio.bobinas_pacote} bobinas`
      : "Bobinas individuales";

  // Rellenar datos en el modal
  document.getElementById(
    "pesoIngresadoModal"
  ).textContent = `${pesoIngresado}kg`;
  document.getElementById(
    "pesoPromedioModal"
  ).textContent = `${datosPromedio.peso_teorico}kg`;
  document.getElementById(
    "diferenciaPorcentajeModal"
  ).textContent = `${diferenciaPorcentaje.toFixed(1)}%`;
  document.getElementById(
    "rangoValidoModal"
  ).textContent = `${datosPromedio.rango_15_inferior}kg - ${datosPromedio.rango_15_superior}kg`;
  document.getElementById("tipoProductoModal").textContent = textoBobinas;
  document.getElementById(
    "baseDatosModal"
  ).textContent = `Peso teórico: ${datosPromedio.gramatura}g/m² × ${datosPromedio.metragem}m × ${datosPromedio.largura}m`;

  // Configurar eventos de botones
  const modal = new bootstrap.Modal(
    document.getElementById("modalConfirmacionPeso")
  );

  document.getElementById("btnCancelarPeso").onclick = function () {
    esperandoConfirmacionPeso = false;
    datosConfirmacionPendiente = null;
    modal.hide();

    const pesoBrutoInput = document.getElementById("peso_bruto");
    if (pesoBrutoInput) {
      pesoBrutoInput.focus();
      pesoBrutoInput.select();
    }

    console.log(
      "❌ Usuario canceló el registro - Peso fuera del rango teórico"
    );
  };

  modal.show();
}

/**
 * Validar antes de enviar el formulario
 */
function validarFormularioAntesDenEnvio(event) {
  const pesoBrutoInput = document.getElementById("peso_bruto");

  if (!pesoBrutoInput) return true;

  if (esperandoConfirmacionPeso) {
    event.preventDefault();
    return false;
  }

  const pesoValido = validarPesoBruto(pesoBrutoInput);

  if (
    !pesoValido &&
    datosPromedioOrden.success &&
    datosPromedioOrden.peso_teorico
  ) {
    const pesoIngresado = parseFloat(pesoBrutoInput.value) || 0;

    event.preventDefault();

    esperandoConfirmacionPeso = true;
    datosConfirmacionPendiente = {
      form: event.target,
      peso: pesoIngresado,
    };

    mostrarModalConfirmacionPeso(pesoIngresado, datosPromedioOrden);
    return false;
  }

  return true;
}

/**
 * Configurar event listeners para recálculo automático
 */
function setupRecalculoPesoTeorico() {
  const metragemInput = document.getElementById("metragem");
  const bobinasPacoteInput = document.getElementById("bobinas_pacote");

  if (metragemInput) {
    metragemInput.addEventListener("input", recalcularPesoTeorico);
    metragemInput.addEventListener("change", recalcularPesoTeorico);
  }

  if (bobinasPacoteInput) {
    bobinasPacoteInput.addEventListener("input", recalcularPesoTeorico);
    bobinasPacoteInput.addEventListener("change", recalcularPesoTeorico);
  }
}

// Exponer funciones globalmente
window.validarPesoBruto = validarPesoBruto;
window.validarFormularioAntesDenEnvio = validarFormularioAntesDenEnvio;
window.recalcularPesoTeorico = recalcularPesoTeorico;
window.setupRecalculoPesoTeorico = setupRecalculoPesoTeorico;
