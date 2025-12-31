<?php
/**
 * Sistema de Captcha con Emojis - Altamente Ofuscado
 * Sistema portable y profesional para validación humana
 * 
 * @namespace CaptchaSystem
 * @author enlinea777@gmail.com
 * @author escuelapintamonos.cl
 * @version 2.0
 */

namespace CaptchaSystem;

class CaptchaEmoji {
    
    private $basePath;
    private $fontsPath;
    private $emojisPath;
    private $sessionPrefix = 'captcha_';
    private $logFile;
    private $suspiciousAgents = ['curl', 'wget', 'python', 'bot', 'spider', 'crawler', 'scraper', 'headless'];
    
    /**
     * Constructor
     * @param string $basePath Ruta base del sistema de captcha
     */
    public function __construct($basePath = null) {
        if (!session_id()) {
            session_start();
        }
        
        $this->basePath = rtrim($basePath ?: __DIR__ . '/../', '/') . '/';
        $this->fontsPath = $this->basePath . 'fonts/captcha/';
        $this->emojisPath = $this->basePath . 'emojis/';
        $this->logFile = $this->basePath . 'captcha_security.log';
        
        // Inicializar contador de llamadas anti-timing
        if (!isset($_SESSION[$this->sessionPrefix . 'timing_calls'])) {
            $_SESSION[$this->sessionPrefix . 'timing_calls'] = 0;
        }
    }
    
    /**
     * Generar captcha con código de texto + emoji superpuesto
     * @param int $width Ancho de la imagen
     * @param int $height Alto de la imagen
     * @return void (genera imagen PNG)
     */
    public function generate($width = 250, $height = 80) {
        // Validaciones de seguridad
        if (!$this->securityCheck('generate')) {
            $this->antiTimingDelay(true); // Delay solo para bots
            $this->logSecurityEvent('BLOCKED', 'generate', 'Security check failed');
            header('HTTP/1.1 403 Forbidden');
            exit('Access denied');
        }
        
        // Delay normal para prevenir timing attacks (0.5-1s)
        usleep(rand(500000, 1000000));
        
        // Generar código aleatorio de 5 caracteres
        $code = $this->generateRandomCode(5);
        
        // Crear imagen
        $image = imagecreatetruecolor($width, $height);
        
        // Colores
        $bg_color = imagecolorallocate($image, rand(240, 255), rand(240, 255), rand(240, 255));
        $text_colors = [];
        for ($i = 0; $i < 5; $i++) {
            $text_colors[] = imagecolorallocate($image, rand(20, 60), rand(20, 60), rand(20, 60));
        }
        
        imagefill($image, 0, 0, $bg_color);
        
        // Obtener todas las fuentes disponibles
        $fonts = $this->getAvailableFonts();
        
        // Dibujar cada carácter con fuente aleatoria
        $x = 15;
        for ($i = 0; $i < strlen($code); $i++) {
            $font_file = $fonts[array_rand($fonts)];
            $font_size = rand(28, 34);
            $angle = rand(-15, 15);
            $y = rand(45, 55);
            $color = $text_colors[$i];
            
            // Intentar dibujar con TTF
            $bbox = @imagettftext($image, $font_size, $angle, $x, $y, $color, $font_file, $code[$i]);
            
            if ($bbox !== false) {
                // Calcular ancho del carácter
                $min_x = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                $max_x = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
                $char_width = $max_x - $min_x;
                $x += max($char_width + 8, 42);
            } else {
                $x += 42;
            }
        }
        
        // Agregar ruido
        $this->addNoise($image, $width, $height);
        
        // Seleccionar y superponer emoji aleatorio
        $emoji_file = $this->selectRandomEmoji();
        $this->overlayEmoji($image, $emoji_file, $width, $height);
        
        // Crear identificador único para este emoji (sin exponer el nombre del archivo)
        $emoji_identifier = basename($emoji_file, '.png');
        
        // Generar string aleatorio de 64 caracteres (mismo largo para todos)
        $emoji_token = $this->generateRandomToken(64);
        
        // Guardar información en sesión
        $_SESSION[$this->sessionPrefix . 'code'] = $code;
        $_SESSION[$this->sessionPrefix . 'emoji_identifier'] = $emoji_identifier;
        $_SESSION[$this->sessionPrefix . 'emoji_token'] = $emoji_token;
        $_SESSION[$this->sessionPrefix . 'time'] = time();
        $_SESSION[$this->sessionPrefix . 'attempts'] = 0;
        
        // Enviar headers y output
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($image);
        imagedestroy($image);
    }
    
