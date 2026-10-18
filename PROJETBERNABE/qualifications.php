<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$edition = false;
$form = [
    'id_employe' => input('id_employe'), 'id_materiel' => input('id_materiel'),
    'date_qualification' => date('Y-m-d'), 'niveau' => 'Intermediaire',
];

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $idEmploye = input_int('id_employe');
    $idMateriel = input_int('id_materiel');

    if (input('op') === 'delete') {
        query('DELETE FROM qualification WHERE id_employe = ? AND id_materiel = ?', [$idEmploye, $idMateriel]);
        flash('success', 'Qualification retiree.');
        redirect('qualifications.php');
    }

    $edition = input('edition') === '1';
    foreach (array_keys($form) as $key) {
        $form[$key] = input($key);
    }

    if (!fetch_value('SELECT COUNT(*) FROM employe WHERE id_employe = ?', [$idEmploye])) {
        $errors[] = 'Selectionnez un employe.';
    }
    if (!fetch_value('SELECT COUNT(*) FROM materiel WHERE id_materiel = ?', [$idMateriel])) {
        $errors[] = 'Selectionnez un materiel.';
    }
    if (!valid_date($form['date_qualification'])) {
        $errors[] = 'La date de qualification est invalide.';
    }
    if (!in_array($form['niveau'], NIVEAUX, true)) {
        $errors[] = 'Le niveau selectionne est invalide.';
    }
    if (!$edition && !$errors && fetch_value('SELECT COUNT(*) FROM qualification WHERE id_employe = ? AND id_materiel = ?', [$idEmploye, $idMateriel])) {
        $errors[] = 'Cet employe est deja qualifie sur ce materiel. Modifiez la qualification existante.';
    }

    if (!$errors) {
        if ($edition) {
            query('UPDATE qualification SET date_qualification = ?, niveau = ? WHERE id_employe = ? AND id_materiel = ?',
                [$form['date_qualification'], $form['niveau'], $idEmploye, $idMateriel]);
            flash('success', 'Qualification mise a jour.');
        } else {
            query('INSERT INTO qualification (id_employe, id_materiel, date_qualification, niveau) VALUES (?, ?, ?, ?)',
                [$idEmploye, $idMateriel, $form['date_qualification'], $form['niveau']]);
            flash('success', 'Qualification enregistree.');
        }
        redirect('qualifications.php');
    }
    $action = 'form';
} elseif ($action === 'form' && input('edition') === '1') {
    $edition = true;
    $form = fetch_one('SELECT * FROM qualification WHERE id_employe = ? AND id_materiel = ?',
        [input_int('id_employe'), input_int('id_materiel')]) ?? redirect('qualifications.php');
}

$activePage = 'qualifications';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $pageTitle = $edition ? 'Modifier la qualification' : 'Nouvelle qualification';
    $pageSubtitle = 'RG2 : tous les salaries ne sont pas qualifies pour tout le materiel.';
    $pageActions = '<a class="btn" href="qualifications.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 860px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="edition" value="<?= $edition ? '1' : '0' ?>">
            <?php if ($edition): ?>
                <input type="hidden" name="id_employe" value="<?= e($form['id_employe']) ?>">
                <input type="hidden" name="id_materiel" value="<?= e($form['id_materiel']) ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="field">
                    <label for="id_employe">Employe <span class="req">*</span></label>
                    <select class="select" id="id_employe" <?= $edition ? 'disabled' : 'name="id_employe" required' ?>><?= options(liste_employes(), 'id_employe', 'nom_complet', $form['id_employe'], 'Choisir un employe') ?></select>
                </div>
                <div class="field">
                    <label for="id_materiel">Materiel <span class="req">*</span></label>
                    <select class="select" id="id_materiel" <?= $edition ? 'disabled' : 'name="id_materiel" required' ?>><?= options(liste_materiels(), 'id_materiel', 'designation', $form['id_materiel'], 'Choisir un materiel') ?></select>
                </div>
                <div class="field">
                    <label for="niveau">Niveau <span class="req">*</span></label>
                    <select class="select" id="niveau" name="niveau"><?= options_list(NIVEAUX, $form['niveau']) ?></select>
                </div>
                <div class="field">
                    <label for="date_qualification">Date de qualification <span class="req">*</span></label>
                    <input class="input" type="date" id="date_qualification" name="date_qualification" required value="<?= e($form['date_qualification']) ?>">
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="qualifications.php">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$filtreNiveau = input('niveau');
$filtreMateriel = input_int('materiel');
$where = [];
$params = [];
if (in_array($filtreNiveau, NIVEAUX, true)) {
    $where[] = 'q.niveau = ?';
    $params[] = $filtreNiveau;
}
if ($filtreMateriel) {
    $where[] = 'q.id_materiel = ?';
    $params[] = $filtreMateriel;
}

$qualifications = fetch_all("
    SELECT q.*, e.nom, e.prenom, s.nom_service, m.designation
    FROM qualification q
    JOIN employe e  ON e.id_employe = q.id_employe
    JOIN service s  ON s.id_service = e.id_service
    JOIN materiel m ON m.id_materiel = q.id_materiel
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    ORDER BY e.nom, e.prenom, m.designation", $params);

$pageTitle = 'Qualifications';
$pageSubtitle = 'Materiels pour lesquels chaque employe est habilite.';
$pageActions = '<a class="btn btn-primary" href="qualifications.php?action=form">' . icon('plus') . '<span>Nouvelle qualification</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form class="toolbar" method="get">
            <div class="search"><?= icon('search') ?><input class="input" type="search" placeholder="Filtrer la liste" data-filter="#table-qualifications"></div>
            <select class="select" name="materiel" data-autosubmit><?= options(liste_materiels(), 'id_materiel', 'designation', $filtreMateriel, 'Tous les materiels') ?></select>
            <select class="select" name="niveau" data-autosubmit><?= options_list(NIVEAUX, $filtreNiveau, 'Tous les niveaux') ?></select>
        </form>
        <span class="muted"><?= count($qualifications) ?> qualification(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="table-qualifications">
            <thead><tr><th>Employe</th><th>Service</th><th>Materiel</th><th>Niveau</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($qualifications as $q): ?>
                <tr>
                    <td class="strong"><a href="employes.php?action=view&id=<?= $q['id_employe'] ?>"><?= e($q['nom'] . ' ' . $q['prenom']) ?></a></td>
                    <td><?= e($q['nom_service']) ?></td>
                    <td><a href="materiels.php?action=view&id=<?= $q['id_materiel'] ?>"><?= e($q['designation']) ?></a></td>
                    <td><?= badge($q['niveau']) ?></td>
                    <td><?= date_fr($q['date_qualification']) ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="qualifications.php?action=form&edition=1&id_employe=<?= $q['id_employe'] ?>&id_materiel=<?= $q['id_materiel'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                        <?= delete_button('qualifications.php', ['id_employe' => $q['id_employe'], 'id_materiel' => $q['id_materiel']], 'Retirer cette qualification ?', 'Retirer') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $qualifications ? '' : empty_row(6) ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
