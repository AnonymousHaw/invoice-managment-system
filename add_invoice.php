<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has company_id
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_invoice') {
    try {
        $conn->begin_transaction();

        $clientId = intval($_POST['client_id']);
        
        // Verify client belongs to company
        $checkClientStmt = $conn->prepare("SELECT id FROM clients WHERE id = ? AND company_id = ?");
        $checkClientStmt->bind_param("ii", $clientId, $company_id);
        $checkClientStmt->execute();
        $result = $checkClientStmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Invalid client selected");
        }

        $issueDate = $_POST['issue_date'];
        $dueDate = $_POST['due_date'];
        $status = $_POST['status'];

        // Insert new invoice with company_id
        $insertInvoiceQuery = "INSERT INTO invoices (client_id, company_id, issue_date, due_date, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertInvoiceQuery);
        $stmt->bind_param("iisss", $clientId, $company_id, $issueDate, $dueDate, $status);
        $stmt->execute();

        $invoiceId = $conn->insert_id;

        // Insert new items
        $insertItemQuery = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertItemQuery);

        foreach ($_POST['items'] as $item) {
            $description = $item['description'];
            $quantity = floatval($item['quantity']);
            $unitPrice = floatval($item['unit_price']);
            
            $stmt->bind_param("isdd", $invoiceId, $description, $quantity, $unitPrice);
            $stmt->execute();
        }

        // Update total amount
        $updateTotalQuery = "UPDATE invoices SET total_amount = (
            SELECT SUM(quantity * unit_price) 
            FROM invoice_items 
            WHERE invoice_id = ? 
        ) WHERE id = ? AND company_id = ?";
        $stmt = $conn->prepare($updateTotalQuery);
        $stmt->bind_param("iii", $invoiceId, $invoiceId, $company_id);
        $stmt->execute();

        $conn->commit();
        header("Location: index.php?success=added");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: add_invoice.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Fetch only clients belonging to the company
$sql = "SELECT id, name FROM clients WHERE company_id = ? ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = $clients_result->fetch_all(MYSQLI_ASSOC);

// Check if there are any clients
if (count($clients) === 0) {
    header("Location: add_client.php?error=no_clients");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Invoice</title>
    <link rel="stylesheet" href="css/add_invoice.css">
</head>
<body>
    <div class="container">
        <h1>Add Invoice</h1>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert error">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <form action="add_invoice.php" method="POST">
            <div class="back-div">
                <a href="index.php" class="btn-secondary">Back</a>
            </div>

            <input type="hidden" name="action" value="add_invoice">

            <div class="form-group">
                <label for="client_id">Client:</label>
                <select name="client_id" id="client_id" required>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>">
                        <?php echo htmlspecialchars($client['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="issue_date">Issue Date:</label>
                <input type="date" name="issue_date" id="issue_date" required>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date:</label>
                <input type="date" name="due_date" id="due_date" required>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select name="status" id="status" required>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            <h3>Invoice Items</h3>
            <div class="items-container" id="items-container">
                <div class="item-row">
                    <input type="text" name="items[0][description]" placeholder="Description" required>
                    <input type="number" name="items[0][quantity]" placeholder="Quantity" required>
                    <input type="number" step="0.01" name="items[0][unit_price]" placeholder="Unit Price" required>
                    <button type="button" class="remove-btn" onclick="removeItem(this)" style="display: none;">×</button>
                </div>
            </div>

            <button type="button" onclick="addItem()" class="btn btn-primary">Add Item</button>

            <div class="form-group">
                <input type="submit" value="Add Invoice" class="add-btn">
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    let itemCount = 1;

    function addItem() {
        const container = document.getElementById('items-container');
        const div = document.createElement('div');
        div.className = 'item-row';
        div.innerHTML = `
            <input type="text" name="items[${itemCount}][description]" placeholder="Description" required>
            <input type="number" name="items[${itemCount}][quantity]" placeholder="Quantity" required>
            <input type="number" step="0.01" name="items[${itemCount}][unit_price]" placeholder="Unit Price" required>
            <button type="button" class="remove-btn" onclick="removeItem(this)">×</button>
        `;
        container.appendChild(div);
        itemCount++;
        
        // Show remove button on first row if there's more than one row
        if (container.children.length > 1) {
            container.querySelector('.remove-btn').style.display = 'inline-block';
        }
    }

    function removeItem(button) {
        const container = document.getElementById('items-container');
        button.parentElement.remove();
        
        // Hide remove button on first row if it's the only row
        if (container.children.length === 1) {
            container.querySelector('.remove-btn').style.display = 'none';
        }
    }
    </script>
</body>
</html>