<?php
require_once "config.php";

$admin_username = "admin";
$admin_email = "admin@hotmail.com";
$admin_password = "1502";
$admin_password_hashed = password_hash($admin_password, PASSWORD_DEFAULT);

// Bu site girişleri "users" tablosu üzerinden yapıyor (role = 'admin'),
// ayrı bir "admins" tablosu yok — bu yüzden admin hesabı da users'a yazılmalı.
$stmt = mysqli_prepare($conn, "SELECT id, role FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $admin_email);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($existing) {
    if ($existing['role'] !== 'admin') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET role = 'admin' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $existing['id']);
        mysqli_stmt_execute($stmt);
        echo "Existing user promoted to admin!<br><br>";
    } else {
        echo "Admin user already exists!<br><br>";
    }
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
    mysqli_stmt_bind_param($stmt, "sss", $admin_username, $admin_email, $admin_password_hashed);

    if (!mysqli_stmt_execute($stmt)) {
        die("Error adding admin: " . mysqli_error($conn));
    }
    echo "Admin account created successfully!<br><br>";
}

echo "<strong>Login Credentials:</strong><br>";
echo "Email: <code>" . htmlspecialchars($admin_email) . "</code><br>";
echo "Password: <code>" . htmlspecialchars($admin_password) . "</code><br><br>";
echo "<a href='login.php' style='padding: 10px 15px; background-color: #e96cc3; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";

mysqli_close($conn);
