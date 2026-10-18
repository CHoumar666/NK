<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$form = ['id_client' => 0, 'nom' => '', 'adresse' => '', 'telephone' => '', 'email' => ''];

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $id = input_int('id_client');

    if (input('op') === 'delete') {
        try_delete('DELETE FROM client WHERE id_client = ?', [$id], 'le client');
        redirect('clients.php');
    }

    foreach (array_keys($form) as $key) {
        $form[$key] = input($key);
    }
    $form['id_client'] = $id;

    if ($form['nom'] === '') {
        $errors[] = 'Le nom du client est obligatoire.';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse e-mail est invalide.';
    }
    if ($form['telephone'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $form['telephone'])) {
        $errors[] = 'Le numero de telephone est invalide.';
    }

    if (!$errors) {
        $params = [$form['nom'], $form['adresse'] ?: null, $form['telephone'] ?: null, $form['email'] ?: null];
        if ($id) {
            query('UPDATE client SET nom = ?, adresse = ?, telephone = ?, email = ? WHERE id_client = ?', [...$params, $id]);
            flash('success', 'Client mis a jour.');
        } else {
            query('INSERT INTO client (nom, adresse, telephone, email) VALUES (?, ?, ?, ?)', $params);
            $id = (int) db()->lastInsertId();
            flash('success', 'Client cree.');
        }
        redirect('clients.php?action=view&id=' . $id);
    }
    $action = 'form';
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM client WHERE id_client = ?', [input_int('id')]) ?? redirect('clients.php');
}

$activePage = 'clients';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $pageTitle = $form['id_client'] ? 'Modifier le client' : 'Nouveau client';
    $pageActions = '<a class="btn" href="clients.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 860px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_client" value="<?= e($form['id_client']) ?>">
            <div class="form-grid">
                <div class="field full">
                    <label for="nom">Raison sociale / Nom <span class="req">*</span></label>
                    <input class="input" id="nom" name="nom" maxlength="120" required value="<?= e($form['nom']) ?>">
                </div>
                <div class="field full">
                    <label for="adresse">Adresse</label>
                    <input class="input" id="adresse" name="adresse" maxlength="255" value="<?= e($form['adresse']) ?>">
                </div>
                <div class="field">
                    <label for="telephone">Telephone</label>
                    <input class="input" type="tel" id="telephone" name="telephone" maxlength="30" value="<?= e($form['telephone']) ?>">
                </div>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input class="input" type="email" id="email" name="email" maxlength="120" value="<?= e($form['email']) ?>">
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="clients.php">Annuler</a>
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
    $client = fetch_one('SELECT * FROM client WHERE id_client = ?', [input_int('id')]) ?? redirect('clients.php');
    $interventions = fetch_all("
        SELECT i.id_intervention, i.date_intervention, i.description, i.statut,
               CONCAT(e.nom, ' ', e.prenom) AS responsable,
               COUNT(a.id_acte) AS nb_actes, COALESCE(SUM(a.cout), 0) AS cout_total
        FROM intervention i
        JOIN employe e ON e.id_employe = i.id_responsable
        LEFT JOIN acte_depannage a ON a.id_intervention = i.id_intervention
        WHERE i.id_client = ?
        GROUP BY i.id_intervention, i.date_intervention, i.description, i.statut, e.nom, e.prenom
        ORDER BY i.date_intervention DESC", [$client['id_client']]);
    $total = array_sum(array_column($interventions, 'cout_total'));

    $pageTitle = $client['nom'];
    $pageSubtitle = 'Fiche client et historique des interventions.';
    $pageActions = '<a class="btn" href="clients.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn" href="clients.php?action=form&id=' . $client['id_client'] . '">' . icon('edit') . '<span>Modifier</span></a>'
        . '<a class="btn btn-primary" href="interventions.php?action=form&id_client=' . $client['id_client'] . '">' . icon('plus') . '<span>Nouvelle intervention</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="grid grid-sidebar">
        <div class="card">
            <div class="card-header"><h2>Interventions</h2><span class="badge badge-info"><?= count($interventions) ?></span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>N°</th><th>Date</th><th>Description</th><th>Responsable</th><th>Statut</th><th class="num">Cout</th></tr></thead>
                    <tbody>
                    <?php foreach ($interventions as $i): ?>
                        <tr>
                            <td class="nowrap"><a href="interventions.php?action=view&id=<?= $i['id_intervention'] ?>">INT-<?= str_pad($i['id_intervention'], 4, '0', STR_PAD_LEFT) ?></a></td>
                            <td class="nowrap"><?= date_fr($i['date_intervention']) ?></td>
                            <td><?= e($i['description']) ?><span class="sub"><?= $i['nb_actes'] ?> acte(s)</span></td>
                            <td><?= e($i['responsable']) ?></td>
                            <td><?= badge($i['statut']) ?></td>
                            <td class="num"><?= money($i['cout_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?= $interventions ? '' : empty_row(6, 'Aucune intervention pour ce client.') ?>
                    </tbody>
                    <?php if ($interventions): ?>
                        <tfoot><tr><td colspan="5">Total facture</td><td class="num"><?= money($total) ?></td></tr></tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Coordonnees</h2></div>
            <div class="card-body">
                <dl class="details">
                    <dt>Code client</dt><dd>CLI-<?= str_pad($client['id_client'], 4, '0', STR_PAD_LEFT) ?></dd>
                    <dt>Adresse</dt><dd><?= e($client['adresse'] ?: '-') ?></dd>
                    <dt>Telephone</dt><dd><?= e($client['telephone'] ?: '-') ?></dd>
                    <dt>E-mail</dt><dd><?= $client['email'] ? '<a href="mailto:' . e($client['email']) . '">' . e($client['email']) . '</a>' : '-' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$clients = fetch_all("
    SELECT c.*, COUNT(DISTINCT i.id_intervention) AS nb_interventions, COALESCE(SUM(a.cout), 0) AS montant
    FROM client c
    LEFT JOIN intervention i ON i.id_client = c.id_client
    LEFT JOIN acte_depannage a ON a.id_intervention = i.id_intervention
    GROUP BY c.id_client, c.nom, c.adresse, c.telephone, c.email
    ORDER BY c.nom");

$pageTitle = 'Clients';
$pageSubtitle = 'Clients a l\'origine des demandes d\'intervention (RG4).';
$pageActions = '<a class="btn btn-primary" href="clients.php?action=form">' . icon('plus') . '<span>Nouveau client</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div class="search"><?= icon('search') ?><input class="input" type="search" placeholder="Rechercher un client" data-filter="#table-clients"></div>
        <span class="muted"><?= count($clients) ?> client(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="table-clients">
            <thead><tr><th>Code</th><th>Client</th><th>Telephone</th><th>E-mail</th><th class="num">Interventions</th><th class="num">Montant</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td class="id">CLI-<?= str_pad($c['id_client'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="strong"><a href="clients.php?action=view&id=<?= $c['id_client'] ?>"><?= e($c['nom']) ?></a><span class="sub"><?= e($c['adresse']) ?></span></td>
                    <td class="nowrap"><?= e($c['telephone']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td class="num"><?= $c['nb_interventions'] ?></td>
                    <td class="num"><?= money($c['montant']) ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="clients.php?action=form&id=<?= $c['id_client'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                        <?= delete_button('clients.php', ['id_client' => $c['id_client']], 'Supprimer le client ' . $c['nom'] . ' ?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $clients ? '' : empty_row(7) ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
