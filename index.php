

<?php
session_start();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Choisir un acteur</title>
    <link rel="stylesheet" href="css/style.css">
    
</head>
<body>
    <div class="container">
        <h1>Optimisation des Examens</h1>
          <form action="dashboard.php" method="post">
        <button type="submit" name="acteur" value="vice_doyen" class="role-btn">Vice-doyen / Doyen</button>
        <button type="submit" name="acteur" value="administrateur" class="role-btn">Administrateur examens</button>
        <button type="submit" name="acteur" value="chef_dept" class="role-btn">Chef de département</button>
        <button type="submit" name="acteur" value="etudiant_prof" class="role-btn">Étudiant / Professeur</button>
    </form>
    </div>
</body>
</html>
