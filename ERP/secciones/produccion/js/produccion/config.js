/**
 * config.js - Configuración e Inicialización del Sistema
 * VERSIÓN LIMPIA: Con validación de peso teórico
 */

// Variables globales para selección
let registroSeleccionado = null;
let appConfigUnificado = {};

// Variables globales para validación de peso teórico
let datosPromedioOrden = {
  peso_teorico: 0,
  peso_promedio: 0, // Mantener compatibilidad
  rango_15_inferior: 0,
  rango_15_superior: 0,
  bobinas_pacote: 1,
  gramatura: 0,
  largura: 0,
  metragem: 0,
  success: false,
};

/**
 * Inicializar la aplicación Unificada
 */
function initializeAppUnificado(config = {}) {
  appConfigUnificado = config;
  console.log("🚀 Configuración de la aplicación Unificada cargada:", appConfigUnificado);
}

/**
 * Detectar tipo de producto actual
 */
function obtenerTipoProductoActual() {
  const tipoInput = document.getElementById("tipo_producto_actual");
  return tipoInput ? tipoInput.value : "GENERICO";
}

/**
 * Configurar datos de peso teórico desde PHP
 */
function configurarValidacionPeso(datosPeso) {
  if (datosPeso && datosPeso.success) {
    datosPromedioOrden = datosPeso;
    console.log("📊 Validación de peso teórico configurada para", 
                datosPromedioOrden.bobinas_pacote, "bobina(s):", datosPromedioOrden);
  } else {
    datosPromedioOrden = { ...datosPeso, success: false };
    console.log("📊 Validación de peso teórico no disponible:", 
                datosPeso?.error || "Sin especificaciones técnicas");
  }
}

/**
 * Configurar auto-focus en campos
 */
function setupAutoFocus() {
  const pesoBrutoInput = document.getElementById("peso_bruto");
  const numeroOrdenInput = document.getElementById("numero_orden");

  if (pesoBrutoInput && pesoBrutoInput.offsetParent !== null) {
    pesoBrutoInput.focus();
    pesoBrutoInput.select();
    console.log("🎯 Auto-focus en peso bruto");
  } else if (numeroOrdenInput) {
    numeroOrdenInput.focus();
    console.log("🎯 Auto-focus en número de orden");
  }
}

/**
 * Funciones de búsqueda por ID
 */
function buscarPorId() {
  const id = document.getElementById("buscar_id").value;
  if (!id) return alert("Ingrese un ID");

  window.location.href = `?orden=${ordenActual}&filtro_id=${id}`;
}

function limpiarFiltro() {
  window.location.href = `?orden=${ordenActual}`;
}

function seleccionarId(id) {
  const row = document.querySelector(`[data-id="${id}"]`);
  if (row) {
    seleccionarRegistro(row);
    document.getElementById("resultado_busqueda").style.display = "none";
  }
}

function buscarNuevaOrden() {
  const totalItems = document.querySelectorAll('.registro-row').length;
  
  let mensaje = "¿Está seguro de que desea buscar una nueva orden?";
  
  if (totalItems > 0) {
    mensaje += `\n\nLa orden actual tiene ${totalItems} items registrados.`;
    mensaje += "\nEste cambio no afectará los datos guardados.";
  }

  if (confirm(mensaje)) {
    window.location.href = window.location.pathname;
  }
}

/**
 * Inicialización principal del sistema
 */
document.addEventListener("DOMContentLoaded", function () {
  try {
    console.log("🚀 Inicializando sistema de producción con validación de peso teórico...");

    const tipoProducto = obtenerTipoProductoActual();
    console.log("🔍 Tipo de producto detectado:", tipoProducto);

    // Configuraciones comunes
    setupAutoFocus();
    setupRegistroRowListeners();
    setupValidacion();
    setupFormValidation();
    actualizarInterfazSeleccion();
    handleSuccessfulRegistration();
    setupReimpresionLoteListeners();

    // Event listeners para peso bruto y tara (todos los tipos)
    const pesoBrutoInput = document.getElementById("peso_bruto");
    const taraInput = document.getElementById("tara");

    if (pesoBrutoInput) {
      pesoBrutoInput.addEventListener("input", calcularPesoLiquido);
    }
    if (taraInput) {
      taraInput.addEventListener("input", calcularPesoLiquido);
    }

    // Configuraciones específicas para TNT/SPUNLACE/LAMINADORA
    if (tipoProducto !== "TOALLITAS" && tipoProducto !== "PAÑOS") {
      const larguraInput = document.getElementById("largura");
      if (larguraInput) {
        larguraInput.addEventListener("input", toggleBobinasPacoteField);
        larguraInput.addEventListener("change", toggleBobinasPacoteField);

        if (larguraInput.value) {
          toggleBobinasPacoteField();
        }
      }

      const bobinasPacoteInput = document.getElementById("bobinas_pacote");
      if (bobinasPacoteInput) {
        bobinasPacoteInput.addEventListener("input", calcularPesoLiquido);
        bobinasPacoteInput.addEventListener("change", calcularPesoLiquido);
      }
    }

    // Configurar orden actual
    const numeroOrdenInput = document.querySelector('input[name="numero_orden"]');
    if (numeroOrdenInput && numeroOrdenInput.value) {
      window.ordenActual = numeroOrdenInput.value;
    } else {
      const urlParams = new URLSearchParams(window.location.search);
      window.ordenActual = urlParams.get("orden") || "";
    }

    console.log("🔍 Orden actual definida:", window.ordenActual);

    // 🆕 NUEVO: Configurar recálculo automático de peso teórico
    if (typeof setupRecalculoPesoTeorico === 'function') {
      setupRecalculoPesoTeorico();
      console.log('📊 Configurado recálculo automático de peso teórico');
    }

    console.log("✅ Inicialización completa del sistema con validación de peso teórico");
    
  } catch (error) {
    console.error("💥 Error en la inicialización:", error);
  }
});

// Exponer funciones globalmente
window.initializeAppUnificado = initializeAppUnificado;
window.configurarValidacionPeso = configurarValidacionPeso;
window.obtenerTipoProductoActual = obtenerTipoProductoActual;
window.buscarPorId = buscarPorId;
window.limpiarFiltro = limpiarFiltro;
window.seleccionarId = seleccionarId;
window.buscarNuevaOrden = buscarNuevaOrden;