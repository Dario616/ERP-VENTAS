// Actualizar reloj
function updateTime() {
  const now = new Date();
  const options = {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  };
  const timeString = now.toLocaleDateString("es-ES", options);
  const timeElement = document.querySelector(".hero-timestamp");
  if (timeElement) {
    timeElement.innerHTML = '<i class="fas fa-clock me-2"></i>' + timeString;
  }
}

// Función para editar transportadora
function editarTransportadora(id, descripcion) {
  document.getElementById("editId").value = id;
  document.getElementById("editDescripcion").value = descripcion;

  const modal = new bootstrap.Modal(
    document.getElementById("modalEditarTransportadora")
  );
  modal.show();
}

// Función para confirmar eliminación
function confirmarEliminar(id, descripcion) {
  document.getElementById("eliminarId").value = id;
  document.getElementById("eliminarNombreTransportadora").textContent =
    descripcion;

  const modal = new bootstrap.Modal(
    document.getElementById("modalEliminarTransportadora")
  );
  modal.show();
}

// Función de búsqueda rápida (opcional)
function filtrarTransportadoras(termino) {
  const cards = document.querySelectorAll(".transportadora-card");
  const rows = document.querySelectorAll("tbody tr");

  cards.forEach((card) => {
    const texto = card.textContent.toLowerCase();
    card.style.display = texto.includes(termino.toLowerCase())
      ? "block"
      : "none";
  });

  rows.forEach((row) => {
    const texto = row.textContent.toLowerCase();
    row.style.display = texto.includes(termino.toLowerCase())
      ? "table-row"
      : "none";
  });
}

// Inicializar funcionalidades cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Iniciar reloj
  setInterval(updateTime, 1000);

  // Validación del formulario de crear transportadora
  const formTransportadora = document.getElementById("formTransportadora");
  if (formTransportadora) {
    formTransportadora.addEventListener("submit", function (e) {
      const descripcion = document.getElementById("descripcion").value.trim();

      if (descripcion.length < 3) {
        e.preventDefault();
        alert("La descripción debe tener al menos 3 caracteres");
        return false;
      }

      // Deshabilitar botón para evitar doble envío
      const btnRegistrar = document.getElementById("btnRegistrar");
      if (btnRegistrar) {
        btnRegistrar.disabled = true;
        btnRegistrar.innerHTML =
          '<i class="fas fa-spinner fa-spin me-1"></i>Registrando...';
      }
    });
  }

  // Validación en tiempo real
  const descripcionInput = document.getElementById("descripcion");
  if (descripcionInput) {
    descripcionInput.addEventListener("input", function () {
      const descripcion = this.value.trim();
      const btnRegistrar = document.getElementById("btnRegistrar");

      if (descripcion.length >= 3) {
        this.classList.remove("is-invalid");
        this.classList.add("is-valid");
        if (btnRegistrar) {
          btnRegistrar.disabled = false;
        }
      } else {
        this.classList.remove("is-valid");
        if (descripcion.length > 0) {
          this.classList.add("is-invalid");
        }
        if (btnRegistrar) {
          btnRegistrar.disabled = descripcion.length === 0 ? false : true;
        }
      }
    });
  }

  // Animaciones de entrada
  const cards = document.querySelectorAll(
    ".dashboard-card, .transportadora-card"
  );
  cards.forEach((card, index) => {
    card.style.opacity = "0";
    card.style.transform = "translateY(20px)";

    setTimeout(() => {
      card.style.transition = "all 0.6s ease";
      card.style.opacity = "1";
      card.style.transform = "translateY(0)";
    }, index * 100);
  });

  // Limpiar formulario cuando se cierre el modal de crear
  const modalRegistroTransportadora = document.getElementById(
    "modalRegistroTransportadora"
  );
  if (modalRegistroTransportadora) {
    modalRegistroTransportadora.addEventListener(
      "hidden.bs.modal",
      function () {
        const form = document.getElementById("formTransportadora");
        if (form) {
          form.reset();
        }

        const btnRegistrar = document.getElementById("btnRegistrar");
        if (btnRegistrar) {
          btnRegistrar.disabled = false;
          btnRegistrar.innerHTML =
            '<i class="fas fa-save me-1"></i>Registrar Transportadora';
        }

        // Limpiar validaciones visuales
        const inputs = document.querySelectorAll("#formTransportadora input");
        inputs.forEach((input) => {
          input.classList.remove("is-valid", "is-invalid");
        });
      }
    );
  }
});

// Función para manejar comportamientos específicos basados en el estado del mensaje
function handleMessageBehavior(mensaje, tipoMensaje) {
  if (!mensaje) return;

  if (tipoMensaje === "error") {
    // Auto-abrir modal si hay errores después del envío
    const modal = new bootstrap.Modal(
      document.getElementById("modalRegistroTransportadora")
    );
    modal.show();
  } else if (tipoMensaje === "success") {
    // Auto-cerrar alert si registro fue exitoso
    setTimeout(function () {
      const alert = document.querySelector(".alert");
      if (alert) {
        alert.style.transition = "all 0.5s ease";
        alert.style.opacity = "0";
        alert.style.transform = "translateY(-20px)";
        setTimeout(() => alert.remove(), 500);
      }
    }, 5000);
  }
}
