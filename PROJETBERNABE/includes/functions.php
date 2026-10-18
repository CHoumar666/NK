<?php
/*
 * Fonctions utilitaires partagees : acces aux donnees, securite,
 * messages flash et formatage.
 */

/* ---------- Acces aux donnees ---------- */

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetch_all(string $sql, array $params = []): array
{
    return query($sql, $params)->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $row = query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetch_value(string $sql, array $params = [])
{
    return query($sql, $params)->fetchColumn();
}

/**
 * Execute une suppression et transforme l'erreur de cle etrangere
 * en message lisible. Retourne true si la suppression a eu lieu.
 */
function try_delete(string $sql, array $params, string $label): bool
{
    try {
        query($sql, $params);
        flash('success', ucfirst($label) . ' supprime(e) avec succes.');
        return true;
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', 'Suppression impossible : ' . $label . ' est encore reference(e) par d\'autres donnees.');
            return false;
        }
        throw $ex;
    }
}

/* ---------- Requete HTTP ---------- */

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function input_int(string $key): int
{
    return (int) input($key, '0');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Securite ---------- */

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Jeton de securite invalide. Rechargez la page et recommencez.');
    }
}

/* ---------- Messages flash ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- Validation ---------- */

function valid_date(string $value, string $format = 'Y-m-d'): bool
{
    $date = DateTime::createFromFormat($format, $value);
    return $date !== false && $date->format($format) === $value;
}

function valid_datetime_local(string $value): bool
{
    return valid_date($value, 'Y-m-d\TH:i') || valid_date($value, 'Y-m-d\TH:i:s');
}

/* ---------- Formatage ---------- */

function money($amount): string
{
    return number_format((float) $amount, 0, ',', ' ') . ' FCFA';
}

function number($value): string
{
    return number_format((float) $value, 0, ',', ' ');
}

function date_fr(?string $date): string
{
    return $date ? date('d/m/Y', strtotime($date)) : '-';
}

function datetime_fr(?string $date): string
{
    return $date ? date('d/m/Y H:i', strtotime($date)) : '-';
}

/** Convertit une date SQL en valeur pour <input type="datetime-local">. */
function datetime_input(?string $date): string
{
    return $date ? date('Y-m-d\TH:i', strtotime($date)) : '';
}

function badge(?string $value): string
{
    $classes = [
        'Terminee'       => 'success',
        'En cours'       => 'info',
        'Planifiee'      => 'warning',
        'Annulee'        => 'neutral',
        'Disponible'     => 'success',
        'En maintenance' => 'warning',
        'Hors service'   => 'danger',
        'Expert'         => 'info',
        'Avance'         => 'success',
        'Intermediaire'  => 'warning',
        'Debutant'       => 'neutral',
    ];
    $class = $classes[$value] ?? 'neutral';
    return '<span class="badge badge-' . $class . '">' . e($value) . '</span>';
}

/**
 * Genere les <option> d'une liste deroulante.
 */
function options(array $rows, string $valueKey, string $labelKey, $selected = null, string $placeholder = ''): string
{
    $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
    foreach ($rows as $row) {
        $isSelected = (string) $row[$valueKey] === (string) $selected ? ' selected' : '';
        $html .= '<option value="' . e($row[$valueKey]) . '"' . $isSelected . '>' . e($row[$labelKey]) . '</option>';
    }
    return $html;
}

function options_list(array $values, $selected = null, string $placeholder = ''): string
{
    $rows = array_map(fn ($v) => ['v' => $v], $values);
    return options($rows, 'v', 'v', $selected, $placeholder);
}

/* ---------- Listes de reference ---------- */

const STATUTS_INTERVENTION = ['Planifiee', 'En cours', 'Terminee', 'Annulee'];
const ETATS_MATERIEL       = ['Disponible', 'En maintenance', 'Hors service'];
const NIVEAUX              = ['Debutant', 'Intermediaire', 'Avance', 'Expert'];

function liste_services(): array
{
    return fetch_all('SELECT id_service, nom_service FROM service ORDER BY nom_service');
}

function liste_fonctions(): array
{
    return fetch_all('SELECT id_fonction, libelle FROM fonction ORDER BY libelle');
}

function liste_employes(): array
{
    return fetch_all("SELECT id_employe, CONCAT(nom, ' ', prenom) AS nom_complet FROM employe ORDER BY nom, prenom");
}

function liste_materiels(): array
{
    return fetch_all('SELECT id_materiel, designation FROM materiel ORDER BY designation');
}

function liste_clients(): array
{
    return fetch_all('SELECT id_client, nom FROM client ORDER BY nom');
}

/* ---------- Icones SVG (traits) ---------- */

function icon(string $name): string
{
    $paths = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'service'      => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1"/>',
        'fonction'     => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/>',
        'employe'      => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-1a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v1"/><path d="M17 4a4 4 0 0 1 0 8"/><path d="M22 21v-1a6 6 0 0 0-4-5.6"/>',
        'materiel'     => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'qualification'=> '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
        'client'       => '<path d="M4 21V5a2 2 0 0 1 2-2h8l6 6v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v6h6"/><path d="M8 13h8M8 17h5"/>',
        'intervention' => '<path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/><circle cx="12" cy="12" r="3"/>',
        'rapport'      => '<path d="M3 3v18h18"/><path d="M7 15v3M11 11v7M15 7v11M19 4v14"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'edit'         => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'trash'        => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
        'eye'          => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'back'         => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'download'     => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'search'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'         => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'home'         => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/* ---------- Composants d'interface ---------- */

/**
 * Bouton de suppression : petit formulaire POST protege par CSRF.
 */
function delete_button(string $action, array $fields, string $confirm, string $label = 'Supprimer'): string
{
    $html = '<form method="post" action="' . e($action) . '" class="inline-form" data-confirm="' . e($confirm) . '">'
        . csrf_field() . '<input type="hidden" name="op" value="delete">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
    }
    return $html . '<button type="submit" class="btn btn-ghost btn-sm btn-danger-text" title="' . e($label) . '">'
        . icon('trash') . '<span>' . e($label) . '</span></button></form>';
}

function empty_row(int $colspan, string $message = 'Aucun enregistrement.'): string
{
    return '<tr><td colspan="' . $colspan . '" class="empty">' . e($message) . '</td></tr>';
}

/**
 * Affiche les erreurs de validation d'un formulaire.
 */
function form_errors(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $html = '<div class="alert alert-error"><strong>Le formulaire contient des erreurs :</strong><ul>';
    foreach ($errors as $error) {
        $html .= '<li>' . e($error) . '</li>';
    }
    return $html . '</ul></div>';
}
