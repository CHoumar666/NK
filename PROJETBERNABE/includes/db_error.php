<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion impossible - Bernabe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="page-center">
    <div class="card error-card">
        <div class="card-body">
            <h1>Connexion a la base impossible</h1>
            <p class="muted">L'application n'a pas pu se connecter a la base <strong><?= htmlspecialchars(DB_NAME) ?></strong>.</p>
            <ol class="steps">
                <li>Verifiez que WampServer est demarre (icone verte).</li>
                <li>Importez le script <code>database/bernabe.sql</code> dans phpMyAdmin.</li>
                <li>Controlez les identifiants dans <code>config/database.php</code>.</li>
            </ol>
            <pre class="sql"><?= htmlspecialchars($ex->getMessage()) ?></pre>
            <a class="btn btn-primary" href="index.html">Retour a l'accueil</a>
        </div>
    </div>
</body>
</html>
