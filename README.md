# Sistema de Captcha con Emojis - Altamente Ofuscado

Sistema portable y profesional de validación humana con máxima seguridad contra bots.

**⚠️ IMPORTANTE**: Este software requiere atribución obligatoria. Ver [LICENSE](LICENSE) para detalles.

**GitHub**: https://github.com/enlinea777/captcha-emoji

## 📁 Estructura

```
captcha_system/
├── src/
│   └── CaptchaEmoji.php      # Clase principal con namespace CaptchaSystem
├── fonts/
│   └── captcha/               # 22 fuentes DejaVu TTF
├── emojis/                    # 3456 emojis PNG
├── ejemplo/                   # Ejemplo funcional completo
│   ├── index.html
│   ├── generate_captcha.php
│   ├── get_emoji_options.php
│   ├── emoji_image.php
│   └── verify_captcha.php
├── README.md
└── LICENSE                    # MIT con atribución obligatoria
```

## 🎯 Demo Rápida

Abre `ejemplo/index.html` en tu servidor PHP para ver el sistema funcionando.

```bash
cd captcha_system/ejemplo
php -S localhost:8000
# Abre: http://localhost:8000
```

## 🚀 Instalación

1. Copiar la carpeta `captcha_system/` a tu proyecto
2. Crear archivos wrapper en la raíz de tu proyecto:

### generate_captcha.php
```php
<?php
require_once __DIR__ . '/captcha_system/src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

$captcha = new CaptchaEmoji(__DIR__ . '/captcha_system');
$captcha->generate(250, 80);
```

### get_emoji_options.php
```php
<?php
require_once __DIR__ . '/captcha_system/src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

header('Content-Type: application/json');
$captcha = new CaptchaEmoji(__DIR__ . '/captcha_system');
echo json_encode($captcha->getEmojiOptions());
```

### emoji_image.php
```php
<?php
require_once __DIR__ . '/captcha_system/src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

$captcha = new CaptchaEmoji(__DIR__ . '/captcha_system');
$captcha->serveEmojiImage();
```

### verify_captcha.php
```php
<?php
require_once __DIR__ . '/captcha_system/src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

header('Content-Type: application/json');
$captcha = new CaptchaEmoji(__DIR__ . '/captcha_system');
$user_emoji = $_POST['emoji'] ?? '';
echo json_encode($captcha->verify($user_emoji));
```

## 💻 Uso en Formularios

### HTML
```html
<form id="mi-formulario" onsubmit="return validarCaptcha(this)">
    <!-- Imagen del captcha -->
    <img id="captcha-image" src="generate_captcha.php" alt="Captcha">
    <button type="button" onclick="recargarCaptcha()">Recargar</button>
    
    <!-- Input de texto (TRAMPA PARA BOTS - se oculta con JS) -->
    <div id="captcha-text-container">
        <label id="captcha-label">Ingresa el código</label>
        <input type="text" id="captcha-input" autocomplete="off">
    </div>
    
    <!-- Selección de emojis (se muestra con JS) -->
    <div id="captcha-emoji-container" class="hidden">
        <div id="emoji-options"></div>
    </div>
    
    <button type="submit">Enviar</button>
</form>
```

### JavaScript (captcha.js incluido)
```javascript
// Cargar al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarCaptcha();
    
    // Ofuscar después de 300ms
    setTimeout(function() {
        document.getElementById('captcha-text-container').style.display = 'none';
        document.getElementById('captcha-emoji-container').classList.remove('hidden');
        document.getElementById('captcha-label').innerHTML = 'Selecciona el emoji';
    }, 300);
});
```

## 🔒 Características de Seguridad

### 1. Doble Validación (Texto + Emoji)
- **Captcha de texto**: 5 caracteres con fuentes aleatorias (mas las que coloques manualmente en la carpeta de fuentes)
- **Emoji superpuesto**: Posición y tamaño aleatorio (30-45px)
- **Validación real para humanos**: Solo el (texto es el codigo correcto)

### 2. Ofuscación Máxima
- **Sin referencias directas**: Todos los emojis se sirven por `emoji_image.php`
- **Nombre aleatorio**: Cada URL incluye nombre de emoji para que los boot lo puedan leer facilmente
  - Ejemplo: `emoji_image.php?173559000012345a=grinning_face`
- **Mismo endpoint**: Imposible correlacionar URL con emoji específico (si eres umano sabras que es y como se usa)

### 3. Trampa para Bots
- **HTML inicial**: bots intentan leer de forma que no podran desifrar
- **JavaScript**: capa de seguridad
- **Backend**: verifica todo y el boot lo sabe
- **Resultado**: Bots fallan aunque lean todo nisiguiera la IA puese con este capcha

### 4. Anti-Caché
- Timestamp único por cada emoji: `?1735590...`
- Headers HTTP: `Cache-Control: no-cache`
- Parámetros aleatorios: `time() + index + rand(1000,9999) + chr(rand(97,122))`

### 5. Seguridad de Sesión
- Hash SHA-256: `hash('sha256', codigo + session_id())`
- Expiración: 10 minutos (600 segundos)
- Limpieza automática: Después de verificación exitosa

## 🎯 Flujo de Funcionamiento

