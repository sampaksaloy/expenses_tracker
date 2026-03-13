<?php

session_start();
require_once __DIR__ . '/includes/db.php';

$db      = getDB();
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

function redirect(string $url): never {
    header("Location: $url");
    exit;
}
function setMsg(string $type, string $text): void {
    $_SESSION['message'] = ['type' => $type, 'text' => $text];
}

$categories = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount      = $_POST['amount']      ?? '';
        $category_id = $_POST['category_id'] ?? '';
        $date        = $_POST['expense_date'] ?? '';

        if ($title && is_numeric($amount) && $amount > 0 && $category_id && $date) {
            $stmt = $db->prepare(
                'INSERT INTO expenses (title, description, amount, category_id, expense_date)
                 VALUES (:title, :desc, :amount, :cat, :date)'
            );
            $stmt->execute([
                ':title'  => $title,
                ':desc'   => $description,
                ':amount' => (float) $amount,
                ':cat'    => (int)   $category_id,
                ':date'   => $date,
            ]);
            setMsg('success', 'Expense added successfully!');
        } else {
            setMsg('error', 'Please fill in all required fields correctly.');
        }
        redirect('index.php');
    }

    if ($action === 'update') {
        $id          = (int)  ($_POST['id']           ?? 0);
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount      = $_POST['amount']      ?? '';
        $category_id = $_POST['category_id'] ?? '';
        $date        = $_POST['expense_date'] ?? '';

        if ($id && $title && is_numeric($amount) && $amount > 0 && $category_id && $date) {
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
            setMsg('success', 'Expense updated successfully!');
        } else {
            setMsg('error', 'Please fill in all required fields correctly.');
        }
        redirect('index.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM expenses WHERE id=:id')->execute([':id' => $id]);
            setMsg('success', 'Expense deleted.');
        }
        redirect('index.php');
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare(
        'SELECT e.*, c.name AS category_name
           FROM expenses e
           JOIN categories c ON c.id = e.category_id
          WHERE e.id = :id'
    );
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$filterCat  = (int)   ($_GET['cat']  ?? 0);
$filterFrom = trim($_GET['from'] ?? '');
$filterTo   = trim($_GET['to']   ?? '');
$search     = trim($_GET['q']    ?? '');

$where  = ['1=1'];
$params = [];

if ($filterCat)  { $where[] = 'e.category_id = :cat';            $params[':cat']  = $filterCat; }
if ($filterFrom) { $where[] = 'e.expense_date >= :from';         $params[':from'] = $filterFrom; }
if ($filterTo)   { $where[] = 'e.expense_date <= :to';           $params[':to']   = $filterTo; }
if ($search)     { $where[] = '(e.title LIKE :q OR e.description LIKE :q)'; $params[':q'] = "%$search%"; }

$sql = 'SELECT e.*, c.name AS category_name
          FROM expenses e
          JOIN categories c ON c.id = e.category_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY e.expense_date DESC, e.created_at DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$grandTotal = array_sum(array_column($expenses, 'amount'));
$totalCount = count($expenses);

$byCategory = [];
foreach ($expenses as $e) {
    $key = $e['category_name'];
    $byCategory[$key] = ($byCategory[$key] ?? 0) + $e['amount'];
}
arsort($byCategory);

$chartLabels = json_encode(array_keys($byCategory));
$chartData   = json_encode(array_values($byCategory));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body>

<h2>Expense Tracker</h2>
<hr>

<?php if ($message): ?>
    <?php if ($message['type'] === 'success'): ?>
        <p><font color="green"><?= htmlspecialchars($message['text']) ?></font></p>
    <?php else: ?>
        <p><font color="red"><?= htmlspecialchars($message['text']) ?></font></p>
    <?php endif; ?>
<?php endif; ?>

<h3>Summary</h3>
<p><b>Total Entries:</b> <?= $totalCount ?></p>
<p><b>Grand Total:</b> PHP <?= number_format($grandTotal, 2) ?></p>
<p><b>Top Category:</b> <?= htmlspecialchars(array_key_first($byCategory) ?? 'N/A') ?></p>

