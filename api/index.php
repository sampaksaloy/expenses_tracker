<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

$uri    = $_SERVER['REQUEST_URI'];
$uri    = str_replace('/expenses_tracker/api', '', $uri);
$uri    = parse_url($uri, PHP_URL_PATH);
$parts  = explode('/', trim($uri, '/'));

$resource = $parts[0];
$id       = isset($parts[1]) ? (int)$parts[1] : null;

$method = $_SERVER['REQUEST_METHOD'];

$body = json_decode(file_get_contents('php://input'), true);
if ($body == null) {
    $body = [];
}

if ($resource == 'categories') {

    if ($method == 'GET') {
        $rows = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }
}

if ($resource == 'expenses') {

    if ($method == 'GET' && $id == null) {

        $sql      = "SELECT e.*, c.name AS category_name FROM expenses e JOIN categories c ON c.id = e.category_id ORDER BY e.expense_date DESC";
        $expenses = $db->query($sql)->fetchAll();

        $total = 0;
        foreach ($expenses as $expense) {
            $total = $total + $expense['amount'];
        }

        echo json_encode([
            'success' => true,
            'count'   => count($expenses),
            'total'   => $total,
            'data'    => $expenses
        ]);
        exit;
    }

    if ($method == 'GET' && $id != null) {

        $sql  = "SELECT e.*, c.name AS category_name FROM expenses e JOIN categories c ON c.id = e.category_id WHERE e.id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetch();

        if ($expense == false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
            exit;
        }

        echo json_encode(['success' => true, 'data' => $expense]);
        exit;
    }

    if ($method == 'POST') {

        $title       = isset($body['title'])       ? trim($body['title'])       : '';
        $description = isset($body['description']) ? trim($body['description']) : '';
        $amount      = isset($body['amount'])      ? $body['amount']            : 0;
        $category_id = isset($body['category_id']) ? $body['category_id']       : '';
        $date        = isset($body['expense_date']) ? $body['expense_date']     : '';

        if ($title == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit;
        }

        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
            exit;
        }

        if ($category_id == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Category is required']);
            exit;
        }

        if ($date == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Date is required']);
            exit;
        }

        $sql  = "INSERT INTO expenses (title, description, amount, category_id, expense_date) VALUES (:title, :description, :amount, :category_id, :expense_date) RETURNING id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':title'        => $title,
            ':description'  => $description,
            ':amount'       => $amount,
            ':category_id'  => $category_id,
            ':expense_date' => $date
        ]);

        $new_id = $stmt->fetchColumn();

        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Expense added successfully', 'id' => $new_id]);
        exit;
    }

    if ($method == 'PUT' && $id != null) {

        $title       = isset($body['title'])       ? trim($body['title'])       : '';
        $description = isset($body['description']) ? trim($body['description']) : '';
        $amount      = isset($body['amount'])      ? $body['amount']            : 0;
        $category_id = isset($body['category_id']) ? $body['category_id']       : '';
        $date        = isset($body['expense_date']) ? $body['expense_date']     : '';

        if ($title == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Title is required']);
            exit;
        }

        if ($amount <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
            exit;
        }

        if ($category_id == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Category is required']);
            exit;
        }

        if ($date == '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Date is required']);
            exit;
        }

        $sql  = "UPDATE expenses SET title=:title, description=:description, amount=:amount, category_id=:category_id, expense_date=:expense_date WHERE id=:id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':title'        => $title,
            ':description'  => $description,
            ':amount'       => $amount,
            ':category_id'  => $category_id,
            ':expense_date' => $date,
            ':id'           => $id
        ]);

        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
        exit;
    }

    if ($method == 'DELETE' && $id != null) {

        $sql  = "DELETE FROM expenses WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() == 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
        exit;
    }
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
exit;