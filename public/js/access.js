document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formAcceso');
    const passwordInput = document.getElementById('Password');
    const mensajeAlerta = document.getElementById('mensajeAlerta');
    const btnSubmit = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const valor = passwordInput.value.trim();

        if (valor === '') {
            mostrarMensaje('Por favor ingresa una contraseña.', 'error');
            passwordInput.focus();
            return;
        }

        const formData = new FormData();
        formData.append('Password', valor);

        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Verificando...';

        try {
            const resp = await fetch('/backend/login.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.exito) {
                mostrarMensaje('Acceso correcto. Redirigiendo...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect || '/public/html/index.html';
                }, 500);
            } else {
                mostrarMensaje(data.mensaje || 'Contraseña incorrecta.', 'error');
                passwordInput.value = '';
                passwordInput.focus();
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Ingresar';
            }
        } catch (err) {
            mostrarMensaje('Error de conexión con el servidor.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Ingresar';
        }
    });

    function mostrarMensaje(texto, tipo) {
        if (!mensajeAlerta) {
            alert(texto);
            return;
        }
        mensajeAlerta.textContent = texto;
        mensajeAlerta.className = `alert-box ${tipo}`;
        mensajeAlerta.style.display = 'block';
    }
});
