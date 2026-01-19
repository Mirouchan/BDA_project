<?php
require '../config.php';
include '../templates/header.php';


$total_salles = $pdo->query("SELECT COUNT(*) FROM lieu_exam")->fetchColumn();
$total_examens = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();
$total_conflicts = $pdo->query("SELECT COUNT(*) FROM conflits_edt WHERE statut='NON_RESOLU'")->fetchColumn();
$prof_hours = $pdo->query("SELECT surveillant_principal_id, COUNT(*) as heures FROM examens GROUP BY surveillant_principal_id")->fetchAll();


$salles_data = $pdo->query("SELECT l.nom, COUNT(e.id) as total FROM lieu_exam l LEFT JOIN examens e ON l.id = e.salle_id GROUP BY l.id")->fetchAll(PDO::FETCH_ASSOC);
$conflits_data = $pdo->query("SELECT d.nom, COUNT(c.id) as total FROM conflits_edt c 
                              LEFT JOIN examens e1 ON c.examen_id_1 = e1.id
                              LEFT JOIN modules m ON e1.module_id = m.id
                              LEFT JOIN formations f ON m.formation_id = f.id
                              LEFT JOIN departements d ON f.departement_id = d.id
                              GROUP BY d.id")->fetchAll(PDO::FETCH_ASSOC);

$data = $pdo->query("
    SELECT 
        COUNT(DISTINCT salle_id) AS salles_utilisees,
        (SELECT COUNT(*) FROM lieu_exam) AS total_salles
    FROM examens
")->fetch();

$taux_salles = 0;
if ($data['total_salles'] > 0) {
    $taux_salles = round(
        ($data['salles_utilisees'] / $data['total_salles']) * 100,
        2
    );
}

$sql = "
SELECT 
    d.nom AS departement,
    COUNT(DISTINCT e.id) AS total_examens,
    COUNT(DISTINCT c.id) AS total_conflits
FROM departements d
LEFT JOIN formations f ON f.departement_id = d.id
LEFT JOIN modules m ON m.formation_id = f.id
LEFT JOIN examens e ON e.module_id = m.id
LEFT JOIN conflits_edt c 
       ON (c.examen_id_1 = e.id OR c.examen_id_2 = e.id)
       AND c.statut = 'NON_RESOLU'
GROUP BY d.id
";

$stats = $pdo->query($sql)->fetchAll();

foreach ($stats as &$row) {
    if ($row['total_examens'] > 0) {
        $row['taux'] = round(
            ($row['total_conflits'] / $row['total_examens']) * 100,
            2
        );
    } else {
        $row['taux'] = 0;
    }
}




?>

<h2>Dashboard Vice-Doyen / Doyen</h2>


<div style="text-align: center; margin: 20px 0;">
    <form method="post" action="valider_edt.php">
        <button type="submit" class="btn-validate"on click="return confirm('Confirmer la validation finale de l’EDT ?')">✔ Valider EDT</button>
    </form>
</div>

<!-- KPIs Cards -->
<div class="cards-row" style="display:flex; flex-wrap:wrap; gap:-1px; justify-content:center;">
    <div class="card kpi-card">
        <h5><i class="bi bi-building"></i> Salles</h5>
        <p><?= $total_salles ?></p>
    </div>
      
     <div class="card kpi-card">
        <h5><i class="bi bi-building"></i> taux des Salles</h5>
        <p><?= $taux_salles ?>%</p>
    </div>
  
    <div class="card kpi-card">
        <h5><i class="bi bi-exclamation-triangle"></i> Conflits</h5>
        <p><?= $total_conflicts ?></p>
    </div>
     <div class="card kpi-card">
        <h5><i class="bi bi-building"></i> taux des conflit</h5>
        <p><?= $row['taux'] ?>%</p>
    </div>
    <div class="card kpi-card">
        <h5><i class="bi bi-people"></i>  nbr Prof seveille</h5>
        <p><?= count($prof_hours) ?></p>
    </div>
     <div class="card kpi-card">
        <h5><i class="bi bi-journal-check"></i> Examens</h5>
        <p><?= $total_examens ?></p>
    </div>
</div>

<!-- Graphiques -->
<div style="display:flex; flex-wrap:wrap; gap:20px; justify-content:center; margin-top:20px;">
    <div style="width:500px;">
        <canvas id="occupationChart"></canvas>
    </div>
    <div style="width:300px;">
        <canvas id="conflitsChart"></canvas>
    </div>
</div>

<h3>Heures par professeur</h3>
<ul>
<?php foreach($prof_hours as $row): ?>
    <li>Prof ID <?= $row['surveillant_principal_id'] ?> : <?= $row['heures'] ?> examens</li>
<?php endforeach; ?>
</ul>

<?php include '../templates/footer.php'; ?>

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

.btn-validate {
    padding: 12px 25px;
    background: linear-gradient(135deg, #0b93b1, #08f1ca);
    color: #fff;
    border:none;
    border-radius:10px;
    font-size:16px;
    cursor:pointer;
    transition: all 0.3s ease;
}
.btn-validate:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}
</style>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const occupationData = {
    labels: [<?php foreach($salles_data as $s) echo "'".$s['nom']."',"; ?>],
    datasets: [{
        label: 'Nombre d\'examens par salle',
        data: [<?php foreach($salles_data as $s) echo $s['total'].","; ?>],
        backgroundColor: '#8cd3c7',
        borderColor: '#b8ded8',
        borderWidth: 1
        
    }]
};

const occupationChart = new Chart(document.getElementById('occupationChart'), {
    type: 'bar',
    data: occupationData,
    options: {
        responsive:true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero:true } }
    }
});

const conflitsData = {
    labels: [<?php foreach($conflits_data as $c) echo "'".$c['nom']."',"; ?>],
    datasets: [{
        label: 'Conflits par département',
        data: [<?php foreach($conflits_data as $c) echo $c['total'].","; ?>],
        backgroundColor: [
            '#f87171','#fbbf24','#34d399','#60a5fa','#a78bfa','#f472b6'
        ],
        borderWidth: 1
    }]
};

const conflitsChart = new Chart(document.getElementById('conflitsChart'), {
    type: 'pie',
    data: conflitsData,
    options: { responsive:true }
});
</script>
