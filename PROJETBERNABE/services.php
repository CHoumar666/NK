<?php
require __DIR__ . '/includes/bootstrap.php';

$action = input('action', 'list');
$errors = [];
$form = ['id_service' => 0, 'nom_service' => '', 'description' => ''];

/* ---------- Traitements ---------- */

if (is_post()) {
    csrf_check();
    $id = input_int('id_service');

    if (input('op') === 'delete') {
        try_delete('DELETE FROM service WHERE id_service = ?', [$id], 'le service');
        redirect('services.php');
    }

    $form = ['id_service' => $id, 'nom_service' => input('nom_service'), 'description' => input('description')];

    if ($form['nom_service'] === '') {
        $errors[] = 'Le nom du service est obligatoire.';
    } elseif (mb_strlen($form['nom_service']) > 100) {
        $errors[] = 'Le nom du service ne doit pas depasser 100 caracteres.';
    }
    if (mb_strlen($form['description']) > 255) {
        $errors[] = 'La description ne doit pas depasser 255 caracteres.';
    }

    if (!$errors) {
        $params = [$form['nom_service'], $form['description'] ?: null];
        if ($id) {
            query('UPDATE service SET nom_service = ?, description = ? WHERE id_service = ?', [...$params, $id]);
            flash('success', 'Service mis a jour.');
        } else {
            query('INSERT INTO service (nom_service, description) VALUES (?, ?)', $params);
            flash('success', 'Service cree.');
        }
        redirect('services.php');
    }
    $action = 'form';
} elseif ($action === 'form' && input_int('id')) {
    $form = fetch_one('SELECT * FROM service WHERE id_service = ?', [input_int('id')]) ?? redirect('services.php');
}

$activePage = 'services';

/* ---------- Formulaire ---------- */

if ($action === 'form') {
    $pageTitle = $form['id_service'] ? 'Modifier le service' : 'Nouveau service';
    $pageActions = '<a class="btn" href="services.php">' . icon('back') . '<span>Retour</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card" style="max-width: 760px">
        <form method="post" class="card-body">
            <?= csrf_field() . form_errors($errors) ?>
            <input type="hidden" name="id_service" value="<?= e($form['id_service']) ?>">
            <div class="form-grid">
                <div class="field full">
                    <label for="nom_service">Nom du service <span class="req">*</span></label>
                    <input class="input" id="nom_service" name="nom_service" maxlength="100" required value="<?= e($form['nom_service']) ?>">
                </div>
                <div class="field full">
                    <label for="description">Description</label>
                    <textarea class="textarea" id="description" name="description" maxlength="255"><?= e($form['description']) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn" href="services.php">Annuler</a>
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
    $service = fetch_one('SELECT * FROM service WHERE id_service = ?', [input_int('id')]) ?? redirect('services.php');
    $employes = fetch_all("
        SELECT e.id_employe, e.nom, e.prenom, e.date_embauche, f.libelle AS fonction,
               CONCAT(s.nom, ' ', s.prenom) AS superieur
        FROM employe e
        JOIN fonction f ON f.id_fonction = e.id_fonction
        LEFT JOIN employe s ON s.id_employe = e.id_superieur
        WHERE e.id_service = ?
        ORDER BY f.id_fonction, e.nom", [$service['id_service']]);

    $pageTitle = $service['nom_service'];
    $pageSubtitle = $service['description'] ?? '';
    $pageActions = '<a class="btn" href="services.php">' . icon('back') . '<span>Retour</span></a>'
        . '<a class="btn btn-primary" href="services.php?action=form&id=' . $service['id_service'] . '">' . icon('edit') . '<span>Modifier</span></a>';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="card">
        <div class="card-header">
            <h2>Employes du service</h2>
            <span class="badge badge-info"><?= count($employes) ?> employe(s)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Employe</th><th>Fonction</th><th>Superieur</th><th>Embauche</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($employes as $e): ?>
                    <tr>
                        <td class="strong"><?= e($e['nom'] . ' ' . $e['prenom']) ?></td>
                        <td><?= e($e['fonction']) ?></td>
                        <td><?= e($e['superieur'] ?? '-') ?></td>
                        <td><?= date_fr($e['date_embauche']) ?></td>
                        <td class="actions"><a class="btn btn-ghost btn-sm" href="employes.php?action=view&id=<?= $e['id_employe'] ?>"><?= icon('eye') ?><span>Fiche</span></a></td>
                    </tr>
                <?php endforeach; ?>
                <?= $employes ? '' : empty_row(5, 'Aucun employe dans ce service.') ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Liste ---------- */

$services = fetch_all("
    SELECT s.id_service, s.nom_service, s.description, COUNT(e.id_employe) AS nb_employes
    FROM service s
    LEFT JOIN employe e ON e.id_service = s.id_service
    GROUP BY s.id_service, s.nom_service, s.description
    ORDER BY s.nom_service");

$pageTitle = 'Services';
$pageSubtitle = 'Services specialises de l\'entreprise et leurs effectifs.';
$pageActions = '<a class="btn btn-primary" href="services.php?action=form">' . icon('plus') . '<span>Nouveau service</span></a>';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <div class="search"><?= icon('search') ?><input class="input" type="search" placeholder="Rechercher un service" data-filter="#table-services"></div>
        <span class="muted"><?= count($services) ?> service(s)</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="table-services">
            <thead><tr><th>ID</th><th>Service</th><th>Description</th><th class="num">Employes</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td class="id">#<?= $s['id_service'] ?></td>
                    <td class="strong"><a href="services.php?action=view&id=<?= $s['id_service'] ?>"><?= e($s['nom_service']) ?></a></td>
                    <td><?= e($s['description']) ?></td>
                    <td class="num"><?= $s['nb_employes'] ?></td>
                    <td class="actions">
                        <a class="btn btn-ghost btn-sm" href="services.php?action=form&id=<?= $s['id_service'] ?>"><?= icon('edit') ?><span>Modifier</span></a>
                        <?= delete_button('services.php', ['id_service' => $s['id_service']], 'Supprimer le service ' . $s['nom_service'] . ' ?') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?= $services ? '' : empty_row(5) ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
