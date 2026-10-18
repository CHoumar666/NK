<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$form = [
    'id_employe' => 0, 'nom' => '', 'prenom' => '', 'date_embauche' => date('Y-m-d'),
    'id_service' => '', 'id_fonction' => '', 'id_superieur' => '',
];

/**
 * Verifie qu'en donnant $idSuperieur comme chef a $idEmploye
 * on ne cree pas de boucle dans la hierarchie (RG1).
 */
function cree_un_cycle(int $idEmploye, int $idSuperieur): bool
{
    $courant = $idSuperieur;
    $vus = [];
    while ($courant && !isset($vus[$courant])) {
        if ($courant === $idEmploye) {
            return true;
        }
        $vus[$courant] = true;
        $courant = (int) fetch_value('SELECT id_superieur FROM employe WHERE id_employe = ?', [$courant]);
    }
    return false;
}

/* Salaire = grille de la fonction pour l'anciennete de l'employe */
const SQL_SALAIRE = "
    LEFT JOIN grille_salaire g ON g.id_fonction = e.id_fonction
         AND TIMESTAMPDIFF(YEAR, e.date_embauche, CURDATE()) BETWEEN g.anciennete_min AND g.anciennete_max";

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $id = input_int('id_employe');

    if (input('op') === 'delete') {
        try_delete('DELETE FROM employe WHERE id_employe = ?', [$id], 'l\'employe');
        redirect('employes.php');
    }

    foreach (array_keys($form) as $key) {
        $form[$key] = input($key);
    }
    $form['id_employe'] = $id;
    $idSup = (int) $form['id_superieur'];

    if ($form['nom'] === '' || $form['prenom'] === '') {
        $errors[] = 'Le nom et le prenom sont obligatoires.';
    }
    if (!valid_date($form['date_embauche'])) {
        $errors[] = 'La date d\'embauche est invalide.';
    } elseif ($form['date_embauche'] > date('Y-m-d')) {
        $errors[] = 'La date d\'embauche ne peut pas etre dans le futur.';
    }
    if (!fetch_value('SELECT COUNT(*) FROM service WHERE id_service = ?', [(int) $form['id_service']])) {
        $errors[] = 'Selectionnez un service.';
    }
    if (!fetch_value('SELECT COUNT(*) FROM fonction WHERE id_fonction = ?', [(int) $form['id_fonction']])) {
        $errors[] = 'Selectionnez une fonction.';
    }
    if ($idSup) {
        if ($idSup === $id) {
            $errors[] = 'Un employe ne peut pas etre son propre superieur.';
        } elseif (!fetch_value('SELECT COUNT(*) FROM employe WHERE id_employe = ?', [$idSup])) {
            $errors[] = 'Le superieur selectionne n\'existe pas.';
        } elseif ($id && cree_un_cycle($id, $idSup)) {
            $errors[] = 'Ce choix de superieur creerait une boucle dans la hierarchie.';
        }
    }

    if (!$errors) {
        $params = [$form['nom'], $form['prenom'], $form['date_embauche'], (int) $form['id_service'], (int) $form['id_fonction'], $idSup ?: null];
        if ($id) {
            query('UPDATE employe SET nom = ?, prenom = ?, date_embauche = ?, id_service = ?, id_fonction = ?, id_superieur = ? WHERE id_employe = ?', [...$params, $id]);
            flash('success', 'Employe mis a jour.');
        } else {
            query('INSERT INTO employe (nom, prenom, date_embauche, id_service, id_fonction, id_superieur) VALUES (?, ?, ?, ?, ?, ?)', $params);
            $id = (int) db()->lastInsertId();
            flash('success', 'Employe cree.');
        }
        redirect('employes.php?action=view&id=' . $id);
    }
    $action = 'form';
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM employe WHERE id_employe = ?', [input_int('id')]) ?? redirect('employes.php');
}

