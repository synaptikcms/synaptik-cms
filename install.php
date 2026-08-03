<?php
/**
 * install.php — SynaptikCMS First-Run Installer
 *
 * Place this file at the root of your SynaptikCMS installation.
 * Access it in your browser to configure the CMS for the first time.
 * The installer locks itself permanently after successful setup.
 *
 * ⚠  DELETE this file from your server after installation.
 */

// ─── Built-in translations (never written to locale files) ────────────────────
// These strings exist only inside this file and disappear when it is deleted.

$i18n = [
    'en' => [
        'lang_label'            => 'EN',
        'page_title'            => 'SynaptikCMS — Installation',
        'subtitle'              => 'First-time setup — complete the form below to configure your site.',
        'already_title'         => 'Already Installed',
        'already_desc'          => 'SynaptikCMS is already configured. The installer is locked.',
        'go_admin'              => '→ Go to Admin Panel',
        'sec_requirements'      => 'System Requirements',
        'sec_site'              => 'Site Configuration',
        'sec_admin'             => 'Admin Access',
        'sec_summary'           => 'What the installer will do',
        'lbl_site_title'        => 'Site Title',
        'lbl_language'          => 'Language',
        'lbl_site_desc'         => 'Site Description',
        'lbl_timezone'          => 'Timezone',
        'lbl_contact_email'     => 'Admin Email',
        'lbl_admin_dir'         => 'Admin Folder Name',
        'lbl_username'          => 'Admin Username',
        'lbl_display_name'      => 'Display Name',
        'help_username'         => 'Used to log in. Letters, numbers, hyphens and underscores only (3–32 characters).',
        'help_display_name'     => 'Shown in the admin sidebar. Leave empty to use username.',
        'lbl_password'          => 'Admin Password',
        'lbl_password_confirm'  => 'Confirm Password',
        'optional'              => '(optional)',
        'help_site_desc'        => 'Displayed in meta descriptions and the site header.',
        'help_contact_email'    => 'Used for contact form submissions and admin password reset.',
        'help_admin_dir'        => 'Letters, numbers, hyphens and underscores only. Min. 3 characters. Cannot be: %s.',
        'dir_preview'           => 'URL: yoursite.com/',
        'btn_install'           => 'Install SynaptikCMS →',
        'rule_length'           => '8+ characters',
        'rule_upper'            => '1 uppercase',
        'rule_digit'            => '1 digit',
        'rule_special'          => '1 special character',
        'rule_match'            => 'Passwords match',
        'fix_errors'            => 'Please fix the following errors:',
        'success_title'         => '✅ Installation complete!',
        'success_desc'          => 'Your site has been configured successfully.',
        'success_admin'         => 'Admin panel:',
        'success_redirect'      => 'Redirecting in %ss…',
        'security_note'         => '⚠ Security reminder: delete <code>install.php</code> from your server.',
        'footer_note'           => '⚠ Remember: delete <code>install.php</code> from your server after installation.',
        'summary_password'      => 'Hash your password and write <code>admin-credentials.php</code>',
        'summary_rename'        => 'Rename the admin folder (the new name is saved in settings.json — no file patching needed)',
        'summary_settings'      => 'Write site settings to <code>settings.json</code>',
        'summary_htaccess'      => 'Add deny-all <code>.htaccess</code> to <code>/data/</code>, <code>/bckps/</code>, <code>/private/</code> — and PHP-execution block to <code>/files/</code>',
        'summary_dirs'          => 'Create missing directories (<code>/files/</code>, <code>/bckps/</code>, <code>/private/</code>)',
        'summary_lock'          => 'Lock the installer with <code>install.lock</code>',
        'req_blocked'           => '⚠ One or more requirements are not met. Fix the issues above before installing.',
        'err_invalid_lang'      => 'Please select a valid language.',
        'err_site_title'        => 'Site title is required.',
        'err_admin_dir_short'   => 'Admin folder name must be at least 3 characters long.',
        'err_admin_dir_reserved'=> '"%s" conflicts with a reserved CMS folder name. Choose a different name.',
        'err_timezone'          => 'The selected timezone is not valid.',
        'err_email'             => 'The admin email address is not valid.',
        'err_email_required'    => 'Admin email is required (used for password reset).',
        'err_pw_length'         => 'Password must be at least 8 characters long.',
        'err_pw_upper'          => 'Password must contain at least one uppercase letter.',
        'err_pw_digit'          => 'Password must contain at least one digit (0–9).',
        'err_pw_special'        => 'Password must contain at least one special character (e.g. !@#$%^&*).',
        'err_pw_match'          => 'Passwords do not match.',
        'err_no_admin_folder'   => 'Admin folder could not be located. Check your installation files.',
        'err_folder_exists'     => 'A folder named "%s" already exists. Choose a different name.',
        'err_rename_failed'     => 'Could not rename the admin folder. Check filesystem write permissions.',
        'err_credentials'       => 'Could not write admin-credentials.php. Check folder permissions.',
        'req_php'               => 'PHP version',
        'req_json'              => 'JSON extension',
        'req_password_hash'     => 'password_hash()',
        'req_admin_folder'      => 'Admin folder',
        'req_root_writable'     => 'Root writable',
        'req_admin_writable'    => 'Admin folder writable',
        'req_data_folder'       => '/data/ folder',
        'req_data_missing'      => 'Missing — will be created on first content save',
    ],
    'fr' => [
        'lang_label'            => 'FR',
        'page_title'            => 'SynaptikCMS — Installation',
        'subtitle'              => 'Première configuration — remplissez le formulaire ci-dessous pour configurer votre site.',
        'already_title'         => 'Déjà installé',
        'already_desc'          => 'SynaptikCMS est déjà configuré. L\'installeur est verrouillé.',
        'go_admin'              => '→ Accéder au panneau admin',
        'sec_requirements'      => 'Configuration requise',
        'sec_site'              => 'Configuration du site',
        'sec_admin'             => 'Accès administrateur',
        'sec_summary'           => 'Ce que l\'installeur va faire',
        'lbl_site_title'        => 'Titre du site',
        'lbl_language'          => 'Langue',
        'lbl_site_desc'         => 'Description du site',
        'lbl_timezone'          => 'Fuseau horaire',
        'lbl_contact_email'     => 'E-mail administrateur',
        'lbl_admin_dir'         => 'Nom du dossier admin',
        'lbl_username'          => 'Identifiant admin',
        'lbl_display_name'      => 'Nom affiché',
        'help_username'         => 'Utilisé pour la connexion. Lettres, chiffres, tirets et underscores uniquement (3–32 caractères).',
        'help_display_name'     => 'Affiché dans la sidebar admin. Laisser vide pour utiliser l\u2019identifiant.',
        'lbl_password'          => 'Mot de passe admin',
        'lbl_password_confirm'  => 'Confirmer le mot de passe',
        'optional'              => '(optionnel)',
        'help_site_desc'        => 'Affiché dans les méta descriptions et l\'en-tête du site.',
        'help_contact_email'    => 'Utilisé pour le formulaire de contact et la réinitialisation du mot de passe admin.',
        'help_admin_dir'        => 'Lettres, chiffres, tirets et underscores uniquement. Min. 3 caractères. Interdit : %s.',
        'dir_preview'           => 'URL : votresite.com/',
        'btn_install'           => 'Installer SynaptikCMS →',
        'rule_length'           => '8 caractères min.',
        'rule_upper'            => '1 majuscule',
        'rule_digit'            => '1 chiffre',
        'rule_special'          => '1 caractère spécial',
        'rule_match'            => 'Mots de passe identiques',
        'fix_errors'            => 'Veuillez corriger les erreurs suivantes :',
        'success_title'         => '✅ Installation réussie !',
        'success_desc'          => 'Votre site a été configuré avec succès.',
        'success_admin'         => 'Panneau admin :',
        'success_redirect'      => 'Redirection dans %ss…',
        'security_note'         => '⚠ Rappel de sécurité : supprimez <code>install.php</code> de votre serveur.',
        'footer_note'           => '⚠ N\'oubliez pas : supprimez <code>install.php</code> de votre serveur après l\'installation.',
        'summary_password'      => 'Hacher votre mot de passe et écrire <code>admin-credentials.php</code>',
        'summary_rename'        => 'Renommer le dossier admin (le nouveau nom est sauvegardé dans settings.json — aucun patch de fichiers)',
        'summary_settings'      => 'Écrire les paramètres dans <code>settings.json</code>',
        'summary_htaccess'      => 'Ajouter <code>.htaccess</code> deny-all sur <code>/data/</code>, <code>/bckps/</code>, <code>/private/</code> — et blocage PHP sur <code>/files/</code>',
        'summary_dirs'          => 'Créer les dossiers manquants (<code>/files/</code>, <code>/bckps/</code>, <code>/private/</code>)',
        'summary_lock'          => 'Verrouiller l\'installeur avec <code>install.lock</code>',
        'req_blocked'           => '⚠ Un ou plusieurs prérequis ne sont pas satisfaits. Corrigez les problèmes avant d\'installer.',
        'err_invalid_lang'      => 'Veuillez sélectionner une langue valide.',
        'err_site_title'        => 'Le titre du site est obligatoire.',
        'err_admin_dir_short'   => 'Le nom du dossier admin doit contenir au moins 3 caractères.',
        'err_admin_dir_reserved'=> '"%s" entre en conflit avec un nom de dossier réservé. Choisissez un autre nom.',
        'err_timezone'          => 'Le fuseau horaire sélectionné n\'est pas valide.',
        'err_email'             => 'L\'adresse e-mail admin n\'est pas valide.',
        'err_email_required'    => 'L\'adresse e-mail admin est obligatoire (utilisée pour réinitialiser le mot de passe).',
        'err_pw_length'         => 'Le mot de passe doit contenir au moins 8 caractères.',
        'err_pw_upper'          => 'Le mot de passe doit contenir au moins une lettre majuscule.',
        'err_pw_digit'          => 'Le mot de passe doit contenir au moins un chiffre (0–9).',
        'err_pw_special'        => 'Le mot de passe doit contenir au moins un caractère spécial (ex. : !@#$%^&*).',
        'err_pw_match'          => 'Les mots de passe ne correspondent pas.',
        'err_no_admin_folder'   => 'Le dossier admin est introuvable. Vérifiez vos fichiers d\'installation.',
        'err_folder_exists'     => 'Un dossier nommé "%s" existe déjà. Choisissez un autre nom.',
        'err_rename_failed'     => 'Impossible de renommer le dossier admin. Vérifiez les permissions du système de fichiers.',
        'err_credentials'       => 'Impossible d\'écrire admin-credentials.php. Vérifiez les permissions du dossier.',
        'req_php'               => 'Version PHP',
        'req_json'              => 'Extension JSON',
        'req_password_hash'     => 'password_hash()',
        'req_admin_folder'      => 'Dossier admin',
        'req_root_writable'     => 'Racine accessible en écriture',
        'req_admin_writable'    => 'Dossier admin accessible en écriture',
        'req_data_folder'       => 'Dossier /data/',
        'req_data_missing'      => 'Absent — sera créé lors du premier enregistrement',
    ],
    'es' => [
        'lang_label'            => 'ES',
        'page_title'            => 'SynaptikCMS — Instalación',
        'subtitle'              => 'Configuración inicial — rellene el formulario para configurar su sitio.',
        'already_title'         => 'Ya instalado',
        'already_desc'          => 'SynaptikCMS ya está configurado. El instalador está bloqueado.',
        'go_admin'              => '→ Ir al panel de administración',
        'sec_requirements'      => 'Requisitos del sistema',
        'sec_site'              => 'Configuración del sitio',
        'sec_admin'             => 'Acceso de administrador',
        'sec_summary'           => 'Qué hará el instalador',
        'lbl_site_title'        => 'Título del sitio',
        'lbl_language'          => 'Idioma',
        'lbl_site_desc'         => 'Descripción del sitio',
        'lbl_timezone'          => 'Zona horaria',
        'lbl_contact_email'     => 'Correo del administrador',
        'lbl_admin_dir'         => 'Nombre de la carpeta admin',
        'lbl_username'          => 'Usuario admin',
        'lbl_display_name'      => 'Nombre mostrado',
        'help_username'         => 'Usado para iniciar sesión. Solo letras, números, guiones y guiones bajos (3–32 caracteres).',
        'help_display_name'     => 'Mostrado en la barra lateral. Dejar vacío para usar el nombre de usuario.',
        'lbl_password'          => 'Contraseña de administrador',
        'lbl_password_confirm'  => 'Confirmar contraseña',
        'optional'              => '(opcional)',
        'help_site_desc'        => 'Aparece en las meta descripciones y en el encabezado del sitio.',
        'help_contact_email'    => 'Usado para el formulario de contacto y el restablecimiento de contraseña.',
        'help_admin_dir'        => 'Solo letras, números, guiones y guiones bajos. Mín. 3 caracteres. No puede ser: %s.',
        'dir_preview'           => 'URL: tusitio.com/',
        'btn_install'           => 'Instalar SynaptikCMS →',
        'rule_length'           => '8+ caracteres',
        'rule_upper'            => '1 mayúscula',
        'rule_digit'            => '1 dígito',
        'rule_special'          => '1 carácter especial',
        'rule_match'            => 'Contraseñas coinciden',
        'fix_errors'            => 'Por favor corrija los siguientes errores:',
        'success_title'         => '✅ ¡Instalación completa!',
        'success_desc'          => 'Su sitio ha sido configurado correctamente.',
        'success_admin'         => 'Panel de administración:',
        'success_redirect'      => 'Redirigiendo en %ss…',
        'security_note'         => '⚠ Aviso de seguridad: elimine <code>install.php</code> de su servidor.',
        'footer_note'           => '⚠ Recuerde: elimine <code>install.php</code> de su servidor tras la instalación.',
        'summary_password'      => 'Cifrar su contraseña y escribir <code>admin-credentials.php</code>',
        'summary_rename'        => 'Renombrar la carpeta admin (el nuevo nombre se guarda en settings.json — sin parcheo de archivos)',
        'summary_settings'      => 'Escribir la configuración en <code>settings.json</code>',
        'summary_htaccess'      => 'Añadir <code>.htaccess</code> deny-all en <code>/data/</code>, <code>/bckps/</code>, <code>/private/</code> — y bloqueo PHP en <code>/files/</code>',
        'summary_dirs'          => 'Crear directorios faltantes (<code>/files/</code>, <code>/bckps/</code>, <code>/private/</code>)',
        'summary_lock'          => 'Bloquear el instalador con <code>install.lock</code>',
        'req_blocked'           => '⚠ Uno o más requisitos no se cumplen. Corrija los problemas antes de instalar.',
        'err_invalid_lang'      => 'Seleccione un idioma válido.',
        'err_site_title'        => 'El título del sitio es obligatorio.',
        'err_admin_dir_short'   => 'El nombre de la carpeta admin debe tener al menos 3 caracteres.',
        'err_admin_dir_reserved'=> '"%s" entra en conflicto con un nombre de carpeta reservado. Elija otro nombre.',
        'err_timezone'          => 'La zona horaria seleccionada no es válida.',
        'err_email'             => 'La dirección de correo del administrador no es válida.',
        'err_email_required'    => 'El correo del administrador es obligatorio (se usa para restablecer la contraseña).',
        'err_pw_length'         => 'La contraseña debe tener al menos 8 caracteres.',
        'err_pw_upper'          => 'La contraseña debe contener al menos una letra mayúscula.',
        'err_pw_digit'          => 'La contraseña debe contener al menos un dígito (0–9).',
        'err_pw_special'        => 'La contraseña debe contener al menos un carácter especial (ej. !@#$%^&*).',
        'err_pw_match'          => 'Las contraseñas no coinciden.',
        'err_no_admin_folder'   => 'No se encontró la carpeta admin. Compruebe sus archivos de instalación.',
        'err_folder_exists'     => 'Ya existe una carpeta llamada "%s". Elija otro nombre.',
        'err_rename_failed'     => 'No se pudo renombrar la carpeta admin. Compruebe los permisos del sistema de archivos.',
        'err_credentials'       => 'No se pudo escribir admin-credentials.php. Compruebe los permisos de la carpeta.',
        'req_php'               => 'Versión PHP',
        'req_json'              => 'Extensión JSON',
        'req_password_hash'     => 'password_hash()',
        'req_admin_folder'      => 'Carpeta admin',
        'req_root_writable'     => 'Raíz con escritura',
        'req_admin_writable'    => 'Carpeta admin con escritura',
        'req_data_folder'       => 'Carpeta /data/',
        'req_data_missing'      => 'Ausente — se creará en el primer guardado',
    ],
];

