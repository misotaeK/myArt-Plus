<?php

$conn = mysqli_connect("localhost", "root", "", "myart");

if (mysqli_connect_errno()) {
    printf("Bağlantı hatası: %s\n", mysqli_connect_error());
    exit();
}

// Türkçe karakter sorunu yaşamamak için
mysqli_set_charset($conn, "utf8mb4");
