const ClienteManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("ClienteManager inicializado", this.config);
  },

  bindEvents: function () {
    this.initBootstrapTooltips();
  },

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

  countrySelector: {
    selectCountry: function (country) {
      document.querySelectorAll(".flag-selector-small").forEach((flag) => {
        flag.classList.remove("active");
      });

      const selectedFlag = document.querySelector(
        `[data-country="${country}"]`
      );
      if (selectedFlag) {
        selectedFlag.classList.add("active");
      }

      const paisField = document.getElementById("pais");
      if (paisField) {
        paisField.value = country;
      }

      const rucField = document.getElementById("ruc-field");
      const cnpjField = document.getElementById("cnpj-field");
      const ieField = document.getElementById("ie-field");
      const rucInput = document.getElementById("ruc");
      const cnpjInput = document.getElementById("cnpj");
      const ieInput = document.getElementById("ie");

      if (country === "PY") {
        if (rucField) rucField.style.display = "block";
        if (cnpjField) cnpjField.style.display = "none";
        if (ieField) ieField.style.display = "none";

        if (cnpjInput) cnpjInput.value = "";
        if (ieInput) ieInput.value = "";
      } else if (country === "BR") {
        if (rucField) rucField.style.display = "none";
        if (cnpjField) cnpjField.style.display = "block";
        if (ieField) ieField.style.display = "block";
        if (rucInput) rucInput.value = "";
      }
    },

    init: function () {
      const paisField = document.getElementById("pais");
      if (paisField) {
        const paisSeleccionado = paisField.value || "PY";
        this.selectCountry(paisSeleccionado);
      }
    },
  },

  fieldFormatters: {
    init: function () {
      this.initCNPJFormatter();
      this.initIEFormatter();
    },

    initCNPJFormatter: function () {
      const cnpjField = document.getElementById("cnpj");
      if (cnpjField) {
        cnpjField.addEventListener("input", function (e) {
          let value = e.target.value.replace(/\D/g, "");

          if (value.length <= 14) {
            value = value.replace(/^(\d{2})(\d)/, "$1.$2");
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
            value = value.replace(/\.(\d{3})(\d)/, ".$1/$2");
            value = value.replace(/(\d{4})(\d)/, "$1-$2");

            e.target.value = value;
          }
        });
      }
    },

    initIEFormatter: function () {
      const ieField = document.getElementById("ie");
      if (ieField) {
        ieField.addEventListener("input", function (e) {
          let value = e.target.value.replace(/\D/g, "");
          e.target.value = value;
        });
      }
    },
  },

  formValidator: {
    init: function () {
      const form = document.querySelector("form");
      if (
        form &&
        (form.action.includes("registrar.php") ||
          form.action.includes("editar.php"))
      ) {
        form.addEventListener("submit", this.validateForm);
      }
    },

    validateForm: function (event) {
      let valid = true;
      const paisField = document.getElementById("pais");

      if (!paisField) return true;

      const pais = paisField.value;

      if (pais === "PY") {
        const ruc = document.getElementById("ruc");
        if (ruc && ruc.value.trim() !== "" && ruc.value.trim().length < 6) {
          alert("Por favor, ingrese un RUC válido.");
          valid = false;
        }
      } else if (pais === "BR") {
        const cnpj = document.getElementById("cnpj");
        if (
          cnpj &&
          cnpj.value.trim() !== "" &&
          !/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/.test(cnpj.value.trim())
        ) {
          alert(
            "Por favor, ingrese un CNPJ válido con el formato XX.XXX.XXX/XXXX-XX."
          );
          valid = false;
        }

        const ie = document.getElementById("ie");
        if (ie && ie.value.trim() !== "") {
          const ieNumeros = ie.value.replace(/\D/g, "");
          if (ieNumeros.length < 6 || ieNumeros.length > 14) {
            alert("La Inscripción Estatal debe tener entre 6 y 14 dígitos.");
            valid = false;
          }
        }
      }

      const telefono = document.getElementById("telefono");
      if (
        telefono &&
        telefono.value.trim() !== "" &&
        !/^[0-9()+\- ]{7,20}$/.test(telefono.value.trim())
      ) {
        alert("Por favor, ingrese un número de teléfono válido.");
        valid = false;
      }

      if (!valid) {
        event.preventDefault();
      }
    },
  },

  indexPage: {
    init: function () {
      this.bindDeleteConfirmation();
    },

    bindDeleteConfirmation: function () {
      window.confirmarEliminar = this.confirmarEliminar;
    },

    confirmarEliminar: function (id) {
      const btnEliminar = document.getElementById("btn-eliminar");
      const modal = document.getElementById("confirmarEliminarModal");

      if (btnEliminar && ClienteManager.config) {
        btnEliminar.href = `${ClienteManager.config.url_base}secciones/clientes/index.php?eliminar=${id}`;
      }

      if (modal && typeof bootstrap !== "undefined") {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      }
    },
  },

  viewPage: {
    init: function (clienteData) {
      if (clienteData) {
        console.log("Cliente cargado:", {
          id: clienteData.id,
          nombre: clienteData.nombre,
          pais: clienteData.pais,
        });
      }
    },
  },

  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);
    },

    validateEmail: function (email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    },

    cleanPhoneNumber: function (phone) {
      return phone.replace(/[^\d+]/g, "");
    },
  },
};

window.selectCountry = function (country) {
  ClienteManager.countrySelector.selectCountry(country);
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof CLIENTE_CONFIG !== "undefined") {
    ClienteManager.init(CLIENTE_CONFIG);
  }

  const currentPath = window.location.pathname;

  if (
    currentPath.includes("registrar.php") ||
    currentPath.includes("editar.php")
  ) {
    ClienteManager.countrySelector.init();
    ClienteManager.fieldFormatters.init();
    ClienteManager.formValidator.init();
  } else if (
    currentPath.includes("index.php") ||
    currentPath.endsWith("/clientes/")
  ) {
    ClienteManager.indexPage.init();
  } else if (currentPath.includes("ver.php")) {
    ClienteManager.viewPage.init();
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = ClienteManager;
}
