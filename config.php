<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "myart";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed:" . $conn->connect_error);
}

function add_notification($conn, $user_id, $type, $message, $link = null, $related_user_id = null) {
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, related_user_id, type, message, link) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iisss", $user_id, $related_user_id, $type, $message, $link);
    mysqli_stmt_execute($stmt);
}

// XAMPP'ın varsayılan kurulumunda giden e-posta yapılandırılmamıştır (Mercury Mail
// kurulu/etkin değil), bu yüzden mail() genelde false döner. Bu bayrak açıkken,
// forgot_password.php sıfırlama bağlantısını ekranda da gösterir ki özellik
// gerçek SMTP kurulmadan da test edilebilsin. Canlıya almadan önce false yapın.
define('DEV_SHOW_RESET_LINK', true);
