<?php
/**
 * Ejemplo - Verificar captcha
 * Referencia: ../src/CaptchaEmoji.php
 */
require_once __DIR__ . '/../src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

header('Content-Type: application/json');
$captcha = new CaptchaEmoji(__DIR__ . '/../');
$user_emoji = $_POST['emoji'] ?? '';
echo json_encode($captcha->verify($user_emoji));