// Captcha functionality - 100% PHP con Emojis
// Variable global para el emoji seleccionado
let emojiSeleccionado = null;

// Cargar captcha al iniciar la página
document.addEventListener('DOMContentLoaded', function() {
    cargarCaptcha();
    
    // OFUSCACIÓN: Ocultar input de texto y mostrar selección de emojis
    // Esto confunde a los bots que esperan un input de texto
    setTimeout(function() {
        // Ocultar el input de texto
        const textContainer = document.getElementById('captcha-text-container');
        if (textContainer) {
            textContainer.style.display = 'none';
        }
        
        // Mostrar la selección de emojis
        const emojiContainer = document.getElementById('captcha-emoji-container');
        if (emojiContainer) {
            emojiContainer.classList.remove('hidden');
        }
        
        // Cambiar el label dinámicamente
        const captchaLabel = document.getElementById('captcha-label');
        if (captchaLabel) {
            captchaLabel.innerHTML = 'Selecciona el emoji que ves en el captcha <span class="text-red-600">*</span>';
        }
    }, 300);
});

// Función para cargar el captcha y los emojis
function cargarCaptcha() {
    const captchaImg = document.getElementById('captcha-image');
    // Agregar timestamp para evitar caché
    captchaImg.src = 'generate_captcha.php?' + new Date().getTime();
    document.getElementById('captcha-input').value = '';
    document.getElementById('captcha-error').classList.add('hidden');
    document.getElementById('emoji-error').classList.add('hidden');
    emojiSeleccionado = null;
    
    // Esperar a que se genere el captcha y luego cargar las opciones de emoji
    setTimeout(cargarOpcionesEmoji, 500);
}

// Función para cargar las opciones de emoji
function cargarOpcionesEmoji() {
    fetch('get_emoji_options.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.options) {
            const container = document.getElementById('emoji-options');
            container.innerHTML = '';
            
            data.options.forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'emoji-option p-2 border-2 border-gray-300 rounded-lg hover:border-blue-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500';
                button.dataset.emojiHash = option.hash;
                button.onclick = function() {
                    seleccionarEmoji(this);
                };
                
                const img = document.createElement('img');
                img.src = option.image;
                img.alt = 'Emoji';
                img.className = 'w-12 h-12 mx-auto';
                
                button.appendChild(img);
                container.appendChild(button);
            });
        }
    })
    .catch(error => {
        console.error('Error al cargar emojis:', error);
    });
}

// Función para seleccionar un emoji
function seleccionarEmoji(button) {
    // Remover selección previa
    document.querySelectorAll('.emoji-option').forEach(btn => {
        btn.classList.remove('border-blue-600', 'bg-blue-50', 'ring-2', 'ring-blue-500');
        btn.classList.add('border-gray-300');
    });
    
    // Marcar como seleccionado
    button.classList.remove('border-gray-300');
    button.classList.add('border-blue-600', 'bg-blue-50', 'ring-2', 'ring-blue-500');
    
    // Guardar selección
    emojiSeleccionado = button.dataset.emojiHash;
    document.getElementById('emoji-error').classList.add('hidden');
}

// Función para recargar el captcha
function recargarCaptcha() {
    cargarCaptcha();
}

// Variable para saber si el captcha fue validado
let captchaValidado = false;

// Modificar la función de envío del formulario
const originalEnviarMatricula = window.enviar_Matricula;

window.enviar_Matricula = function(form) {
    // Prevenir envío por defecto
    if (typeof event !== 'undefined') {
        event.preventDefault();
    }
    
    const submitButton = document.getElementById('wpforms-submit-105');
    const emojiError = document.getElementById('emoji-error');
    
    // SOLO validar que se seleccionó un emoji (ignorar el input de texto)
    if (!emojiSeleccionado) {
        emojiError.textContent = 'Por favor, selecciona el emoji que ves en el captcha.';
        emojiError.classList.remove('hidden');
        return false;
    }
    
    // Si ya fue validado, permitir envío directo
    if (captchaValidado) {
        if (typeof originalEnviarMatricula === 'function') {
            return originalEnviarMatricula.call(this, form);
        }
        return true;
    }
    
    // Deshabilitar botón durante verificación
    submitButton.disabled = true;
    submitButton.textContent = 'Verificando...';
    
    // Verificar SOLO el emoji (el código de texto es trampa para bots)
    fetch('verify_captcha.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'emoji=' + encodeURIComponent(emojiSeleccionado)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Emoji correcto
            emojiError.classList.add('hidden');
            submitButton.textContent = 'Enviando...';
            captchaValidado = true;
            
            // Agregar campo oculto al formulario para indicar que el captcha fue validado
            let captchaValidatedInput = document.createElement('input');
            captchaValidatedInput.type = 'hidden';
            captchaValidatedInput.name = 'captcha_validated';
            captchaValidatedInput.value = 'true';
            form.appendChild(captchaValidatedInput);
            
            // Agregar el emoji seleccionado al formulario
            let emojiInput = document.createElement('input');
            emojiInput.type = 'hidden';
            emojiInput.name = 'emoji';
            emojiInput.value = emojiSeleccionado;
            form.appendChild(emojiInput);
            
            // Llamar a la función original de envío
            if (typeof originalEnviarMatricula === 'function') {
                return originalEnviarMatricula.call(this, form);
            } else {
                // Si no existe, enviar el formulario normalmente
                form.submit();
            }
        } else {
            // Emoji incorrecto
            emojiError.textContent = data.message || 'Emoji incorrecto. Por favor, intenta de nuevo.';
            emojiError.classList.remove('hidden');
            submitButton.disabled = false;
            submitButton.textContent = 'Enviar';
            captchaValidado = false;
            recargarCaptcha();
        }
    })
    .catch(error => {
        console.error('Error al verificar captcha:', error);
        emojiError.textContent = 'Error al verificar el captcha. Por favor, intenta de nuevo.';
        emojiError.classList.remove('hidden');
        submitButton.disabled = false;
        submitButton.textContent = 'Enviar';
        captchaValidado = false;
        recargarCaptcha();
    });
    
    return false;
};
