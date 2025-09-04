const ProductosManager = {
  config: null,

  init: function (config) {
    this.config = config;
    this.bindEvents();
    console.log("ProductosManager inicializado", this.config);
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

  // Nuevo módulo para cálculo automático de peso
  pesoCalculator: {
    init: function () {
      this.bindDescripcionListener();
      this.addPesoAutoIndicator();
    },

    bindDescripcionListener: function () {
      const descripcionInput = document.getElementById("descripcion");
      const cantidadInput = document.getElementById("cantidad");

      if (descripcionInput && cantidadInput) {
        descripcionInput.addEventListener(
          "input",
          this.handleDescripcionChange.bind(this)
        );
        descripcionInput.addEventListener(
          "blur",
          this.handleDescripcionChange.bind(this)
        );
      }
    },

    handleDescripcionChange: function () {
      const descripcionInput = document.getElementById("descripcion");
      const cantidadInput = document.getElementById("cantidad");

      if (!descripcionInput || !cantidadInput) return;

      const descripcion = descripcionInput.value.trim();
      const pesoAutomatico = this.calcularPesoAutomatico(descripcion);

      if (pesoAutomatico !== null) {
        // Calcular peso automático
        cantidadInput.value = pesoAutomatico.toLocaleString("es-PY", {
          minimumFractionDigits: 3,
          maximumFractionDigits: 3,
        });
        cantidadInput.readOnly = true;
        cantidadInput.classList.add("auto-calculated");

        // Mostrar indicador
        this.showAutoCalculationIndicator(pesoAutomatico);

        console.log("Peso calculado automáticamente:", pesoAutomatico);
      } else {
        // Permitir edición manual
        cantidadInput.readOnly = false;
        cantidadInput.classList.remove("auto-calculated");
        this.hideAutoCalculationIndicator();
      }
    },

    calcularPesoAutomatico: function (descripcion) {
      // Verificar si contiene g/m²
      if (!/g\/m²/i.test(descripcion)) {
        return null;
      }

      try {
        // Extraer gramatura
        const gramaturaMatch = descripcion.match(/(\d+[,.]?\d*)\s*g\/m²/i);
        if (!gramaturaMatch) return null;
        const gramatura = parseFloat(gramaturaMatch[1].replace(",", "."));

        // Extraer ancho
        const anchoMatch = descripcion.match(/Ancho\s+(\d+[,.]?\d*)\s*cm/i);
        if (!anchoMatch) return null;
        const ancho = parseFloat(anchoMatch[1].replace(",", "."));

        // Extraer rollo
        const rolloMatch = descripcion.match(
          /Rollo\s+de\s+(\d+[,.]?\d*)\s*metros/i
        );
        if (!rolloMatch) return null;
        const rollo = parseFloat(rolloMatch[1].replace(",", "."));

        // Verificar que todos los valores sean válidos
        if (gramatura <= 0 || ancho <= 0 || rollo <= 0) {
          return null;
        }

        // Calcular peso: gramatura * (ancho/100) * rollo / 1000
        const peso = (gramatura * (ancho / 100.0) * rollo) / 1000.0;

        return Math.round(peso * 1000) / 1000; // Redondear a 3 decimales
      } catch (error) {
        console.error("Error calculando peso automático:", error);
        return null;
      }
    },

    showAutoCalculationIndicator: function (peso) {
      // Remover indicador existente
      this.hideAutoCalculationIndicator();

      const cantidadGroup = document
        .getElementById("cantidad")
        .closest(".input-group");
      if (cantidadGroup) {
        const indicator = document.createElement("div");
        indicator.id = "peso-auto-indicator";
        indicator.className = "alert alert-info mt-2 mb-0 py-2";
        indicator.innerHTML = `
          <i class="fas fa-calculator me-2"></i>
          <strong>Peso calculado automáticamente:</strong> ${peso.toLocaleString(
            "es-PY",
            {
              minimumFractionDigits: 3,
              maximumFractionDigits: 3,
            }
          )} kg
          <br><small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Este peso se calculó basándose en la gramatura, ancho y metros del rollo en la descripción.
          </small>
        `;
        cantidadGroup.parentNode.appendChild(indicator);
      }
    },

    hideAutoCalculationIndicator: function () {
      const indicator = document.getElementById("peso-auto-indicator");
      if (indicator) {
        indicator.remove();
      }
    },

    addPesoAutoIndicator: function () {
      const cantidadInput = document.getElementById("cantidad");
      if (cantidadInput) {
        // Agregar estilos CSS para el campo auto-calculado
        const style = document.createElement("style");
        style.textContent = `
          .form-control.auto-calculated {
            background-color: #e3f2fd !important;
            border-color: #2196f3 !important;
            font-weight: 500;
          }
          
          .form-control.auto-calculated:focus {
            box-shadow: 0 0 0 0.25rem rgba(33, 150, 243, 0.25) !important;
          }
        `;
        document.head.appendChild(style);
      }
    },
  },

  imageHandler: {
    init: function () {
      this.bindImagePreview();
      this.bindDeleteImageToggle();
    },

    bindImagePreview: function () {
      const imagenInput = document.getElementById("imagen");
      if (imagenInput) {
        imagenInput.addEventListener("change", function (e) {
          const file = e.target.files[0];
          const previewContainer = document.getElementById(
            "image-preview-container"
          );
          const preview = document.getElementById("image-preview");
          const currentImageContainer = document.getElementById(
            "current-image-container"
          );

          if (file) {
            const tiposPermitidos = [
              "image/jpeg",
              "image/jpg",
              "image/png",
              "image/gif",
            ];
            if (!tiposPermitidos.includes(file.type)) {
              alert(
                "Tipo de archivo no permitido. Solo se aceptan JPG, PNG y GIF."
              );
              e.target.value = "";
              return;
            }

            if (file.size > 2 * 1024 * 1024) {
              alert("El archivo es demasiado grande. Tamaño máximo: 2MB.");
              e.target.value = "";
              return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
              if (preview) {
                preview.src = e.target.result;
                previewContainer.style.display = "block";
              }

              if (currentImageContainer) {
                currentImageContainer.style.display = "none";
              }
            };

            reader.readAsDataURL(file);
          } else {
            if (preview) {
              preview.src = "#";
              previewContainer.style.display = "none";
            }

            if (currentImageContainer) {
              currentImageContainer.style.display = "block";
            }
          }
        });
      }
    },

    bindDeleteImageToggle: function () {
      const imagenInput = document.getElementById("imagen");
      const eliminarCheckbox = document.getElementById("eliminar_imagen");

      if (imagenInput && eliminarCheckbox) {
        imagenInput.addEventListener("change", function (e) {
          if (e.target.files.length > 0) {
            eliminarCheckbox.checked = false;
            eliminarCheckbox.disabled = true;
          } else {
            eliminarCheckbox.disabled = false;
          }
        });

        eliminarCheckbox.addEventListener("change", function () {
          const currentImageContainer = document.getElementById(
            "current-image-container"
          );
          if (this.checked && currentImageContainer) {
            currentImageContainer.style.opacity = "0.3";
          } else if (currentImageContainer) {
            currentImageContainer.style.opacity = "1";
          }
        });
      }
    },
  },

  fieldFormatters: {
    init: function () {
      this.initCantidadFormatter();
    },

    initCantidadFormatter: function () {
      const cantidadField = document.getElementById("cantidad");
      if (cantidadField) {
        cantidadField.addEventListener("blur", function (e) {
          // No formatear si está calculado automáticamente
          if (this.classList.contains("auto-calculated")) {
            return;
          }

          let value = this.value.trim();

          if (!value) return;

          value = value.replace(/[^\d.,]/g, "");

          if (value.includes(",")) {
            const parts = value.split(",");
            if (parts.length === 2) {
              const entero = parts[0].replace(/\./g, "");
              const decimal = parts[1];
              value = entero + "." + decimal;
            }
          }

          const numero = parseFloat(value);
          if (!isNaN(numero) && numero > 0) {
            this.value = numero.toLocaleString("es-PY", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            });
          }
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

      const unidadesMedida = document.querySelectorAll(
        'input[name="unidades_medida[]"]:checked'
      );
      if (unidadesMedida.length === 0) {
        alert("Debe seleccionar al menos una unidad de medida.");
        valid = false;
      }

      const cantidad = document.getElementById("cantidad");
      const descripcion = document.getElementById("descripcion");

      // Solo validar cantidad manualmente si no está calculada automáticamente
      if (
        cantidad &&
        !cantidad.classList.contains("auto-calculated") &&
        cantidad.value.trim() !== ""
      ) {
        const cantidadValue = cantidad.value
          .replace(/[^\d.,]/g, "")
          .replace(",", ".");
        if (isNaN(parseFloat(cantidadValue))) {
          alert("El peso líquido debe ser un valor numérico válido.");
          valid = false;
        }
      }

      if (!valid) {
        event.preventDefault();
      }
    },
  },

  indexPage: {
    init: function () {
      this.bindDeleteConfirmation();
      this.bindFilterEvents();
    },

    bindDeleteConfirmation: function () {
      window.confirmarEliminar = this.confirmarEliminar;
    },

    confirmarEliminar: function (id) {
      const btnEliminar = document.getElementById("btn-eliminar");
      const modal = document.getElementById("confirmarEliminarModal");

      if (btnEliminar && ProductosManager.config) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set("eliminar", id);
        btnEliminar.href = "index.php?" + urlParams.toString();
      }

      if (modal && typeof bootstrap !== "undefined") {
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
      }
    },

    bindFilterEvents: function () {
      const filtros = ["descripcion", "codigo"];
      filtros.forEach(function (filtro) {
        const input = document.getElementById(filtro);
        if (input) {
          input.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
              document.querySelector("form").submit();
            }
          });
        }
      });
    },
  },

  catalogPage: {
    init: function (productos) {
      this.productos = productos || [];
      this.bindSearchEvents();
      this.bindFilterEvents();
      this.bindViewToggle();
      console.log("Catálogo cargado con", this.productos.length, "productos");
    },

    bindSearchEvents: function () {
      const searchInput = document.getElementById("searchInput");
      if (searchInput) {
        searchInput.addEventListener("input", this.handleSearch.bind(this));
      }
    },

    handleSearch: function () {
      const filtro = document.getElementById("searchInput").value.toLowerCase();
      let hayResultados = false;

      document.querySelectorAll(".section-productos").forEach((seccion) => {
        let hayResultadosEnSeccion = false;

        if (document.querySelector(".table")) {
          const filas = seccion.querySelectorAll(".producto-row");
          filas.forEach((fila) => {
            const texto = fila.textContent.toLowerCase();
            const visible = texto.includes(filtro);
            fila.style.display = visible ? "" : "none";
            if (visible) hayResultadosEnSeccion = true;
          });
        } else {
          const tarjetas = seccion.querySelectorAll(".producto-card");
          tarjetas.forEach((tarjeta) => {
            const texto = tarjeta.textContent.toLowerCase();
            const visible = texto.includes(filtro);
            tarjeta.style.display = visible ? "" : "none";
            if (visible) hayResultadosEnSeccion = true;
          });
        }

        seccion.style.display =
          filtro === "" || hayResultadosEnSeccion ? "" : "none";
        if (hayResultadosEnSeccion) hayResultados = true;
      });
    },

    bindFilterEvents: function () {
      document.querySelectorAll(".btn-tipo-filter").forEach((btn) => {
        btn.addEventListener("click", function () {
          document
            .querySelectorAll(".btn-tipo-filter")
            .forEach((b) => b.classList.remove("active"));
          this.classList.add("active");

          const tipoSeleccionado = this.getAttribute("data-tipo");

          document.querySelectorAll(".section-productos").forEach((seccion) => {
            if (tipoSeleccionado === "todos") {
              seccion.style.display = "";
            } else {
              const tipoSeccion = seccion.getAttribute("data-tipo");
              seccion.style.display =
                tipoSeccion === tipoSeleccionado ? "" : "none";
            }
          });

          const searchInput = document.getElementById("searchInput");
          if (searchInput) {
            searchInput.value = "";
          }
        });
      });
    },

    bindViewToggle: function () {
      document
        .querySelectorAll('[data-bs-toggle="collapse"]')
        .forEach((toggle) => {
          toggle.addEventListener("click", function () {
            const icon = this.querySelector(".collapse-toggle");
            if (icon) {
              icon.classList.toggle("collapsed");
            }
          });
        });
    },

    verDetalles: function (id) {
      const producto = this.productos.find((p) => p.id == id);
      if (!producto) {
        console.error("Producto no encontrado:", id);
        return;
      }

      this.mostrarModalDetalles(producto);
    },

    mostrarModalDetalles: function (producto) {
      let imagenHtml = "";
      if (producto.base64img && producto.tipoimg) {
        imagenHtml = `<img src="data:${producto.tipoimg};base64,${producto.base64img}" 
                           alt="${producto.descripcion}" 
                           class="img-fluid rounded" 
                           style="max-height: 300px; width: 100%; object-fit: contain;">`;
      } else {
        imagenHtml = `<div class="bg-light rounded p-4 d-flex align-items-center justify-content-center" style="height: 200px;">
                        <div class="text-center">
                          <i class="fas fa-box fa-4x text-muted"></i>
                          <p class="mt-2 mb-0 text-muted">Sin imagen</p>
                        </div>
                      </div>`;
      }

      // Indicador de peso automático
      const pesoIndicador = producto.peso_automatico
        ? '<span class="badge bg-success ms-2"><i class="fas fa-calculator me-1"></i>Auto</span>'
        : "";

      const contenido = `
        <div class="row">
          <div class="col-md-5 text-center mb-3">
            ${imagenHtml}
            ${
              producto.nombreimg
                ? `<p class="text-muted mt-2 small">${producto.nombreimg}</p>`
                : ""
            }
          </div>
          <div class="col-md-7">
            <table class="table table-borderless">
              <tr>
                <th width="35%"><i class="fas fa-hashtag me-2 text-primary"></i>Código:</th>
                <td><span class="badge badge-custom">${producto.id}</span></td>
              </tr>
              <tr>
                <th><i class="fas fa-tag me-2 text-primary"></i>Descripción:</th>
                <td class="fw-semibold">${producto.descripcion}</td>
              </tr>
              <tr>
                <th><i class="fas fa-barcode me-2 text-primary"></i>Código de Barras:</th>
                <td>${producto.codigobr || "N/A"}</td>
              </tr>
              <tr>
                <th><i class="fas fa-layer-group me-2 text-primary"></i>Tipo:</th>
                <td>${producto.tipo}</td>
              </tr>
              <tr>
                <th><i class="fas fa-cubes me-2 text-primary"></i>Cantidad:</th>
                <td>
                  ${
                    producto.cantidad
                      ? parseFloat(producto.cantidad).toLocaleString("es-ES", {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        })
                      : "-"
                  }
                  ${pesoIndicador}
                </td>
              </tr>
              <tr>
                <th><i class="fas fa-receipt me-2 text-primary"></i>NCM:</th>
                <td>${producto.ncm || "N/A"}</td>
              </tr>
            </table>
            
            <div class="mt-3">
              <button class="btn btn-outline-primary btn-sm" onclick="ProductosManager.catalogPage.cargarUnidadesMedida(${
                producto.id
              })">
                <i class="fas fa-ruler me-2"></i>Ver Unidades de Medida
              </button>
              <div id="unidades-${producto.id}" class="mt-2"></div>
            </div>
          </div>
        </div>
      `;

      const modalContent = document.getElementById("modalContent");
      if (modalContent) {
        modalContent.innerHTML = contenido;
        const modal = new bootstrap.Modal(
          document.getElementById("detallesModal")
        );
        modal.show();
      }
    },

    cargarUnidadesMedida: function (id) {
      const contenedor = document.getElementById(`unidades-${id}`);
      if (!contenedor) return;

      contenedor.innerHTML =
        '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

      fetch(
        `${ProductosManager.config.url_base}secciones/productos/index.php?action=obtener_unidades&id=${id}`
      )
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.unidades.length > 0) {
            let unidadesHtml = '<div class="mt-2">';
            data.unidades.forEach((unidad) => {
              unidadesHtml += `<span class="badge bg-secondary me-2 mb-1">
                                 <i class="fas fa-check-circle me-1"></i>${unidad}
                               </span>`;
            });
            unidadesHtml += "</div>";
            contenedor.innerHTML = unidadesHtml;
          } else {
            contenedor.innerHTML =
              '<div class="text-muted mt-2"><i class="fas fa-info-circle me-1"></i>No hay unidades de medida definidas</div>';
          }
        })
        .catch((error) => {
          console.error("Error cargando unidades:", error);
          contenedor.innerHTML =
            '<div class="text-danger mt-2"><i class="fas fa-exclamation-triangle me-1"></i>Error al cargar unidades</div>';
        });
    },
  },

  utils: {
    showToast: function (message, type = "info") {
      console.log(`[${type.toUpperCase()}] ${message}`);
    },

    formatNumber: function (number, decimals = 2) {
      return parseFloat(number).toLocaleString("es-PY", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });
    },

    validateImageFile: function (file) {
      const tiposPermitidos = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/gif",
      ];
      const tamañoMaximo = 2 * 1024 * 1024;

      if (!tiposPermitidos.includes(file.type)) {
        return "Tipo de archivo no permitido. Solo se aceptan JPG, PNG y GIF.";
      }

      if (file.size > tamañoMaximo) {
        return "El archivo es demasiado grande. Tamaño máximo: 2MB.";
      }

      return null;
    },
  },
};

