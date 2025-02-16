<?php
require_once 'includes/database.php';

$patchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$patchId) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid patch ID');
}

$db = Database::getInstance();
$stmt = $db->prepare('SELECT patch_content FROM patches WHERE id = :id');
$stmt->bindValue(':id', $patchId, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    header('Content-Type: text/plain');
    echo $row['patch_content'];
} else {
    header('HTTP/1.1 404 Not Found');
    exit('Patch not found');
}
