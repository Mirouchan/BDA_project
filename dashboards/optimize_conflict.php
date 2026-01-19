<?php
require '../config.php';
include __DIR__ . '/../templates/header.php';


if(!isset($_GET['conflict_id'])){
    die("Conflit non sélectionné");
}

$conflict_id = (int)$_GET['conflict_id'];


$stmt = $pdo->prepare("SELECT * FROM conflits_edt WHERE id=?");
$stmt->execute([$conflict_id]);
$conflict = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$conflict){
    die("Conflit introuvable");
}


$ids = [];
if(!empty($conflict['examen_id_1'])) $ids[] = (int)$conflict['examen_id_1'];
if(!empty($conflict['examen_id_2'])) $ids[] = (int)$conflict['examen_id_2'];
if(count($ids) === 0) die("Aucun examen associé à ce conflit");


$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT 
        e.id, e.date_heure, e.salle_id, e.surveillant_principal_id,
        m.nom AS module, l.nom AS salle
    FROM examens e
    JOIN modules m ON e.module_id = m.id
    JOIN lieu_exam l ON e.salle_id = l.id
    WHERE e.id IN ($placeholders)
");
$stmt->execute($ids);
$examens = $stmt->fetchAll(PDO::FETCH_ASSOC);


if(isset($_POST['save_optimization'])){
    $new_salle = $_POST['new_salle'] ?? [];
    $new_date  = $_POST['new_date'] ?? [];
    $new_prof  = $_POST['new_prof'] ?? [];

    foreach($ids as $exam_id){
        $salle_id = $new_salle[$exam_id] ?? null;
        $date     = $new_date[$exam_id] ?? null;
        $prof_id  = $new_prof[$exam_id] ?? null;

        $sql = "UPDATE examens SET ";
        $updates = [];
        $params = [];

        if($salle_id) { $updates[] = "salle_id=?"; $params[] = $salle_id; }
        if($date)     { $updates[] = "date_heure=?"; $params[] = $date; }
        if($prof_id)  { $updates[] = "surveillant_principal_id=?"; $params[] = $prof_id; }

        if(!empty($updates)){
            $sql .= implode(", ", $updates) . " WHERE id=?";
            $params[] = $exam_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }


    $stmt = $pdo->prepare("UPDATE conflits_edt SET statut='RESOLU' WHERE id=?");
    $stmt->execute([$conflict_id]);

    echo "<p style='color:green;'>Conflit optimisé avec succès !</p>";
    echo "<a href='administrateur.php'>Retour au dashboard</a>";
    include __DIR__ . '/../templates/footer.php';
    exit;
}


echo "<h2>Optimisation du conflit</h2>";
echo "<p><strong>".htmlspecialchars($conflict['description'])."</strong></p>";

echo "<form method='post'>";
foreach($examens as $e){
    echo "<fieldset style='margin-bottom:15px; padding:10px;'>";
    echo "<legend>Examen : <strong>{$e['module']}</strong></legend>";

    if($conflict['type_conflit'] === 'Salle'){
        echo "Salle libre : <select name='new_salle[{$e['id']}]'>";
        $salles = $pdo->query("
            SELECT s.id, s.nom 
            FROM lieu_exam s
            WHERE s.id NOT IN (
                SELECT salle_id FROM examens WHERE DATE(date_heure) = DATE('{$e['date_heure']}')
            )
        ")->fetchAll();
        foreach($salles as $s){
            $sel = ($s['id'] == $e['salle_id']) ? 'selected' : '';
            echo "<option value='{$s['id']}' $sel>{$s['nom']}</option>";
        }
        echo "</select><br>";
    }

    if($conflict['type_conflit'] === 'Etudiant'){
        echo "Nouvelle date : <input type='datetime-local' name='new_date[{$e['id']}]' value='".date('Y-m-d\TH:i', strtotime($e['date_heure']))."'><br>";
    }

    if($conflict['type_conflit'] === 'Professeur'){
        echo "Nouveau surveillant : <select name='new_prof[{$e['id']}]'>";
        $profs = $pdo->query("
            SELECT id, CONCAT(nom,' ',prenom) AS nom 
            FROM professeurs
            WHERE id NOT IN (
                SELECT surveillant_principal_id
                FROM examens
                WHERE DATE(date_heure) = DATE('{$e['date_heure']}')
                GROUP BY surveillant_principal_id
                HAVING COUNT(*) >= 3
            )
        ")->fetchAll();
        foreach($profs as $p){
            $sel = ($p['id']==$e['surveillant_principal_id']) ? 'selected' : '';
            echo "<option value='{$p['id']}' $sel>{$p['nom']}</option>";
        }
        echo "</select><br>";
    }

    echo "</fieldset>";
}
echo "<button type='submit' name='save_optimization'>Enregistrer</button>";
echo "</form>";

include __DIR__ . '/../templates/footer.php';
?>
