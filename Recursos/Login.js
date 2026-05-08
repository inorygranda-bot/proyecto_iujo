const formularioLogin = document.getElementById('FormLogin');
const mensajeError = document.getElementById('MensajeError');

const handleSubmitLogin = async (event) => {
    event.preventDefault();
    
    mensajeError.textContent = '';
    mensajeError.style.display = 'none';

    const usuario = document.getElementById('usuario').value.trim();
    const password = document.getElementById('password').value;

    if (!usuario || !password) {
        mensajeError.textContent = 'Debes completar usuario y contraseña.';
        mensajeError.style.display = 'block';
        return;
    }

    try {

        const respuesta = await fetch('../Controladores/controlador_login.php', {
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
            mensajeError.style.display = 'block';
            return;
        }

        window.location.href = resultado.redirect || '../index.php';

    } catch (error) {
        mensajeError.textContent = 'Error de red o del servidor. Intenta nuevamente.';
        mensajeError.style.display = 'block';
        console.error("Error en login:", error);
    }
};

if (formularioLogin) {
    formularioLogin.addEventListener('submit', handleSubmitLogin);
}