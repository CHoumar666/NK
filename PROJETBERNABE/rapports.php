<?php
require __DIR__ . '/includes/bootstrap.php';

/*
 * Requetes demandees par le Directeur (R1 a R9).
 */
$rapports = [
    'R1' => [
        'titre' => 'Liste des employes avec leur service et leur fonction',
        'sql'   => "SELECT e.id_employe, e.nom, e.prenom, s.nom_service, f.libelle AS fonction
FROM employe e
JOIN service s ON e.id_service = s.id_service
JOIN fonction f ON e.id_fonction = f.id_fonction
ORDER BY s.nom_service, e.nom",
    ],
    'R2' => [
        'titre' => 'Employes et leurs superieurs',
        'sql'   => "SELECT e.nom AS employe, e.prenom AS prenom_employe,
       CONCAT(sup.nom, ' ', sup.prenom) AS superieur
FROM employe e
LEFT JOIN employe sup ON e.id_superieur = sup.id_employe",
    ],
    'R3' => [
        'titre' => 'Materiels et leurs composants',
        'sql'   => "SELECT m1.designation AS materiel, m2.designation AS composant, c.quantite
FROM composer c
JOIN materiel m1 ON c.id_materiel_parent = m1.id_materiel
JOIN materiel m2 ON c.id_materiel_composant = m2.id_materiel
ORDER BY m1.designation",
    ],
    'R4' => [
        'titre' => 'Interventions avec le client et le responsable',
        'sql'   => "SELECT i.id_intervention, i.date_intervention, c.nom AS client,
       CONCAT(e.nom, ' ', e.prenom) AS responsable, i.statut
FROM intervention i
JOIN client c ON i.id_client = c.id_client
JOIN employe e ON i.id_responsable = e.id_employe
ORDER BY i.date_intervention",
    ],
    'R5' => [
        'titre' => 'Cout total de chaque intervention',
        'sql'   => "SELECT i.id_intervention, i.date_intervention, c.nom AS client, SUM(a.cout) AS cout_total
FROM intervention i
JOIN client c ON i.id_client = c.id_client
JOIN acte_depannage a ON i.id_intervention = a.id_intervention
GROUP BY i.id_intervention, i.date_intervention, c.nom
ORDER BY i.id_intervention",
    ],
    'R6' => [
        'titre' => 'Employes participant aux actes',
        'sql'   => "SELECT CONCAT(e.nom, ' ', e.prenom) AS employe, a.id_acte, a.description AS acte,
       p.date_debut, p.date_fin
FROM participation p
JOIN employe e ON p.id_employe = e.id_employe
JOIN acte_depannage a ON p.id_acte = a.id_acte
ORDER BY p.date_debut",
    ],
    'R7' => [
        'titre' => 'Materiels pour lesquels chaque employe est qualifie',
        'sql'   => "SELECT CONCAT(e.nom, ' ', e.prenom) AS employe, m.designation AS materiel,
       q.niveau, q.date_qualification
FROM qualification q
JOIN employe e ON q.id_employe = e.id_employe
JOIN materiel m ON q.id_materiel = m.id_materiel
ORDER BY e.nom, m.designation",
    ],
    'R8' => [
        'titre' => 'Nombre d\'employes par service',
        'sql'   => "SELECT s.nom_service, COUNT(e.id_employe) AS nombre_employes
FROM service s
LEFT JOIN employe e ON s.id_service = e.id_service
GROUP BY s.id_service, s.nom_service
ORDER BY nombre_employes DESC",
    ],
    'R9' => [
        'titre' => 'Nombre d\'actes par intervention',
        'sql'   => "SELECT i.id_intervention, c.nom AS client, COUNT(a.id_acte) AS nombre_actes
FROM intervention i
JOIN client c ON i.id_client = c.id_client
LEFT JOIN acte_depannage a ON i.id_intervention = a.id_intervention
GROUP BY i.id_intervention, c.nom",
    ],
];

$code = strtoupper(input('r', 'R1'));
if (!isset($rapports[$code])) {
    $code = 'R1';
}
$rapport = $rapports[$code];
$lignes = fetch_all($rapport['sql']);
$colonnes = $lignes ? array_keys($lignes[0]) : [];

/* ---------- Export CSV (compatible Excel) ---------- */

if (input('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rapport_' . $code . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $colonnes, ';');
    foreach ($lignes as $ligne) {
        fputcsv($out, $ligne, ';');
    }
    fclose($out);
    exit;
}

function libelle_colonne(string $colonne): string
{
    return ucfirst(str_replace(['id_', '_'], ['N° ', ' '], $colonne));
}

function cellule(string $colonne, $valeur): string
{
    if ($valeur === null) {
        return '<span class="muted">-</span>';
    }
    if (str_starts_with($colonne, 'cout')) {
        return money($valeur);
    }
    if (in_array($colonne, ['date_debut', 'date_fin'], true)) {
        return datetime_fr($valeur);
    }
    if (str_starts_with($colonne, 'date')) {
        return date_fr($valeur);
    }
    if (in_array($colonne, ['statut', 'niveau'], true)) {
        return badge($valeur);
    }
    return e($valeur);
}

function numerique(string $colonne): bool
{
    return str_starts_with($colonne, 'cout') || str_starts_with($colonne, 'nombre') || in_array($colonne, ['quantite', 'id_acte', 'id_employe', 'id_intervention'], true);
}

$activePage = 'rapports';
$pageTitle = 'Rapports';
$pageSubtitle = 'Requetes demandees par la Direction, executees en temps reel sur la base.';
$pageActions = '<a class="btn" href="rapports.php?r=' . $code . '&export=csv">' . icon('download') . '<span>Exporter en CSV</span></a>'
    . '<button class="btn" type="button" onclick="window.print()">Imprimer</button>';
require __DIR__ . '/includes/header.php';
?>
<nav class="tabs">
    <?php foreach ($rapports as $cle => $r): ?>
        <a class="tab<?= $cle === $code ? ' active' : '' ?>" href="rapports.php?r=<?= $cle ?>" title="<?= e($r['titre']) ?>"><?= $cle ?></a>
    <?php endforeach; ?>
</nav>

<div class="card">
    <div class="card-header">
        <div>
            <h2><?= $code ?> - <?= e($rapport['titre']) ?></h2>
            <p class="muted"><?= count($lignes) ?> ligne(s) retournee(s)</p>
        </div>
    </div>
    <div class="card-body" style="border-bottom: 1px solid var(--border)">
        <details class="sql-block">
            <summary>Afficher la requete SQL</summary>
            <pre class="sql"><?= e($rapport['sql']) ?>;</pre>
        </details>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <?php foreach ($colonnes as $col): ?>
                    <th class="<?= numerique($col) ? 'num' : '' ?>"><?= e(libelle_colonne($col)) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lignes as $ligne): ?>
                <tr>
                    <?php foreach ($ligne as $col => $val): ?>
                        <td class="<?= numerique($col) ? 'num' : '' ?>"><?= cellule($col, $val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?= $lignes ? '' : empty_row(max(1, count($colonnes)), 'Aucun resultat.') ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
