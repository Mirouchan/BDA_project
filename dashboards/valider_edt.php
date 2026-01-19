<?php
require '../config.php';


try {
    $stmt = $pdo->prepare("
        UPDATE examens
        SET statu = 'VALIDATION_FINAL'
    ");
    $stmt->execute();

    header("Location: vice_doyen.php?validated=1");
    exit;

} catch (PDOException $e) {
    die("Erreur lors de la validation de l’EDT : " . $e->getMessage());
}
