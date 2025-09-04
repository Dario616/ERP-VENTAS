/**
 * form-handler.js - Manejo de Formularios con Peso Teórico
 * VERSIÓN LIMPIA: Solo peso teórico, sin peso promedio histórico
 */

/**
 * Calcular peso líquido automáticamente
 */
function calcularPesoLiquido() {
  const tipoProducto = obtenerTipoProductoActual();
  const pesoBrutoInput = document.getElementById("peso_bruto");
  const taraInput = document.getElementById("tara");
  const pesoLiquidoInput = document.getElementById("peso_liquido_calculado");

  if (!pesoBrutoInput || !taraInput || !pesoLiquidoInput) return;

  const pesoBruto = parseFloat(pesoBrutoInput.value) || 0;
  const tara = parseFloat(taraInput.value) || 0;

  if (tipoProducto === "TOALLITAS" || tipoProducto === "PAÑOS") {
    // TOALLITAS/PAÑOS: tara simple
    if (pesoBruto > 0 && tara >= 0) {
      const pesoLiquido = pesoBruto - tara;
      pesoLiquidoInput.value = pesoLiquido.toFixed(2);

      // Cambiar color según resultado
      if (pesoLiquido <= 0) {
        pesoLiquidoInput.style.color = "#dc3545"; // Rojo
        pesoLiquidoInput.style.fontWeight = "bold";
      } else if (pesoLiquido < 5) {
        pesoLiquidoInput.style.color = "#fd7e14"; // Naranja
        pesoLiquidoInput.style.fontWeight = "normal";
      } else {
        pesoLiquidoInput.style.color = "#198754"; // Verde
        pesoLiquidoInput.style.fontWeight = "normal";
      }

      console.log(
        `🏷️ ${tipoProducto} - Peso líquido: ${pesoLiquido.toFixed(2)} kg`
      );
    } else {
      pesoLiquidoInput.value = "";
      pesoLiquidoInput.style.color = "";
      pesoLiquidoInput.style.fontWeight = "";
    }
  } else {
    // TNT/SPUNLACE/LAMINADORA: considerar bobinas_pacote
    const bobinasPacoteInput = document.getElementById("bobinas_pacote");
    const larguraInput = document.getElementById("largura");
    const larguraValue = parseFloat(larguraInput?.value || 0);

    let bobinasPacote = 1;

    // Solo considerar bobinas_pacote si largura < 1.0
    if (larguraValue > 0 && larguraValue < 1.0 && bobinasPacoteInput) {
      bobinasPacote = parseInt(bobinasPacoteInput.value) || 1;
    }

    if (pesoBruto > 0 && tara >= 0) {
      const taraTotal = tara * bobinasPacote;
      const pesoLiquido = pesoBruto - taraTotal;

      pesoLiquidoInput.value = pesoLiquido.toFixed(2);

      // Cambiar color según peso líquido
      if (pesoLiquido <= 0) {
        pesoLiquidoInput.style.color = "#dc3545"; // Rojo
        pesoLiquidoInput.style.fontWeight = "bold";
      } else if (pesoLiquido < 10) {
        pesoLiquidoInput.style.color = "#fd7e14"; // Naranja
        pesoLiquidoInput.style.fontWeight = "normal";
      } else {
        pesoLiquidoInput.style.color = "#198754"; // Verde
        pesoLiquidoInput.style.fontWeight = "normal";
      }

      console.log(
        `📦 ${tipoProducto} - Peso líquido: ${pesoLiquido.toFixed(
          2
        )} kg (${bobinasPacote} bobinas)`
      );
    } else {
      pesoLiquidoInput.value = "";
      pesoLiquidoInput.style.color = "";
      pesoLiquidoInput.style.fontWeight = "";
    }
  }
}

/**
 * Mostrar/ocultar campo bobinas_pacote según largura
 */
