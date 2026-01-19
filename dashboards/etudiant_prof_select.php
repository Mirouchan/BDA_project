<?php
session_start();
include __DIR__ . '/../templates/header.php';
?>

<h2>Étudiant / Professeur - Entrer vos informations</h2>
<form action="etudiant_prof.php" method="post">
    <label for="id">ID :</label>
    <input type="number" name="id" id="id" required><br><br>

    <label for="nom">Nom :</label>
    <input type="text" name="nom" id="nom" required><br><br>

    <label for="prenom">Prénom :</label>
    <input type="text" name="prenom" id="prenom" required><br><br>

    <input type="submit" value="Voir mon planning">
</form>

<?php
include __DIR__ . '/../templates/footer.php';
?>
