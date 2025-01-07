<?php
session_start();
require_once 'config.php'; // Use the MySQLi connection from config.php

// Ensure user is logged in and the session has the company_id
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id']; // Get company_id from session

// Check if it's a POST request and that the update action is set
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_invoice') {
    
    // Validate the invoice belongs to the logged-in company
    $invoiceId = intval($_POST['id']);
    $query = $conn->prepare("SELECT * FROM invoices WHERE id = ? AND company_id = ?");
    $query->bind_param("ii", $invoiceId, $company_id);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows === 0) {
        // Invoice not found for the logged-in company
        header("Location: index.php");
        exit();
    }

    try {
        $conn->begin_transaction(); // Start the transaction

        // Retrieve POST values
        $clientId = intval($_POST['client_id']);
        $issueDate = $_POST['issue_date'];
        $dueDate = $_POST['due_date'];
        $status = $_POST['status'];

        // Update the invoice
        $updateInvoiceQuery = "UPDATE invoices SET client_id = ?, issue_date = ?, due_date = ?, status = ? WHERE id = ? AND company_id = ?";
        $stmt = $conn->prepare($updateInvoiceQuery);
        $stmt->bind_param("isssii", $clientId, $issueDate, $dueDate, $status, $invoiceId, $company_id);
        $stmt->execute();

        // Delete existing items for this invoice
        $deleteItemsQuery = "DELETE FROM invoice_items WHERE invoice_id = ?";
        $stmt = $conn->prepare($deleteItemsQuery);
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();

        // Insert the new/updated items
        $insertItemQuery = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insertItemQuery);

        foreach ($_POST['items'] as $item) {
            $description = $item['description'];
            $quantity = floatval($item['quantity']);
            $unitPrice = floatval($item['unit_price']);
            $stmt->bind_param("isdd", $invoiceId, $description, $quantity, $unitPrice);
            $stmt->execute();
        }

        // Update the total amount of the invoice
        $updateTotalQuery = "UPDATE invoices SET total_amount = (
            SELECT SUM(quantity * unit_price) 
            FROM invoice_items 
            WHERE invoice_id = ? 
        ) WHERE id = ?";
        $stmt = $conn->prepare($updateTotalQuery);
        $stmt->bind_param("ii", $invoiceId, $invoiceId);
        $stmt->execute();

        // Commit the transaction
        $conn->commit();
        header("Location: index.php?success=updated");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Rollback if any error occurs
        header("Location: index.php?error=update_failed");
        exit();
    }
}

// Ensure invoice ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']); // Prevent SQL injection

// Fetch the invoice data
$sql = "SELECT i.*, c.name FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE i.id = ? AND i.company_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $company_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();

// Check if the invoice exists
if (!$invoice) {
    header("Location: index.php");
    exit();
}

// Fetch invoice items
$sql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$invoice_items_result = $stmt->get_result();
$invoice_items = $invoice_items_result->fetch_all(MYSQLI_ASSOC);

// Fetch all clients for the dropdown
$sql = "SELECT id, name FROM clients WHERE company_id = ? ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$clients_result = $stmt->get_result();
$clients = $clients_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Invoice</title>
    <link rel="stylesheet" href="css/editinvoice.css">
</head>
<body>
    <h1>Edit Invoice</h1>

    <form action="edit_invoice.php" method="POST">
        <a href="index.php">back</a>

        <input type="hidden" name="action" value="update_invoice">
        <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">

        <div class="form-group">
            <label>Client:</label>
            <select name="client_id" required>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo ($client['id'] == $invoice['client_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($client['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Issue Date:</label>
            <input type="date" name="issue_date" value="<?php echo $invoice['issue_date']; ?>" required>
        </div>

        <div class="form-group">
            <label>Due Date:</label>
            <input type="date" name="due_date" value="<?php echo $invoice['due_date']; ?>" required>
        </div>

        <div class="form-group">
            <label>Status:</label>
            <select name="status" required>
                <option value="draft" <?php echo ($invoice['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                <option value="sent" <?php echo ($invoice['status'] == 'sent') ? 'selected' : ''; ?>>Sent</option>
                <option value="paid" <?php echo ($invoice['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>

        <h3>Invoice Items</h3>
        <div class="items-container" id="items-container">
            <?php foreach ($invoice_items as $index => $item): ?>
                <div class="item-row">
                    <input type="hidden" name="items[<?php echo $index; ?>][id]" value="<?php echo $item['id']; ?>">
                    <input type="text" name="items[<?php echo $index; ?>][description]" placeholder="Description" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                    <input type="number" name="items[<?php echo $index; ?>][quantity]" placeholder="Quantity" value="<?php echo $item['quantity']; ?>" required>
                    <input type="number" step="0.01" name="items[<?php echo $index; ?>][unit_price]" placeholder="Unit Price" value="<?php echo $item['unit_price']; ?>" required>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" onclick="addItem()" class="btn">Add Item</button>

        <div class="form-group" style="margin-top: 20px;">
            <input type="submit" value="Update Invoice" class="btn">
            <a href="index.php" class="btn btn-danger" style="text-decoration: none; margin-left: 10px;">Cancel</a>
        </div>
    </form>

    <script>
        let itemCount = <?php echo count($invoice_items); ?>;

        function addItem() {
            const container = document.getElementById('items-container');
            const div = document.createElement('div');
            div.className = 'item-row';
            div.innerHTML = `
                <input type="text" name="items[${itemCount}][description]" placeholder="Description" required>
                <input type="number" name="items[${itemCount}][quantity]" placeholder="Quantity" required>
                <input type="number" step="0.01" name="items[${itemCount}][unit_price]" placeholder="Unit Price" required>
            `;
            container.appendChild(div);
            itemCount++;
        }
    </script>
</body>
</html>