window.confirmarEliminar = function (id) {
  ProductosManager.indexPage.confirmarEliminar(id);
};

window.verDetalles = function (id) {
  ProductosManager.catalogPage.verDetalles(id);
};

window.cargarUnidadesMedida = function (id) {
  ProductosManager.catalogPage.cargarUnidadesMedida(id);
};

document.addEventListener("DOMContentLoaded", function () {
  if (typeof PRODUCTOS_CONFIG !== "undefined") {
    ProductosManager.init(PRODUCTOS_CONFIG);
  }

  const currentPath = window.location.pathname;

  if (
    currentPath.includes("registrar.php") ||
    currentPath.includes("editar.php")
  ) {
    ProductosManager.imageHandler.init();
    ProductosManager.fieldFormatters.init();
    ProductosManager.formValidator.init();
    // Inicializar calculadora automática de peso
    ProductosManager.pesoCalculator.init();
  } else if (
    currentPath.includes("index.php") ||
    currentPath.endsWith("/productos/")
  ) {
    ProductosManager.indexPage.init();
  } else if (currentPath.includes("verproducto.php")) {
    if (typeof productos !== "undefined") {
      ProductosManager.catalogPage.init(productos);
    }
  }
});

if (typeof module !== "undefined" && module.exports) {
  module.exports = ProductosManager;
}
