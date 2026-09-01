<?php
declare(strict_types=1);

$siteName = 'VERTEX';
$whatsappNumber = preg_replace('/\D+/', '', getenv('VERTEX_WHATSAPP') ?: '5511900000000');
$whatsappNumber = $whatsappNumber ?: '5511900000000';
$currentYear = date('Y');
$templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';

if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template do site nao encontrado.');
}

ob_start();
include $templatePath;
$page = ob_get_clean();

$page = str_replace(
    '© 2026 VERTEX',
    '© ' . $currentYear . ' ' . $siteName,
    $page
);
$page = str_replace(
    'https://wa.me/5511900000000',
    'https://wa.me/' . $whatsappNumber,
    $page
);
$page = str_replace(
    "const NUMERO_WHATSAPP = '5511900000000';",
    'const NUMERO_WHATSAPP = ' . json_encode($whatsappNumber, JSON_THROW_ON_ERROR) . ';',
    $page
);

echo $page;
