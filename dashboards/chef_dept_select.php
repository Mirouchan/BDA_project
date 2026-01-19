<?php
session_start();
require '../config.php';
include __DIR__ . '/../templates/header.php';


$departements = $pdo->query("SELECT id, nom FROM departements ORDER BY nom")->fetchAll();
?>

<h2>Chef de département - Sélection du département</h2>
<form action="chef_dept.php" method="post">
    <label for="departement">Choisir le département :</label>
    <select name="departement_id" id="departement" required>
        <option value="">   Choisir </option>
        <?php foreach($departements as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nom']) ?></option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <input type="submit" value="Accéder au chef departement Dashboard">
</form>

<?php
include __DIR__ . '/../templates/footer.php';
?>
