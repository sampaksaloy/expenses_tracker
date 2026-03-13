<?php

session_start();

require_once __DIR__ . '/includes/db.php';

$message      = "";
$message_type = "";

if (isset($_SESSION['message'])) {
    $message      = $_SESSION['message']['text'];
    $message_type = $_SESSION['message']['type'];
    unset($_SESSION['message']);
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $action = $_POST['action'];

    if ($action == 'add') {

        $title       = trim($_POST['title']);
        $description = trim($_POST['description']);
        $amount      = $_POST['amount'];
        $category_id = $_POST['category_id'];
        $date        = $_POST['expense_date'];

        if ($title == '' || $amount <= 0 || $category_id == '' || $date == '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Please fill in all required fields.'];
        } else {
            $sql  = "INSERT INTO expenses (title, description, amount, category_id, expense_date) VALUES (:title, :description, :amount, :category_id, :expense_date)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':title'        => $title,
                ':description'  => $description,
                ':amount'       => $amount,
                ':category_id'  => $category_id,
                ':expense_date' => $date
            ]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Expense added successfully!'];
        }

        header("Location: index.php");
        exit;
    }

    if ($action == 'update') {

        $id          = $_POST['id'];
        $title       = trim($_POST['title']);
        $description = trim($_POST['description']);
        $amount      = $_POST['amount'];
        $category_id = $_POST['category_id'];
        $date        = $_POST['expense_date'];

        if ($title == '' || $amount <= 0 || $category_id == '' || $date == '') {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Please fill in all required fields.'];
        } else {
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
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Expense updated successfully!'];
        }

        header("Location: index.php");
        exit;
    }

    if ($action == 'delete') {

        $id   = $_POST['id'];
        $sql  = "DELETE FROM expenses WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Expense deleted.'];

        header("Location: index.php");
        exit;
    }
}

$editing = null;

if (isset($_GET['edit'])) {
    $id   = $_GET['edit'];
    $sql  = "SELECT * FROM expenses WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $editing = $stmt->fetch();
}

$sql      = "SELECT e.*, c.name AS category_name FROM expenses e JOIN categories c ON c.id = e.category_id ORDER BY e.expense_date DESC";
$expenses = $db->query($sql)->fetchAll();

$grand_total = 0;
foreach ($expenses as $expense) {
    $grand_total = $grand_total + $expense['amount'];
}

$by_category = [];
foreach ($expenses as $expense) {
    $cat = $expense['category_name'];
    if (!isset($by_category[$cat])) {
        $by_category[$cat] = 0;
    }
    $by_category[$cat] = $by_category[$cat] + $expense['amount'];
}
arsort($by_category);

$chart_labels = json_encode(array_keys($by_category));
$chart_data   = json_encode(array_values($by_category));
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

<?php if ($message != ''): ?>
    <?php if ($message_type == 'success'): ?>
        <p><font color="green"><?= $message ?></font></p>
    <?php else: ?>
        <p><font color="red"><?= $message ?></font></p>
    <?php endif; ?>
<?php endif; ?>

<h3>Summary</h3>
<p><b>Total Expenses:</b> <?= count($expenses) ?></p>
<p><b>Grand Total:</b> PHP <?= number_format($grand_total, 2) ?></p>

<?php if (count($by_category) > 0): ?>
<h4>Expenses by Category:</h4>
<table border="1" cellpadding="6" cellspacing="0">
    <tr>
        <th>Category</th>
        <th>Total Amount</th>
        <th>Percentage</th>
    </tr>
    <?php foreach ($by_category as $cat_name => $cat_total): ?>
    <tr>
        <td><?= $cat_name ?></td>
        <td>PHP <?= number_format($cat_total, 2) ?></td>
        <td>
            <?php
            if ($grand_total > 0) {
                echo round(($cat_total / $grand_total) * 100) . '%';
            } else {
                echo '0%';
            }
            ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<h4>Spending Chart</h4>
<canvas id="spendChart" width="200" height="200"></canvas>
<?php endif; ?>

<hr>

<?php if ($editing): ?>
    <h3>Edit Expense</h3>
    <p><i>You are editing: <?= htmlspecialchars($editing['title']) ?></i></p>
<?php else: ?>
    <h3>Add New Expense</h3>
<?php endif; ?>

<form method="POST" action="index.php">

    <?php if ($editing): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $editing['id'] ?>">
    <?php else: ?>
        <input type="hidden" name="action" value="add">
    <?php endif; ?>

    <label>Title:</label><br>
    <input type="text" name="title" required
           value="<?= isset($editing['title']) ? htmlspecialchars($editing['title']) : '' ?>"><br><br>

    <label>Amount (PHP):</label><br>
    <input type="number" name="amount" step="0.01" min="0.01" required
           value="<?= isset($editing['amount']) ? $editing['amount'] : '' ?>"><br><br>

    <label>Category:</label><br>
    <select name="category_id" required>
        <option value="">-- Select Category --</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"
            <?= (isset($editing['category_id']) && $editing['category_id'] == $cat['id']) ? 'selected' : '' ?>>
            <?= $cat['name'] ?>
        </option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Date:</label><br>
    <input type="date" name="expense_date" required
           value="<?= isset($editing['expense_date']) ? $editing['expense_date'] : date('Y-m-d') ?>"><br><br>

    <label>Notes (optional):</label><br>
    <textarea name="description" rows="3" cols="40"><?= isset($editing['description']) ? htmlspecialchars($editing['description']) : '' ?></textarea><br><br>

    <?php if ($editing): ?>
        <input type="submit" value="Save Changes">
        &nbsp;<a href="index.php">Cancel</a>
    <?php else: ?>
        <input type="submit" value="Add Expense">
    <?php endif; ?>

</form>

<hr>

<h3>All Expenses</h3>

<form method="GET" action="index.php">
    Search: <input type="text" name="q" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" placeholder="Search by title">
    &nbsp;
    <input type="submit" value="Search">
    &nbsp;
    <a href="index.php">Reset</a>
</form>

<br>

<?php if (count($expenses) == 0): ?>
    <p>No expenses recorded yet.</p>
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
        <?php foreach ($expenses as $expense): ?>
        <tr>
            <td><?= htmlspecialchars($expense['title']) ?></td>
            <td><?= htmlspecialchars($expense['description']) ?></td>
            <td><?= $expense['category_name'] ?></td>
            <td><?= date('M d, Y', strtotime($expense['expense_date'])) ?></td>
            <td>PHP <?= number_format($expense['amount'], 2) ?></td>
            <td>
                <a href="index.php?edit=<?= $expense['id'] ?>">Edit</a>
                &nbsp;
                <form method="POST" action="index.php" style="display:inline"
                      onsubmit="return confirm('Are you sure you want to delete this?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $expense['id'] ?>">
                    <input type="submit" value="Delete">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="4"><b>Grand Total</b></td>
            <td><b>PHP <?= number_format($grand_total, 2) ?></b></td>
            <td></td>
        </tr>
    </table>
<?php endif; ?>

<?php if (count($by_category) > 0): ?>
<script>
    var labels = <?= $chart_labels ?>;
    var data   = <?= $chart_data ?>;

    var ctx = document.getElementById('spendChart').getContext('2d');

    var myChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderWidth: 2
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: true
        }
    });
</script>
<?php endif; ?>

</body>
</html>