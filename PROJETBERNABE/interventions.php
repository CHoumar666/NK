<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$acteErrors = [];
$partErrors = [];
$form = [
    'id_intervention' => 0, 'date_intervention' => date('Y-m-d'), 'description' => '',
    'id_client' => input('id_client'), 'id_responsable' => '', 'statut' => 'Planifiee',
];
$acteForm = ['id_acte' => 0, 'description' => '', 'cout' => ''];
$partForm = ['id_acte' => '', 'id_employe' => '', 'date_debut' => '', 'date_fin' => ''];

function numero(int $id): string
{
    return 'INT-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $op = input('op');
    $id = input_int('id_intervention');
    $retourFiche = 'interventions.php?action=view&id=' . $id;

    switch ($op) {
        case 'delete':
            // Une intervention se decompose en actes (RG6) : on supprime
            // l'ensemble dans une transaction.
            db()->beginTransaction();
            query('DELETE p FROM participation p JOIN acte_depannage a ON a.id_acte = p.id_acte WHERE a.id_intervention = ?', [$id]);
            query('DELETE FROM acte_depannage WHERE id_intervention = ?', [$id]);
            query('DELETE FROM intervention WHERE id_intervention = ?', [$id]);
            db()->commit();
            flash('success', 'Intervention ' . numero($id) . ' supprimee avec ses actes.');
            redirect('interventions.php');

        case 'delete_acte':
            $idActe = input_int('id_acte');
            db()->beginTransaction();
            query('DELETE FROM participation WHERE id_acte = ?', [$idActe]);
            query('DELETE FROM acte_depannage WHERE id_acte = ? AND id_intervention = ?', [$idActe, $id]);
            db()->commit();
            flash('success', 'Acte de depannage supprime.');
            redirect($retourFiche);

        case 'delete_participation':
            query('DELETE FROM participation WHERE id_acte = ? AND id_employe = ?', [input_int('id_acte'), input_int('id_employe')]);
            flash('success', 'Participation retiree.');
            redirect($retourFiche);

        case 'save_acte':
            $acteForm = ['id_acte' => input_int('id_acte'), 'description' => input('description'), 'cout' => input('cout')];
            $cout = filter_var($acteForm['cout'], FILTER_VALIDATE_FLOAT);
            if ($acteForm['description'] === '') {
                $acteErrors[] = 'La description de l\'acte est obligatoire.';
            }
            if ($cout === false || $cout < 0) {
                $acteErrors[] = 'Le cout doit etre un montant positif ou nul.';
            }
            if (!$acteErrors) {
                if ($acteForm['id_acte']) {
                    query('UPDATE acte_depannage SET description = ?, cout = ? WHERE id_acte = ? AND id_intervention = ?',
                        [$acteForm['description'], $cout, $acteForm['id_acte'], $id]);
                    flash('success', 'Acte mis a jour.');
                } else {
                    query('INSERT INTO acte_depannage (id_intervention, description, cout) VALUES (?, ?, ?)',
                        [$id, $acteForm['description'], $cout]);
                    flash('success', 'Acte ajoute a l\'intervention.');
                }
                redirect($retourFiche);
            }
            $action = $acteForm['id_acte'] ? 'acte_form' : 'view';
            $_GET['id'] = $id;
            break;

        case 'add_participation':
            foreach (array_keys($partForm) as $key) {
                $partForm[$key] = input($key);
            }
            $idActe = (int) $partForm['id_acte'];
            $idEmploye = (int) $partForm['id_employe'];

            if (!fetch_value('SELECT COUNT(*) FROM acte_depannage WHERE id_acte = ? AND id_intervention = ?', [$idActe, $id])) {
                $partErrors[] = 'Selectionnez un acte de cette intervention.';
            }
            if (!fetch_value('SELECT COUNT(*) FROM employe WHERE id_employe = ?', [$idEmploye])) {
                $partErrors[] = 'Selectionnez un employe.';
            }
            if (!valid_datetime_local($partForm['date_debut'])) {
                $partErrors[] = 'La date de debut est obligatoire.';
            }
            if ($partForm['date_fin'] !== '' && !valid_datetime_local($partForm['date_fin'])) {
                $partErrors[] = 'La date de fin est invalide.';
            } elseif ($partForm['date_fin'] !== '' && $partForm['date_fin'] < $partForm['date_debut']) {
                $partErrors[] = 'La date de fin doit etre posterieure a la date de debut.';
            }
            if (!$partErrors && fetch_value('SELECT COUNT(*) FROM participation WHERE id_acte = ? AND id_employe = ?', [$idActe, $idEmploye])) {
                $partErrors[] = 'Cet employe participe deja a cet acte.';
            }
            if (!$partErrors) {
                query('INSERT INTO participation (id_employe, id_acte, date_debut, date_fin) VALUES (?, ?, ?, ?)', [
                    $idEmploye, $idActe,
                    str_replace('T', ' ', $partForm['date_debut']),
                    $partForm['date_fin'] !== '' ? str_replace('T', ' ', $partForm['date_fin']) : null,
                ]);
                flash('success', 'Participation enregistree.');
                redirect($retourFiche);
            }
            $action = 'view';
            $_GET['id'] = $id;
            break;

        default:
            foreach (array_keys($form) as $key) {
                $form[$key] = input($key);
            }
            $form['id_intervention'] = $id;

            if (!valid_date($form['date_intervention'])) {
                $errors[] = 'La date d\'intervention est invalide.';
            }
            if (!fetch_value('SELECT COUNT(*) FROM client WHERE id_client = ?', [(int) $form['id_client']])) {
                $errors[] = 'Une intervention se fait toujours a la demande d\'un client (RG4).';
            }
            if (!fetch_value('SELECT COUNT(*) FROM employe WHERE id_employe = ?', [(int) $form['id_responsable']])) {
                $errors[] = 'Une intervention se fait toujours sous la direction d\'un responsable (RG5).';
            }
            if (!in_array($form['statut'], STATUTS_INTERVENTION, true)) {
                $errors[] = 'Le statut selectionne est invalide.';
            }

            if (!$errors) {
                $params = [$form['date_intervention'], $form['description'] ?: null, (int) $form['id_client'], (int) $form['id_responsable'], $form['statut']];
                if ($id) {
                    query('UPDATE intervention SET date_intervention = ?, description = ?, id_client = ?, id_responsable = ?, statut = ? WHERE id_intervention = ?', [...$params, $id]);
                    flash('success', 'Intervention mise a jour.');
                } else {
                    query('INSERT INTO intervention (date_intervention, description, id_client, id_responsable, statut) VALUES (?, ?, ?, ?, ?)', $params);
                    $id = (int) db()->lastInsertId();
                    flash('success', 'Intervention ' . numero($id) . ' creee. Ajoutez maintenant ses actes de depannage.');
                }
                redirect('interventions.php?action=view&id=' . $id);
            }
            $action = 'form';
    }
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM intervention WHERE id_intervention = ?', [input_int('id')]) ?? redirect('interventions.php');
} elseif ($action === 'acte_form') {
    $acteForm = fetch_one('SELECT * FROM acte_depannage WHERE id_acte = ?', [input_int('id_acte')]) ?? redirect('interventions.php');
    $_GET['id'] = $acteForm['id_intervention'];
}

