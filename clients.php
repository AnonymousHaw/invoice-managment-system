<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}
$company_id = $_SESSION['company_id'];

// Search functionality
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = '%' . $_GET['search'] . '%';
}

// Pagination functionality
$limit = 10; // Number of clients per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Delete Client
if (isset($_GET['delete_client'])) {
    $id = (int)$_GET['delete_client'];
    
    // Check for existing invoices
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = ? and company_id = ?");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $hasInvoices = $row['count'] > 0;
    
    if (!$hasInvoices) {
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ? and company_id = ?");
        $stmt->bind_param("ii", $id, $company_id);
        $stmt->execute();
        header("Location: clients.php?success=deleted");
    } else {
        header("Location: clients.php?error=has_invoices");
    }
    exit();
}

// Fetch total clients for pagination
$searchQueryForPagination = $searchQuery ? "AND (name LIKE ? OR email LIKE ? OR address LIKE ?)" : '';

// Prepare the statement for COUNT query
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM clients WHERE company_id = ? $searchQueryForPagination");

// Bind parameters based on search query
if ($searchQuery) {
    // If search query is set, bind 4 parameters (company_id, searchQuery 3 times)
    $stmt->bind_param("isss", $company_id, $searchQuery, $searchQuery, $searchQuery);
} else {
    // If no search query, bind only 1 parameter (company_id)
    $stmt->bind_param("i", $company_id);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$totalClients = $row['total'];
$totalPages = ceil($totalClients / $limit);

// Fetch clients with pagination and search
$stmt = $conn->prepare("SELECT * FROM clients WHERE company_id = ? $searchQueryForPagination ORDER BY name LIMIT ? OFFSET ?");

// Bind parameters based on search query
if ($searchQuery) {
    // If search query is set, bind 5 parameters (company_id, searchQuery 3 times, limit, offset)
    $stmt->bind_param("issiii", $company_id, $searchQuery, $searchQuery, $searchQuery, $limit, $offset);
} else {
    // If no search query, bind only 3 parameters (company_id, limit, offset)
    $stmt->bind_param("iii", $company_id, $limit, $offset);
}

$stmt->execute();
$clients = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Management</title>
    <link rel="stylesheet" href="css/client.css">
</head>
<body>
    <div class="container">
        <h1>Client Management</h1>
    
        <div class="back-div">
            <a href="index.php" class="btn-primary">Back to Dashboard</a>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="clients.php">
                <input type="text" name="search"  placeholder="Search by name, email, or address">
                <button type="submit" class="btn-primary">Search</button>
            </form>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                <?php 
                    if ($_GET['success'] == 'created') echo "Client created successfully!";
                    if ($_GET['success'] == 'updated') echo "Client updated successfully!";
                    if ($_GET['success'] == 'deleted') echo "Client deleted successfully!";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert error">
                <?php 
                    if ($_GET['error'] == 'has_invoices') echo "Cannot delete client with existing invoices!";
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Client Button -->
        <div class="action-bar">
            <a href="add_client.php" class="btn-primary">Add New Client</a>
        </div>

        <!-- Client List -->
        <div class="list-section">
            <h2>Client List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($client = $clients->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['address']); ?></td>
                            <td class="actions">
                                <a href="edit_client.php?id=<?php echo $client['id']; ?>" class="btn-edit">Edit</a>
                                <button onclick="confirmDelete(<?php echo $client['id']; ?>)" class="btn-delete">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="clients.php?page=<?php echo $i; ?>&search=<?php echo $searchQuery ? urlencode($searchQuery) : ''; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>

    </div>

    <script>
    function confirmDelete(id) {
        if (confirm('Are you sure you want to delete this client?')) {
            window.location.href = `clients.php?delete_client=${id}`;
        }
    }
    </script>
</body>
</html>
