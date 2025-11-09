<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload.'
    ]);
    exit;
}

$name = isset($payload['name']) ? trim((string)$payload['name']) : '';
$password = isset($payload['password']) ? (string)$payload['password'] : '';

if ($name === '' || $password === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Name and password are required.'
    ]);
    exit;
}

$dbHost = 'localhost';
$dbName = 'opr_portal';
$dbUser = 'opr_user';
$dbPass = 'replace_with_strong_password';

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to the database.'
    ]);
    exit;
}

$statement = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE name = :name LIMIT 1');
$statement->execute([':name' => $name]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials. Please try again.'
    ]);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_name'] = $user['name'];

echo json_encode([
    'success' => true,
    'redirectUrl' => 'portal.html'
]);

