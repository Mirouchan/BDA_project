<?php
session_start();
require '../config.php';
include __DIR__ . '/../templates/header.php';
echo "<h2>STATISTIQUE - Département</h2>";



if (!isset($_REQUEST['departement_id'])) {
    exit("Département non sélectionné");
}


$dept_id = intval($_REQUEST['departement_id']);


if (isset($_POST['validate_exam'])) {

    $exam_id = intval($_POST['exam_id']);

    $stmt = $pdo->prepare("
        UPDATE examens 
        SET statu = 'VALIDER' 
        WHERE id = ? AND statu != 'VALIDATION_FINAL'
    ");
    $stmt->execute([$exam_id]);

    header("Location: " . $_SERVER['PHP_SELF'] . "?departement_id=" . $dept_id);
    exit;
}



$total_exams = $pdo->prepare("
    SELECT COUNT(*) 
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ?
");
$total_exams->execute([$dept_id]);
$total_exams = $total_exams->fetchColumn();


$validated_by_chef = $pdo->prepare("
    SELECT COUNT(*) 
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ? AND e.statu = 'VALIDER'
");
$validated_by_chef->execute([$dept_id]);
$val_chef = $validated_by_chef->fetchColumn();


$validated_by_admin = $pdo->prepare("
    SELECT COUNT(*) 
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ? AND e.statu = 'VALIDATION_FINAL'
");
$validated_by_admin->execute([$dept_id]);
$val_admin = $validated_by_admin->fetchColumn();


$not_validated = $total_exams - ($val_chef + $val_admin);


$conflicts_count = $pdo->prepare("
    SELECT COUNT(*) 
    FROM conflits_edt c
    LEFT JOIN examens e1 ON c.examen_id_1 = e1.id
    LEFT JOIN modules m ON e1.module_id = m.id
    LEFT JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ?
");
$conflicts_count->execute([$dept_id]);
$total_conflicts = $conflicts_count->fetchColumn();


$taux_conflicts = $total_exams > 0 ? round(($total_conflicts / $total_exams) * 100, 2) : 0;


$prof_hours = $pdo->prepare("
    SELECT DISTINCT e.surveillant_principal_id
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ?
");
$prof_hours->execute([$dept_id]);
$prof_hours = $prof_hours->fetchAll(PDO::FETCH_COLUMN);


echo '<div class="cards-row" style="display:flex; flex-wrap:wrap; gap:-1px; justify-content:center;">';

echo' 
    <div class="card kpi-card">
        <h5><i class="bi bi-journal-check"></i> Total Examens</h5>
        <p>'.$total_exams.'</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-person-check"></i> Validés par chef</h5>
        <p>'.$val_chef.'</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-person-badge"></i> Validés par admin</h5>
        <p>'.$val_admin.'</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-x-circle"></i> Non validés</h5>
        <p>'.$not_validated.'</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-exclamation-triangle"></i> Conflits par departement</h5>
        <p>'.$total_conflicts.'</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-percent"></i> Taux conflits</h5>
        <p>'.$taux_conflicts.'%</p>
      </div>';

echo '<div class="card kpi-card">
        <h5><i class="bi bi-people"></i> Professeurs</h5>
        <p>'.count($prof_hours).'</p>
      </div>';

echo '</div>';



$examens = $pdo->prepare("
    SELECT e.id, m.nom AS module, j.date AS jour, l.nom AS salle, e.date_heure, e.statu, f.nom AS formation
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN jours_session j ON e.jour_id = j.id
    JOIN lieu_exam l ON e.salle_id = l.id
    JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ?
    ORDER BY f.nom, j.date, e.date_heure
");
$examens->execute([$dept_id]);
$rows = $examens->fetchAll();

echo "<h2>Planning - Département</h2>";
if($rows){
    $current_formation = '';
    echo "<ul>";
    foreach($rows as $r){
        if($current_formation != $r['formation']){
            $current_formation = $r['formation'];
            echo "<h3>Formation: {$current_formation}</h3>";
        }

        echo "<li style='margin-bottom:10px;'>";
        echo $r['module']." - ".$r['jour']." - ".$r['salle']." - ".$r['date_heure'];

        if($r['statu'] == 'VALIDATION_FINAL'){
            echo "    Validé par admin";
        } else if($r['statu'] == 'VALIDER'){
            echo "    Validé par chef département";
        } else {
            echo '<form method="post" style="display:inline; margin-left:10px;">
                    <input type="hidden" name="exam_id" value="'.$r['id'].'">
                    <input type="hidden" name="departement_id" value="'.$dept_id.'">
                    <button type="submit" name="validate_exam"> Valider</button>
                  </form>';
        }

        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Aucun examen trouvé.</p>";
}


$conflicts = $pdo->prepare("
    SELECT c.*, m.nom AS module, f.nom AS formation
    FROM conflits_edt c
    LEFT JOIN examens e1 ON c.examen_id_1 = e1.id
    LEFT JOIN modules m ON e1.module_id = m.id
    LEFT JOIN formations f ON m.formation_id = f.id
    WHERE f.departement_id = ?
    ORDER BY f.nom
");
$conflicts->execute([$dept_id]);
$conflicts_list = $conflicts->fetchAll();

echo "<h2>Conflits - Département</h2>";
if($conflicts_list){
    $current_formation = '';
    echo "<ul>";
    foreach($conflicts_list as $c){
        if($current_formation != $c['formation']){
            $current_formation = $c['formation'];
            echo "<h3>Formation: {$current_formation}</h3>";
        }

        echo "<li>";
        echo htmlspecialchars($c['description']);

        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Aucun conflit pour ce département.</p>";
}



include __DIR__ . '/../templates/footer.php';
?>
<style>
.cards-row .kpi-card {
    background: linear-gradient(135deg, #052455, #a6c8ff);
    padding: 10px 50px;
    border-radius: 12px;
    text-align: center;
    color: #fff;
    min-width: 190px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}
.cards-row .kpi-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.cards-row .kpi-card h5 {
    margin-bottom: 5px;
    font-size: 18px;
}

.cards-row .kpi-card p {
    padding: 5px 35px;
    font-size: 28px;
    font-weight: bold;
    margin:0;
}
