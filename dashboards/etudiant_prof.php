<?php
session_start();
require '../config.php';
include __DIR__ . '/../templates/header.php';

echo "<h2>Planning</h2>";


if(!isset($_POST['id'], $_POST['nom'], $_POST['prenom'])){
    echo "<p>Informations manquantes. <a href='etudiant_prof_select.php'>Retour</a></p>";
    exit;
}

$id = $_POST['id'];
$nom = $_POST['nom'];
$prenom = $_POST['prenom'];


$departements = $pdo->query("SELECT id, nom FROM departements ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);


$formations = [];
if(isset($_POST['departement_id']) && $_POST['departement_id'] != ""){
    $dept_id = (int) $_POST['departement_id'];
    $stmt = $pdo->prepare("SELECT id, nom FROM formations WHERE departement_id=? ORDER BY nom");
    $stmt->execute([$dept_id]);
    $formations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


$selected_dept = isset($_POST['departement_id']) ? $_POST['departement_id'] : '';
$selected_formation = isset($_POST['formation_id']) ? $_POST['formation_id'] : '';




echo '<form method="post" style="margin-bottom:10px; display:flex; gap:20px;">';
echo '<input type="hidden" name="id" value="'.$id.'">';
echo '<input type="hidden" name="nom" value="'.$nom.'">';
echo '<input type="hidden" name="prenom" value="'.$prenom.'">';


echo '<div style="display:flex; flex-direction:column;">';
echo '<label for="departement_id">Département</label>';
echo '<select id="departement_id" name="departement_id" onchange="this.form.submit()" style="padding:5px; width:150px;">';
echo '<option value=""> Tous </option>';
foreach($departements as $d){
    $sel = ($selected_dept == $d['id']) ? 'selected' : '';
    echo "<option value='{$d['id']}' $sel>{$d['nom']}</option>";
}
echo '</select>';
echo '</div>';

echo '<div style="display:flex; flex-direction:column;">';
echo '<label for="formation_id">Formation</label>';
echo '<select id="formation_id" name="formation_id" onchange="this.form.submit()" style="padding:5px; width:150px;">';
echo '<option value=""> Tous </option>';
foreach($formations as $f){
    $sel = ($selected_formation == $f['id']) ? 'selected' : '';
    echo "<option value='{$f['id']}' $sel>{$f['nom']}</option>";
}
echo '</select>';
echo '</div>';

echo '</form>';



$sql_base = "
SELECT e.id AS examen_id, e.statu, m.nom AS module, j.date AS jour, l.nom AS salle, e.date_heure
FROM examens e
JOIN modules m ON e.module_id = m.id
JOIN jours_session j ON e.jour_id = j.id
JOIN lieu_exam l ON e.salle_id = l.id
JOIN formations f ON m.formation_id = f.id
";


$sql_where = "";
$params = [];

$student = $pdo->prepare("SELECT * FROM etudiants WHERE id=? AND nom=? AND prenom=?");
$student->execute([$id, $nom, $prenom]);
$etudiant = $student->fetch();

if($etudiant){
    $sql_where = " WHERE EXISTS (
        SELECT 1 FROM inscriptions i WHERE i.etudiant_id=? AND i.module_id = e.module_id
    )";
    $params[] = $id;
}else{
   
    $prof = $pdo->prepare("SELECT * FROM professeurs WHERE id=? AND nom=? AND prenom=?");
    $prof->execute([$id, $nom, $prenom]);
    $professeur = $prof->fetch();
    if($professeur){
        $sql_where = " WHERE e.surveillant_principal_id = ?";
        $params[] = $id;
    }else{
        echo "<p>Aucun étudiant ou professeur trouvé.</p>";
        include __DIR__ . '/../templates/footer.php';
        exit;
    }
}


if($selected_dept) $sql_where .= ($sql_where ? " AND " : " WHERE ")."f.departement_id = ?";
if($selected_formation) $sql_where .= ($sql_where ? " AND " : " WHERE ")."f.id = ?";

if($selected_dept) $params[] = $selected_dept;
if($selected_formation) $params[] = $selected_formation;


$sql_order = " ORDER BY j.date, e.date_heure";

$stmt = $pdo->prepare($sql_base . $sql_where . $sql_order);
$stmt->execute($params);
$examens = $stmt->fetchAll(PDO::FETCH_ASSOC);



if($examens){
    echo "<ul>";
    foreach($examens as $e){
        echo "<li>";
        echo "{$e['module']} - {$e['jour']} - {$e['salle']} - {$e['date_heure']}";

        if($e['statu'] != 'VALIDATION_FINAL' && $etudiant === false){
          
            echo '<form method="post" style="display:inline; margin-left:10px;">
                <input type="hidden" name="exam_id" value="'.$e['examen_id'].'">
                <input type="hidden" name="id" value="'.$id.'">
                <input type="hidden" name="nom" value="'.$nom.'">
                <input type="hidden" name="prenom" value="'.$prenom.'">
                <input type="hidden" name="departement_id" value="'.$selected_dept.'">
                <input type="hidden" name="formation_id" value="'.$selected_formation.'">
               
            </form>';
        }

        echo " - <strong>{$e['statu']}</strong>";
        echo "</li>";
    }
    echo "</ul>";
}else{
    echo "<p>Aucun examen trouvé.</p>";
}

include __DIR__ . '/../templates/footer.php';

