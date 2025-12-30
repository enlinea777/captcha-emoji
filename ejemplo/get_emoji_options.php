<?php
/**
 * Ejemplo - Obtener opciones de emoji
 * Referencia: ../src/CaptchaEmoji.php
 */
require_once __DIR__ . '/../src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

header('Content-Type: application/json');
$captcha = new CaptchaEmoji(__DIR__ . '/../');
echo json_encode($captcha->getEmojiOptions());