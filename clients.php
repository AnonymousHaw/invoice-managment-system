<?php
// clients.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}
$company_id = $_SESSION['company_id'];
// Delete Client
if (isset($_GET['delete_client'])) {
    $id = (int)$_GET['delete_client'];
    
    // Check for existing invoices
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = ? and company_id = ?");
    $stmt->bind_param("ii", $id,$company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $hasInvoices = $row['count'] > 0;
    
    if (!$hasInvoices) {
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ? and company_id = ?");
        $stmt->bind_param("ii", $id,$company_id );
        $stmt->execute();
        header("Location: clients.php?success=deleted");
    } else {
        header("Location: clients.php?error=has_invoices");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Management</title>
    <link rel="stylesheet" href="css/client_style.css">

</head>
<body>
    <div class="container">
        <h1>Client Management</h1>
    
        <div class="back-div">
            <a href="index.php" class="btn-primary">back</a>
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
                    <?php
                    $result = $conn->query("SELECT * FROM clients where company_id = $company_id ORDER BY name");
                    while ($client = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($client['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($client['email']) . "</td>";
                        echo "<td>" . htmlspecialchars($client['address']) . "</td>";
                        echo "<td class='actions'>
                                <a href='edit_client.php?id={$client['id']}' class='btn-edit'>Edit</a>
                                <button onclick=\"confirmDelete({$client['id']})\" class='btn-delete'>Delete</button>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
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