// ─── Language detection ───────────────────────────────────────────────────────

$availableLangs = array_keys($i18n);

/**
 * Resolves the installer UI language.
 * Priority: POST hidden field > GET param > browser Accept-Language > first available.
 */
function installer_detect_lang(array $available): string
{
    $fromRequest = $_GET['lang'] ?? $_POST['lang'] ?? null;
    if ($fromRequest && in_array($fromRequest, $available)) return $fromRequest;

    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (explode(',', $accept) as $part) {
        $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
        if (in_array($code, $available)) return $code;
    }
    return $available[0] ?? 'en';
}

$currentLang = installer_detect_lang($availableLangs);

// Merge with 'en' as fallback so missing keys in partial translations never cause notices.
$t = array_merge($i18n['en'], $i18n[$currentLang] ?? []);

/**
 * Returns a translated installer string.
 * Accepts optional sprintf arguments for strings containing %s / %d placeholders.
 */
function __i(string $key, ...$args): string
{
    global $t;
    $str = $t[$key] ?? $key;
    return $args ? vsprintf($str, $args) : $str;
}

/** Builds a URL pointing to this installer with the given lang code applied. */
function lang_url(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;
    return '?' . http_build_query($params);
}

// ─── Already-installed guard ──────────────────────────────────────────────────

