<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$compoErrors = [];
$form = ['id_materiel' => 0, 'designation' => '', 'description' => '', 'date_acquisition' => '', 'etat' => 'Disponible'];

function composants_directs(int $idMateriel): array
{
    return fetch_all("
        SELECT m.id_materiel, m.designation, m.etat, c.quantite
        FROM composer c JOIN materiel m ON m.id_materiel = c.id_materiel_composant
        WHERE c.id_materiel_parent = ? ORDER BY m.designation", [$idMateriel]);
}

/** Vrai si $cible figure dans la decomposition (directe ou non) de $idMateriel. */
function contient(int $idMateriel, int $cible, array $vus = []): bool
{
    foreach (composants_directs($idMateriel) as $c) {
        $idc = (int) $c['id_materiel'];
        if ($idc === $cible) {
            return true;
        }
        if (!isset($vus[$idc])) {
            $vus[$idc] = true;
            if (contient($idc, $cible, $vus)) {
                return true;
            }
        }
    }
    return false;
}

/** Arbre de decomposition affiche recursivement (RG3). */
function arbre_html(int $idMateriel, array $vus = []): string
{
    $composants = composants_directs($idMateriel);
    if (!$composants) {
        return '';
    }
    $html = '<ul>';
    foreach ($composants as $c) {
        $idc = (int) $c['id_materiel'];
        $html .= '<li><a href="materiels.php?action=view&id=' . $idc . '">' . e($c['designation']) . '</a>'
            . '<span class="qty">x ' . (int) $c['quantite'] . '</span>';
        if (!isset($vus[$idc])) {
            $html .= arbre_html($idc, $vus + [$idc => true]);
        }
        $html .= '</li>';
    }
    return $html . '</ul>';
}

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $op = input('op');
    $id = input_int('id_materiel');

    if ($op === 'delete') {
        // Les liens de composition n'ont pas de sens sans le materiel ;
        // les qualifications, elles, bloquent la suppression.
        if (fetch_value('SELECT COUNT(*) FROM qualification WHERE id_materiel = ?', [$id])) {
            flash('error', 'Suppression impossible : des employes sont qualifies sur ce materiel.');
        } else {
            db()->beginTransaction();
            query('DELETE FROM composer WHERE id_materiel_parent = ? OR id_materiel_composant = ?', [$id, $id]);
            query('DELETE FROM materiel WHERE id_materiel = ?', [$id]);
            db()->commit();
            flash('success', 'Materiel supprime.');
        }
        redirect('materiels.php');
    }

    if ($op === 'remove_composant') {
        query('DELETE FROM composer WHERE id_materiel_parent = ? AND id_materiel_composant = ?', [$id, input_int('id_composant')]);
        flash('success', 'Composant retire.');
        redirect('materiels.php?action=view&id=' . $id);
    }

    if ($op === 'add_composant') {
        $idComposant = input_int('id_composant');
        $quantite = filter_var(input('quantite'), FILTER_VALIDATE_INT);

        if (!fetch_value('SELECT COUNT(*) FROM materiel WHERE id_materiel = ?', [$idComposant])) {
            $compoErrors[] = 'Selectionnez un composant.';
        } elseif ($idComposant === $id) {
            $compoErrors[] = 'Un materiel ne peut pas etre son propre composant.';
        } elseif (contient($idComposant, $id)) {
            $compoErrors[] = 'Ce composant contient deja ce materiel : la composition serait circulaire.';
        }
        if ($quantite === false || $quantite < 1) {
            $compoErrors[] = 'La quantite doit etre un entier superieur ou egal a 1.';
        }
        if (!$compoErrors) {
            query('INSERT INTO composer (id_materiel_parent, id_materiel_composant, quantite) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE quantite = VALUES(quantite)', [$id, $idComposant, $quantite]);
            flash('success', 'Composition mise a jour.');
            redirect('materiels.php?action=view&id=' . $id);
        }
        $action = 'view';
        $_GET['id'] = $id;
    } else {
        foreach (array_keys($form) as $key) {
            $form[$key] = input($key);
        }
        $form['id_materiel'] = $id;

        if ($form['designation'] === '') {
            $errors[] = 'La designation est obligatoire.';
        }
        if ($form['date_acquisition'] !== '' && !valid_date($form['date_acquisition'])) {
            $errors[] = 'La date d\'acquisition est invalide.';
        }
        if (!in_array($form['etat'], ETATS_MATERIEL, true)) {
            $errors[] = 'L\'etat selectionne est invalide.';
        }

        if (!$errors) {
            $params = [$form['designation'], $form['description'] ?: null, $form['date_acquisition'] ?: null, $form['etat']];
            if ($id) {
                query('UPDATE materiel SET designation = ?, description = ?, date_acquisition = ?, etat = ? WHERE id_materiel = ?', [...$params, $id]);
                flash('success', 'Materiel mis a jour.');
            } else {
                query('INSERT INTO materiel (designation, description, date_acquisition, etat) VALUES (?, ?, ?, ?)', $params);
                $id = (int) db()->lastInsertId();
                flash('success', 'Materiel reference.');
            }
            redirect('materiels.php?action=view&id=' . $id);
        }
        $action = 'form';
    }
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM materiel WHERE id_materiel = ?', [input_int('id')]) ?? redirect('materiels.php');
}

