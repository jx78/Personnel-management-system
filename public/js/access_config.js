document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formAccesoConfig');
    const passwordInput = document.getElementById('Password');
    const mensajeAlerta = document.getElementById('mensajeAlerta');
    const btnSubmit = form.querySelector('button[type="submit"]');

    fetch('/backend/auth_check.php')
        .then(res => res.json())
        .then(data => {
            if (!data.autenticado) {
                window.location.href = '/public/html/access.html';
                return;
            }
            if (data.config_autenticado) {
                window.location.href = '/public/html/configuraciones.html';
            }
        })
        .catch(err => {
            console.warn(err);
        });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const valor = passwordInput.value.trim();

        if (valor === '') {
            mostrarMensaje('Por favor ingresa la contraseña de configuración.', 'error');
            passwordInput.focus();
            return;
        }

        const formData = new FormData();
        formData.append('Password', valor);

        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Verificando...';

        try {
            const resp = await fetch('/backend/login_config.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();

            if (data.exito) {
                mostrarMensaje('Acceso autorizado. Redirigiendo...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect || '/public/html/configuraciones.html';
                }, 500);
            } else {
                mostrarMensaje(data.mensaje || 'Contraseña de configuración incorrecta.', 'error');
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                    return;
                }
                passwordInput.value = '';
                passwordInput.focus();
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Entrar a Configuraciones';
            }
        } catch (err) {
            mostrarMensaje('Error de conexión con el servidor.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Entrar a Configuraciones';
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
