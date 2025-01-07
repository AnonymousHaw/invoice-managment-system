<?php
// add_client.php
session_start();
require_once 'config.php';

// Check if user is logged in and has company_id
if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}


$company_id = $_SESSION['company_id'];

// Create Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_client') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    
    // Basic validation
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if (empty($address)) $errors[] = "Address is required";

    $query = $conn->prepare("select * from companies where id = ?");
    $query->bind_param("i", $company_id);
    
    if ($query->execute()) {
        $result = $query->get_result(); // Get the result of the query
        if ($result->num_rows > 0) {
            // Company exists
            echo "Company found";
        } else {
            // Company not found
            $errors[] = 'Company not found';
        }
    } else {
        // Query execution failed
        $errors[] = "Failed to check company: " . $conn->error;
    }
    
    if (empty($errors)) {
        // Modified query to include company_id
        $stmt = $conn->prepare("INSERT INTO clients (name, email, address, company_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $email, $address, $company_id);
        
        if ($stmt->execute()) {
            header("Location: clients.php?success=created");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Client</title>
    <link rel="stylesheet" href="css/add_client.css">
</head>
<body>
    <div class="container">
        <div class="back-div">
            <a href="clients.php" class="btn-primary">back</a>
        </div>
        <h1>Add New Client</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" action="add_client.php">
                <input type="hidden" name="action" value="create_client">
                
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Address:</label>
                    <textarea name="address" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn-primary">Add Client</button>
                    <a href="clients.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>