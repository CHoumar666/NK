<?php
require __DIR__ . '/includes/bootstrap.php';

$stats = fetch_one("
    SELECT
        (SELECT COUNT(*) FROM employe)                          AS employes,
        (SELECT COUNT(*) FROM service)                          AS services,
        (SELECT COUNT(*) FROM client)                           AS clients,
        (SELECT COUNT(*) FROM materiel)                         AS materiels,
        (SELECT COUNT(*) FROM materiel WHERE etat <> 'Disponible') AS materiels_indispo,
        (SELECT COUNT(*) FROM intervention)                     AS interventions,
        (SELECT COUNT(*) FROM acte_depannage)                   AS actes,
        (SELECT COALESCE(SUM(cout), 0) FROM acte_depannage)     AS chiffre_affaires
");

$parStatut = fetch_all("
    SELECT statut, COUNT(*) AS total
    FROM intervention
    GROUP BY statut
    ORDER BY FIELD(statut, 'En cours', 'Planifiee', 'Terminee', 'Annulee')
");

$parService = fetch_all("
    SELECT s.id_service, s.nom_service, COUNT(e.id_employe) AS total
    FROM service s
    LEFT JOIN employe e ON e.id_service = s.id_service
    GROUP BY s.id_service, s.nom_service
    ORDER BY total DESC, s.nom_service
");
$maxService = max(array_column($parService, 'total') ?: [1]);

$topClients = fetch_all("
    SELECT c.id_client, c.nom, COUNT(DISTINCT i.id_intervention) AS nb, COALESCE(SUM(a.cout), 0) AS montant
    FROM client c
    JOIN intervention i ON i.id_client = c.id_client
    LEFT JOIN acte_depannage a ON a.id_intervention = i.id_intervention
    GROUP BY c.id_client, c.nom
    ORDER BY montant DESC
    LIMIT 5
");

$recentes = fetch_all("
    SELECT i.id_intervention, i.date_intervention, i.description, i.statut,
           c.nom AS client, CONCAT(e.nom, ' ', e.prenom) AS responsable,
           COALESCE(SUM(a.cout), 0) AS cout_total
    FROM intervention i
    JOIN client c  ON c.id_client = i.id_client
    JOIN employe e ON e.id_employe = i.id_responsable
    LEFT JOIN acte_depannage a ON a.id_intervention = i.id_intervention
    GROUP BY i.id_intervention, i.date_intervention, i.description, i.statut, c.nom, e.nom, e.prenom
    ORDER BY i.date_intervention DESC
    LIMIT 6
");

$enCours = (int) fetch_value("SELECT COUNT(*) FROM intervention WHERE statut IN ('En cours', 'Planifiee')");
$panierMoyen = $stats['interventions'] ? $stats['chiffre_affaires'] / $stats['interventions'] : 0;

$pageTitle = 'Tableau de bord';
$pageSubtitle = 'Vue d\'ensemble de l\'activite de l\'entreprise de depannage.';
$activePage = 'dashboard';
$pageActions = '<a class="btn" href="rapports.php">' . icon('rapport') . '<span>Rapports</span></a>'
    . '<a class="btn btn-primary" href="interventions.php?action=form">' . icon('plus') . '<span>Nouvelle intervention</span></a>';
require __DIR__ . '/includes/header.php';
?>

<div class="kpis">
    <div class="card kpi">
        <div class="kpi-label">Chiffre d'affaires</div>
        <div class="kpi-value small"><?= money($stats['chiffre_affaires']) ?></div>
        <div class="kpi-hint">Somme des couts des <?= number($stats['actes']) ?> actes</div>
    </div>
    <div class="card kpi">
        <div class="kpi-label">Interventions</div>
        <div class="kpi-value"><?= number($stats['interventions']) ?></div>
        <div class="kpi-hint"><?= $enCours ?> en cours ou planifiee(s)</div>
    </div>
    <div class="card kpi">
        <div class="kpi-label">Cout moyen par intervention</div>
        <div class="kpi-value small"><?= money($panierMoyen) ?></div>
        <div class="kpi-hint">Sur l'ensemble des interventions</div>
    </div>
    <div class="card kpi">
        <div class="kpi-label">Employes</div>
        <div class="kpi-value"><?= number($stats['employes']) ?></div>
        <div class="kpi-hint">Repartis dans <?= number($stats['services']) ?> services</div>
    </div>
    <div class="card kpi">
        <div class="kpi-label">Materiels references</div>
        <div class="kpi-value"><?= number($stats['materiels']) ?></div>
        <div class="kpi-hint"><?= number($stats['materiels_indispo']) ?> indisponible(s)</div>
    </div>
    <div class="card kpi">
        <div class="kpi-label">Clients</div>
        <div class="kpi-value"><?= number($stats['clients']) ?></div>
        <div class="kpi-hint">Portefeuille actif</div>
    </div>
</div>

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-header">
            <div>
                <h2>Interventions recentes</h2>
                <p class="muted">Les six dernieres interventions enregistrees</p>
            </div>
            <a class="btn btn-sm" href="interventions.php">Tout voir</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr><th>N°</th><th>Date</th><th>Client</th><th>Responsable</th><th>Statut</th><th class="num">Cout</th></tr>
                </thead>
                <tbody>
                <?php foreach ($recentes as $i): ?>
                    <tr>
                        <td class="nowrap"><a href="interventions.php?action=view&id=<?= $i['id_intervention'] ?>">INT-<?= str_pad($i['id_intervention'], 4, '0', STR_PAD_LEFT) ?></a></td>
                        <td class="nowrap"><?= date_fr($i['date_intervention']) ?></td>
                        <td><?= e($i['client']) ?><span class="sub"><?= e($i['description']) ?></span></td>
                        <td><?= e($i['responsable']) ?></td>
                        <td><?= badge($i['statut']) ?></td>
                        <td class="num"><?= money($i['cout_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?= $recentes ? '' : empty_row(6) ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-header"><h2>Interventions par statut</h2></div>
            <div class="card-body bars">
                <?php foreach ($parStatut as $row): ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= badge($row['statut']) ?></span>
                        <span class="bar-track"><span class="bar-fill" style="width: <?= round($row['total'] / max(1, $stats['interventions']) * 100) ?>%"></span></span>
                        <span class="bar-value"><?= $row['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Meilleurs clients</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($topClients as $c): ?>
                        <tr>
                            <td><a href="clients.php?action=view&id=<?= $c['id_client'] ?>"><?= e($c['nom']) ?></a>
                                <span class="sub"><?= $c['nb'] ?> intervention(s)</span></td>
                            <td class="num"><?= money($c['montant']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px">
    <div class="card-header">
        <div>
            <h2>Effectif par service</h2>
            <p class="muted">Requete R8 - nombre d'employes par service</p>
        </div>
        <a class="btn btn-sm" href="services.php">Gerer les services</a>
    </div>
    <div class="card-body bars">
        <?php foreach ($parService as $row): ?>
            <div class="bar-row">
                <a class="bar-label" href="services.php?action=view&id=<?= $row['id_service'] ?>"><?= e($row['nom_service']) ?></a>
                <span class="bar-track"><span class="bar-fill" style="width: <?= round($row['total'] / max(1, $maxService) * 100) ?>%"></span></span>
                <span class="bar-value"><?= $row['total'] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