    /**
     * Obtener opciones de emoji para selección (1 correcto + 3 distractores)
     * @return array JSON response con opciones ofuscadas
     */
    public function getEmojiOptions() {
        // Verificar que exista un emoji en sesión
        if (!isset($_SESSION[$this->sessionPrefix . 'emoji_identifier']) || !isset($_SESSION[$this->sessionPrefix . 'emoji_token'])) {
            return ['success' => false, 'message' => 'No hay captcha activo'];
        }
        
        $correct_emoji = $_SESSION[$this->sessionPrefix . 'emoji_identifier'] . '.png';
        $correct_token = $_SESSION[$this->sessionPrefix . 'emoji_token'];
        
        // Obtener todos los emojis disponibles
        $all_emojis = glob($this->emojisPath . '*.png');
        
        // Filtrar el emoji correcto de la lista
        $all_emojis = array_filter($all_emojis, function($emoji) use ($correct_emoji) {
            $basename = basename($emoji);
            return $basename !== $correct_emoji && pathinfo($basename, PATHINFO_EXTENSION) === 'png';
        });
        
        // Seleccionar 3 emojis distractores aleatorios
        $distractors = [];
        if (count($all_emojis) >= 3) {
            $random_keys = array_rand($all_emojis, 3);
            if (!is_array($random_keys)) {
                $random_keys = [$random_keys];
            }
            foreach ($random_keys as $key) {
                $distractors[] = basename($all_emojis[$key]);
            }
        }
        
        // Combinar el correcto con los distractores
        $options = array_merge([$correct_emoji], $distractors);
        
        // Mezclar aleatoriamente
        shuffle($options);
        
        // Guardar cola de imágenes en sesión
        $_SESSION[$this->sessionPrefix . 'images_queue'] = [];
        foreach ($options as $index => $emoji_file) {
            $_SESSION[$this->sessionPrefix . 'images_queue'][$index] = $this->emojisPath . $emoji_file;
        }
        
        // Crear respuesta con máxima ofuscación
        $response = [
            'success' => true,
            'options' => []
        ];
        
        // Obtener lista de todos los nombres de emojis para ofuscación adicional
        $all_emoji_names = array_map(function($path) {
            return pathinfo(basename($path), PATHINFO_FILENAME);
        }, glob($this->emojisPath . '*.png'));
        
        foreach ($options as $index => $emoji_file) {
            // Obtener identificador del emoji (sin extensión)
            // $emoji_file puede ser con o sin extensión después del shuffle
            $emoji_identifier = pathinfo($emoji_file, PATHINFO_FILENAME);
            
            // Si es el emoji correcto, usar el token guardado
            // Si no, generar token aleatorio del MISMO LARGO (64 chars)
            if ($emoji_identifier === $_SESSION[$this->sessionPrefix . 'emoji_identifier']) {
                $emoji_token = $correct_token;
            } else {
                $emoji_token = $this->generateRandomToken(64);
            }
            
            // OFUSCACIÓN MÁXIMA: Agregar nombre aleatorio de emoji al endpoint
            $random_emoji_name = $all_emoji_names[array_rand($all_emoji_names)];
            $timestamp = time() . $index . rand(1000, 9999);
            
            $response['options'][] = [
                'token' => $emoji_token,
                'image' => 'emoji_image.php?' . $timestamp . chr(rand(97, 122)) . '=' . urlencode($random_emoji_name)
            ];
        }
        
        return $response;
    }
    
