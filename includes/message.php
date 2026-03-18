<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $product_id  = (int)($_POST['product_id']  ?? null) ?: null;
    $message     = clean($_POST['message'] ?? '');
    $redirect    = $_POST['redirect'] ?? '../index.php';

    if ($receiver_id && $message) {
        $db   = getDB();
        $stmt = $db->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message) VALUES (?,?,?,?)");
        $stmt->bind_param("iiis", $_SESSION['user_id'], $receiver_id, $product_id, $message);
        $stmt->execute();
    }
    header("Location: ../$redirect");
    exit;
}
header("Location: ../index.php");
exit;
?>
