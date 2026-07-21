<?php
session_start();
require_once "config.php";

if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']);

    // "Remember me" sadece e-postayı saklar, şifreyi asla saklamaz
    if ($remember && $email !== '') {
        setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
    } else {
        setcookie('remember_email', '', time() - 3600, '/');
    }

    $stmt = mysqli_prepare($conn, "SELECT id, username, password, role, account_status FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if ($row && password_verify($password, $row['password'])) {
        if (isset($row['account_status']) && in_array($row['account_status'], ['suspended', 'banned'])) {
            header("Location: index.php?error=suspended");
            exit();
        }
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'] ?? 'user';
        header("Location: home.php");
        exit();
    } else {
        // Kullanıcı bulunamadı ve yanlış şifre için aynı mesaj (kullanıcı adı sızıntısını önler)
        header("Location: index.php?error=invalid");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