    /**
     * Servir imagen de emoji de forma ofuscada
     * Siempre sirve el PRIMER elemento del arreglo y lo elimina
     * @return void (genera imagen PNG)
     */
    public function serveEmojiImage() {
        $queue_key = $this->sessionPrefix . 'images_queue';
        
        // Verificar que exista el arreglo de imágenes en sesión
        if (!isset($_SESSION[$queue_key]) || !is_array($_SESSION[$queue_key])) {
            header('HTTP/1.1 404 Not Found');
            echo 'No emoji queue in session';
            exit;
        }
        
        // SIEMPRE obtener el PRIMER elemento del arreglo
        $_SESSION[$queue_key] = array_values($_SESSION[$queue_key]);
        
        if (count($_SESSION[$queue_key]) === 0) {
            header('HTTP/1.1 404 Not Found');
            echo 'No more emojis in queue';
            exit;
        }
        
        // Obtener el PRIMER emoji (índice 0)
        $emoji_file = $_SESSION[$queue_key][0];
        $full_path = $emoji_file;
        
        // Verificar que el archivo existe
        if (!file_exists($full_path)) {
            header('HTTP/1.1 404 Not Found');
            echo 'File not found: ' . $full_path;
            exit;
        }
        
        // Obtener información del archivo
        $image_info = @getimagesize($full_path);
        if ($image_info === false) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }
        
