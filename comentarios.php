<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'dados';
$dataFile = $dataDirectory . DIRECTORY_SEPARATOR . 'comentarios.json';
$adminPassword = getenv('VERTEX_ADMIN_PASSWORD') ?: 'vertex-admin';

if (!is_dir($dataDirectory)) {
    mkdir($dataDirectory, 0750, true);
}

if (!is_file($dataFile)) {
    $initialComments = [
        [
            'id' => bin2hex(random_bytes(8)),
            'name' => 'Empresa XYZ',
            'message' => 'Serviço impecável! Fizeram a montagem e configuração de toda a infraestrutura da nossa empresa com rapidez e qualidade técnica impressionante.'
        ],
        [
            'id' => bin2hex(random_bytes(8)),
            'name' => 'Maria Silva',
            'message' => 'Consegui recuperar arquivos que achei perdidos. Atendimento excelente, muito profissional e transparente.'
        ]
    ];
    file_put_contents($dataFile, json_encode($initialComments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readComments(string $dataFile): array
{
    $comments = json_decode((string) file_get_contents($dataFile), true);
    return is_array($comments) ? $comments : [];
}

function requireAdmin(): void
{
    if (empty($_SESSION['vertex_admin'])) {
        respond(['error' => 'Acesso de administrador necessário.'], 401);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $password = is_array($body) ? (string) ($body['password'] ?? '') : '';
    if (!hash_equals($adminPassword, $password)) {
        respond(['error' => 'Senha incorreta.'], 403);
    }
    session_regenerate_id(true);
    $_SESSION['vertex_admin'] = true;
    respond(['ok' => true]);
}

if ($method === 'POST' && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
}

if ($method === 'GET') {
    respond(['comments' => readComments($dataFile), 'admin' => !empty($_SESSION['vertex_admin'])]);
}

if ($method === 'POST' && $action === 'create') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $name = trim((string) ($body['name'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));
    if ($name === '' || $message === '' || mb_strlen($name) > 60 || mb_strlen($message) > 420) {
        respond(['error' => 'Dados inválidos.'], 422);
    }
    $comments[] = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'message' => $message];
    file_put_contents($dataFile, json_encode(array_slice($comments, -50), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    respond(['ok' => true]);
}

requireAdmin();
$comments = readComments($dataFile);

$id = (string) ($_GET['id'] ?? '');
$index = array_search($id, array_column($comments, 'id'), true);
if ($index === false) {
    respond(['error' => 'Comentário não encontrado.'], 404);
}

if ($method === 'PATCH') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $message = trim((string) ($body['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 420) {
        respond(['error' => 'Comentário inválido.'], 422);
    }
    $comments[$index]['message'] = $message;
    file_put_contents($dataFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    array_splice($comments, $index, 1);
    file_put_contents($dataFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    respond(['ok' => true]);
}

respond(['error' => 'Método não suportado.'], 405);