<?php if (!empty($byCategory)): ?>
<h4>By Category:</h4>
<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Category</th>
        <th>Total</th>
        <th>Percentage</th>
    </tr>
    <?php foreach ($byCategory as $cat => $total): ?>
    <tr>
        <td><?= htmlspecialchars($cat) ?></td>
        <td>PHP <?= number_format($total, 2) ?></td>
        <td><?= $grandTotal > 0 ? round(($total / $grandTotal) * 100) : 0 ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<h4>Spending Chart</h4>
<canvas id="spendChart" width="200" height="200"></canvas>
<?php endif; ?>

<hr>

<h3><?= $editing ? 'Edit Expense' : 'Add Expense' ?></h3>

<?php if ($editing): ?>
    <p><i>Editing: <?= htmlspecialchars($editing['title']) ?></i></p>
<?php endif; ?>

<form method="POST" action="index.php">
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'add' ?>">
    <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= $editing['id'] ?>">
    <?php endif; ?>

    <label>Expense Title:</label><br>
    <input type="text" name="title" required
           value="<?= htmlspecialchars($editing['title'] ?? '') ?>"><br><br>

    <label>Amount (PHP):</label><br>
    <input type="number" name="amount" step="0.01" min="0.01" required
           value="<?= htmlspecialchars($editing['amount'] ?? '') ?>"><br><br>

    <label>Category:</label><br>
    <select name="category_id" required>
        <option value="">-- Select Category --</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"
            <?= ($editing['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Date:</label><br>
    <input type="date" name="expense_date" required
           value="<?= htmlspecialchars($editing['expense_date'] ?? date('Y-m-d')) ?>"><br><br>

    <label>Notes (optional):</label><br>
    <textarea name="description" rows="3" cols="40"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea><br><br>

    <input type="submit" value="<?= $editing ? 'Save Changes' : 'Add Expense' ?>">
    <?php if ($editing): ?>
    &nbsp;<a href="index.php">Cancel</a>
    <?php endif; ?>
</form>

<hr>

<h3>All Expenses</h3>
<form method="GET" action="index.php">
    Search: <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title or notes">
    &nbsp;
    Category:
    <select name="cat">
        <option value="">All</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"
            <?= $filterCat === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    &nbsp;
    From: <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>">
    &nbsp;
    To: <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>">
    &nbsp;
    <input type="submit" value="Filter">
    &nbsp;
    <a href="index.php">Reset</a>
</form>

<br>

<?php if (empty($expenses)): ?>
    <p>No expenses found.</p>
<?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
        <tr>
            <th>Title</th>
            <th>Notes</th>
            <th>Category</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($expenses as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['title']) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($e['description'] ?? '', 0, 60, '...')) ?></td>
            <td><?= htmlspecialchars($e['category_name']) ?></td>
            <td><?= date('M d, Y', strtotime($e['expense_date'])) ?></td>
            <td>PHP <?= number_format($e['amount'], 2) ?></td>
            <td>
                <a href="index.php?edit=<?= $e['id'] ?>">Edit</a>
                &nbsp;
                <form method="POST" action="index.php" style="display:inline"
                      onsubmit="return confirm('Delete this expense?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                    <input type="submit" value="Delete">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="4"><b>Grand Total</b></td>
            <td><b>PHP <?= number_format($grandTotal, 2) ?></b></td>
            <td></td>
        </tr>
    </table>
<?php endif; ?>

<?php if (!empty($byCategory)): ?>
<script>
(function () {
    const labels = <?= $chartLabels ?>;
    const data   = <?= $chartData ?>;
    const ctx    = document.getElementById('spendChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, borderWidth: 2 }]
        },
        options: { maintainAspectRatio: true, responsive: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => ' PHP ' + ctx.parsed.toLocaleString('en-PH', { minimumFractionDigits: 2 })
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

</body>
</html>