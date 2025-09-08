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

// Descriptions for roles
const roleDescriptions = {
  1: '<small class="text-danger"><i class="fas fa-crown me-1"></i>Acceso completo al sistema, gestión de usuarios y configuración</small>',
  2: '<small class="text-primary"><i class="fas fa-industry me-1"></i>Gestión de procesos de producción y control de líneas</small>',
  3: '<small class="text-warning"><i class="fas fa-truck me-1"></i>Gestión de expedición, envíos y distribución</small>',
};

// Función para editar usuario
function editarUsuario(id, nombre, usuario, rol) {
  document.getElementById("editId").value = id;
  document.getElementById("editNombre").value = nombre;
  document.getElementById("editUsuario").value = usuario;
  document.getElementById("editRol").value = rol;
  document.getElementById("nuevaContrasenia").value = "";

  const modal = new bootstrap.Modal(
    document.getElementById("modalEditarUsuario")
  );
  modal.show();
}

// Función para confirmar eliminación
function confirmarEliminar(id, nombre) {
  document.getElementById("eliminarId").value = id;
  document.getElementById("eliminarNombreUsuario").textContent = nombre;

  const modal = new bootstrap.Modal(
    document.getElementById("modalEliminarUsuario")
  );
  modal.show();
}

// Inicializar funcionalidades cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
  // Iniciar reloj
  setInterval(updateTime, 1000);

  // Update role description
  const rolSelect = document.getElementById("rol");
  if (rolSelect) {
    rolSelect.addEventListener("change", function () {
      const description =
        roleDescriptions[this.value] ||
        '<small class="text-muted">Seleccione un rol para ver su descripción</small>';
      document.getElementById("rolDescription").innerHTML = description;
    });
  }

  // Password strength indicator
  const contraseniaInput = document.getElementById("contrasenia");
  if (contraseniaInput) {
    contraseniaInput.addEventListener("input", function () {
      const password = this.value;
      const strengthBar = document.getElementById("passwordStrength");

      if (password.length === 0) {
        strengthBar.className = "password-strength";
        return;
      }

      let strength = 0;
      if (password.length >= 6) strength++;
      if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      strengthBar.className = "password-strength ";
      if (strength <= 2) {
        strengthBar.className += "password-weak";
      } else if (strength === 3) {
        strengthBar.className += "password-medium";
      } else {
        strengthBar.className += "password-strong";
      }
    });
  }

  // Mostrar/ocultar contraseñas
  const togglePassword1 = document.getElementById("togglePassword1");
  if (togglePassword1) {
    togglePassword1.addEventListener("click", function () {
      const password = document.getElementById("contrasenia");
      const icon = this.querySelector("i");

      if (password.type === "password") {
        password.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        password.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });
  }

  const togglePassword2 = document.getElementById("togglePassword2");
  if (togglePassword2) {
    togglePassword2.addEventListener("click", function () {
      const password = document.getElementById("confirmar_contrasenia");
      const icon = this.querySelector("i");

      if (password.type === "password") {
        password.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        password.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });
  }

  // Validación de contraseñas en tiempo real
  const confirmarContraseniaInput = document.getElementById(
    "confirmar_contrasenia"
  );
  if (confirmarContraseniaInput) {
    confirmarContraseniaInput.addEventListener("input", function () {
      const password1 = document.getElementById("contrasenia").value;
      const password2 = this.value;
      const matchDiv = document.getElementById("passwordMatch");

      if (password2.length === 0) {
        matchDiv.innerHTML = "";
        this.classList.remove("is-valid", "is-invalid");
        return;
      }

      if (password1 === password2) {
        matchDiv.innerHTML =
          '<small class="text-success"><i class="fas fa-check me-1"></i>Las contraseñas coinciden</small>';
        this.classList.remove("is-invalid");
        this.classList.add("is-valid");
        this.setCustomValidity("");
      } else {
        matchDiv.innerHTML =
          '<small class="text-danger"><i class="fas fa-times me-1"></i>Las contraseñas no coinciden</small>';
        this.classList.remove("is-valid");
        this.classList.add("is-invalid");
        this.setCustomValidity("Las contraseñas no coinciden");
      }
    });
  }

  // Validación del formulario de crear usuario
  const formUsuario = document.getElementById("formUsuario");
  if (formUsuario) {
    formUsuario.addEventListener("submit", function (e) {
      const password1 = document.getElementById("contrasenia").value;
      const password2 = document.getElementById("confirmar_contrasenia").value;

      if (password1 !== password2) {
        e.preventDefault();
        alert("Las contraseñas no coinciden");
        return false;
      }

      if (password1.length < 6) {
        e.preventDefault();
        alert("La contraseña debe tener al menos 6 caracteres");
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

  // Animaciones de entrada
  const cards = document.querySelectorAll(".dashboard-card");
  cards.forEach((card, index) => {
    card.style.opacity = "0";
    card.style.transform = "translateY(20px)";

    setTimeout(() => {
      card.style.transition = "all 0.6s ease";
      card.style.opacity = "1";
      card.style.transform = "translateY(0)";
    }, index * 200);
  });

  // Limpiar formulario cuando se cierre el modal de crear
  const modalRegistroUsuario = document.getElementById("modalRegistroUsuario");
  if (modalRegistroUsuario) {
    modalRegistroUsuario.addEventListener("hidden.bs.modal", function () {
      const form = document.getElementById("formUsuario");
      if (form) {
        form.reset();
      }

      const passwordStrength = document.getElementById("passwordStrength");
      if (passwordStrength) {
        passwordStrength.className = "password-strength";
      }

      const passwordMatch = document.getElementById("passwordMatch");
      if (passwordMatch) {
        passwordMatch.innerHTML = "";
      }

      const rolDescription = document.getElementById("rolDescription");
      if (rolDescription) {
        rolDescription.innerHTML =
          '<small class="text-muted">Seleccione un rol para ver su descripción</small>';
      }

      const btnRegistrar = document.getElementById("btnRegistrar");
      if (btnRegistrar) {
        btnRegistrar.disabled = false;
        btnRegistrar.innerHTML =
          '<i class="fas fa-save me-1"></i>Registrar Usuario';
      }

      // Limpiar validaciones visuales
      const inputs = document.querySelectorAll(
        "#formUsuario input, #formUsuario select"
      );
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
      document.getElementById("modalRegistroUsuario")
    );
    modal.show();
  } else if (tipoMensaje === "success") {
    // Auto-cerrar modal si registro fue exitoso y auto-hide alert después de 5 segundos
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
