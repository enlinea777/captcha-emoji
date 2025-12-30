<?php
/**
 * Ejemplo - Generar Captcha
 * Referencia: ../src/CaptchaEmoji.php
 */
require_once __DIR__ . '/../src/CaptchaEmoji.php';
use CaptchaSystem\CaptchaEmoji;

$captcha = new CaptchaEmoji(__DIR__ . '/../');
$captcha->generate(250, 80);