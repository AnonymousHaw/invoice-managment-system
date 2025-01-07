<?php
// edit_client.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}
$company_id = $_SESSION['company_id'];

// Fetch client data
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    
    if (!$client) {
        header("Location: clients.php");
        exit();
    }
} else {
    header("Location: clients.php");
    exit();
}

// Verify company exists
$query = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$query->bind_param("i", $company_id);

if ($query->execute()) {
    $result = $query->get_result(); // Get the result of the query
    if ($result->num_rows === 0) {
        // Company not found
        header("Location: index.php");
        exit();
    }
} else {
    // Query execution failed
    header("Location: index.php");
    exit();
}

// Update Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Compare company_id from the client data with the current session's company_id
    if ($client['company_id'] == $company_id) { 
        $stmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, address = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $address, $id);
        
        if ($stmt->execute()) {
            header("Location: clients.php?success=updated");
            exit();
        } else {
            $error = "Update failed: " . $conn->error;
        }
    } else {
        $error = "You cannot update this client.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Client</title>
    <link rel="stylesheet" href="css/edit_client.css">
</head>
<body>
    <div class="container">
        <h1>Edit Client</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" action="edit_client.php">
                <input type="hidden" name="action" value="update_client">
                <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
                
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Address:</label>
                    <textarea name="address" required><?php echo htmlspecialchars($client['address']); ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-primary">Update Client</button>
                    <a href="clients.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
