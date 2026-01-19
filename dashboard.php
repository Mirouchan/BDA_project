<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acteur'])) {
    $acteur = $_POST['acteur'];
    $_SESSION['acteur'] = $acteur;

    switch ($acteur) {
        case 'vice_doyen':
            header('Location: dashboards/vice_doyen.php');
            exit;
        case 'administrateur':
            header('Location: dashboards/administrateur.php');
            exit;
        case 'chef_dept':
            header('Location: dashboards/chef_dept_select.php');
            exit;
        case 'etudiant_prof':
            header('Location: dashboards/etudiant_prof_select.php');
            exit;
        default:
            die('Acteur non reconnu');
    }
} else {
    header('Location: index.php');
    exit;
}
