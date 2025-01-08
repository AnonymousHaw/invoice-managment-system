<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has company_id
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$error = null;

// Fetch client data
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Verify the client belongs to the company
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    
    if (!$client) {
        header("Location: clients.php?error=client_not_found");
        exit();
    }
} else {
    header("Location: clients.php");
    exit();
}

// Update Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    try {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        // Basic validation
        if (empty($name)) throw new Exception("Name is required");
        if (empty($email)) throw new Exception("Email is required");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email format");
        if (empty($address)) throw new Exception("Address is required");
        
        // Double-check client belongs to company
        if ($client['company_id'] != $company_id) {
            throw new Exception("You don't have permission to edit this client");
        }
        
        // Update the client
        $stmt = $conn->prepare("UPDATE clients SET name = ?, email = ?, address = ? WHERE id = ? AND company_id = ?");
        $stmt->bind_param("sssii", $name, $email, $address, $id, $company_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        header("Location: clients.php?success=updated");
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client</title>
    <link rel="stylesheet" href="css/edit_client.css">
</head>
<body>
    <div class="container">
        <div class="back-div">
            <a href="clients.php" class="btn-secondary">Back</a>
        </div>
        
        <h1>Edit Client</h1>
        
        <?php if ($error): ?>
            <div class="alert error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" action="edit_client.php?id=<?php echo $client['id']; ?>">
                <input type="hidden" name="action" value="update_client">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($client['id']); ?>">
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($client['name']); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($client['email']); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" 
                              name="address" 
                              required><?php echo htmlspecialchars($client['address']); ?></textarea>
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