const formularioLogin = document.getElementById('FormLogin');
const mensajeError = document.getElementById('MensajeError');

const manejarEnvioLogin = async (event) => {
    event.preventDefault();
    mensajeError.textContent = '';

    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value;

    if (!usuario || !password) {
        mensajeError.textContent = 'Debes completar usuario y contraseña.';
        return;
    }

    try {
        const respuesta = await fetch('controlador_inicioSesion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({
                usuario_login: usuario,
                password_login: password,
            }),
        });

        const resultado = await respuesta.json();

        if (!resultado.ok) {
            mensajeError.textContent = resultado.mensaje || 'No fue posible iniciar sesión.';
            return;
        }

        /* La sesión queda en cookies PHP (controlador_inicioSesion.php); no se guarda nada en el navegador. */
        window.location.href = resultado.redirect || '../../index.php';
    } catch (error) {
        mensajeError.textContent = 'Error de red o del servidor. Intenta nuevamente.';
    }
};

formularioLogin.addEventListener('submit', manejarEnvioLogin);
