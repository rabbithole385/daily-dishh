<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Bad request.']);
    exit;
}
echo json_encode(game_daily_spin($pdo), JSON_UNESCAPED_UNICODE);