function toggleBobinasPacoteField() {
  const tipoProducto = obtenerTipoProductoActual();

  // Solo para TNT/SPUNLACE/LAMINADORA
  if (tipoProducto === "TOALLITAS" || tipoProducto === "PAÑOS") {
    return;
  }

  const larguraInput = document.getElementById("largura");
  const bobinasPacoteContainer = document.getElementById(
    "bobinas_pacote_container"
  );
  const bobinasPacoteInput = document.getElementById("bobinas_pacote");
  const pesoBrutoInput = document.getElementById("peso_bruto");

  if (!larguraInput || !bobinasPacoteContainer || !bobinasPacoteInput) {
    return;
  }

  const larguraValue = parseFloat(larguraInput.value) || 0;

  if (larguraValue > 0 && larguraValue < 1.0) {
    // Mostrar campo para productos angostos
    bobinasPacoteContainer.style.display = "block";
    bobinasPacoteInput.required = true;
    bobinasPacoteInput.value = bobinasPacoteInput.value || "1";
    bobinasPacoteContainer.classList.add("campo-obligatorio");

    // Recalcular peso teórico y revalidar
    if (typeof recalcularPesoTeorico === "function") {
      recalcularPesoTeorico();
    }

    console.log(
      `📦 Campo mostrado - Largura: ${larguraValue}m (producto angosto)`
    );
  } else {
    // Ocultar campo para productos anchos
    bobinasPacoteContainer.style.display = "none";
    bobinasPacoteInput.required = false;
    bobinasPacoteInput.value = "1";
    bobinasPacoteContainer.classList.remove("campo-obligatorio");

    // Recalcular peso teórico y revalidar
    if (typeof recalcularPesoTeorico === "function") {
      recalcularPesoTeorico();
    }

    console.log(`📦 Campo oculto - Largura: ${larguraValue}m (producto ancho)`);
  }

  // Revalidar peso bruto si existe
  setTimeout(() => {
    if (pesoBrutoInput && pesoBrutoInput.value) {
      const pesoActual = parseFloat(pesoBrutoInput.value);
      if (pesoActual > 0 && typeof validarPesoBruto === "function") {
        validarPesoBruto(pesoBrutoInput);
      }
    }
  }, 100);
}

/**
 * Configurar validaciones según tipo de producto
 */
function setupValidacion() {
  const tipoProducto = obtenerTipoProductoActual();
  const pesoBrutoInput = document.getElementById("peso_bruto");
  const taraInput = document.getElementById("tara");

  console.log(
    `🔧 Configurando validación para ${tipoProducto} con peso teórico ±15%`
  );

  // Validación para peso bruto
  if (pesoBrutoInput) {
    pesoBrutoInput.addEventListener("input", function () {
      const valor = parseFloat(this.value);
      if (valor <= 0) {
        this.setCustomValidity("El peso bruto debe ser mayor a 0");
      } else {
        this.setCustomValidity("");
      }

      // Validar contra peso teórico
      if (typeof validarPesoBruto === "function") {
        validarPesoBruto(this);
      }

      calcularPesoLiquido();
    });

    pesoBrutoInput.addEventListener("blur", function () {
      if (typeof validarPesoBruto === "function") {
        validarPesoBruto(this);
      }
    });
  }

  // Validación para tara
  if (taraInput) {
    taraInput.addEventListener("input", function () {
      const valor = parseFloat(this.value);
      const pesoBruto = parseFloat(pesoBrutoInput?.value || 0);

      if (valor < 0) {
        this.setCustomValidity("La tara no puede ser negativa");
      } else if (valor >= pesoBruto && pesoBruto > 0) {
        this.setCustomValidity("La tara debe ser menor al peso bruto");
      } else {
        this.setCustomValidity("");
      }

      calcularPesoLiquido();
    });
  }

  // Validación especial para bobinas_pacote
  if (tipoProducto !== "TOALLITAS" && tipoProducto !== "PAÑOS") {
    const bobinasPacoteInput = document.getElementById("bobinas_pacote");

    if (bobinasPacoteInput) {
      const manejarCambioBobinas = function () {
        const nuevoBobinasPacote = parseInt(this.value) || 1;
        console.log(`🔄 Cambio en bobinas_pacote: ${nuevoBobinasPacote}`);

        // Recalcular peso teórico
        if (typeof recalcularPesoTeorico === "function") {
          recalcularPesoTeorico();
        }

        // Revalidar peso bruto
        if (pesoBrutoInput && pesoBrutoInput.value) {
          const pesoActual = parseFloat(pesoBrutoInput.value);
          if (pesoActual > 0 && typeof validarPesoBruto === "function") {
            setTimeout(() => validarPesoBruto(pesoBrutoInput), 100);
          }
        }

        calcularPesoLiquido();
      };

      // Event listeners para bobinas_pacote
      bobinasPacoteInput.addEventListener("input", calcularPesoLiquido);
      bobinasPacoteInput.addEventListener("change", manejarCambioBobinas);
      bobinasPacoteInput.addEventListener("blur", manejarCambioBobinas);

      // Debounce para escritura manual
      bobinasPacoteInput.addEventListener("keyup", function () {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
          manejarCambioBobinas.call(this);
        }, 500);
      });
    }

    // Event listener para metragem (también afecta el peso teórico)
    const metragemInput = document.getElementById("metragem");
    if (metragemInput) {
      metragemInput.addEventListener("input", function () {
        // Recalcular peso teórico cuando cambia metragem
        if (typeof recalcularPesoTeorico === "function") {
          clearTimeout(this.debounceTimer);
          this.debounceTimer = setTimeout(() => {
            recalcularPesoTeorico();
          }, 300);
        }
      });

      metragemInput.addEventListener("change", function () {
        if (typeof recalcularPesoTeorico === "function") {
          recalcularPesoTeorico();
        }
      });
    }
  }
}