$activePage = 'materiels';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $pageTitle = $form['id_materiel'] ? 'Modifier le materiel' : 'Nouveau materiel';
    $pageActions = '<a class="btn" href="materiels.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 860px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_materiel" value="<?= e($form['id_materiel']) ?>">
            <div class="form-grid">
                <div class="field full">
                    <label for="designation">Designation <span class="req">*</span></label>
                    <input class="input" id="designation" name="designation" maxlength="150" required value="<?= e($form['designation']) ?>">
                </div>
                <div class="field full">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description" maxlength="255"><?= e($form['description']) ?></textarea>
                </div>
                <div class="field">
                    <label for="date_acquisition">Date d'acquisition</label>
                    <input class="input" type="date" id="date_acquisition" name="date_acquisition" value="<?= e($form['date_acquisition']) ?>">
                </div>
                <div class="field">
                    <label for="etat">Etat <span class="req">*</span></label>
                    <select class="select" id="etat" name="etat"><?= options_list(ETATS_MATERIEL, $form['etat']) ?></select>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="materiels.php">Annuler</a>
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
    $materiel = fetch_one('SELECT * FROM materiel WHERE id_materiel = ?', [input_int('id')]) ?? redirect('materiels.php');
    $id = (int) $materiel['id_materiel'];
    $composants = composants_directs($id);
    $parents = fetch_all("
        SELECT m.id_materiel, m.designation, c.quantite
        FROM composer c JOIN materiel m ON m.id_materiel = c.id_materiel_parent
        WHERE c.id_materiel_composant = ? ORDER BY m.designation", [$id]);
    $qualifies = fetch_all("
        SELECT e.id_employe, e.nom, e.prenom, s.nom_service, q.niveau, q.date_qualification
        FROM qualification q
        JOIN employe e ON e.id_employe = q.id_employe
        JOIN service s ON s.id_service = e.id_service
        WHERE q.id_materiel = ?
        ORDER BY FIELD(q.niveau, 'Expert', 'Avance', 'Intermediaire', 'Debutant'), e.nom", [$id]);
    $candidats = array_filter(liste_materiels(), fn ($m) => (int) $m['id_materiel'] !== $id);

    $pageTitle = $materiel['designation'];
    $pageSubtitle = $materiel['description'] ?? '';
    $pageActions = '<a class="btn" href="materiels.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn btn-primary" href="materiels.php?action=form&id=' . $id . '">' . icon('edit') . '<span>Modifier</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="grid grid-sidebar">
        <div class="stack">
            <div class="card">
                <div class="card-header">
                    <div><h2>Composants directs</h2><p class="muted">Decomposition du materiel (association COMPOSER)</p></div>
                    <span class="badge badge-info"><?= count($composants) ?></span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Composant</th><th>Etat</th><th class="num">Quantite</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($composants as $c): ?>
                            <tr>
                                <td><a href="materiels.php?action=view&id=<?= $c['id_materiel'] ?>"><?= e($c['designation']) ?></a></td>
                                <td><?= badge($c['etat']) ?></td>
                                <td class="num"><?= $c['quantite'] ?></td>
                                <td class="actions"><?= delete_button('materiels.php', ['op' => 'remove_composant', 'id_materiel' => $id, 'id_composant' => $c['id_materiel']], 'Retirer ce composant ?', 'Retirer') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?= $composants ? '' : empty_row(4, 'Ce materiel n\'est pas decompose.') ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <?= form_errors($compoErrors) ?>
                    <form method="post" class="inline-add">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="add_composant">
                        <input type="hidden" name="id_materiel" value="<?= $id ?>">
                        <div class="field" style="flex-grow: 3"><label>Composant</label>
                            <select class="select" name="id_composant" required><?= options($candidats, 'id_materiel', 'designation', input('id_composant'), 'Choisir un materiel') ?></select></div>
                        <div class="field" style="max-width: 120px"><label>Quantite</label><input class="input" type="number" name="quantite" min="1" value="<?= e(input('quantite', '1')) ?>" required></div>
                        <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Ajouter</span></button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Employes qualifies</h2><a class="btn btn-sm" href="qualifications.php?action=form&id_materiel=<?= $id ?>"><?= icon('plus') ?><span>Qualifier</span></a></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Employe</th><th>Service</th><th>Niveau</th><th>Depuis</th></tr></thead>
                        <tbody>
                        <?php foreach ($qualifies as $q): ?>
                            <tr>
                                <td><a href="employes.php?action=view&id=<?= $q['id_employe'] ?>"><?= e($q['nom'] . ' ' . $q['prenom']) ?></a></td>
                                <td><?= e($q['nom_service']) ?></td>
                                <td><?= badge($q['niveau']) ?></td>
                                <td><?= date_fr($q['date_qualification']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?= $qualifies ? '' : empty_row(4, 'Aucun employe qualifie sur ce materiel.') ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><h2>Fiche</h2><?= badge($materiel['etat']) ?></div>
                <div class="card-body">
                    <dl class="details">
                        <dt>Reference</dt><dd>MAT-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></dd>
                        <dt>Acquisition</dt><dd><?= date_fr($materiel['date_acquisition']) ?></dd>
                        <dt>Composants</dt><dd><?= count($composants) ?></dd>
                        <dt>Utilise dans</dt><dd><?= count($parents) ?> materiel(s)</dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Arborescence complete</h2></div>
                <div class="card-body">
                    <?php if ($composants): ?>
                        <ul class="tree"><li><strong><?= e($materiel['designation']) ?></strong><?= arbre_html($id, [$id => true]) ?></li></ul>
                    <?php else: ?>
                        <p class="muted">Materiel reference comme un tout.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Fait partie de</h2></div>
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                        <?php foreach ($parents as $p): ?>
                            <tr><td><a href="materiels.php?action=view&id=<?= $p['id_materiel'] ?>"><?= e($p['designation']) ?></a></td><td class="num">x <?= $p['quantite'] ?></td></tr>
                        <?php endforeach; ?>
                        <?= $parents ? '' : empty_row(2, 'Aucun materiel parent.') ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$filtreEtat = input('etat');
$params = [];
$where = '';
if (in_array($filtreEtat, ETATS_MATERIEL, true)) {
    $where = 'WHERE m.etat = ?';
    $params[] = $filtreEtat;
}

$materiels = fetch_all("
    SELECT m.*,
           (SELECT COUNT(*) FROM composer c WHERE c.id_materiel_parent = m.id_materiel) AS nb_composants,
           (SELECT COUNT(*) FROM composer c WHERE c.id_materiel_composant = m.id_materiel) AS nb_parents,
           (SELECT COUNT(*) FROM qualification q WHERE q.id_materiel = m.id_materiel) AS nb_qualifies
    FROM materiel m
    $where
    ORDER BY m.designation", $params);

$pageTitle = 'Materiels';
$pageSubtitle = 'Gros materiels references par l\'entreprise et leur decomposition.';
$pageActions = '<a class="btn btn-primary" href="materiels.php?action=form">' . icon('plus') . '<span>Nouveau materiel</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form class="toolbar" method="get">
            <div class="search"><?= icon('search') ?><input class="input" type="search" placeholder="Rechercher un materiel" data-filter="#table-materiels"></div>
            <select class="select" name="etat" data-autosubmit><?= options_list(ETATS_MATERIEL, $filtreEtat, 'Tous les etats') ?></select>
        </form>
        <span class="muted"><?= count($materiels) ?> materiel(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="table-materiels">
            <thead><tr><th>Reference</th><th>Designation</th><th>Acquisition</th><th>Etat</th><th class="num">Composants</th><th class="num">Employes qualifies</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($materiels as $m): ?>
                <tr>
                    <td class="id">MAT-<?= str_pad($m['id_materiel'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="strong"><a href="materiels.php?action=view&id=<?= $m['id_materiel'] ?>"><?= e($m['designation']) ?></a><span class="sub"><?= e($m['description']) ?></span></td>
                    <td><?= date_fr($m['date_acquisition']) ?></td>
                    <td><?= badge($m['etat']) ?></td>
                    <td class="num"><?= $m['nb_composants'] ?></td>
                    <td class="num"><?= $m['nb_qualifies'] ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="materiels.php?action=form&id=<?= $m['id_materiel'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                        <?= delete_button('materiels.php', ['id_materiel' => $m['id_materiel']], 'Supprimer le materiel ' . $m['designation'] . ' ?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $materiels ? '' : empty_row(7) ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
