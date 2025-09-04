/**
 * Configuración global y estado de la aplicación de rejillas
 * Version: 4.6
 */

// Configuración global
let REJILLAS_CONFIG = {
  urlBase: "",
  usuario: "Usuario",
  debug: false,
  version: "4.6",
};

// Estado global de la aplicación
const appState = {
  rejillaSeleccionada: null,
  filtrosActivos: {},
  modoVisualizacion: "grid",
  autoRefresh: true,
  ultimaActualizacion: null,
  // Campos para búsqueda y filtros
  filtros: {
    busquedaTexto: "",
    clienteSeleccionado: "",
    productoSeleccionado: "",
    tipoUnidadSeleccionado: "",
  },
  itemsOriginales: [],
  itemsFiltrados: [],
};

// Variables globales para componentes
let modalDetallesRejilla;
let intervaloRefresh;
let datosRejillas = [];
let estadisticasGlobales = {};

/**
 * Inicialización de la configuración global
 * Replica exactamente la lógica del archivo original
 */
function inicializarConfiguracion() {
  console.log(
    "Sistema de Rejillas v" +
      REJILLAS_CONFIG.version +
      " iniciado (Versión Modularizada)"
  );

  try {
    // Hacer exactamente lo mismo que el archivo original
    if (typeof window.REJILLAS_CONFIG !== "undefined") {
      REJILLAS_CONFIG = { ...REJILLAS_CONFIG, ...window.REJILLAS_CONFIG };
    }

    console.log("Configuración inicializada correctamente");
    return true;
  } catch (error) {
    console.error("Error inicializando configuración:", error);
    return false;
  }
}

/**
 * Resetear estado de filtros
 */
function resetearFiltros() {
  appState.filtros = {
    busquedaTexto: "",
    clienteSeleccionado: "",
    productoSeleccionado: "",
    tipoUnidadSeleccionado: "",
  };
}

/**
 * Actualizar timestamp de última actualización
 */
function actualizarTimestampActualizacion() {
  appState.ultimaActualizacion = new Date();
}

/**
 * Obtener configuración actual
 */
function obtenerConfiguracion() {
  return REJILLAS_CONFIG;
}

/**
 * Obtener estado actual
 */
function obtenerEstado() {
  return appState;
}

// Exportar para uso global
window.REJILLAS_CONFIG = REJILLAS_CONFIG;
window.appState = appState;
window.inicializarConfiguracion = inicializarConfiguracion;
window.resetearFiltros = resetearFiltros;
window.actualizarTimestampActualizacion = actualizarTimestampActualizacion;
