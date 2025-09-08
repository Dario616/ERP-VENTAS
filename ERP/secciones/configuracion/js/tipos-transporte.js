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

// Función para editar tipo
function editarTipo(id, nombre) {
  document.getElementById("editId").value = id;
  document.getElementById("editNombre").value = nombre;

  const modal = new bootstrap.Modal(document.getElementById("modalEditarTipo"));
  modal.show();
}

// Función para confirmar eliminación
function confirmarEliminar(id, nombre) {
  document.getElementById("eliminarId").value = id;
  document.getElementById("eliminarNombreTipo").textContent = nombre;

  const modal = new bootstrap.Modal(
    document.getElementById("modalEliminarTipo")
  );
  modal.show();
}

// Inicializar funcionalidades cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Iniciar reloj
  setInterval(updateTime, 1000);

  // Validación del formulario de crear tipo
  const formTipo = document.getElementById("formTipo");
  if (formTipo) {
    formTipo.addEventListener("submit", function (e) {
      const nombre = document.getElementById("nombre").value.trim();

      if (nombre.length < 3) {
        e.preventDefault();
        alert("El nombre debe tener al menos 3 caracteres");
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
  const nombreInput = document.getElementById("nombre");
  if (nombreInput) {
    nombreInput.addEventListener("input", function () {
      const nombre = this.value.trim();
      const btnRegistrar = document.getElementById("btnRegistrar");

      if (nombre.length >= 3) {
        this.classList.remove("is-invalid");
        this.classList.add("is-valid");
        if (btnRegistrar) {
          btnRegistrar.disabled = false;
        }
      } else {
        this.classList.remove("is-valid");
        if (nombre.length > 0) {
          this.classList.add("is-invalid");
        }
        if (btnRegistrar) {
          btnRegistrar.disabled = nombre.length === 0 ? false : true;
        }
      }
    });
  }

  // Animaciones de entrada
  const cards = document.querySelectorAll(".dashboard-card, .tipo-card");
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
  const modalRegistroTipo = document.getElementById("modalRegistroTipo");
  if (modalRegistroTipo) {
    modalRegistroTipo.addEventListener("hidden.bs.modal", function () {
      const form = document.getElementById("formTipo");
      if (form) {
        form.reset();
      }

      const btnRegistrar = document.getElementById("btnRegistrar");
      if (btnRegistrar) {
        btnRegistrar.disabled = false;
        btnRegistrar.innerHTML =
          '<i class="fas fa-save me-1"></i>Registrar Tipo';
      }

      // Limpiar validaciones visuales
      const inputs = document.querySelectorAll("#formTipo input");
      inputs.forEach((input) => {
        input.classList.remove("is-valid", "is-invalid");
      });
    });
  }
});

// Función para manejar comportamientos específicos basados en el estado del mensaje
function handleMessageBehavior(mensaje, tipoMensaje) {
  if (!mensaje) return;

  if (tipoMensaje === "error") {
    // Auto-abrir modal si hay errores después del envío
    const modal = new bootstrap.Modal(
      document.getElementById("modalRegistroTipo")
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
