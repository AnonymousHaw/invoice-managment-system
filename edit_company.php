<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['company_id'])) {
    header("Location: login.php");
    exit();
}

$company_id = $_SESSION['company_id'];
$message = '';
$error = '';

// Fetch current company info
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
   
    
    // Handle logo upload
    $logo_url = $company['logo_path']; // Keep existing logo by default
    
    if (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['logo']['name'];
        $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($filetype, $allowed)) {
            $new_filename = 'company_' . $company_id . '_' . time() . '.' . $filetype;
            $upload_path = 'uploads/logos/' . $new_filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo_url = $upload_path;
            } else {
                $error = "Failed to upload logo";
            }
        } else {
            $error = "Invalid file type. Allowed: JPG, JPEG, PNG";
        }
    }
    
    if (empty($error)) {
        $stmt = $conn->prepare("UPDATE companies SET name = ?, email = ?, phone = ?, logo_path = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $phone, $logo_url, $company_id);
        
        if ($stmt->execute()) {
            $_SESSION['company_name'] = $name;
            $message = "Company information updated successfully";
            
            // Refresh company data
            $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $company = $result->fetch_assoc();
            header("location:index.php");
        } else {
            $error = "Failed to update company information";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Company Profile</title>
    <link rel="stylesheet" href="css/edit_company.css">
    
    
</head>
<body>
    <div class="container">
        <div class="back-div">
            <a href="index.php" class="btn-secondary">Back to Dashboard</a>
        </div>
        
        <h1>Edit Company Profile</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="form-section">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Company Logo:</label>
                    <?php if ($company['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Current Logo" class="preview-logo">
                    <?php endif; ?>
                    <input type="file" name="logo" accept="image/jpeg,image/png">
                </div>
                
                <div class="form-group">
                    <label>Company Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($company['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($company['email']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>Phone:</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($company['phone']); ?>">
                </div>
                
                
                
                <div class="button-group">
                    <button type="submit" class="btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>