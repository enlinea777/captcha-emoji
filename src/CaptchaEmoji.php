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
    }
    
    /**
     * Generar captcha con código de texto + emoji superpuesto
     * @param int $width Ancho de la imagen
     * @param int $height Alto de la imagen
     * @return void (genera imagen PNG)
     */
    public function generate($width = 250, $height = 80) {
        // Delay aleatorio anti-timing attack (500-1000 ms)
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
        
        // Generar salt aleatorio único para esta sesión
        $salt = $this->generateSalt(rand(256, 512));
        
        // Crear identificador único para este emoji (sin exponer el nombre del archivo)
        $emoji_identifier = basename($emoji_file, '.png');
        
        // Generar hash con bcrypt (imposible de revertir)
        $emoji_hash = password_hash($emoji_identifier . $salt, PASSWORD_BCRYPT,['cost' => 12]);
        
        // Guardar información en sesión (el salt y el identificador se mantienen en el servidor)
        $_SESSION[$this->sessionPrefix . 'code'] = $code;
        $_SESSION[$this->sessionPrefix . 'emoji_identifier'] = $emoji_identifier;
        $_SESSION[$this->sessionPrefix . 'salt'] = $salt;
        $_SESSION[$this->sessionPrefix . 'emoji_hash'] = $emoji_hash;
        $_SESSION[$this->sessionPrefix . 'time'] = time();
        
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
        if (!isset($_SESSION[$this->sessionPrefix . 'emoji_identifier']) || !isset($_SESSION[$this->sessionPrefix . 'salt'])) {
            return ['success' => false, 'message' => 'No hay captcha activo'];
        }
        
        $correct_emoji = $_SESSION[$this->sessionPrefix . 'emoji_identifier'] . '.png';
        $correct_hash = $_SESSION[$this->sessionPrefix . 'emoji_hash'];
        
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
            $emoji_identifier = pathinfo($emoji_file, PATHINFO_FILENAME);
            
            // Si es el emoji correcto, usar el hash guardado en sesión
            // Si no, generar hash diferente (distractor)
            if ($emoji_identifier === $_SESSION[$this->sessionPrefix . 'emoji_identifier']) {
                $emoji_hash = $correct_hash;
            } else {
                // Distractor: generar hash con salt diferente
                $distractor_salt = $this->generateSalt(rand(256, 512));
                $emoji_hash = password_hash($emoji_identifier . $distractor_salt, PASSWORD_BCRYPT, ['cost' => 12]);
            }
            
            // OFUSCACIÓN MÁXIMA: Agregar nombre aleatorio de emoji al endpoint
            $random_emoji_name = $all_emoji_names[array_rand($all_emoji_names)];
            $timestamp = time() . $index . rand(1000, 9999);
            
            $response['options'][] = [
                'hash' => $emoji_hash,
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
    public function verify($emoji_hash) {
        $response = ['success' => false, 'message' => '', 'field' => 'emoji'];
        
        // Verificar que existe el emoji en sesión
        if (!isset($_SESSION[$this->sessionPrefix . 'emoji_identifier']) || 
            !isset($_SESSION[$this->sessionPrefix . 'salt']) || 
            !isset($_SESSION[$this->sessionPrefix . 'time'])) {
            $response['message'] = 'Captcha expirado. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Verificar tiempo de expiración (10 minutos)
        if (time() - $_SESSION[$this->sessionPrefix . 'time'] > 600) {
            $this->clearSession();
            $response['message'] = 'Captcha expirado. Por favor, recarga el captcha.';
            return $response;
        }
        
        // Obtener el hash correcto guardado en sesión
        $correct_hash = $_SESSION[$this->sessionPrefix . 'emoji_hash'];
        
        // Verificar SOLO el emoji comparando directamente el hash
        // El cliente envía el hash que seleccionó de las opciones
        if ($emoji_hash !== $correct_hash) {
            $response['message'] = 'Emoji incorrecto. Por favor, selecciona el emoji que ves en la imagen.';
            return $response;
        }
        
        // Todo correcto
        $response['success'] = true;
        $response['message'] = 'Captcha verificado correctamente';
        
        // Limpiar sesión después de verificación exitosa
        $this->clearSession();
        
        return $response;
    }
    
    /**
     * Limpiar datos de sesión del captcha
     */
    public function clearSession() {
        unset($_SESSION[$this->sessionPrefix . 'code']);
        unset($_SESSION[$this->sessionPrefix . 'time']);
        unset($_SESSION[$this->sessionPrefix . 'emoji_hash']);
        unset($_SESSION[$this->sessionPrefix . 'emoji_identifier']);
        unset($_SESSION[$this->sessionPrefix . 'salt']);
        unset($_SESSION[$this->sessionPrefix . 'images_queue']);
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
     * Generar salt aleatorio para bcrypt
     */
    private function generateSalt($length = 32) {
        return bin2hex(random_bytes($length));
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
        
        // Posición aleatoria que no se salga del recuadro
        $max_x = $width - $new_size - 10;
        $max_y = $height - $new_size - 10;
        $x = rand(10, max(10, $max_x));
        $y = rand(10, max(10, $max_y));
        
        // Copiar emoji sobre la imagen base
        imagecopy($base_image, $emoji_resized, $x, $y, 0, 0, $new_size, $new_size);
        
        imagedestroy($emoji);
        imagedestroy($emoji_resized);
    }
}
