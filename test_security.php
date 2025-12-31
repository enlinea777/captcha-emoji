<?php
/**
 * Test de seguridad del sistema de captcha
 */

require_once __DIR__ . '/src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

// Iniciar sesión limpia
session_start();
session_destroy();
session_start();

echo "=== TEST DE SEGURIDAD CAPTCHA EMOJI ===\n\n";

// Test 1: Generar captcha normal
echo "1. Generando captcha normal...\n";
$captcha = new CaptchaEmoji(__DIR__);

ob_start();
$captcha->generate(250, 80);
$image_data = ob_get_clean();

if (strlen($image_data) > 100) {
    echo "   ✅ Captcha generado correctamente (" . strlen($image_data) . " bytes)\n";
} else {
    echo "   ❌ Error generando captcha\n";
}

// Limpiar sesión para test 2
session_destroy();
session_start();

// Test 2: Honeypot activado
echo "\n2. Test Honeypot (campo website lleno)...\n";
$_POST['website'] = 'http://bot-site.com';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

$captcha2 = new CaptchaEmoji(__DIR__);
ob_start();
$captcha2->generate(250, 80);
$output = ob_get_clean();

// Debe retornar 403
if (empty($output)) {
    echo "   ✅ Honeypot detectado - Acceso bloqueado\n";
} else {
    echo "   ❌ Honeypot NO funcionó\n";
}

unset($_POST['website']);

// Limpiar sesión para test 3
session_destroy();
session_start();

// Test 3: User-Agent sospechoso
echo "\n3. Test User-Agent sospechoso (curl)...\n";
$_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
$_SERVER['REMOTE_ADDR'] = '192.168.1.101';

$captcha3 = new CaptchaEmoji(__DIR__);
ob_start();
$captcha3->generate(250, 80);
$output = ob_get_clean();

if (empty($output)) {
    echo "   ✅ User-Agent sospechoso bloqueado\n";
} else {
    echo "   ❌ User-Agent sospechoso NO bloqueado\n";
}

// Limpiar sesión para test 4
session_destroy();
session_start();

// Test 4: Verificar log
echo "\n4. Verificando archivo de log...\n";
$log_file = __DIR__ . '/captcha_security.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", trim($log_content));
    $valid_lines = array_filter($lines, function($line) {
        return !empty(trim($line));
    });
    
    echo "   ✅ Log creado con " . count($valid_lines) . " eventos registrados\n";
    
    if (count($valid_lines) > 0) {
        echo "\n   Últimas 5 líneas del log:\n";
        $last_lines = array_slice($valid_lines, -5);
        foreach ($last_lines as $line) {
            echo "   " . $line . "\n";
        }
    }
} else {
    echo "   ❌ Log no creado\n";
}

// Test 5: Anti-timing progresivo
echo "\n5. Test Anti-Timing Progresivo...\n";
session_destroy();
session_start();
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
$_SERVER['REMOTE_ADDR'] = '192.168.1.102';

$captcha5 = new CaptchaEmoji(__DIR__);

// Primera generación
$start1 = microtime(true);
ob_start();
$captcha5->generate(250, 80);
ob_end_clean();
$time1 = (microtime(true) - $start1) * 1000;

// Segunda llamada (debe tener delay mayor)
$start2 = microtime(true);
ob_start();
$captcha5->generate(250, 80);
ob_end_clean();
$time2 = (microtime(true) - $start2) * 1000;

echo "   1ª llamada: " . round($time1, 2) . "ms\n";
echo "   2ª llamada: " . round($time2, 2) . "ms\n";

if ($time2 > $time1 * 10) {
    echo "   ✅ Anti-timing progresivo funcionando (2ª llamada " . round($time2/$time1, 1) . "x más lenta)\n";
} else {
    echo "   ⚠️ Delay progresivo menor al esperado\n";
}

echo "\n=== FIN DE TESTS ===\n";
echo "\nRevisa el log completo en: captcha_security.log\n";
