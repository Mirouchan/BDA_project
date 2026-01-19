<?php
session_start();
require '../config.php';
include __DIR__ . '/../templates/header.php';

echo "<h2>Dashboard Administrateur</h2>";


echo '<form method="post">
        <input type="submit" name="generate" value="Générer EDT">
      </form>';

if (isset($_POST['generate'])) {
    $start = microtime(true);

    // Vider tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("TRUNCATE TABLE conflits_edt;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

   
    $modules = $pdo->query("
        SELECT m.id AS module_id, m.responsable_id, COUNT(i.id) AS nb_etudiants
        FROM modules m
        LEFT JOIN inscriptions i ON m.id = i.module_id
        GROUP BY m.id
        ORDER BY nb_etudiants DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

 
    $jours = $pdo->query("SELECT id, date FROM jours_session WHERE est_ferie = 0 ORDER BY date")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT id, nom, capacite FROM lieu_exam ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);

    $slots = ['09:00:00', '13:00:00', '15:30:00']; 


    $all_slots = [];
    foreach ($jours as $jour) {
        foreach ($slots as $time) {
            foreach ($salles as $salle) {
                $all_slots[] = [
                    'datetime' => $jour['date'].' '.$time,
                    'jour_id' => $jour['id'],
                    'salle_id' => $salle['id'],
                    'capacite' => $salle['capacite']
                ];
            }
        }
    }


    $salle_occupancy_set = [];
    $prof_schedule_set = [];

    $success_count = 0;
    $conflicts = [];

 
    $insertExam = $pdo->prepare("
        INSERT INTO examens (module_id, salle_id, jour_id, surveillant_principal_id, date_heure, duree_minute)
        VALUES (?, ?, ?, ?, ?, 90)
    ");

    $pdo->beginTransaction();

    foreach ($modules as $mod) {
        $placed = false;
        foreach ($all_slots as $slot) {
            $key_salle = $slot['salle_id'].'_'.$slot['datetime'];
            $key_prof  = $mod['responsable_id'].'_'.$slot['datetime'];

            if (empty($salle_occupancy_set[$key_salle]) && empty($prof_schedule_set[$key_prof]) && $slot['capacite'] >= $mod['nb_etudiants']) {
                // Insérer examen
                $insertExam->execute([
                    $mod['module_id'],
                    $slot['salle_id'],
                    $slot['jour_id'],
                    $mod['responsable_id'],
                    $slot['datetime']
                ]);

         
                $salle_occupancy_set[$key_salle] = true;
                $prof_schedule_set[$key_prof] = true;

                $success_count++;
                $placed = true;
                break;
            }
        }

        if (!$placed) {
            $conflicts[] = [
                'type' => 'Impossible de placer',
                'description' => "Module ID {$mod['module_id']} avec {$mod['nb_etudiants']} étudiants"
            ];
        }
    }

 
    if ($conflicts) {
        $stmtConflict = $pdo->prepare("
            INSERT INTO conflits_edt (type_conflit, description, statut)
            VALUES (?, ?, 'NON_RESOLU')
        ");
        foreach ($conflicts as $c) {
            $stmtConflict->execute([$c['type'], $c['description']]);
        }
    }

    $pdo->commit();

    $end = microtime(true);
    $duration = round($end - $start, 2);

    echo "<div class='alert alert-success'>Génération terminée: $success_count modules placés. Durée: {$duration} sec.</div>";
}


$conflicts = $pdo->query("SELECT * FROM conflits_edt WHERE statut='NON_RESOLU'")->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Conflits non résolus</h3>";
if ($conflicts) {
    echo "<ul>";
    foreach ($conflicts as $c) {
        echo "<li>".htmlspecialchars($c['description']);
        echo '
        <form method="get" action="optimize_conflict.php" style="display:inline; margin-left:10px;">
            <input type="hidden" name="conflict_id" value="'.$c['id'].'">
            <button type="submit">Optimiser</button>
        </form>
        ';
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Aucun conflit détecté.</p>";
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
</style>
