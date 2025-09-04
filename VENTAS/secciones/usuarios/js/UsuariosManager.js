/**
 * Usuarios Management System - JavaScript Module
 * Manejo de funcionalidades para la gestión de usuarios
 */

// Objeto principal para manejar todas las funcionalidades de usuarios
const UsuariosManager = {
  // Configuración global (se carga desde PHP)
  config: null,

  /**
   * Inicializar el módulo con la configuración
   */
  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("UsuariosManager inicializado", this.config);
  },

  /**
   * Vincular eventos generales
   */
  bindEvents: function () {
    // Eventos que se ejecutan en todas las páginas
    this.initBootstrapTooltips();
  },

  /**
   * Inicializar tooltips de Bootstrap
   */
  initBootstrapTooltips: function () {
    if (typeof bootstrap !== "undefined") {
      const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
      );
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  },

  /**
   * Módulo de validación de formularios
   */
  formValidator: {
    /**
     * Inicializar validación de formularios
     */
    init: function () {
      const form = document.querySelector("form");
      if (
        form &&
        (form.action.includes("registrar.php") ||
          form.action.includes("editar.php"))
      ) {
        form.addEventListener("submit", this.validateForm);
        this.bindPasswordToggle();
        this.bindPasswordMatch();
      }
    },

    /**
     * Validar formulario antes del envío
     */
    validateForm: function (event) {
      let valid = true;

      // Validar nombre
      const nombre = document.getElementById("nombre");
      if (nombre && nombre.value.trim().length === 0) {
        UsuariosManager.utils.showAlert("El nombre es obligatorio.", "danger");
        valid = false;
      }

      // Validar usuario
      const usuario = document.getElementById("usuario");
      if (usuario && usuario.value.trim().length === 0) {
        UsuariosManager.utils.showAlert("El usuario es obligatorio.", "danger");
        valid = false;
      }

      // Validar rol
      const rol = document.getElementById("rol");
      if (rol && rol.value === "") {
        UsuariosManager.utils.showAlert("Debe seleccionar un rol.", "danger");
        valid = false;
      }

      // Verificar si estamos en página de edición o registro
      const cambiarContrasenia = document.getElementById("cambiar_contrasenia");
      const contrasenia = document.getElementById("contrasenia");

      if (cambiarContrasenia) {
        // Estamos en edición - solo validar si se está cambiando contraseña
        if (cambiarContrasenia.checked) {
          const confirmarContrasenia = document.getElementById(
            "confirmar_contrasenia"
          );

          if (contrasenia.value.length < 4) {
            UsuariosManager.utils.showAlert(
              "La contraseña debe tener al menos 4 caracteres.",
              "danger"
            );
            valid = false;
          }

          if (contrasenia.value !== confirmarContrasenia.value) {
            UsuariosManager.utils.showAlert(
              "Las contraseñas no coinciden.",
              "danger"
            );
            valid = false;
          }
        }
      } else if (contrasenia) {
        // Estamos en registro - contraseña obligatoria
        const confirmarContrasenia = document.getElementById(
          "confirmar_contrasenia"
        );

        if (contrasenia.value.length < 4) {
          UsuariosManager.utils.showAlert(
            "La contraseña debe tener al menos 4 caracteres.",
            "danger"
          );
          valid = false;
        }

        if (contrasenia.value !== confirmarContrasenia.value) {
          UsuariosManager.utils.showAlert(
            "Las contraseñas no coinciden.",
            "danger"
          );
          valid = false;
        }
      }

      if (!valid) {
        event.preventDefault();
      }
    },

    /**
     * Vincular toggle de mostrar/ocultar contraseña
     */
    bindPasswordToggle: function () {
      // Toggle para contraseña principal
      const togglePassword = document.getElementById("togglePassword");
      if (togglePassword) {
        togglePassword.addEventListener("click", function () {
          const passwordInput = document.getElementById("contrasenia");
          const type =
            passwordInput.getAttribute("type") === "password"
              ? "text"
              : "password";
          passwordInput.setAttribute("type", type);
          this.querySelector("i").classList.toggle("fa-eye");
          this.querySelector("i").classList.toggle("fa-eye-slash");
        });
      }

      // Toggle para confirmar contraseña
      const toggleConfirmPassword = document.getElementById(
        "toggleConfirmPassword"
      );
      if (toggleConfirmPassword) {
        toggleConfirmPassword.addEventListener("click", function () {
          const passwordInput = document.getElementById(
            "confirmar_contrasenia"
          );
          const type =
            passwordInput.getAttribute("type") === "password"
              ? "text"
              : "password";
          passwordInput.setAttribute("type", type);
          this.querySelector("i").classList.toggle("fa-eye");
          this.querySelector("i").classList.toggle("fa-eye-slash");
        });
      }
    },

    /**
     * Validación en tiempo real de coincidencia de contraseñas
     */
    bindPasswordMatch: function () {
      const contrasenia = document.getElementById("contrasenia");
      const confirmarContrasenia = document.getElementById(
        "confirmar_contrasenia"
      );

      if (contrasenia && confirmarContrasenia) {
        confirmarContrasenia.addEventListener("input", function () {
          if (this.value && contrasenia.value) {
            if (this.value === contrasenia.value) {
              this.classList.remove("is-invalid");
              this.classList.add("is-valid");
            } else {
              this.classList.remove("is-valid");
              this.classList.add("is-invalid");
            }
          } else {
            this.classList.remove("is-valid", "is-invalid");
          }
        });
      }
    },
  },

  /**
   * Módulo para la página de índice/listado
   */
  indexPage: {
    /**
     * Inicializar funcionalidades de la página de índice
     */
    init: function () {
      this.bindDeleteConfirmation();
      this.bindSearchEvents();
      this.loadStatistics();
    },

    /**
     * Vincular confirmación de eliminación
     */
    bindDeleteConfirmation: function () {
      // La función se expone globalmente para uso en onclick
      window.confirmarEliminar = this.confirmarEliminar;
    },

    /**
     * Mostrar modal de confirmación para eliminar usuario
     */
    confirmarEliminar: function (id) {
      const btnEliminar = document.getElementById("btn-eliminar");
      const modal = document.getElementById("confirmarEliminarModal");

      if (btnEliminar && UsuariosManager.config) {
        btnEliminar.href = `${UsuariosManager.config.url_base}secciones/usuarios/index.php?eliminar=${id}`;
      }

      if (modal && typeof bootstrap !== "undefined") {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      }
    },

    /**
     * Vincular eventos de búsqueda
     */
    bindSearchEvents: function () {
      const searchInput = document.getElementById("searchUsuarios");
      if (searchInput) {
        searchInput.addEventListener("input", this.handleSearch.bind(this));
      }
    },

    /**
     * Manejar búsqueda en tiempo real
     */
    handleSearch: function () {
      const filtro = document
        .getElementById("searchUsuarios")
        .value.toLowerCase();
      const filas = document.querySelectorAll("#usuariosTableBody tr");

      filas.forEach((fila) => {
        const texto = fila.textContent.toLowerCase();
        const visible = texto.includes(filtro);
        fila.style.display = visible ? "" : "none";
      });

      // Mostrar mensaje si no hay resultados
      const filasVisibles = Array.from(filas).filter(
        (fila) => fila.style.display !== "none"
      );
      this.toggleNoResultsMessage(
        filasVisibles.length === 0 && filtro.length > 0
      );
    },

    /**
     * Mostrar/ocultar mensaje de sin resultados
     */
    toggleNoResultsMessage: function (show) {
      let messageRow = document.getElementById("noResultsRow");

      if (show && !messageRow) {
        const tbody = document.getElementById("usuariosTableBody");
        messageRow = document.createElement("tr");
        messageRow.id = "noResultsRow";
        messageRow.innerHTML = `
          <td colspan="5" class="text-center text-muted py-4">
            <i class="fas fa-search me-2"></i>No se encontraron usuarios que coincidan con la búsqueda
          </td>
        `;
        tbody.appendChild(messageRow);
      } else if (!show && messageRow) {
        messageRow.remove();
      }
    },

    /**
     * Cargar estadísticas via AJAX
     */
    loadStatistics: function () {
      if (!UsuariosManager.config) return;

      fetch(
        `${UsuariosManager.config.url_base}secciones/usuarios/index.php?action=obtener_estadisticas`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.displayStatistics(data.estadisticas);
          }
        })
        .catch((error) => {
          console.error("Error cargando estadísticas:", error);
        });
    },

    /**
     * Mostrar estadísticas en la interfaz
     */
    displayStatistics: function (stats) {
      // Actualizar contadores en tarjetas de estadísticas si existen
      const totalUsuarios = document.getElementById("totalUsuarios");
      const totalAdmins = document.getElementById("totalAdmins");
      const totalVendedores = document.getElementById("totalVendedores");

      if (totalUsuarios) totalUsuarios.textContent = stats.total_usuarios || 0;
      if (totalAdmins) totalAdmins.textContent = stats.administradores || 0;
      if (totalVendedores) totalVendedores.textContent = stats.vendedores || 0;
    },
  },

  /**
   * Módulo para páginas de edición
   */
  editPage: {
    /**
     * Inicializar funcionalidades de edición
     */
    init: function () {
      this.bindPasswordToggle();
    },

    /**
     * Manejar toggle de cambiar contraseña
     */
    bindPasswordToggle: function () {
      const cambiarCheckbox = document.getElementById("cambiar_contrasenia");
      const passwordFields = document.getElementById("password-fields");

      if (cambiarCheckbox && passwordFields) {
        cambiarCheckbox.addEventListener("change", function () {
          if (this.checked) {
            passwordFields.style.display = "block";
            document.getElementById("contrasenia").setAttribute("required", "");
            document
              .getElementById("confirmar_contrasenia")
              .setAttribute("required", "");
          } else {
            passwordFields.style.display = "none";
            document.getElementById("contrasenia").removeAttribute("required");
            document
              .getElementById("confirmar_contrasenia")
              .removeAttribute("required");
            // Limpiar valores
            document.getElementById("contrasenia").value = "";
            document.getElementById("confirmar_contrasenia").value = "";
            // Limpiar clases de validación
            document
              .getElementById("contrasenia")
              .classList.remove("is-valid", "is-invalid");
            document
              .getElementById("confirmar_contrasenia")
              .classList.remove("is-valid", "is-invalid");
          }
        });
      }
    },
  },

  /**
   * Módulo de búsqueda avanzada con autocompletado
   */
  searchModule: {
    /**
     * Inicializar búsqueda con autocompletado
     */
    init: function () {
      this.bindAutoComplete();
    },

    /**
     * Autocompletado de usuarios
     */
    bindAutoComplete: function () {
      const searchInput = document.getElementById("busquedaAvanzada");
      if (!searchInput) return;

      let timeoutId;

      searchInput.addEventListener("input", function () {
        clearTimeout(timeoutId);
        const termino = this.value.trim();

        if (termino.length >= 2) {
          timeoutId = setTimeout(() => {
            UsuariosManager.searchModule.buscarUsuarios(termino);
          }, 300);
        } else {
          UsuariosManager.searchModule.ocultarResultados();
        }
      });

      // Ocultar resultados al hacer clic fuera
      document.addEventListener("click", function (e) {
        if (!e.target.closest("#busquedaAvanzada")) {
          UsuariosManager.searchModule.ocultarResultados();
        }
      });
    },

    /**
     * Buscar usuarios via AJAX
     */
    buscarUsuarios: function (termino) {
      if (!UsuariosManager.config) return;

      fetch(
        `${
          UsuariosManager.config.url_base
        }secciones/usuarios/index.php?action=buscar_usuarios&termino=${encodeURIComponent(
          termino
        )}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.mostrarResultados(data.usuarios);
          }
        })
        .catch((error) => {
          console.error("Error en búsqueda:", error);
        });
    },

    /**
     * Mostrar resultados de búsqueda
     */
    mostrarResultados: function (usuarios) {
      let container = document.getElementById("resultadosBusqueda");

      if (!container) {
        container = document.createElement("div");
        container.id = "resultadosBusqueda";
        container.className =
          "position-absolute bg-white border rounded-3 shadow-lg mt-1 w-100";
        container.style.zIndex = "1000";
        document
          .getElementById("busquedaAvanzada")
          .parentNode.appendChild(container);
      }

      if (usuarios.length === 0) {
        container.innerHTML = `
          <div class="p-3 text-muted text-center">
            <i class="fas fa-search me-2"></i>No se encontraron usuarios
          </div>
        `;
      } else {
        let html = '<div class="list-group list-group-flush">';
        usuarios.forEach((usuario) => {
          html += `
            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-bold">${usuario.nombre}</div>
                <small class="text-muted">@${usuario.usuario}</small>
              </div>
              <div>
                ${this.getRoleBadge(usuario.rol)}
              </div>
            </div>
          `;
        });
        html += "</div>";
        container.innerHTML = html;
      }

      container.style.display = "block";
    },

    /**
     * Ocultar resultados de búsqueda
     */
    ocultarResultados: function () {
      const container = document.getElementById("resultadosBusqueda");
      if (container) {
        container.style.display = "none";
      }
    },

    /**
     * Obtener badge del rol
     */
    getRoleBadge: function (rol) {
      switch (rol) {
        case "1":
          return '<span class="badge bg-danger"><i class="fas fa-user-shield me-1"></i>Admin</span>';
        case "2":
          return '<span class="badge bg-success"><i class="fas fa-user-tie me-1"></i>Vendedor</span>';
        case "3":
          return '<span class="badge bg-warning"><i class="fas fa-calculator me-1"></i>Contador</span>';
        case "4":
          return '<span class="badge bg-primary"><i class="fas fa-cogs me-1"></i>PCP</span>';
        default:
          return '<span class="badge bg-secondary"><i class="fas fa-user me-1"></i>Usuario</span>';
      }
    },
  },

  /**
   * Utilidades generales
   */
  utils: {
    /**
     * Mostrar alerta
     */
    showAlert: function (message, type = "info") {
      // Crear alerta temporal
      const alertDiv = document.createElement("div");
      alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
      alertDiv.innerHTML = `
        <i class="fas fa-exclamation-circle me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;

      // Insertar al inicio del container principal
      const container = document.querySelector(".container-fluid");
      if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-hide después de 5 segundos
        setTimeout(() => {
          alertDiv.remove();
        }, 5000);
      }
    },

    /**
     * Validar formato de email (si se usa)
     */
    isValidEmail: function (email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    },

    /**
     * Formatear fecha
     */
    formatDate: function (dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString("es-PY");
    },
  },
};

// Funciones globales para compatibilidad con onclick en HTML
window.confirmarEliminar = function (id) {
  UsuariosManager.indexPage.confirmarEliminar(id);
};

window.togglePasswordFields = function () {
  UsuariosManager.editPage.bindPasswordToggle();
};

// Inicialización automática cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Buscar la configuración global en el HTML
  if (typeof USUARIOS_CONFIG !== "undefined") {
    UsuariosManager.init(USUARIOS_CONFIG);
  }

  // Detectar qué página estamos viendo e inicializar módulos correspondientes
  const currentPath = window.location.pathname;

  if (
    currentPath.includes("registrar.php") ||
    currentPath.includes("editar.php")
  ) {
    // Páginas de formulario
    UsuariosManager.formValidator.init();
    if (currentPath.includes("editar.php")) {
      UsuariosManager.editPage.init();
    }
  } else if (
    currentPath.includes("index.php") ||
    currentPath.endsWith("/usuarios/")
  ) {
    // Página de listado
    UsuariosManager.indexPage.init();
    UsuariosManager.searchModule.init();
  }
});

// Exportar para uso en módulos (si es necesario)
if (typeof module !== "undefined" && module.exports) {
  module.exports = UsuariosManager;
}
