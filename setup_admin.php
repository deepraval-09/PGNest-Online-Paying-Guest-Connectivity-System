<?php
include 'db.php';

$name = "Super Admin";
$email = "pgnest.official@gmail.com";
$password = "admin123"; 
$phone = "";
$role = "admin";

// 1. Check if admin exists
$check = $conn->query("SELECT * FROM users WHERE role='admin'");
if ($check->num_rows > 0) {
    die("Admin account already exists! Login with: $email / $password");
}

// 2. Create Admin
$hashed = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO users (full_name, email, phone, password, role) VALUES ('$name', '$email', '$phone', '$hashed', '$role')";

if ($conn->query($sql)) {
    echo "<h1>Admin Created Successfully!</h1>";
    echo "<p>Email: <strong>$email</strong></p>";
    echo "<p>Password: <strong>$password</strong></p>";
    echo "<a href='login.php'>Go to Login</a>";
} else {
    echo "Error: " . $conn->error;
}
?>