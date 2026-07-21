<?php
// Geriye dönük uyumluluk: eski bağlantılar (bildirimler vb.) buraya gelirse
// yeni birleşik forum arayüzüne yönlendir.
$thread_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: forum.php?thread=" . $thread_id);
exit();
