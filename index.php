<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

include "config.php";

$companyQuery = "SELECT * FROM companies WHERE id = ?";
$stmt = $conn->prepare($companyQuery);
$stmt->bind_param("i", $_SESSION['company_id']);
$stmt->execute();
$companyResult = $stmt->get_result();
$companyInfo = $companyResult->fetch_assoc();

// Search functionality
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = '%' . $_GET['search'] . '%';
}

// Pagination functionality
$limit = 10; // Number of invoices per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

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

// Fetch total invoices for pagination
$searchQueryForPagination = $searchQuery ? "AND (i.invoice_number LIKE ? OR c.name LIKE ?)" : ' '; // Use `c.name` instead of `name`
$stmt = $conn->prepare("SELECT COUNT(*) as total 
                       FROM invoices i
                       LEFT JOIN clients c ON i.client_id = c.id 
                       WHERE i.company_id = ? $searchQueryForPagination");

if ($searchQuery) {
    $stmt->bind_param("iss", $_SESSION['company_id'], $searchQuery, $searchQuery); // Adjusted parameters
} else {
    $stmt->bind_param("i", $_SESSION['company_id']);
}

$stmt->execute();
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalInvoices = $row['total'];
$totalPages = ceil($totalInvoices / $limit);

// Fetch invoices with pagination and search
$query = "
    SELECT i.*, c.name AS client_name 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    WHERE i.company_id = ? $searchQueryForPagination
    ORDER BY i.created_at DESC 
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
if ($searchQuery) {
    $stmt->bind_param("issii", $_SESSION['company_id'], $searchQuery, $searchQuery, $limit, $offset);
} else {
    $stmt->bind_param("iii", $_SESSION['company_id'], $limit, $offset);
}
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
            <div class="header-left">
                <div class="profile-dropdown">
                    <div class="profile-section" onclick="toggleProfileDropdown()">
                        <img src="<?php echo $companyInfo['logo_path'] ?? 'images/default-logo.png'; ?>" alt="Company Logo" class="company-logo">
                        <div class="company-info">
                            <span class="company-name"><?php echo htmlspecialchars($companyInfo['name']); ?></span>
                            <span class="company-email"><?php echo htmlspecialchars($companyInfo['email']); ?></span>
                        </div>
                    </div>
                    <div class="profile-dropdown-content" id="profileDropdown">
                        <a href="edit_company.php">Edit Company Profile</a>
                        <a href="logout.php">Logout</a>
                    </div>
                </div>

                <h1>Invoice Management System</h1>
                <nav>
                    <a href="index.php">Dashboard</a>
                    <a href="clients.php">Clients</a>
                    <a href="add_invoice.php">Create Invoice</a>
                    <div class="user-info">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['company_name']); ?></span>
                    </div>
                </nav>
            </div>
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

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="index.php">
                <input type="text" name="search" placeholder="Search by invoice number or client name" >
                <button type="submit" class="btn-primary">Search</button>
            </form>
        </div>

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

        <!-- Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="index.php?page=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        function toggleProfileDropdown() {
            const dropdownContent = document.getElementById('profileDropdown');
            dropdownContent.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (event) {
            const dropdownContent = document.getElementById('profileDropdown');
            const profileSection = document.querySelector('.profile-section');

            if (!profileSection.contains(event.target) && !dropdownContent.contains(event.target)) {
                dropdownContent.classList.remove('active');
            }
        });

        // Confirm delete function
        function confirmDelete(invoiceId) {
            if (confirm('Are you sure you want to delete this invoice?')) {
                window.location.href = `index.php?delete_invoice=${invoiceId}`;
            }
        }
    </script>

</body>

</html>
