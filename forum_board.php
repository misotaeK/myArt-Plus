<?php
// Geriye dönük uyumluluk: eski bağlantılar buraya gelirse
// yeni birleşik forum arayüzüne (pano filtresiyle) yönlendir.
$board_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: forum.php?board=" . $board_id);
exit();