        // Establecer headers para la imagen
        header('Content-Type: ' . $image_info['mime']);
        header('Content-Length: ' . filesize($full_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Servir la imagen
        readfile($full_path);
        
        // ELIMINAR el primer elemento del arreglo después de servir
        array_shift($_SESSION[$queue_key]);
        
        exit;
    }
    
    /**
     * Verificar captcha (solo emoji, el texto es trampa para bots)
     * @param string $emoji_hash Hash del emoji seleccionado
     * @return array Resultado de la verificación
     */
    public function verify($emoji_token) {
        $response = ['success' => false, 'message' => '', 'field' => 'emoji'];
        
        // Validaciones de seguridad
        if (!$this->securityCheck('verify')) {
            $this->antiTimingDelay(true); // Delay progresivo para bots
            $this->logSecurityEvent('BLOCKED', 'verify', 'Security check failed');
            $response['message'] = 'Acceso denegado.';
            return $response;
        }
        
        // Verificar que existe el emoji en sesión
        if (!isset($_SESSION[$this->sessionPrefix . 'emoji_token']) || 
            !isset($_SESSION[$this->sessionPrefix . 'time'])) {
            usleep(rand(1000, 10000)); // Delay mínimo
            $this->logSecurityEvent('FAIL', 'verify', 'Session expired or missing');
            $response['message'] = 'Captcha expirado. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Verificar tiempo de expiración (10 minutos)
        if (time() - $_SESSION[$this->sessionPrefix . 'time'] > 600) {
            $this->clearSession();
            usleep(rand(1000, 10000)); // Delay mínimo
            $this->logSecurityEvent('FAIL', 'verify', 'Captcha expired (timeout)');
            $response['message'] = 'Captcha expirado. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Incrementar intentos
        $_SESSION[$this->sessionPrefix . 'attempts']++;
        
        // Si supera 1 intento, invalidar inmediatamente
        if ($_SESSION[$this->sessionPrefix . 'attempts'] > 1) {
            $this->clearSession();
            $this->antiTimingDelay(true); // Delay progresivo por múltiples intentos
            $this->logSecurityEvent('BLOCKED', 'verify', 'Too many attempts');
            $response['message'] = 'Demasiados intentos. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Obtener token correcto de la sesión
        $correct_token = $_SESSION[$this->sessionPrefix . 'emoji_token'];
        
        // Comparación directa de strings
        if ($emoji_token !== $correct_token) {
            $this->antiTimingDelay(true); // Delay progresivo por fallo
            $this->clearSession();
            $this->logSecurityEvent('FAIL', 'verify', 'Invalid token');
            $response['message'] = 'Emoji incorrecto. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Todo correcto
        $response['success'] = true;
        $response['message'] = 'Captcha verificado correctamente';
        
        // Log de éxito
        $this->logSecurityEvent('SUCCESS', 'verify', 'Valid captcha');
        
        // Limpiar sesión después de verificación (exitosa o fallida)
        $this->clearSession();
        
        return $response;
    }
    
    /**
     * Limpiar datos de sesión del captcha
     */
    public function clearSession() {
        unset($_SESSION[$this->sessionPrefix . 'code']);
        unset($_SESSION[$this->sessionPrefix . 'time']);
        unset($_SESSION[$this->sessionPrefix . 'emoji_token']);
        unset($_SESSION[$this->sessionPrefix . 'emoji_identifier']);
        unset($_SESSION[$this->sessionPrefix . 'images_queue']);
        unset($_SESSION[$this->sessionPrefix . 'attempts']);
        // NO limpiar timing_calls ni generate_count - se acumulan por sesión completa
    }
    
    // ========== MÉTODOS PRIVADOS ==========
    
    /**
     * Generar código aleatorio
     */
    private function generateRandomCode($length = 5) {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    /**
     * Obtener fuentes TTF disponibles
     */
    private function getAvailableFonts() {
        // Leer TODAS las fuentes .ttf de la carpeta
        $all_fonts = glob($this->fontsPath . '*.ttf');
        
        $available_fonts = [];
        foreach ($all_fonts as $font_path) {
            // Validar que exista y tenga tamaño razonable (> 10KB)
            if (file_exists($font_path) && filesize($font_path) > 10000) {
                $available_fonts[] = $font_path;
            }
        }
        
        if (empty($available_fonts)) {
            throw new \Exception('No valid TTF fonts found in: ' . $this->fontsPath);
        }
        
        return $available_fonts;
    }
    
    /**
     * Seleccionar una fuente TTF aleatoria
     */
    private function selectRandomFont() {
        $fonts = $this->getAvailableFonts();
        return $fonts[array_rand($fonts)];
    }
    
    /**
     * Agregar ruido a la imagen
     */
    private function addNoise($image, $width, $height) {
        // Líneas aleatorias
        for ($i = 0; $i < 3; $i++) {
            $color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
            imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $color);
        }
        
        // Puntos aleatorios
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($image, rand(200, 255), rand(200, 255), rand(200, 255));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $color);
        }
    }
    
    /**
     * Seleccionar emoji aleatorio
     */
    private function selectRandomEmoji() {
        $emojis = glob($this->emojisPath . '*.png');
        if (empty($emojis)) {
            throw new \Exception('No emoji files found');
        }
        return $emojis[array_rand($emojis)];
    }
    
    /**
     * Generar token aleatorio de longitud fija
     */
    private function generateRandomToken($length = 64) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $token = '';
        $max = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, $max)];
        }
        return $token;
    }
    
    /**
     * Superponer emoji en posición aleatoria
     */
    private function overlayEmoji($base_image, $emoji_path, $width, $height) {
        $emoji = @imagecreatefrompng($emoji_path);
        if ($emoji === false) {
            return;
        }
        
        $emoji_width = imagesx($emoji);
        $emoji_height = imagesy($emoji);
        
        // Tamaño del emoji (30-45 px)
        $new_size = rand(30, 45);
        $emoji_resized = imagecreatetruecolor($new_size, $new_size);
        
        // Preservar transparencia
        imagealphablending($emoji_resized, false);
        imagesavealpha($emoji_resized, true);
        
        imagecopyresampled($emoji_resized, $emoji, 0, 0, 0, 0, $new_size, $new_size, $emoji_width, $emoji_height);
        
        // Aplicar transparencia al emoji (70-90% opacidad)
        $opacity = rand(70, 90);
        imagefilter($emoji_resized, IMG_FILTER_COLORIZE, 0, 0, 0, 127 - ($opacity * 127 / 100));
        
        // Posición aleatoria que no se salga del recuadro
        $max_x = $width - $new_size - 10;
        $max_y = $height - $new_size - 10;
        $x = rand(10, max(10, $max_x));
        $y = rand(10, max(10, $max_y));
        
        // Copiar emoji sobre la imagen base con alpha blending
        imagealphablending($base_image, true);
        imagecopy($base_image, $emoji_resized, $x, $y, 0, 0, $new_size, $new_size);
        
        // Agregar formas geométricas semitransparentes sobre el emoji
        $this->addGeometricShapes($base_image, $width, $height);
        
        // Agregar sombra/silueta de emoji aleatorio
        $this->addEmojiShadow($base_image, $width, $height);
        
        // Agregar 2 emojis adicionales cortados en los bordes
        $this->addOffsetEmojis($base_image, $width, $height);
        
        // Agregar códigos aleatorios en las 4 esquinas
        $this->addCornerCodes($base_image, $width, $height);
        
        imagedestroy($emoji);
        imagedestroy($emoji_resized);
    }
    
    /**
     * Agregar 2 emojis adicionales con diferentes tamaños y transparencias
     * posicionados de forma "cortada" en los bordes (desfasados)
     */
    private function addOffsetEmojis($base_image, $width, $height) {
        for ($i = 0; $i < 2; $i++) {
            $emoji_file = $this->selectRandomEmoji();
            $emoji = @imagecreatefrompng($emoji_file);
            
            if ($emoji === false) {
                continue;
            }
            
            $emoji_width = imagesx($emoji);
            $emoji_height = imagesy($emoji);
            
            // Tamaño aleatorio variado (30-80px)
            $new_size = rand(30, 80);
            $emoji_resized = imagecreatetruecolor($new_size, $new_size);
            
            imagealphablending($emoji_resized, false);
            imagesavealpha($emoji_resized, true);
            
            imagecopyresampled($emoji_resized, $emoji, 0, 0, 0, 0, $new_size, $new_size, $emoji_width, $emoji_height);
            
            // Transparencia aleatoria (40-80% opacidad)
            $opacity = rand(40, 80);
            imagefilter($emoji_resized, IMG_FILTER_COLORIZE, 0, 0, 0, 127 - ($opacity * 127 / 100));
            
            // Posición desfasada - siempre cortado en algún borde
            $position_type = rand(1, 4);
            
            switch ($position_type) {
                case 1: // Cortado arriba
                    $x = rand(10, $width - $new_size - 10);
                    $y = rand(-$new_size + 10, -10); // Parte superior cortada
                    break;
                    
                case 2: // Cortado abajo
                    $x = rand(10, $width - $new_size - 10);
                    $y = rand($height - 10, $height + $new_size - 10); // Parte inferior cortada
                    break;
                    
                case 3: // Cortado izquierda
                    $x = rand(-$new_size + 10, -10); // Parte izquierda cortada
                    $y = rand(10, $height - $new_size - 10);
                    break;
                    
                case 4: // Cortado derecha
                    $x = rand($width - 10, $width + $new_size - 10); // Parte derecha cortada
                    $y = rand(10, $height - $new_size - 10);
                    break;
            }
            
            // Copiar emoji desfasado sobre la imagen base
            imagealphablending($base_image, true);
            imagecopy($base_image, $emoji_resized, $x, $y, 0, 0, $new_size, $new_size);
            
            imagedestroy($emoji);
            imagedestroy($emoji_resized);
        }
    }
    
    /**
     * Agregar códigos alfanuméricos aleatorios en esquinas aleatorias
     * Similar a los códigos de emojis pero más cortos (4 caracteres)
     */
    private function addCornerCodes($base_image, $width, $height) {
        // Todas las posiciones disponibles de las 4 esquinas
        $all_positions = [
            ['x' => 5, 'y' => 15, 'align' => 'left'],      // Superior izquierda
            ['x' => $width - 5, 'y' => 15, 'align' => 'right'],   // Superior derecha
            ['x' => 5, 'y' => $height - 5, 'align' => 'left'],    // Inferior izquierda
            ['x' => $width - 5, 'y' => $height - 5, 'align' => 'right'] // Inferior derecha
        ];
        
        // Mezclar las posiciones
        shuffle($all_positions);
        
        // Siempre mostrar al menos 1 código (primera posición después del shuffle)
        $selected_positions = [$all_positions[0]];
        
        // Caracteres hexadecimales para generar códigos similares a los tokens
        $chars = '0123456789abcdef';
        
        foreach ($selected_positions as $pos) {
            // Generar código aleatorio de 4 caracteres hexadecimales
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $chars[rand(0, strlen($chars) - 1)];
            }
            
            // Seleccionar fuente aleatoria
            $font_file = $this->selectRandomFont();
            
            // Tamaño de fuente pequeño (6-8)
            $font_size = rand(6, 8);
            
            // Color aleatorio con transparencia moderada
            $red = rand(50, 200);
            $green = rand(50, 200);
            $blue = rand(50, 200);
            $alpha = rand(40, 70); // Semi-transparente
            $color = imagecolorallocatealpha($base_image, $red, $green, $blue, $alpha);
            
            // Calcular posición del texto según alineación
            $bbox = imagettfbbox($font_size, 0, $font_file, $code);
            $text_width = $bbox[2] - $bbox[0];
            
            $x = $pos['x'];
            if ($pos['align'] === 'right') {
                $x -= $text_width;
            }
            
            // Dibujar el código
            imagettftext($base_image, $font_size, 0, $x, $pos['y'], $color, $font_file, $code);
        }
    }
    
    /**
     * Agregar sombra/silueta de emoji aleatorio (solo forma, sin color)
     */
    private function addEmojiShadow($base_image, $width, $height) {
        // Seleccionar emoji aleatorio diferente
        $shadow_emoji_file = $this->selectRandomEmoji();
        $shadow_emoji = @imagecreatefrompng($shadow_emoji_file);
        
        if ($shadow_emoji === false) {
            return;
        }
        
        $emoji_width = imagesx($shadow_emoji);
        $emoji_height = imagesy($shadow_emoji);
        
        // Tamaño aleatorio de la sombra (20-40 px)
        $shadow_size = rand(20, 40);
        $shadow_resized = imagecreatetruecolor($shadow_size, $shadow_size);
        
        // Preservar transparencia
        imagealphablending($shadow_resized, false);
        imagesavealpha($shadow_resized, true);
        
        imagecopyresampled($shadow_resized, $shadow_emoji, 0, 0, 0, 0, $shadow_size, $shadow_size, $emoji_width, $emoji_height);
        
        // Convertir a escala de grises
        imagefilter($shadow_resized, IMG_FILTER_GRAYSCALE);
        
        // Aplicar alta transparencia para que sea sutil (85-110 = muy transparente)
        $shadow_alpha = rand(85, 110);
        imagefilter($shadow_resized, IMG_FILTER_COLORIZE, 0, 0, 0, $shadow_alpha);
        
        // Posición aleatoria
        $shadow_x = rand(5, $width - $shadow_size - 5);
        $shadow_y = rand(5, $height - $shadow_size - 5);
        
        // Copiar sombra sobre la imagen base
        imagealphablending($base_image, true);
        imagecopy($base_image, $shadow_resized, $shadow_x, $shadow_y, 0, 0, $shadow_size, $shadow_size);
        
        imagedestroy($shadow_emoji);
        imagedestroy($shadow_resized);
    }
    
    /**
     * Agregar formas geométricas semitransparentes para dificultar reconocimiento
     */
    private function addGeometricShapes($image, $width, $height) {
        // Número aleatorio de formas (mínimo 4, máximo 8)
        $num_shapes = rand(4, 8);
        
        for ($i = 0; $i < $num_shapes; $i++) {
            // Color aleatorio con transparencia (alpha 60-100 = semitransparente)
            $red = rand(100, 255);
            $green = rand(100, 255);
            $blue = rand(100, 255);
            $alpha = rand(60, 100); // Más alto = más transparente
            $color = imagecolorallocatealpha($image, $red, $green, $blue, $alpha);
            
            // Tipo de forma aleatoria
            $shape_type = rand(1, 4);
            
            switch ($shape_type) {
                case 1: // Rectángulo
                    $x1 = rand(0, $width - 30);
                    $y1 = rand(0, $height - 20);
                    $x2 = $x1 + rand(15, 40);
                    $y2 = $y1 + rand(10, 30);
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);
                    break;
                    
                case 2: // Elipse/Círculo
                    $cx = rand(20, $width - 20);
                    $cy = rand(20, $height - 20);
                    $w = rand(15, 35);
                    $h = rand(15, 35);
                    imagefilledellipse($image, $cx, $cy, $w, $h, $color);
                    break;
                    
                case 3: // Triángulo
                    $x1 = rand(10, $width - 10);
                    $y1 = rand(10, $height - 10);
                    $x2 = $x1 + rand(-25, 25);
                    $y2 = $y1 + rand(15, 35);
                    $x3 = $x1 + rand(-25, 25);
                    $y3 = $y1 + rand(-10, 10);
                    $points = array($x1, $y1, $x2, $y2, $x3, $y3);
                    imagefilledpolygon($image, $points, 3, $color);
                    break;
                    
                case 4: // Línea gruesa semitransparente
                    imagesetthickness($image, rand(2, 5));
                    $x1 = rand(0, $width);
                    $y1 = rand(0, $height);
                    $x2 = rand(0, $width);
                    $y2 = rand(0, $height);
                    imageline($image, $x1, $y1, $x2, $y2, $color);
                    imagesetthickness($image, 1); // Resetear grosor
                    break;
            }
        }
    }
    
    /**
     * Delay anti-timing progresivo basado en llamadas sospechosas acumuladas
     * Solo se aplica cuando hay comportamiento sospechoso
     * @param bool $isSuspicious Si es una llamada sospechosa (bot, fallo, múltiples intentos)
     */
    private function antiTimingDelay($isSuspicious = false) {
        if (!$isSuspicious) {
            // Delay mínimo para timing normal
            usleep(rand(1000, 10000)); // 1-10ms
            return;
        }
        
        // Incrementar contador SOLO para llamadas sospechosas
        $_SESSION[$this->sessionPrefix . 'timing_calls']++;
        $calls = $_SESSION[$this->sessionPrefix . 'timing_calls'];
        
        // Límite de 10 captchas generados sin verificar = sospechoso
        if (!isset($_SESSION[$this->sessionPrefix . 'generate_count'])) {
            $_SESSION[$this->sessionPrefix . 'generate_count'] = 0;
        }
        
        // Base: 100-500ms, se multiplica por cada fallo
        // Fallo 1: 100-500ms
        // Fallo 2: 1-5s
        // Fallo 3: 10-50s
        $base_min = 100000;  // 100ms
        $base_max = 500000;  // 500ms
        
        if ($calls > 1) {
            $multiplier = pow(10, min($calls - 1, 3)); // Max multiplicador: 1000
            $delay_min = min($base_min * $multiplier, 10000000); // Max 10s
            $delay_max = min($base_max * $multiplier, 50000000); // Max 50s
        } else {
            $delay_min = $base_min;
            $delay_max = $base_max;
        }
        
        usleep(rand($delay_min, $delay_max));
    }
    
    /**
     * Verificación de seguridad: honeypot + user-agent
     * @param string $action Acción que se está ejecutando
     * @return bool True si pasa las validaciones
     */
    private function securityCheck($action) {
        // 1. Honeypot: campo 'website' no debe estar lleno
        if (!empty($_POST['website'])) {
            $this->logSecurityEvent('BOT', $action, 'Honeypot triggered');
            return false;
        }
        
        // 2. User-Agent analysis
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Si no tiene user-agent, es sospechoso
        if (empty($user_agent)) {
            $this->logSecurityEvent('SUSPICIOUS', $action, 'No user-agent');
            return false;
        }
        
        // Verificar agentes sospechosos
        foreach ($this->suspiciousAgents as $agent) {
            if (strpos($user_agent, $agent) !== false) {
                $this->logSecurityEvent('BOT', $action, "Suspicious user-agent: $agent");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Registrar evento de seguridad en log compatible con fail2ban
     * @param string $level Nivel: SUCCESS, FAIL, BLOCKED, BOT, SUSPICIOUS
     * @param string $action Acción ejecutada
     * @param string $message Mensaje descriptivo
     */
    private function logSecurityEvent($level, $action, $message) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'none';
        $timestamp = date('Y-m-d H:i:s');
        
        // Formato compatible con fail2ban
        $log_entry = sprintf(
            "[%s] %s: IP=%s ACTION=%s MESSAGE='%s' USER_AGENT='%s'\n",
            $timestamp,
            $level,
            $ip,
            $action,
            $message,
            $user_agent
        );
        
        // Escribir al archivo de log
        @file_put_contents($this->logFile, $log_entry, FILE_APPEND | LOCK_EX);
    }
}