$activePage = 'employes';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $superieurs = array_filter(liste_employes(), fn ($e) => (int) $e['id_employe'] !== (int) $form['id_employe']);

    $pageTitle = $form['id_employe'] ? 'Modifier l\'employe' : 'Nouvel employe';
    $pageActions = '<a class="btn" href="employes.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 860px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_employe" value="<?= e($form['id_employe']) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input class="input" id="nom" name="nom" maxlength="80" required value="<?= e($form['nom']) ?>">
                </div>
                <div class="field">
                    <label for="prenom">Prenom <span class="req">*</span></label>
                    <input class="input" id="prenom" name="prenom" maxlength="80" required value="<?= e($form['prenom']) ?>">
                </div>
                <div class="field">
                    <label for="date_embauche">Date d'embauche <span class="req">*</span></label>
                    <input class="input" type="date" id="date_embauche" name="date_embauche" max="<?= date('Y-m-d') ?>" required value="<?= e($form['date_embauche']) ?>">
                </div>
                <div class="field">
                    <label for="id_service">Service <span class="req">*</span></label>
                    <select class="select" id="id_service" name="id_service" required><?= options(liste_services(), 'id_service', 'nom_service', $form['id_service'], 'Choisir un service') ?></select>
                </div>
                <div class="field">
                    <label for="id_fonction">Fonction <span class="req">*</span></label>
                    <select class="select" id="id_fonction" name="id_fonction" required><?= options(liste_fonctions(), 'id_fonction', 'libelle', $form['id_fonction'], 'Choisir une fonction') ?></select>
                    <span class="help">Le salaire est deduit de la grille de la fonction.</span>
                </div>
                <div class="field">
                    <label for="id_superieur">Superieur hierarchique</label>
                    <select class="select" id="id_superieur" name="id_superieur"><?= options($superieurs, 'id_employe', 'nom_complet', $form['id_superieur'], 'Aucun superieur') ?></select>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="employes.php">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Fiche ---------- */

