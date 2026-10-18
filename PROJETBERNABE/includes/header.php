<?php
/*
 * Gabarit commun : barre laterale + en-tete de page.
 * Variables attendues : $pageTitle, $activePage
 * Variables optionnelles : $pageSubtitle, $pageActions (HTML)
 */
$navigation = [
    'Pilotage' => [
        'dashboard'     => ['Tableau de bord', 'dashboard.php', 'dashboard'],
        'rapports'      => ['Rapports R1 - R9', 'rapports.php', 'rapport'],
    ],
    'Organisation' => [
        'services'      => ['Services', 'services.php', 'service'],
        'fonctions'     => ['Fonctions et salaires', 'fonctions.php', 'fonction'],
        'employes'      => ['Employes', 'employes.php', 'employe'],
    ],
    'Parc materiel' => [
        'materiels'     => ['Materiels', 'materiels.php', 'materiel'],
        'qualifications'=> ['Qualifications', 'qualifications.php', 'qualification'],
    ],
    'Activite' => [
        'clients'       => ['Clients', 'clients.php', 'client'],
        'interventions' => ['Interventions', 'interventions.php', 'intervention'],
    ],
];
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - Bernabe Depannage</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark">B</span>
            <span class="brand-text">
                <strong>Bernabe</strong>
                <small>Gestion des depannages</small>
            </span>
        </a>
        <nav class="nav">
            <?php foreach ($navigation as $group => $items): ?>
                <div class="nav-group">
                    <span class="nav-title"><?= e($group) ?></span>
                    <?php foreach ($items as $key => [$label, $url, $ico]): ?>
                        <a href="<?= e($url) ?>" class="nav-link<?= $key === $activePage ? ' active' : '' ?>">
                            <?= icon($ico) ?><span><?= e($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="index.html" class="nav-link"><?= icon('home') ?><span>Site vitrine</span></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon menu-toggle" type="button" data-sidebar-toggle aria-label="Ouvrir le menu"><?= icon('menu') ?></button>
            <div class="topbar-title">
                <span class="crumb">Bernabe</span>
                <span class="crumb-sep">/</span>
                <span><?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-right">
                <span class="db-status"><span class="dot"></span>Base <?= e(DB_NAME) ?></span>
                <span class="avatar" title="Direction">DG</span>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="muted"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($pageActions)): ?>
                    <div class="page-actions"><?= $pageActions ?></div>
                <?php endif; ?>
            </div>

            <?php foreach (flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" data-dismissible><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
