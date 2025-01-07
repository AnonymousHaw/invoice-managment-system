<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

include "config.php";

// Handle invoice deletion
if (isset($_GET['delete_invoice'])) {
    $invoiceId = intval($_GET['delete_invoice']);
    $companyId = $_SESSION['company_id'];
    
    // Verify invoice belongs to company before deleting
    $verifyQuery = "SELECT id FROM invoices WHERE id = ? AND company_id = ?";
    $stmt = $conn->prepare($verifyQuery);
    $stmt->bind_param("ii", $invoiceId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Delete invoice items first
        $deleteItemsQuery = "DELETE FROM invoice_items WHERE invoice_id = ?";
        $stmt = $conn->prepare($deleteItemsQuery);
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();

        // Delete the invoice
        $deleteInvoiceQuery = "DELETE FROM invoices WHERE id = ? AND company_id = ?";
        $stmt = $conn->prepare($deleteInvoiceQuery);
        $stmt->bind_param("ii", $invoiceId, $companyId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            header("Location: index.php?success=deleted");
        } else {
            header("Location: index.php?error=delete_failed");
        }
        exit();
    }
}

// Fetch invoices for the logged-in company
$query = "
    SELECT i.*, c.name AS client_name 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE i.company_id = ?
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['company_id']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query failed: " . $conn->error);
}

$invoices = [];
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Invoice Management</title>
    <link rel="stylesheet" href="css/index_style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Invoice Management System</h1>
            <nav>
                <a href="index.php">Dashboard</a>
                <a href="clients.php">Clients</a>
                <a href="add_invoice.php">Create Invoice</a>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?></span>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </div>
            </nav>
        </header>

        <!-- Success Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                <?php
                switch ($_GET['success']) {
                    case 'created':
                        echo 'Invoice created successfully';
                        break;
                    case 'updated':
                        echo 'Invoice updated successfully';
                        break;
                    case 'deleted':
                        echo 'Invoice deleted successfully';
                        break;
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">
                <?php
                switch ($_GET['error']) {
                    case 'creation_failed':
                        echo 'Failed to create invoice';
                        break;
                    case 'update_failed':
                        echo 'Failed to update invoice';
                        break;
                    case 'delete_failed':
                        echo 'Failed to delete invoice';
                        break;
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Invoice Table -->
        <?php if (empty($invoices)): ?>
            <div class="no-records">
                No invoices found. <a href="add_invoice.php">Create your first invoice</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Client</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo sprintf('INV-%06d', $invoice['id']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                        <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($invoice['status']); ?>">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </td>
                        <td class="action-buttons">
                            <a href="edit_invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-edit">Edit</a>
                            <a href="generate_pdf.php?id=<?php echo $invoice['id']; ?>" class="btn btn-pdf">PDF</a>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?php echo $invoice['id']; ?>)" class="btn btn-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    function confirmDelete(invoiceId) {
        if (confirm('Are you sure you want to delete this invoice?')) {
            window.location.href = `index.php?delete_invoice=${invoiceId}`;
        }
    }
    </script>
</body>
</html>