if ($action === 'view') {
    $employe = fetch_one("
        SELECT e.*, s.nom_service, f.libelle AS fonction, g.salaire,
               TIMESTAMPDIFF(YEAR, e.date_embauche, CURDATE()) AS anciennete,
               CONCAT(sup.nom, ' ', sup.prenom) AS superieur
        FROM employe e
        JOIN service s  ON s.id_service = e.id_service
        JOIN fonction f ON f.id_fonction = e.id_fonction
        LEFT JOIN employe sup ON sup.id_employe = e.id_superieur
        " . SQL_SALAIRE . "
        WHERE e.id_employe = ?", [input_int('id')]) ?? redirect('employes.php');
    $id = $employe['id_employe'];

    $subalternes = fetch_all("
        SELECT e.id_employe, e.nom, e.prenom, f.libelle AS fonction
        FROM employe e JOIN fonction f ON f.id_fonction = e.id_fonction
        WHERE e.id_superieur = ? ORDER BY e.nom", [$id]);

    $qualifications = fetch_all("
        SELECT m.id_materiel, m.designation, q.niveau, q.date_qualification
        FROM qualification q JOIN materiel m ON m.id_materiel = q.id_materiel
        WHERE q.id_employe = ? ORDER BY m.designation", [$id]);

    $participations = fetch_all("
        SELECT a.id_acte, a.description AS acte, a.cout, p.date_debut, p.date_fin,
               i.id_intervention, c.nom AS client
        FROM participation p
        JOIN acte_depannage a ON a.id_acte = p.id_acte
        JOIN intervention i   ON i.id_intervention = a.id_intervention
        JOIN client c         ON c.id_client = i.id_client
        WHERE p.id_employe = ? ORDER BY p.date_debut DESC", [$id]);

    $dirigees = fetch_all("
        SELECT i.id_intervention, i.date_intervention, i.statut, c.nom AS client
        FROM intervention i JOIN client c ON c.id_client = i.id_client
        WHERE i.id_responsable = ? ORDER BY i.date_intervention DESC", [$id]);

    $pageTitle = $employe['nom'] . ' ' . $employe['prenom'];
    $pageSubtitle = $employe['fonction'] . ' - Service ' . $employe['nom_service'];
    $pageActions = '<a class="btn" href="employes.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn btn-primary" href="employes.php?action=form&id=' . $id . '">' . icon('edit') . '<span>Modifier</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="kpis">
        <div class="card kpi"><div class="kpi-label">Salaire mensuel</div><div class="kpi-value small"><?= $employe['salaire'] !== null ? money($employe['salaire']) : 'Hors grille' ?></div></div>
        <div class="card kpi"><div class="kpi-label">Anciennete</div><div class="kpi-value"><?= $employe['anciennete'] ?> an(s)</div></div>
        <div class="card kpi"><div class="kpi-label">Actes realises</div><div class="kpi-value"><?= count($participations) ?></div></div>
        <div class="card kpi"><div class="kpi-label">Interventions dirigees</div><div class="kpi-value"><?= count($dirigees) ?></div></div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h2>Informations</h2></div>
            <div class="card-body">
                <dl class="details">
                    <dt>Matricule</dt><dd>EMP-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></dd>
                    <dt>Service</dt><dd><a href="services.php?action=view&id=<?= $employe['id_service'] ?>"><?= e($employe['nom_service']) ?></a></dd>
                    <dt>Fonction</dt><dd><a href="fonctions.php?action=view&id=<?= $employe['id_fonction'] ?>"><?= e($employe['fonction']) ?></a></dd>
                    <dt>Date d'embauche</dt><dd><?= date_fr($employe['date_embauche']) ?></dd>
                    <dt>Superieur</dt><dd><?= $employe['id_superieur'] ? '<a href="employes.php?action=view&id=' . $employe['id_superieur'] . '">' . e($employe['superieur']) . '</a>' : 'Aucun' ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Subalternes</h2><span class="badge badge-info"><?= count($subalternes) ?></span></div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($subalternes as $s): ?>
                        <tr><td><a href="employes.php?action=view&id=<?= $s['id_employe'] ?>"><?= e($s['nom'] . ' ' . $s['prenom']) ?></a></td><td><?= e($s['fonction']) ?></td></tr>
                    <?php endforeach; ?>
                    <?= $subalternes ? '' : empty_row(2, 'Aucun subalterne.') ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Qualifications materiel</h2><a class="btn btn-sm" href="qualifications.php?action=form&id_employe=<?= $id ?>"><?= icon('plus') ?><span>Ajouter</span></a></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Materiel</th><th>Niveau</th><th>Depuis</th></tr></thead>
                    <tbody>
                    <?php foreach ($qualifications as $q): ?>
                        <tr>
                            <td><a href="materiels.php?action=view&id=<?= $q['id_materiel'] ?>"><?= e($q['designation']) ?></a></td>
                            <td><?= badge($q['niveau']) ?></td>
                            <td><?= date_fr($q['date_qualification']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $qualifications ? '' : empty_row(3, 'Aucune qualification.') ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Interventions dirigees</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                    <?php foreach ($dirigees as $d): ?>
                        <tr>
                            <td class="nowrap"><a href="interventions.php?action=view&id=<?= $d['id_intervention'] ?>">INT-<?= str_pad($d['id_intervention'], 4, '0', STR_PAD_LEFT) ?></a><span class="sub"><?= e($d['client']) ?></span></td>
                            <td><?= date_fr($d['date_intervention']) ?></td>
                            <td><?= badge($d['statut']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $dirigees ? '' : empty_row(3, 'Aucune intervention dirigee.') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 20px">
        <div class="card-header"><h2>Participations aux actes de depannage</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Intervention</th><th>Acte</th><th>Debut</th><th>Fin</th><th class="num">Cout de l'acte</th></tr></thead>
                <tbody>
                <?php foreach ($participations as $p): ?>
                    <tr>
                        <td class="nowrap"><a href="interventions.php?action=view&id=<?= $p['id_intervention'] ?>">INT-<?= str_pad($p['id_intervention'], 4, '0', STR_PAD_LEFT) ?></a><span class="sub"><?= e($p['client']) ?></span></td>
                        <td><?= e($p['acte']) ?></td>
                        <td class="nowrap"><?= datetime_fr($p['date_debut']) ?></td>
                        <td class="nowrap"><?= $p['date_fin'] ? datetime_fr($p['date_fin']) : badge('En cours') ?></td>
                        <td class="num"><?= money($p['cout']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?= $participations ? '' : empty_row(5, 'Aucune participation.') ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$filtreService = input_int('service');
$filtreFonction = input_int('fonction');
$recherche = input('q');

$where = [];
$params = [];
if ($filtreService) {
    $where[] = 'e.id_service = ?';
    $params[] = $filtreService;
}
if ($filtreFonction) {
    $where[] = 'e.id_fonction = ?';
    $params[] = $filtreFonction;
}
if ($recherche !== '') {
    $where[] = "CONCAT(e.nom, ' ', e.prenom) LIKE ?";
    $params[] = '%' . $recherche . '%';
}

$employes = fetch_all("
    SELECT e.id_employe, e.nom, e.prenom, e.date_embauche, s.nom_service, f.libelle AS fonction,
           CONCAT(sup.nom, ' ', sup.prenom) AS superieur, g.salaire,
           TIMESTAMPDIFF(YEAR, e.date_embauche, CURDATE()) AS anciennete
    FROM employe e
    JOIN service s  ON s.id_service = e.id_service
    JOIN fonction f ON f.id_fonction = e.id_fonction
    LEFT JOIN employe sup ON sup.id_employe = e.id_superieur
    " . SQL_SALAIRE . "
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY s.nom_service, e.nom, e.prenom", $params);

$masseSalariale = array_sum(array_column($employes, 'salaire'));

$pageTitle = 'Employes';
$pageSubtitle = 'Personnel, rattachement aux services, hierarchie et remuneration.';
$pageActions = '<a class="btn btn-primary" href="employes.php?action=form">' . icon('plus') . '<span>Nouvel employe</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form class="toolbar" method="get">
            <div class="search"><?= icon('search') ?><input class="input" type="search" name="q" placeholder="Nom ou prenom" value="<?= e($recherche) ?>"></div>
            <select class="select" name="service" data-autosubmit><?= options(liste_services(), 'id_service', 'nom_service', $filtreService, 'Tous les services') ?></select>
            <select class="select" name="fonction" data-autosubmit><?= options(liste_fonctions(), 'id_fonction', 'libelle', $filtreFonction, 'Toutes les fonctions') ?></select>
            <button class="btn" type="submit">Filtrer</button>
            <?php if ($where): ?><a class="btn btn-ghost" href="employes.php">Reinitialiser</a><?php endif; ?>
        </form>
        <span class="muted"><?= count($employes) ?> employe(s) - masse salariale <?= money($masseSalariale) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Matricule</th><th>Employe</th><th>Service</th><th>Fonction</th><th>Superieur</th><th class="num">Anciennete</th><th class="num">Salaire</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($employes as $e): ?>
                <tr>
                    <td class="id">EMP-<?= str_pad($e['id_employe'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="strong"><a href="employes.php?action=view&id=<?= $e['id_employe'] ?>"><?= e($e['nom'] . ' ' . $e['prenom']) ?></a></td>
                    <td><?= e($e['nom_service']) ?></td>
                    <td><?= e($e['fonction']) ?></td>
                    <td><?= e($e['superieur'] ?? '-') ?></td>
                    <td class="num"><?= $e['anciennete'] ?> an(s)</td>
                    <td class="num"><?= $e['salaire'] !== null ? money($e['salaire']) : badge('Hors grille') ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="employes.php?action=form&id=<?= $e['id_employe'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                        <?= delete_button('employes.php', ['id_employe' => $e['id_employe']], 'Supprimer l\'employe ' . $e['nom'] . ' ' . $e['prenom'] . ' ?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $employes ? '' : empty_row(8, 'Aucun employe ne correspond aux criteres.') ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
