<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'dados';
$dataFile = $dataDirectory . DIRECTORY_SEPARATOR . 'comentarios.json';
$adminPassword = getenv('VERTEX_ADMIN_PASSWORD') ?: 'vertex-admin';

if (!is_dir($dataDirectory)) mkdir($dataDirectory, 0750, true);
if (!is_file($dataFile)) {
    $comments = [
        ['id' => bin2hex(random_bytes(8)), 'name' => 'Empresa XYZ', 'message' => 'Serviço impecável! Fizeram a montagem e configuração de toda a infraestrutura da nossa empresa com rapidez e qualidade técnica impressionante.'],
        ['id' => bin2hex(random_bytes(8)), 'name' => 'Maria Silva', 'message' => 'Consegui recuperar arquivos que achei perdidos. Atendimento excelente, muito profissional e transparente.']
    ];
    file_put_contents($dataFile, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function comments(string $file): array {
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : [];
}
function adminOnly(): void {
    if (empty($_SESSION['vertex_admin'])) respond(['error' => 'Acesso de administrador necessário.'], 401);
}
function saveComments(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $password = is_array($body) ? (string) ($body['password'] ?? '') : '';
    if (!hash_equals($adminPassword, $password)) respond(['error' => 'Senha incorreta.'], 403);
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
    respond(['comments' => comments($dataFile), 'admin' => !empty($_SESSION['vertex_admin'])]);
}
if ($method === 'POST' && $action === 'create') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $name = trim((string) ($body['name'] ?? ''));
    $message = trim((string) ($body['message'] ?? ''));
    if ($name === '' || $message === '' || mb_strlen($name) > 60 || mb_strlen($message) > 420) respond(['error' => 'Dados inválidos.'], 422);
    $data = comments($dataFile);
    $data[] = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'message' => $message];
    saveComments($dataFile, array_slice($data, -50));
    respond(['ok' => true]);
}

adminOnly();
$data = comments($dataFile);
$id = (string) ($_GET['id'] ?? '');
$index = array_search($id, array_column($data, 'id'), true);
if ($index === false) respond(['error' => 'Comentário não encontrado.'], 404);

if ($method === 'PATCH') {
    $body = json_decode((string) file_get_contents('php://input'), true);
    $message = trim((string) ($body['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 420) respond(['error' => 'Comentário inválido.'], 422);
    $data[$index]['message'] = $message;
    saveComments($dataFile, $data);
    respond(['ok' => true]);
}
if ($method === 'DELETE') {
    array_splice($data, $index, 1);
    saveComments($dataFile, $data);
    respond(['ok' => true]);
}
respond(['error' => 'Método não suportado.'], 405);
