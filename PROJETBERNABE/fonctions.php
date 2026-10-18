<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$form = ['id_fonction' => 0, 'libelle' => ''];
$grilleForm = ['anciennete_min' => '', 'anciennete_max' => '', 'salaire' => ''];

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $op = input('op');
    $id = input_int('id_fonction');

    if ($op === 'delete') {
        // La grille appartient a la fonction : on la supprime avec elle,
        // a condition qu'aucun employe n'occupe cette fonction.
        if (fetch_value('SELECT COUNT(*) FROM employe WHERE id_fonction = ?', [$id])) {
            flash('error', 'Suppression impossible : des employes occupent encore cette fonction.');
        } else {
            db()->beginTransaction();
            query('DELETE FROM grille_salaire WHERE id_fonction = ?', [$id]);
            query('DELETE FROM fonction WHERE id_fonction = ?', [$id]);
            db()->commit();
            flash('success', 'Fonction et grille salariale supprimees.');
        }
        redirect('fonctions.php');
    }

    if ($op === 'delete_grille') {
        query('DELETE FROM grille_salaire WHERE id_grille = ?', [input_int('id_grille')]);
        flash('success', 'Tranche salariale supprimee.');
        redirect('fonctions.php?action=view&id=' . $id);
    }

    if ($op === 'add_grille') {
        $grilleForm = [
            'anciennete_min' => input('anciennete_min'),
            'anciennete_max' => input('anciennete_max'),
            'salaire'        => input('salaire'),
        ];
        $min = filter_var($grilleForm['anciennete_min'], FILTER_VALIDATE_INT);
        $max = filter_var($grilleForm['anciennete_max'], FILTER_VALIDATE_INT);
        $salaire = filter_var($grilleForm['salaire'], FILTER_VALIDATE_FLOAT);

        if ($min === false || $min < 0) {
            $errors[] = 'L\'anciennete minimale doit etre un entier positif ou nul.';
        }
        if ($max === false || ($min !== false && $max < $min)) {
            $errors[] = 'L\'anciennete maximale doit etre un entier superieur ou egal au minimum.';
        }
        if ($salaire === false || $salaire <= 0) {
            $errors[] = 'Le salaire doit etre un montant strictement positif.';
        }
        if (!$errors) {
            $chevauchement = fetch_value(
                'SELECT COUNT(*) FROM grille_salaire WHERE id_fonction = ? AND anciennete_min <= ? AND anciennete_max >= ?',
                [$id, $max, $min]
            );
            if ($chevauchement) {
                $errors[] = 'Cette tranche chevauche une tranche existante de la grille.';
            }
        }
        if (!$errors) {
            query('INSERT INTO grille_salaire (id_fonction, anciennete_min, anciennete_max, salaire) VALUES (?, ?, ?, ?)',
                [$id, $min, $max, $salaire]);
            flash('success', 'Tranche salariale ajoutee.');
            redirect('fonctions.php?action=view&id=' . $id);
        }
        $action = 'view';
        $_GET['id'] = $id;
    } else {
        $form = ['id_fonction' => $id, 'libelle' => input('libelle')];

        if ($form['libelle'] === '') {
            $errors[] = 'Le libelle est obligatoire.';
        } elseif (fetch_value('SELECT COUNT(*) FROM fonction WHERE libelle = ? AND id_fonction <> ?', [$form['libelle'], $id])) {
            $errors[] = 'Une fonction porte deja ce libelle.';
        }

        if (!$errors) {
            if ($id) {
                query('UPDATE fonction SET libelle = ? WHERE id_fonction = ?', [$form['libelle'], $id]);
                flash('success', 'Fonction mise a jour.');
            } else {
                query('INSERT INTO fonction (libelle) VALUES (?)', [$form['libelle']]);
                $id = (int) db()->lastInsertId();
                flash('success', 'Fonction creee. Ajoutez maintenant sa grille salariale.');
            }
            redirect('fonctions.php?action=view&id=' . $id);
        }
        $action = 'form';
    }
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM fonction WHERE id_fonction = ?', [input_int('id')]) ?? redirect('fonctions.php');
}

$activePage = 'fonctions';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $pageTitle = $form['id_fonction'] ? 'Modifier la fonction' : 'Nouvelle fonction';
    $pageActions = '<a class="btn" href="fonctions.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 640px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_fonction" value="<?= e($form['id_fonction']) ?>">
            <div class="field">
                <label for="libelle">Libelle <span class="req">*</span></label>
                <input class="input" id="libelle" name="libelle" maxlength="100" required value="<?= e($form['libelle']) ?>">
                <span class="help">Le libelle doit etre unique dans l'entreprise.</span>
            </div>
            <div class="form-actions">
                <a class="btn" href="fonctions.php">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Fiche et grille salariale ---------- */

