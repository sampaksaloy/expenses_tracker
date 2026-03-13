<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function response(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = explode('/', trim($uri, '/'));

$resource = $parts[array_search('api', $parts) + 1] ?? '';
$id       = isset($parts[array_search('api', $parts) + 2]) ? (int) $parts[array_search('api', $parts) + 2] : null;

$db   = getDB();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($resource === 'categories') {
    if ($method === 'GET') {
        $rows = $db->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        response(200, ['success' => true, 'data' => $rows]);
    }
    response(405, ['success' => false, 'message' => 'Method not allowed']);
}

if ($resource === 'expenses') {

    if ($method === 'GET' && !$id) {
        $filterCat  = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
        $filterFrom = trim($_GET['from'] ?? '');
        $filterTo   = trim($_GET['to']   ?? '');
        $search     = trim($_GET['q']    ?? '');

        $where  = ['1=1'];
        $params = [];

        if ($filterCat)  { $where[] = 'e.category_id = :cat';                       $params[':cat']  = $filterCat; }
        if ($filterFrom) { $where[] = 'e.expense_date >= :from';                     $params[':from'] = $filterFrom; }
        if ($filterTo)   { $where[] = 'e.expense_date <= :to';                       $params[':to']   = $filterTo; }
        if ($search)     { $where[] = '(e.title ILIKE :q OR e.description ILIKE :q)'; $params[':q']   = "%$search%"; }

        $sql  = 'SELECT e.id, e.title, e.description, e.amount, e.expense_date,
                        c.id AS category_id, c.name AS category_name
                   FROM expenses e
                   JOIN categories c ON c.id = e.category_id
                  WHERE ' . implode(' AND ', $where) . '
                  ORDER BY e.expense_date DESC, e.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        $total = array_sum(array_column($expenses, 'amount'));

        response(200, [
            'success' => true,
            'count'   => count($expenses),
            'total'   => (float) $total,
            'data'    => $expenses,
        ]);
    }

    if ($method === 'GET' && $id) {
        $stmt = $db->prepare(
            'SELECT e.id, e.title, e.description, e.amount, e.expense_date,
                    c.id AS category_id, c.name AS category_name
               FROM expenses e
               JOIN categories c ON c.id = e.category_id
              WHERE e.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetch();

        if (!$expense) response(404, ['success' => false, 'message' => 'Expense not found']);

        response(200, ['success' => true, 'data' => $expense]);
    }

    if ($method === 'POST') {
        $title       = trim($body['title']       ?? '');
        $description = trim($body['description'] ?? '');
        $amount      = $body['amount']      ?? null;
        $category_id = $body['category_id'] ?? null;
        $date        = $body['expense_date'] ?? '';

        if (!$title)                              response(422, ['success' => false, 'message' => 'Title is required']);
        if (!is_numeric($amount) || $amount <= 0) response(422, ['success' => false, 'message' => 'Amount must be a positive number']);
        if (!$category_id)                        response(422, ['success' => false, 'message' => 'Category is required']);
        if (!$date)                               response(422, ['success' => false, 'message' => 'Date is required']);

        $stmt = $db->prepare(
            'INSERT INTO expenses (title, description, amount, category_id, expense_date)
             VALUES (:title, :desc, :amount, :cat, :date)
             RETURNING id'
        );
        $stmt->execute([
            ':title'  => $title,
            ':desc'   => $description,
            ':amount' => (float) $amount,
            ':cat'    => (int)   $category_id,
            ':date'   => $date,
        ]);
        $newId = $stmt->fetchColumn();

        response(201, ['success' => true, 'message' => 'Expense added', 'id' => $newId]);
    }

    if ($method === 'PUT' && $id) {
        $title       = trim($body['title']       ?? '');
        $description = trim($body['description'] ?? '');
        $amount      = $body['amount']      ?? null;
        $category_id = $body['category_id'] ?? null;
        $date        = $body['expense_date'] ?? '';

        if (!$title)                              response(422, ['success' => false, 'message' => 'Title is required']);
        if (!is_numeric($amount) || $amount <= 0) response(422, ['success' => false, 'message' => 'Amount must be a positive number']);
        if (!$category_id)                        response(422, ['success' => false, 'message' => 'Category is required']);
        if (!$date)                               response(422, ['success' => false, 'message' => 'Date is required']);

        $stmt = $db->prepare(
            'UPDATE expenses
                SET title=:title, description=:desc, amount=:amount,
                    category_id=:cat, expense_date=:date
              WHERE id=:id'
        );
        $stmt->execute([
            ':title'  => $title,
            ':desc'   => $description,
            ':amount' => (float) $amount,
            ':cat'    => (int)   $category_id,
            ':date'   => $date,
            ':id'     => $id,
        ]);

        if ($stmt->rowCount() === 0) response(404, ['success' => false, 'message' => 'Expense not found']);

        response(200, ['success' => true, 'message' => 'Expense updated']);
    }

    if ($method === 'DELETE' && $id) {
        $stmt = $db->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) response(404, ['success' => false, 'message' => 'Expense not found']);

        response(200, ['success' => true, 'message' => 'Expense deleted']);
    }

    response(405, ['success' => false, 'message' => 'Method not allowed']);
}

response(404, ['success' => false, 'message' => 'Endpoint not found']);