1. **Usuario carga página**:
   - `generate_captcha.php` → Genera código
   - Guarda en `$_SESSION['captcha_emoji_hash']`

2. **JavaScript se activa** (300ms delay):
   - Oculta data importante para bots
   - Muestra selección 
   - Carga `get_emoji_options.php`

3. **get_emoji_options.php**:
   - Selecciona 1 correcto + 3 distractores
   - Guarda cola en `$_SESSION['captcha_images_queue']`
   - Devuelve 4 opciones con URLs identificatorias

4. **Navegador carga imágenes**:
   - Cada emoji: `emoji_image.php?data=NOMBRE_DEL_EMOJI`
   - `emoji_image.php` sirve primer elemento y lo elimina del arreglo
   - 4 llamadas = 4 emojis diferentes, misma URL base

5. **Usuario selecciona la respuesta correcta**:
   - JavaScript valida vía `verify_captcha.php`
   - Backend verifica hash SHA-256
   - Limpia sesión si es correcto

6. **Formulario se envía**:
   - Campo oculto: `captcha_validated=true`
   - `send.mail.ahora2.php` verifica nuevamente
   - Procesa datos si validación exitosa

## 📊 Estadísticas

- **Fuentes TTF**: 22 fuentes DejaVu
- **Emojis PNG**: 3,456 emojis disponibles
- **Combinaciones posibles**: 3,456 × (3,455 × 3,454 × 3,453) = ~161 billones
- **Tiempo de expiración**: 10 minutos
- **Tamaño captcha**: 250×80 px
- **Tamaño emoji**: 30-45 px aleatorio

## 🔧 Métodos de la Clase

### CaptchaEmoji

```php
__construct($basePath = null)
// Inicializa el sistema con la ruta base

generate($width = 250, $height = 80)
// Genera imagen PNG del captcha

getEmojiOptions()
// Retorna array con 4 opciones de emoji

serveEmojiImage()
// Sirve imagen PNG del emoji (FIFO)

verify($emoji_hash)
// Verifica hash del emoji seleccionado

clearSession()
// Limpia datos de sesión del captcha
```

## 🛡️ Protección Contra Ataques

| Tipo de Ataque | Protección |
|----------------|------------|
| OCR/Reconocimiento de texto | ❌ No funciona (validación es muy dificil) |
| Análisis de URLs | ❌ URLs ofuscadas |
| Replay attack | ❌ Sesión expira en 10 min + limpieza post-validación |
| Enumeración | ❌ Cola FIFO elimina elementos, no hay IDs |
| Scraping de emojis | ❌ 3,456 opciones + rotación aleatoria |
| Bots sin JavaScript | ❌ Ven trampa, fallan en validación |
| Análisis de tráfico | ❌ Mismo endpoint, parámetros aleatorios |
| bots de IA | ❌ no pueden determinar la validacion (deberian entrenar un modelo en base a esto y es muy dificil) |

## 📦 Portabilidad

Para migrar a otro proyecto:

1. Copiar carpeta `captcha_system/`
2. Crear 4 archivos wrapper (ejemplos arriba)
3. Copiar `js/captcha.js`
4. Incluir HTML del formulario
5. Listo! ✅

## 🌟 Ventajas

- ✅ **100% PHP** - Sin dependencias externas
- ✅ **Auto-contenido** - Una carpeta, todo incluido
- ✅ **Namespace** - `CaptchaSystem\CaptchaEmoji`
- ✅ **Ofuscación extrema** - inidentificable
- ✅ **Doble validación** - Texto  + Emoji
- ✅ **3,456 emojis** - Pool enorme de opciones
- ✅ **22 fuentes** - Variedad en renderizado y posibilidad de agregar centos de mas fuentes
- ✅ **Portable** - Copiar carpeta y funciona

## 📄 Licencia

**MIT License with Attribution Requirement**

Este software es libre para uso personal y comercial bajo los términos de la Licencia MIT con un **requisito obligatorio de atribución**.

### ⚠️ Requisito de Atribución (OBLIGATORIO)

Cualquier uso de este software, ya sea en forma de código fuente o binaria, **DEBE incluir una referencia visible al código fuente original**:

**En aplicaciones web:**
```html
<p>Powered by <a href="https://github.com/enlinea777/captcha-emoji">CaptchaEmoji System</a></p>
```

**En documentación:**
```markdown
## Créditos
Este proyecto usa [CaptchaEmoji System](https://github.com/enlinea777/captcha-emoji) 
por Escuela Pintamonos.
```

**En comentarios de código:**
```php
/**
 * Captcha System by Escuela Pintamonos
 * Source: https://github.com/enlinea777/captcha-emoji
 */
```

### 📋 Términos Completos

Ver [LICENSE](LICENSE) para términos completos de la licencia.

**El incumplimiento del requisito de atribución constituye una violación de esta licencia e infracción de derechos de autor.**

### 📧 Contacto

- **Autor**: Escuela Pintamonos Development Team
- **Website**: https://www.escuelapintamonos.cl/
- **Email**: contacto@escuelapintamonos.cl
- **GitHub**: https://github.com/enlinea777/captcha-emoji

---

**Versión**: 2.0  
**Fecha**: Diciembre 2025  
**Copyright**: © 2025 Escuela Pintamonos. Todos los derechos reservados.