if ($action === 'view') {
    $fonction = fetch_one('SELECT * FROM fonction WHERE id_fonction = ?', [input_int('id')]) ?? redirect('fonctions.php');
    $grille = fetch_all('SELECT * FROM grille_salaire WHERE id_fonction = ? ORDER BY anciennete_min', [$fonction['id_fonction']]);
    $employes = fetch_all("
        SELECT e.id_employe, e.nom, e.prenom, s.nom_service,
               TIMESTAMPDIFF(YEAR, e.date_embauche, CURDATE()) AS anciennete, g.salaire
        FROM employe e
        JOIN service s ON s.id_service = e.id_service
        LEFT JOIN grille_salaire g ON g.id_fonction = e.id_fonction
             AND TIMESTAMPDIFF(YEAR, e.date_embauche, CURDATE()) BETWEEN g.anciennete_min AND g.anciennete_max
        WHERE e.id_fonction = ?
        ORDER BY e.nom", [$fonction['id_fonction']]);

    $pageTitle = $fonction['libelle'];
    $pageSubtitle = 'Grille salariale : le salaire depend de la fonction et de l\'anciennete.';
    $pageActions = '<a class="btn" href="fonctions.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn btn-primary" href="fonctions.php?action=form&id=' . $fonction['id_fonction'] . '">' . icon('edit') . '<span>Renommer</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-header"><h2>Grille salariale</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Anciennete</th><th class="num">Salaire mensuel</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($grille as $g): ?>
                        <tr>
                            <td>De <?= $g['anciennete_min'] ?> a <?= $g['anciennete_max'] ?> an(s)</td>
                            <td class="num strong"><?= money($g['salaire']) ?></td>
                            <td class="actions"><?= delete_button('fonctions.php', ['op' => 'delete_grille', 'id_grille' => $g['id_grille'], 'id_fonction' => $fonction['id_fonction']], 'Supprimer cette tranche ?') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $grille ? '' : empty_row(3, 'Aucune tranche definie.') ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form method="post" class="inline-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="add_grille">
                    <input type="hidden" name="id_fonction" value="<?= $fonction['id_fonction'] ?>">
                    <div class="field"><label>Anciennete min</label><input class="input" type="number" min="0" name="anciennete_min" required value="<?= e($grilleForm['anciennete_min']) ?>"></div>
                    <div class="field"><label>Anciennete max</label><input class="input" type="number" min="0" name="anciennete_max" required value="<?= e($grilleForm['anciennete_max']) ?>"></div>
                    <div class="field"><label>Salaire (FCFA)</label><input class="input" type="number" min="1" step="1" name="salaire" required value="<?= e($grilleForm['salaire']) ?>"></div>
                    <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Ajouter</span></button>
                </form>
                <?= $errors ? '<div style="margin-top:14px">' . form_errors($errors) . '</div>' : '' ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Employes occupant cette fonction</h2>
                <span class="badge badge-info"><?= count($employes) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Employe</th><th class="num">Anciennete</th><th class="num">Salaire</th></tr></thead>
                    <tbody>
                    <?php foreach ($employes as $e): ?>
                        <tr>
                            <td><a href="employes.php?action=view&id=<?= $e['id_employe'] ?>"><?= e($e['nom'] . ' ' . $e['prenom']) ?></a><span class="sub"><?= e($e['nom_service']) ?></span></td>
                            <td class="num"><?= $e['anciennete'] ?> an(s)</td>
                            <td class="num"><?= $e['salaire'] !== null ? money($e['salaire']) : badge('Hors grille') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $employes ? '' : empty_row(3, 'Aucun employe.') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$fonctions = fetch_all("
    SELECT f.id_fonction, f.libelle,
           (SELECT COUNT(*) FROM employe e WHERE e.id_fonction = f.id_fonction) AS nb_employes,
           (SELECT COUNT(*) FROM grille_salaire g WHERE g.id_fonction = f.id_fonction) AS nb_tranches,
           (SELECT MIN(salaire) FROM grille_salaire g WHERE g.id_fonction = f.id_fonction) AS salaire_min,
           (SELECT MAX(salaire) FROM grille_salaire g WHERE g.id_fonction = f.id_fonction) AS salaire_max
    FROM fonction f
    ORDER BY f.id_fonction");

$pageTitle = 'Fonctions et salaires';
$pageSubtitle = 'Fonctions de l\'entreprise et grilles salariales par anciennete.';
$pageActions = '<a class="btn btn-primary" href="fonctions.php?action=form">' . icon('plus') . '<span>Nouvelle fonction</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div class="search"><?= icon('search') ?><input class="input" type="search" placeholder="Rechercher une fonction" data-filter="#table-fonctions"></div>
        <span class="muted"><?= count($fonctions) ?> fonction(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="table-fonctions">
            <thead><tr><th>ID</th><th>Fonction</th><th class="num">Employes</th><th class="num">Tranches</th><th class="num">Fourchette salariale</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fonctions as $f): ?>
                <tr>
                    <td class="id">#<?= $f['id_fonction'] ?></td>
                    <td class="strong"><a href="fonctions.php?action=view&id=<?= $f['id_fonction'] ?>"><?= e($f['libelle']) ?></a></td>
                    <td class="num"><?= $f['nb_employes'] ?></td>
                    <td class="num"><?= $f['nb_tranches'] ?></td>
                    <td class="num"><?= $f['salaire_min'] !== null ? money($f['salaire_min']) . ' - ' . money($f['salaire_max']) : '-' ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="fonctions.php?action=view&id=<?= $f['id_fonction'] ?>"><?= icon('eye') ?><span>Grille</span></a>
                        <?= delete_button('fonctions.php', ['id_fonction' => $f['id_fonction']], 'Supprimer la fonction ' . $f['libelle'] . ' et sa grille ?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