$activePage = 'interventions';

/* ---------- Formulaire intervention ---------- */

if ($action === 'form') {
    $pageTitle = $form['id_intervention'] ? 'Modifier ' . numero((int) $form['id_intervention']) : 'Nouvelle intervention';
    $pageActions = '<a class="btn" href="interventions.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 860px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_intervention" value="<?= e($form['id_intervention']) ?>">
            <div class="form-grid">
                <div class="field">
                    <label for="id_client">Client demandeur <span class="req">*</span></label>
                    <select class="select" id="id_client" name="id_client" required><?= options(liste_clients(), 'id_client', 'nom', $form['id_client'], 'Choisir un client') ?></select>
                </div>
                <div class="field">
                    <label for="id_responsable">Responsable <span class="req">*</span></label>
                    <select class="select" id="id_responsable" name="id_responsable" required><?= options(liste_employes(), 'id_employe', 'nom_complet', $form['id_responsable'], 'Choisir un responsable') ?></select>
                </div>
                <div class="field">
                    <label for="date_intervention">Date <span class="req">*</span></label>
                    <input class="input" type="date" id="date_intervention" name="date_intervention" required value="<?= e($form['date_intervention']) ?>">
                </div>
                <div class="field">
                    <label for="statut">Statut <span class="req">*</span></label>
                    <select class="select" id="statut" name="statut"><?= options_list(STATUTS_INTERVENTION, $form['statut']) ?></select>
                </div>
                <div class="field full">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description" maxlength="255"><?= e($form['description']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="interventions.php">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Formulaire acte ---------- */

if ($action === 'acte_form') {
    $idIntervention = input_int('id');
    $pageTitle = 'Modifier l\'acte de depannage';
    $pageSubtitle = 'Intervention ' . numero($idIntervention);
    $pageActions = '<a class="btn" href="interventions.php?action=view&id=' . $idIntervention . '">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 760px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($acteErrors) ?>
            <input type="hidden" name="op" value="save_acte">
            <input type="hidden" name="id_intervention" value="<?= $idIntervention ?>">
            <input type="hidden" name="id_acte" value="<?= e($acteForm['id_acte']) ?>">
            <div class="form-grid">
                <div class="field full">
                    <label for="description">Description <span class="req">*</span></label>
                    <input class="input" id="description" name="description" maxlength="255" required value="<?= e($acteForm['description']) ?>">
                </div>
                <div class="field">
                    <label for="cout">Cout (FCFA) <span class="req">*</span></label>
                    <input class="input" type="number" min="0" step="1" id="cout" name="cout" required value="<?= e($acteForm['cout']) ?>">
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="interventions.php?action=view&id=<?= $idIntervention ?>">Annuler</a>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Fiche intervention ---------- */

if ($action === 'view') {
    $intervention = fetch_one("
        SELECT i.*, c.nom AS client, c.adresse, c.telephone,
               CONCAT(e.nom, ' ', e.prenom) AS responsable, f.libelle AS fonction_responsable
        FROM intervention i
        JOIN client c   ON c.id_client = i.id_client
        JOIN employe e  ON e.id_employe = i.id_responsable
        JOIN fonction f ON f.id_fonction = e.id_fonction
        WHERE i.id_intervention = ?", [input_int('id')]) ?? redirect('interventions.php');
    $id = (int) $intervention['id_intervention'];

    $actes = fetch_all('SELECT * FROM acte_depannage WHERE id_intervention = ? ORDER BY id_acte', [$id]);
    $participations = fetch_all("
        SELECT p.*, CONCAT(e.nom, ' ', e.prenom) AS employe,
               TIMESTAMPDIFF(MINUTE, p.date_debut, p.date_fin) AS duree
        FROM participation p
        JOIN employe e ON e.id_employe = p.id_employe
        JOIN acte_depannage a ON a.id_acte = p.id_acte
        WHERE a.id_intervention = ?
        ORDER BY p.date_debut", [$id]);

    $parActe = [];
    foreach ($participations as $p) {
        $parActe[$p['id_acte']][] = $p;
    }
    $coutTotal = array_sum(array_column($actes, 'cout'));
    $intervenants = count(array_unique(array_column($participations, 'id_employe')));
    $minutes = array_sum(array_column($participations, 'duree'));

    $pageTitle = 'Intervention ' . numero($id);
    $pageSubtitle = $intervention['description'] ?? '';
    $pageActions = '<a class="btn" href="interventions.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn btn-primary" href="interventions.php?action=form&id=' . $id . '">' . icon('edit') . '<span>Modifier</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="kpis">
        <div class="card kpi"><div class="kpi-label">Cout total</div><div class="kpi-value small"><?= money($coutTotal) ?></div><div class="kpi-hint">Requete R5</div></div>
        <div class="card kpi"><div class="kpi-label">Actes de depannage</div><div class="kpi-value"><?= count($actes) ?></div><div class="kpi-hint">Requete R9</div></div>
        <div class="card kpi"><div class="kpi-label">Intervenants</div><div class="kpi-value"><?= $intervenants ?></div></div>
        <div class="card kpi"><div class="kpi-label">Temps passe</div><div class="kpi-value"><?= floor($minutes / 60) ?> h <?= str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) ?></div></div>
    </div>

    <div class="grid grid-sidebar">
        <div class="stack">
            <div class="card">
                <div class="card-header"><div><h2>Actes de depannage</h2><p class="muted">Chaque acte comporte un cout et fait intervenir un ou plusieurs employes</p></div></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Acte</th><th>Intervenants</th><th class="num">Cout</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($actes as $a): ?>
                            <tr>
                                <td class="strong"><?= e($a['description']) ?><span class="sub">Acte #<?= $a['id_acte'] ?></span></td>
                                <td>
                                    <?php foreach ($parActe[$a['id_acte']] ?? [] as $p): ?>
                                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap">
                                            <a href="employes.php?action=view&id=<?= $p['id_employe'] ?>"><?= e($p['employe']) ?></a>
                                            <span class="sub" style="display:inline"><?= datetime_fr($p['date_debut']) ?> - <?= $p['date_fin'] ? date('H:i', strtotime($p['date_fin'])) : 'en cours' ?></span>
                                            <?= delete_button('interventions.php', ['op' => 'delete_participation', 'id_intervention' => $id, 'id_acte' => $a['id_acte'], 'id_employe' => $p['id_employe']], 'Retirer ' . $p['employe'] . ' de cet acte ?', 'Retirer') ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?= empty($parActe[$a['id_acte']]) ? '<span class="muted">Aucun intervenant</span>' : '' ?>
                                </td>
                                <td class="num"><?= money($a['cout']) ?></td>
                                <td class="actions">
                                    <a class="btn btn-ghost btn-sm" href="interventions.php?action=acte_form&id_acte=<?= $a['id_acte'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                                    <?= delete_button('interventions.php', ['op' => 'delete_acte', 'id_intervention' => $id, 'id_acte' => $a['id_acte']], 'Supprimer cet acte et ses participations ?') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?= $actes ? '' : empty_row(4, 'Aucun acte pour le moment.') ?>
                        </tbody>
                        <?php if ($actes): ?>
                            <tfoot><tr><td colspan="2">Cout total de l'intervention</td><td class="num"><?= money($coutTotal) ?></td><td></td></tr></tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="card-footer">
                    <?= form_errors($acteErrors) ?>
                    <form method="post" class="inline-add">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="save_acte">
                        <input type="hidden" name="id_intervention" value="<?= $id ?>">
                        <div class="field" style="flex-grow: 3"><label>Nouvel acte</label><input class="input" name="description" maxlength="255" placeholder="Ex. Diagnostic, remplacement de piece" required value="<?= e($acteForm['description']) ?>"></div>
                        <div class="field"><label>Cout (FCFA)</label><input class="input" type="number" name="cout" min="0" step="1" required value="<?= e($acteForm['cout']) ?>"></div>
                        <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Ajouter l'acte</span></button>
                    </form>
                </div>
            </div>

            <?php if ($actes): ?>
            <div class="card">
                <div class="card-header"><div><h2>Affecter un employe a un acte</h2><p class="muted">Les dates de debut et de fin de participation sont notees</p></div></div>
                <div class="card-body">
                    <?= form_errors($partErrors) ?>
                    <form method="post" class="form-grid">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="add_participation">
                        <input type="hidden" name="id_intervention" value="<?= $id ?>">
                        <div class="field">
                            <label>Acte <span class="req">*</span></label>
                            <select class="select" name="id_acte" required><?= options($actes, 'id_acte', 'description', $partForm['id_acte'], 'Choisir un acte') ?></select>
                        </div>
                        <div class="field">
                            <label>Employe <span class="req">*</span></label>
                            <select class="select" name="id_employe" required><?= options(liste_employes(), 'id_employe', 'nom_complet', $partForm['id_employe'], 'Choisir un employe') ?></select>
                        </div>
                        <div class="field">
                            <label>Debut <span class="req">*</span></label>
                            <input class="input" type="datetime-local" name="date_debut" required value="<?= e($partForm['date_debut']) ?>">
                        </div>
                        <div class="field">
                            <label>Fin</label>
                            <input class="input" type="datetime-local" name="date_fin" value="<?= e($partForm['date_fin']) ?>">
                            <span class="help">Laisser vide si la participation est en cours.</span>
                        </div>
                        <div class="form-actions full" style="margin-top: 0">
                            <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Enregistrer la participation</span></button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-header"><h2>Synthese</h2><?= badge($intervention['statut']) ?></div>
                <div class="card-body">
                    <dl class="details">
                        <dt>Numero</dt><dd><?= numero($id) ?></dd>
                        <dt>Date</dt><dd><?= date_fr($intervention['date_intervention']) ?></dd>
                        <dt>Client</dt><dd><a href="clients.php?action=view&id=<?= $intervention['id_client'] ?>"><?= e($intervention['client']) ?></a></dd>
                        <dt>Adresse</dt><dd><?= e($intervention['adresse'] ?: '-') ?></dd>
                        <dt>Telephone</dt><dd><?= e($intervention['telephone'] ?: '-') ?></dd>
                        <dt>Responsable</dt><dd><a href="employes.php?action=view&id=<?= $intervention['id_responsable'] ?>"><?= e($intervention['responsable']) ?></a><span class="sub"><?= e($intervention['fonction_responsable']) ?></span></dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>Zone sensible</h3>
                    <p class="muted" style="margin-bottom: 14px">La suppression retire aussi les actes et les participations associes.</p>
                    <?= delete_button('interventions.php', ['id_intervention' => $id], 'Supprimer definitivement l\'intervention ' . numero($id) . ' ?', 'Supprimer l\'intervention') ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$filtreStatut = input('statut');
$filtreClient = input_int('client');
$recherche = input('q');
$where = [];
$params = [];
if (in_array($filtreStatut, STATUTS_INTERVENTION, true)) {
    $where[] = 'i.statut = ?';
    $params[] = $filtreStatut;
}
if ($filtreClient) {
    $where[] = 'i.id_client = ?';
    $params[] = $filtreClient;
}
if ($recherche !== '') {
    $where[] = 'i.description LIKE ?';
    $params[] = '%' . $recherche . '%';
}

$interventions = fetch_all("
    SELECT i.id_intervention, i.date_intervention, i.description, i.statut,
           c.id_client, c.nom AS client, CONCAT(e.nom, ' ', e.prenom) AS responsable,
           COUNT(a.id_acte) AS nb_actes, COALESCE(SUM(a.cout), 0) AS cout_total
    FROM intervention i
    JOIN client c  ON c.id_client = i.id_client
    JOIN employe e ON e.id_employe = i.id_responsable
    LEFT JOIN acte_depannage a ON a.id_intervention = i.id_intervention
    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
    GROUP BY i.id_intervention, i.date_intervention, i.description, i.statut, c.id_client, c.nom, e.nom, e.prenom
    ORDER BY i.date_intervention DESC", $params);

$pageTitle = 'Interventions';
$pageSubtitle = 'Demandes de depannage, responsables, actes et couts.';
$pageActions = '<a class="btn btn-primary" href="interventions.php?action=form">' . icon('plus') . '<span>Nouvelle intervention</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <form class="toolbar" method="get">
            <div class="search"><?= icon('search') ?><input class="input" type="search" name="q" placeholder="Rechercher dans les descriptions" value="<?= e($recherche) ?>"></div>
            <select class="select" name="statut" data-autosubmit><?= options_list(STATUTS_INTERVENTION, $filtreStatut, 'Tous les statuts') ?></select>
            <select class="select" name="client" data-autosubmit><?= options(liste_clients(), 'id_client', 'nom', $filtreClient, 'Tous les clients') ?></select>
            <button class="btn" type="submit">Filtrer</button>
            <?php if ($where): ?><a class="btn btn-ghost" href="interventions.php">Reinitialiser</a><?php endif; ?>
        </form>
        <span class="muted"><?= count($interventions) ?> intervention(s) - <?= money(array_sum(array_column($interventions, 'cout_total'))) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>N°</th><th>Date</th><th>Client</th><th>Responsable</th><th>Statut</th><th class="num">Actes</th><th class="num">Cout total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($interventions as $i): ?>
                <tr>
                    <td class="strong"><a href="interventions.php?action=view&id=<?= $i['id_intervention'] ?>"><?= numero((int) $i['id_intervention']) ?></a></td>
                    <td class="nowrap"><?= date_fr($i['date_intervention']) ?></td>
                    <td><a href="clients.php?action=view&id=<?= $i['id_client'] ?>"><?= e($i['client']) ?></a><span class="sub"><?= e($i['description']) ?></span></td>
                    <td><?= e($i['responsable']) ?></td>
                    <td><?= badge($i['statut']) ?></td>
                    <td class="num"><?= $i['nb_actes'] ?></td>
                    <td class="num strong"><?= money($i['cout_total']) ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="interventions.php?action=view&id=<?= $i['id_intervention'] ?>"><?= icon('eye') ?><span>Ouvrir</span></a>
                        <a class="btn btn-ghost btn-sm" href="interventions.php?action=form&id=<?= $i['id_intervention'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $interventions ? '' : empty_row(8, 'Aucune intervention ne correspond aux criteres.') ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