if (file_exists(__DIR__ . '/install.lock')) {
    $adminDir = 'admin';
    if (file_exists(__DIR__ . '/settings.json')) {
        $s = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);
        if (!empty($s['admin_dir'])) $adminDir = $s['admin_dir'];
    }
    http_response_code(403);
    die('<!DOCTYPE html><html lang="' . htmlspecialchars($currentLang) . '"><head><meta charset="UTF-8">
<title>' . __i('page_title') . '</title>
<style>*{box-sizing:border-box}body{font-family:Helvetica,sans-serif;background:#1a2432;color:#b2bac6;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}.box{text-align:center;background:#1e2a3a;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:48px 56px;max-width:420px}h2{color:#fff;margin:0 0 12px}p{margin:0 0 24px;color:#718096}a{color:#4fa75c;text-decoration:none;font-weight:600}a:hover{text-decoration:underline}.lock{font-size:2.5rem;margin-bottom:16px}</style>
</head><body><div class="box"><div class="lock">🔒</div>
<h2>' . __i('already_title') . '</h2>
<p>' . __i('already_desc') . '</p>
<a href="' . htmlspecialchars($adminDir, ENT_QUOTES) . '/auth.php">' . __i('go_admin') . '</a>
</div></body></html>');
}

// ─── Detect admin folder ───────────────────────────────────────────────────────

/**
 * Scans the root for a folder that looks like the CMS admin panel.
 * Checks known names first, then falls back to a full directory scan.
 */
function installer_detect_admin(): ?string
{
    $root = __DIR__;
    foreach (['admin'] as $candidate) {
        if (
            is_dir($root . '/' . $candidate) &&
            file_exists($root . '/' . $candidate . '/auth.php') &&
            file_exists($root . '/' . $candidate . '/index.php')
        ) {
            return $candidate;
        }
    }
    foreach (scandir($root) as $entry) {
        if ($entry === '.' || $entry === '..' || !is_dir($root . '/' . $entry)) continue;
        if (
            file_exists($root . '/' . $entry . '/auth.php') &&
            file_exists($root . '/' . $entry . '/index.php') &&
            file_exists($root . '/' . $entry . '/includes/admin-functions.php')
        ) {
            return $entry;
        }
    }
    return null;
}

// ─── Requirements check ───────────────────────────────────────────────────────

/**
 * Runs pre-installation requirement checks.
 * Returns an array of ['label', 'status' (ok|warning|error), 'detail'] entries.
 */
function installer_check_requirements(string $root, ?string $adminName): array
{
    $checks = [];
    $phpOk  = version_compare(PHP_VERSION, '7.4.0', '>=');

    $checks[] = ['label' => __i('req_php'),           'status' => $phpOk ? 'ok' : 'error',
                 'detail' => PHP_VERSION . ($phpOk ? '' : ' — PHP 7.4+ required')];
    $checks[] = ['label' => __i('req_json'),          'status' => function_exists('json_encode')    ? 'ok' : 'error',
                 'detail' => function_exists('json_encode')    ? 'Available' : 'Missing'];
    $checks[] = ['label' => __i('req_password_hash'), 'status' => function_exists('password_hash') ? 'ok' : 'error',
                 'detail' => function_exists('password_hash') ? 'Available' : 'Missing'];
    $checks[] = ['label' => __i('req_admin_folder'),  'status' => $adminName ? 'ok' : 'error',
                 'detail' => $adminName ? 'Found: /' . $adminName . '/' : 'Not found'];

    $rootW = is_writable($root);
    $checks[] = ['label' => __i('req_root_writable'), 'status' => $rootW ? 'ok' : 'error',
                 'detail' => $rootW ? 'OK' : 'Not writable'];

    if ($adminName && is_dir($root . '/' . $adminName)) {
        $adminW = is_writable($root . '/' . $adminName);
        $checks[] = ['label' => __i('req_admin_writable'), 'status' => $adminW ? 'ok' : 'error',
                     'detail' => $adminW ? 'OK' : 'Not writable'];
    }

    $dataExists = is_dir($root . '/data');
    $checks[] = ['label' => __i('req_data_folder'), 'status' => $dataExists ? 'ok' : 'warning',
                 'detail' => $dataExists ? 'Found' : __i('req_data_missing')];

    return $checks;
}

// ─── Detect available CMS languages ──────────────────────────────────────────

$cmsLanguages = [];
$langDir = __DIR__ . '/lang';
if (is_dir($langDir)) {
    foreach (glob($langDir . '/*.json') as $langFile) {
        $locale = basename($langFile, '.json');
        $raw    = @json_decode(file_get_contents($langFile), true);
        $name   = (is_array($raw) && isset($raw['_meta']['language']))
                ? $raw['_meta']['language']
                : strtoupper($locale);
        $cmsLanguages[$locale] = $name;
    }
}
if (empty($cmsLanguages)) $cmsLanguages = ['en' => 'English'];

// ─── Timezone list ─────────────────────────────────────────────────────────────

$timezones = [
    'UTC'     => ['UTC' => 'UTC'],
    'Europe'  => [
        'Europe/London' => 'London', 'Europe/Lisbon' => 'Lisbon', 'Europe/Paris' => 'Paris',
        'Europe/Brussels' => 'Brussels', 'Europe/Amsterdam' => 'Amsterdam', 'Europe/Berlin' => 'Berlin',
        'Europe/Madrid' => 'Madrid', 'Europe/Rome' => 'Rome', 'Europe/Zurich' => 'Zurich',
        'Europe/Vienna' => 'Vienna', 'Europe/Prague' => 'Prague', 'Europe/Warsaw' => 'Warsaw',
        'Europe/Stockholm' => 'Stockholm', 'Europe/Helsinki' => 'Helsinki',
        'Europe/Athens' => 'Athens', 'Europe/Istanbul' => 'Istanbul', 'Europe/Moscow' => 'Moscow',
    ],
    'America' => [
        'America/New_York' => 'New York (EST)', 'America/Chicago' => 'Chicago (CST)',
        'America/Denver' => 'Denver (MST)', 'America/Los_Angeles' => 'Los Angeles (PST)',
        'America/Toronto' => 'Toronto', 'America/Vancouver' => 'Vancouver',
        'America/Mexico_City' => 'Mexico City', 'America/Bogota' => 'Bogotá',
        'America/Lima' => 'Lima', 'America/Santiago' => 'Santiago',
        'America/Buenos_Aires' => 'Buenos Aires', 'America/Sao_Paulo' => 'São Paulo',
    ],
    'Asia'    => [
        'Asia/Dubai' => 'Dubai', 'Asia/Karachi' => 'Karachi', 'Asia/Kolkata' => 'Mumbai / Kolkata',
        'Asia/Dhaka' => 'Dhaka', 'Asia/Bangkok' => 'Bangkok', 'Asia/Singapore' => 'Singapore',
        'Asia/Hong_Kong' => 'Hong Kong', 'Asia/Shanghai' => 'Shanghai / Beijing',
        'Asia/Seoul' => 'Seoul', 'Asia/Tokyo' => 'Tokyo',
    ],
    'Pacific' => ['Pacific/Auckland' => 'Auckland', 'Pacific/Sydney' => 'Sydney', 'Pacific/Honolulu' => 'Honolulu'],
    'Africa'  => [
        'Africa/Casablanca' => 'Casablanca', 'Africa/Cairo' => 'Cairo',
        'Africa/Lagos' => 'Lagos', 'Africa/Johannesburg' => 'Johannesburg',
    ],
];

// ─── Run checks ───────────────────────────────────────────────────────────────

$currentAdminName  = installer_detect_admin();
$requirementChecks = installer_check_requirements(__DIR__, $currentAdminName);
$requirementsOk    = !array_filter($requirementChecks, fn($c) => $c['status'] === 'error');
$reservedNames     = ['data', 'files', 'lang', 'theme', 'bckps', 'css', 'js', 'install'];

// ─── Process form submission ───────────────────────────────────────────────────

$errors      = [];
$success     = false;
$redirectUrl = '';

$pv = [
    'language' => array_key_first($cmsLanguages) ?? 'en', 'site_title' => '',
    'site_description' => '', 'admin_dir' => 'admin',
    'timezone' => 'Europe/Paris', 'contact_email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $language     = trim($_POST['language']         ?? 'en');
    $siteTitle    = trim($_POST['site_title']       ?? '');
    $siteDesc     = trim($_POST['site_description'] ?? '');
    $adminDir     = strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($_POST['admin_dir'] ?? 'admin')));
    $timezone     = trim($_POST['timezone']         ?? 'UTC');
    $contactEmail = trim($_POST['contact_email']    ?? '');
    $adminUsername = preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($_POST['admin_username'] ?? 'admin'));
    $adminDisplayName = trim($_POST['admin_display_name'] ?? '');
    $password     = $_POST['password']              ?? '';
    $passwordConf = $_POST['password_confirm']      ?? '';

    $pv = ['language' => $language, 'site_title' => $siteTitle, 'site_description' => $siteDesc,
           'admin_dir' => $adminDir, 'timezone' => $timezone, 'contact_email' => $contactEmail,
           'admin_username' => $adminUsername, 'admin_display_name' => $adminDisplayName];

    // ── Validation ────────────────────────────────────────────────────────────

    if (!array_key_exists($language, $cmsLanguages))   $errors[] = __i('err_invalid_lang');
    if ($siteTitle === '')                              $errors[] = __i('err_site_title');
    if (strlen($adminDir) < 3)                          $errors[] = __i('err_admin_dir_short');
    if (in_array($adminDir, $reservedNames))            $errors[] = __i('err_admin_dir_reserved', htmlspecialchars($adminDir));
    if (!in_array($timezone, DateTimeZone::listIdentifiers())) $errors[] = __i('err_timezone');
    if ($contactEmail === '') {
        $errors[] = __i('err_email_required');
    } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __i('err_email');
    }

    // Password — 4 criteria matching change-password.php exactly:
    //   mb_strlen >= 8  |  /[A-Z]/  |  /[0-9]/  |  /[\W_]/ (special chars incl. underscore)
    if (mb_strlen($password) < 8) {
        $errors[] = __i('err_pw_length');
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = __i('err_pw_upper');
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = __i('err_pw_digit');
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = __i('err_pw_special');
    }
    if ($password !== $passwordConf) $errors[] = __i('err_pw_match');

    // ── Execute installation ──────────────────────────────────────────────────

    if (empty($errors)) {
        if (!$currentAdminName) {
            $errors[] = __i('err_no_admin_folder');
        } else {
            $dstAdminPath = __DIR__ . '/' . $adminDir;

            // Step 1 — Rename admin folder if the user picked a different name
            if ($currentAdminName !== $adminDir) {
                if (is_dir($dstAdminPath)) {
                    $errors[] = __i('err_folder_exists', htmlspecialchars($adminDir));
                } elseif (!rename(__DIR__ . '/' . $currentAdminName, $dstAdminPath)) {
                    $errors[] = __i('err_rename_failed');
                }
            }
        }
    }

    if (empty($errors)) {
        $dstAdminPath = __DIR__ . '/' . $adminDir;

        // Step 2 — Write credentials (username, display_name, password, email)
        $hash        = password_hash($password, PASSWORD_BCRYPT);
        $esc         = fn(string $v): string => str_replace("'", "\\'", $v);
        $credContent = "<?php\n// Admin credentials — generated by SynaptikCMS installer\n"
                     . "\$admin_username     = '" . $esc($adminUsername ?: 'admin') . "';\n"
                     . "\$admin_display_name = '" . $esc($adminDisplayName) . "';\n"
                     . "\$admin_password     = '" . $esc($hash) . "';\n"
                     . "\$admin_email        = '" . $esc($contactEmail) . "';\n"
                     . "?>\n";
        if (!file_put_contents($dstAdminPath . '/admin-credentials.php', $credContent)) {
            $errors[] = __i('err_credentials');
        }
    }

    if (empty($errors)) {
        $dstAdminPath = __DIR__ . '/' . $adminDir;

        // Step 3 — Write a complete settings.json with all defaults + user-supplied values.
        // This ensures the file is explicit and complete from day one; nothing relies
        // on runtime-only defaults after first install.
        $settingsFile = __DIR__ . '/settings.json';

        // Scan installed themes from the filesystem (same logic as getAvailableThemes())
        $_themes  = [];
        $_themeDir = __DIR__ . '/theme';
        if (is_dir($_themeDir)) {
            foreach (scandir($_themeDir) as $_t) {
                if ($_t === '.' || $_t === '..' || $_t[0] === '.') continue;
                if (is_dir($_themeDir . '/' . $_t) && file_exists($_themeDir . '/' . $_t . '/css/style.css')) {
                    $_themes[] = $_t;
                }
            }
        }
        if (empty($_themes)) $_themes = ['default'];

        $settings = [
            // Content display
            'articles_per_page'          => 6,
            'projects_per_page'          => 3,
            'show_articles_on_homepage'  => true,
            'show_projects_on_homepage'  => true,
            'show_breadcrumbs'           => false,

            // Menu
            'main_menu'                  => [],
            'use_custom_menu'            => false,
            'show_search_icon'           => false,
            'default_menu_style'         => 'grouped',
            'default_menu_order'         => 'date_desc',

            // Site metadata
            'site_title'                 => $siteTitle,
            'site_description'           => $siteDesc,
            'default_meta_title'         => '{page_title} | {site_title}',
            'default_meta_description'   => '{site_description}',
            'enable_seo'                 => true,
            'show_site_title_in_header'  => true,
            'date_format'                => 'Y-m-d',

            // Homepage
            'homepage_type'              => 'default',
            'homepage_page_id'           => '',

            // Theme & language
            'active_theme'               => 'default',
            'available_themes'           => $_themes,
            'active_language'            => $language,

            // Image optimization
            'image_optimization_enabled' => true,
            'max_width'                  => 1920,
            'max_height'                 => 1080,
            'image_quality'              => 85,
            'create_thumbnails'          => true,
            'thumb_width'                => 350,
            'thumb_height'               => 350,
            'convert_to_webp'            => true,

            // Footer
            'footer_text'                => 'Powered by <a href="https://synaptikcms.com">SynaptikCMS</a> • &copy; {year}',
            'footer_show_login'          => false,
            'footer_show_social'         => false,
            'footer_social_links'        => [],

            // Editor autosave
            'autosave_enabled'           => true,
            'autosave_interval'          => 10,

            // Contact form
            'contact_email'              => $contactEmail,
            'contact_subject'            => 'New message from {name}',
            'contact_success_message'    => '',
            'contact_error_message'      => '',
            'hcaptcha_site_key'          => '',
            'hcaptcha_secret_key'        => '',

            // Custom fields schema
            'custom_fields_schema'       => ['article' => [], 'page' => [], 'project' => []],

            // System
            'admin_dir'                  => $adminDir,
            'timezone'                   => $timezone,

            // Branding
            'site_logo'                  => '',
            'site_favicon'               => '',
        ];

        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Step 4 — .htaccess on sensitive directories + ensure required dirs exist
        //
        // Deny-all block: dual-syntax covers Apache 2.2 (Deny from all)
        // and Apache 2.4 (Require all denied).
        $denyAll = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                 . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";

        // Deny-all protection on data, bckps, private.
        // Always (re)write the file so an upgrade or partial install gets the
        // correct content even if the directory already existed.
        foreach (['data', 'bckps', 'private'] as $d) {
            $dp = __DIR__ . '/' . $d;
            if (!is_dir($dp)) @mkdir($dp, 0755, true);
            if (is_dir($dp)) file_put_contents($dp . '/.htaccess', $denyAll);
        }

        // Create /files/ and block PHP execution inside it.
        // Media files (images, documents) continue to be served normally.
        $filesDir = __DIR__ . '/files';
        if (!is_dir($filesDir)) @mkdir($filesDir, 0755, true);
        $filesHtaccess = $filesDir . '/.htaccess';
        if (!file_exists($filesHtaccess)) {
            $filesHta = "# Block PHP execution inside /files/ — media files are served normally.\n"
                      . "<FilesMatch \\.php[s5]?\$>\n"
                      . "    <IfModule mod_authz_core.c>\n"
                      . "        Require all denied\n"
                      . "    </IfModule>\n"
                      . "    <IfModule !mod_authz_core.c>\n"
                      . "        Order allow,deny\n"
                      . "        Deny from all\n"
                      . "    </IfModule>\n"
                      . "</FilesMatch>\n";
            file_put_contents($filesHtaccess, $filesHta);
        }

        // Step 5 — Lock the installer
        file_put_contents(__DIR__ . '/install.lock', json_encode(
            ['installed_at' => date('Y-m-d H:i:s'), 'admin_dir' => $adminDir],
            JSON_PRETTY_PRINT
        ));

        // Step 6 — Self-delete. install.lock is the real safeguard (checked above
        // on every request), but removing this file closes the window entirely —
        // no lock file to lose, no way to re-run the installer even if install.lock
        // is ever deleted by accident. Best-effort: silently ignored if the
        // filesystem permissions don't allow it (installer stays lock-protected).
        @unlink(__FILE__);

        $success     = true;
        $redirectUrl = $adminDir . '/auth.php';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __i('page_title') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --primary: #4fa75c; --primary-dark: #398643; --primary-light: #e2eee2;
    --secondary: #1e2a3a; --sec-dark: #162535;
    --secondary-light: #2c3b4c;
    --text-light: #b2bac6; --text-muted: #718096;
    --danger: #e74c3c; --danger-light: #ffebee;
    --bg-light: #e8eef2;
    --bg-dark: #1a2432; --border: rgba(255,255,255,.1);
    --radius: 8px; --radius-sm: 4px;
    --font: Helvetica, sans-serif; --font-head: "Saira Condensed", sans-serif;
}
body { font-family: var(--font); background: var(--bg-dark); color: var(--text-light); line-height: 1.6; min-height: 100vh; padding: 40px 16px 80px; }
.installer-wrap { max-width: 760px; margin: 0 auto; }
.installer-header { text-align: center; margin-bottom: 40px; }
.logo { font-family: var(--font-head); font-size: 2.2rem; font-weight: 700; color: #fff; }
.logo span { color: var(--primary); }
.installer-header > p { color: var(--text-muted); margin-top: 6px; font-size: .95rem; }
.lang-switcher { display: flex; justify-content: center; gap: 8px; margin-top: 14px; }
.lang-btn { font-family: var(--font-head); font-size: .85rem; font-weight: 700; padding: 4px 14px; border-radius: 20px; border: 1px solid var(--border); background: var(--sec-dark); color: var(--text-muted); text-decoration: none; transition: all .15s; }
.lang-btn:hover { border-color: var(--primary); color: var(--primary); }
.lang-btn.active { background: rgba(79,167,92,.15); border-color: var(--primary); color: var(--primary); }
.card { background: var(--secondary-light); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px 32px; margin-bottom: 24px; }
.card-title { font-family: var(--font-head); font-size: 1.25rem; font-weight: 700; color: #fff; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.req-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.req-item { display: flex; align-items: center; gap: 10px; background: var(--sec-dark); border-radius: var(--radius-sm); padding: 10px 14px; }
.req-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.req-dot.ok { background: var(--primary); } .req-dot.warning { background: #f0a500; } .req-dot.error { background: var(--danger); }
.req-label { color: #fff; font-weight: 600; font-size: .875rem; } .req-detail { color: var(--text-muted); font-size: .8rem; }
.req-blocked { background: rgba(231,76,60,.12); border: 1px solid rgba(231,76,60,.3); border-radius: var(--radius-sm); padding: 14px 18px; color: #f5b7b1; font-size: .9rem; margin-top: 16px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-group { margin-bottom: 18px; } .form-group:last-child { margin-bottom: 0; }
label { display: block; font-size: .875rem; font-weight: 600; color: #fff; margin-bottom: 6px; }
label .opt { font-weight: 400; color: var(--text-muted); font-size: .8rem; }
input[type="text"], input[type="email"], input[type="password"], select { width: 100%; background: var(--sec-dark); border: 1px solid rgba(255,255,255,.15); border-radius: var(--radius-sm); color: #fff; padding: 9px 12px; font-size: .9rem; font-family: var(--font); outline: none; transition: border-color .2s; }
input:focus, select:focus { border-color: var(--primary); }
select option { background: var(--secondary); }
.help-text { margin-top: 5px; font-size: .8rem; color: var(--text-muted); }
.dir-preview { margin-top: 6px; font-size: .82rem; color: var(--text-muted); font-family: monospace; }
.dir-preview span { color: var(--primary); }
.pw-rules { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
.pw-rule { font-size: .78rem; padding: 2px 8px; border-radius: 20px; background: var(--sec-dark); color: var(--text-muted); border: 1px solid rgba(255,255,255,.08); transition: all .2s; }
.pw-rule.valid { background: rgba(79,167,92,.15); color: var(--primary); border-color: rgba(79,167,92,.4); }
.msg-error { background: var(--danger-light); border-left: 4px solid var(--danger); color: #c0392b; border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 24px; font-size: .9rem; }
.msg-error ul { padding-left: 18px; margin: 6px 0 0; } .msg-error li { margin-bottom: 2px; }
.msg-success { background: var(--primary-light); border-left: 4px solid var(--primary); color: #1b5e20; border-radius: var(--radius-sm); padding: 24px 28px; text-align: center; }
.msg-success h3 { font-size: 1.3rem; margin-bottom: 8px; } .msg-success p { margin-bottom: 4px; }
.msg-success a { color: var(--primary-dark); font-weight: 700; }
.btn-install { display: block; width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius); font-family: var(--font-head); font-size: 1.15rem; font-weight: 700; letter-spacing: .5px; cursor: pointer; transition: background .2s; text-transform: uppercase; }
.btn-install:hover { background: var(--primary-dark); }
.btn-install:disabled { background: var(--text-muted); cursor: not-allowed; }
.installer-footer { text-align: center; color: var(--text-muted); font-size: .82rem; margin-top: 24px; }
.installer-footer strong { color: #f0a500; }
@media (max-width: 600px) { .form-row, .req-grid { grid-template-columns: 1fr; } .card { padding: 20px 18px; } }
</style>
</head>
<body>
<div class="installer-wrap">

    <div class="installer-header">
        <div class="logo">Synaptik<span>CMS</span></div>
        <p><?= __i('subtitle') ?></p>
        <?php if (count($availableLangs) > 1): ?>
        <nav class="lang-switcher" aria-label="Installer language">
            <?php foreach ($availableLangs as $lc): ?>
            <?php if (!isset($i18n[$lc])) continue; ?>
            <a href="<?= htmlspecialchars(lang_url($lc)) ?>"
               class="lang-btn <?= $lc === $currentLang ? 'active' : '' ?>">
                <?= htmlspecialchars($i18n[$lc]['lang_label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>

    <div class="card">
        <div class="msg-success">
            <h3><?= __i('success_title') ?></h3>
            <p><?= __i('success_desc') ?></p>
            <p><?= __i('success_admin') ?> <strong><a href="<?= htmlspecialchars($redirectUrl) ?>">/<?= htmlspecialchars($redirectUrl) ?></a></strong></p>
            <p style="margin-top:12px;font-size:.85rem;color:#388e3c;"><?= sprintf(__i('success_redirect'), '<span id="cd">5</span>') ?></p>
        </div>
    </div>
    <div class="installer-footer"><strong><?= __i('security_note') ?></strong></div>
    <script>
        var n = 5, el = document.getElementById('cd');
        setInterval(function() { n--; if (el) el.textContent = n; if (n <= 0) window.location.href = '<?= htmlspecialchars($redirectUrl) ?>'; }, 1000);
    </script>

    <?php else: ?>

    <div class="card">
        <div class="card-title"><span>🔎</span> <?= __i('sec_requirements') ?></div>
        <div class="req-grid">
            <?php foreach ($requirementChecks as $chk): ?>
            <div class="req-item">
                <div class="req-dot <?= $chk['status'] ?>"></div>
                <div>
                    <div class="req-label"><?= htmlspecialchars($chk['label']) ?></div>
                    <div class="req-detail"><?= htmlspecialchars($chk['detail']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$requirementsOk): ?><div class="req-blocked"><?= __i('req_blocked') ?></div><?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="msg-error">
        <strong><?= __i('fix_errors') ?></strong>
        <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars(lang_url($currentLang)) ?>">
        <input type="hidden" name="lang" value="<?= htmlspecialchars($currentLang) ?>">

        <div class="card">
            <div class="card-title"><span>🌐</span> <?= __i('sec_site') ?></div>
            <div class="form-row">
                <div class="form-group">
                    <label for="site_title"><?= __i('lbl_site_title') ?></label>
                    <input type="text" id="site_title" name="site_title"
                           value="<?= htmlspecialchars($pv['site_title']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="language"><?= __i('lbl_language') ?></label>
                    <select id="language" name="language" required>
                        <?php foreach ($cmsLanguages as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= ($pv['language'] === $code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="site_description"><?= __i('lbl_site_desc') ?> <span class="opt"><?= __i('optional') ?></span></label>
                <input type="text" id="site_description" name="site_description"
                       value="<?= htmlspecialchars($pv['site_description']) ?>"
                       placeholder="A short description of your site — shown in search results and the site header.">
                <p class="help-text"><?= __i('help_site_desc') ?></p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="timezone"><?= __i('lbl_timezone') ?></label>
                    <select id="timezone" name="timezone" required>
                        <?php foreach ($timezones as $grp => $zones): ?>
                        <?php if ($grp === 'UTC'): foreach ($zones as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($pv['timezone'] === $v) ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; else: ?>
                        <optgroup label="<?= $grp ?>">
                            <?php foreach ($zones as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($pv['timezone'] === $v) ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="contact_email"><?= __i('lbl_contact_email') ?></label>
                    <input type="email" id="contact_email" name="contact_email"
                           value="<?= htmlspecialchars($pv['contact_email']) ?>" placeholder="you@example.com" required>
                    <p class="help-text"><?= __i('help_contact_email') ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span>🔐</span> <?= __i('sec_admin') ?></div>
            <div class="form-group">
                <label for="admin_dir"><?= __i('lbl_admin_dir') ?></label>
                <input type="text" id="admin_dir" name="admin_dir"
                       value="<?= htmlspecialchars($pv['admin_dir']) ?>"
                       required pattern="[a-zA-Z0-9\-_]{3,}" autocomplete="off" spellcheck="false">
                <div class="dir-preview"><?= __i('dir_preview') ?><span id="dir-val"><?= htmlspecialchars($pv['admin_dir']) ?></span>/</div>
                <p class="help-text"><?= __i('help_admin_dir', implode(', ', $reservedNames)) ?></p>
                <span id="dir-reserved-error" style="display:none;margin-top:5px;font-size:.82rem;color:var(--danger);font-weight:600;"></span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_username"><?= __i('lbl_username') ?></label>
                    <input type="text" id="admin_username" name="admin_username"
                           value="<?= htmlspecialchars($pv['admin_username'] ?? 'admin') ?>"
                           required pattern="[a-zA-Z0-9_\-]{3,32}" autocomplete="username" spellcheck="false">
                    <p class="help-text"><?= __i('help_username') ?></p>
                </div>
                <div class="form-group">
                    <label for="admin_display_name"><?= __i('lbl_display_name') ?> <span class="opt"><?= __i('optional') ?></span></label>
                    <input type="text" id="admin_display_name" name="admin_display_name"
                           value="<?= htmlspecialchars($pv['admin_display_name'] ?? '') ?>">
                    <p class="help-text"><?= __i('help_display_name') ?></p>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password"><?= __i('lbl_password') ?></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <div class="pw-rules">
                        <span class="pw-rule" id="r-len"><?= __i('rule_length') ?></span>
                        <span class="pw-rule" id="r-up"><?= __i('rule_upper') ?></span>
                        <span class="pw-rule" id="r-dig"><?= __i('rule_digit') ?></span>
                        <span class="pw-rule" id="r-spc"><?= __i('rule_special') ?></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm"><?= __i('lbl_password_confirm') ?></label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                    <div class="pw-rules">
                        <span class="pw-rule" id="r-match"><?= __i('rule_match') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="border-color:rgba(79,167,92,.25);">
            <div class="card-title"><span>📋</span> <?= __i('sec_summary') ?></div>
            <ul style="list-style:none;display:flex;flex-direction:column;gap:8px;font-size:.875rem;color:var(--text-muted);">
                <li>✅ <?= __i('summary_password') ?></li>
                <li>✅ <?= __i('summary_rename') ?></li>
                <li>✅ <?= __i('summary_settings') ?></li>
                <li>✅ <?= __i('summary_htaccess') ?></li>
                <li>✅ <?= __i('summary_dirs') ?></li>
                <li>✅ <?= __i('summary_lock') ?></li>
            </ul>
        </div>

        <button type="submit" class="btn-install" <?= $requirementsOk ? '' : 'disabled' ?>>
            <?= __i('btn_install') ?>
        </button>
    </form>

    <div class="installer-footer"><strong><?= __i('footer_note') ?></strong></div>

    <?php endif; ?>
</div>

<script>
(function () {
    // ── Admin folder name live preview + reserved-name guard ─────────────────
    var dirInput   = document.getElementById('admin_dir');
    var dirVal     = document.getElementById('dir-val');
    var dirErr     = document.getElementById('dir-reserved-error');
    var installBtn = document.querySelector('.btn-install');
    var reserved   = <?= json_encode($reservedNames) ?>;

    function checkAdminDir() {
        if (!dirInput) return;
        var raw = dirInput.value.toLowerCase().replace(/[^a-z0-9\-_]/g, '');
        if (dirVal) dirVal.textContent = raw || '\u2026';
        var isReserved = reserved.indexOf(raw) !== -1;
        if (dirErr) {
            if (isReserved) {
                dirErr.textContent = '\u26A0 \u201C' + raw + '\u201D is a reserved folder name. Choose another.';
                dirErr.style.display = 'block';
            } else {
                dirErr.style.display = 'none';
            }
        }
        dirInput.style.borderColor = isReserved ? 'var(--danger)' : '';
        if (installBtn) installBtn.disabled = isReserved || <?= $requirementsOk ? 'false' : 'true' ?>;
    }

    if (dirInput) {
        dirInput.addEventListener('input', checkAdminDir);
        checkAdminDir();
    }

    // ── Password strength badges ──────────────────────────────────────────────
    // Mirrors the 4 criteria in change-password.php:
    //   mb_strlen >= 8  |  /[A-Z]/  |  /[0-9]/  |  /[\W_]/
    var pw  = document.getElementById('password');
    var pwc = document.getElementById('password_confirm');

    /** Toggles the 'valid' CSS class on a rule badge element. */
    function setRule(id, valid) {
        var el = document.getElementById(id);
        if (el) el.classList.toggle('valid', valid);
    }

    function checkPassword() {
        var v = pw  ? pw.value  : '';
        var c = pwc ? pwc.value : '';
        setRule('r-len',   v.length >= 8);
        setRule('r-up',    /[A-Z]/.test(v));
        setRule('r-dig',   /[0-9]/.test(v));
        setRule('r-spc',   /[\W_]/.test(v));
        setRule('r-match', v.length > 0 && v === c);
    }

    if (pw)  pw.addEventListener('input',  checkPassword);
    if (pwc) pwc.addEventListener('input', checkPassword);
    checkPassword();
})();
</script>
</body>
</html>