/**
 * Configurar validación del formulario antes del envío
 */
function setupFormValidation() {
  const form = document.getElementById("formRegistrarProduccion");
  const tipoProducto = obtenerTipoProductoActual();

  if (form) {
    form.addEventListener("submit", function (e) {
      console.log("🔍 Validando formulario antes del envío...");

      // CRÍTICO: Validar peso teórico ANTES de todo
      if (typeof validarFormularioAntesDenEnvio === "function") {
        if (!validarFormularioAntesDenEnvio(e)) {
          console.log(
            "❌ Validación de peso teórico falló - Formulario bloqueado"
          );
          return false;
        }
      }

      const pesoLiquido = parseFloat(
        document.getElementById("peso_liquido_calculado")?.value || 0
      );

      // Validación: peso líquido debe ser > 0
      if (pesoLiquido <= 0) {
        e.preventDefault();
        alert(
          "El peso líquido debe ser mayor a 0. Verifique el peso bruto y la tara."
        );
        console.log("❌ Peso líquido <= 0 - Formulario bloqueado");
        return false;
      }

      // Validación para productos angostos
      if (tipoProducto !== "TOALLITAS" && tipoProducto !== "PAÑOS") {
        const larguraInput = document.getElementById("largura");
        const bobinasPacoteInput = document.getElementById("bobinas_pacote");

        if (larguraInput && bobinasPacoteInput) {
          const larguraValue = parseFloat(larguraInput.value) || 0;
          const bobinasPacoteValue = parseInt(bobinasPacoteInput.value) || 0;

          if (
            larguraValue > 0 &&
            larguraValue < 1.0 &&
            bobinasPacoteValue < 1
          ) {
            e.preventDefault();
            alert(
              "❌ Para productos angostos, debe especificar cantidad de bobinas (mínimo 1)"
            );
            bobinasPacoteInput.focus();
            console.log("❌ Bobinas_pacote inválido - Formulario bloqueado");
            return false;
          }
        }
      }

      console.log(
        `✅ Formulario ${tipoProducto} validado - Peso líquido: ${pesoLiquido.toFixed(
          2
        )} kg`
      );
      return true;
    });
  }
}

/**
 * Limpiar formulario después de registro exitoso
 */
function handleSuccessfulRegistration() {
  const mensajeExito = document.querySelector(".alert-success");

  if (mensajeExito) {
    setTimeout(() => {
      const pesoBrutoInput = document.getElementById("peso_bruto");
      const taraInput = document.getElementById("tara");
      const pesoLiquidoInput = document.getElementById(
        "peso_liquido_calculado"
      );

      // Limpiar campos principales
      if (pesoBrutoInput) {
        pesoBrutoInput.value = "";
        pesoBrutoInput.focus();

        // Limpiar advertencias de peso teórico
        if (typeof limpiarAdvertenciasPeso === "function") {
          limpiarAdvertenciasPeso(pesoBrutoInput);
        }
      }
      if (taraInput) {
        taraInput.value = "";
      }
      if (pesoLiquidoInput) {
        pesoLiquidoInput.value = "";
        pesoLiquidoInput.style.color = "";
        pesoLiquidoInput.style.fontWeight = "";
      }

      // Desmarcar selección si existe
      if (typeof desmarcarSeleccion === "function") {
        desmarcarSeleccion();
      }

      // Ocultar mensaje después de 3 segundos
      setTimeout(() => {
        mensajeExito.style.display = "none";
      }, 2000);

      console.log("🧹 Formulario limpiado después de registro exitoso");
    }, 100);
  }
}

// Exponer funciones globalmente
window.calcularPesoLiquido = calcularPesoLiquido;
window.toggleBobinasPacoteField = toggleBobinasPacoteField;
window.setupValidacion = setupValidacion;
window.setupFormValidation = setupFormValidation;
window.handleSuccessfulRegistration = handleSuccessfulRegistration;
