<?php
// Security check
if (!defined('INCLUDED')) {
	header('HTTP/1.1 403 Forbidden');
	exit('Direct access to this file is not allowed');
}

/**
 * Translation Editor
 *
 * Lists every i18n string for a chosen scope (front / admin) and locale,
 * lets the user edit the values inline, and provides a "New locale"
 * action that duplicates en.json under a chosen language code.
 *
 * Read/write operations call admin/translations-api.php via fetch().
 *
 * URL: index.php?action=translations[&scope=front|admin][&locale=xx]
 */

$appSettings = isset($appSettings) ? $appSettings : admin_load_config();

$scope  = ($_GET['scope']  ?? 'front') === 'admin' ? 'admin' : 'front';
$locale = preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $_GET['locale'] ?? '')
	? $_GET['locale']
	: ($appSettings['active_language'] ?? 'en');

// Available locale files for the active scope. We bypass lang_available()
// because it depends on LANG_CONTEXT (admin here), but we need either scope.
$_root = dirname(dirname(__DIR__));
$_scopeDir = $scope === 'admin' ? $_root . '/lang/admin/' : $_root . '/lang/front/';
$availableLocales = [];
if (is_dir($_scopeDir)) {
	foreach (glob($_scopeDir . '*.json') as $_f) {
		$_code = basename($_f, '.json');
		$_meta = json_decode(file_get_contents($_f), true);
		$availableLocales[$_code] = $_meta['_meta']['language'] ?? strtoupper($_code);
	}
	ksort($availableLocales);
}
?>

<div class="content-header">
	<h1><?php echo admin_icon('globe'); ?> <?php _e('translations_subtitle'); ?></h1>
	<p class="help-text" style="margin-top:-10px;margin-bottom:20px;"><?php _e('translations_intro'); ?></p>
</div>

<!-- ══════════════════ Import a locale (ZIP) ══════════════════ -->
<div class="site-settings-section" style="margin-bottom: 30px;">
	<h3><?php echo admin_icon('package'); ?> <?php _e('translations_import_title'); ?></h3>
	<div class="form-group">
		<p class="help-text"><?php _e('translations_import_help'); ?></p>
		<?php if (!class_exists('ZipArchive')): ?>
			<p style="color:var(--danger-text);"><?php _e('theme_ziparchive_missing'); ?></p>
		<?php else: ?>
			<form method="POST" action="extension-upload.php" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
				<input type="hidden" name="csrf_token" value="<?php echo hsc($_SESSION['csrf_token'] ?? ''); ?>">
				<input type="hidden" name="_type" value="locale">
				<div class="form-group" style="margin-bottom:0;">
					<label for="locale_label"><?php _e('translations_new_label'); ?></label>
					<input type="text" id="locale_label" name="locale_label" class="form-control"
						placeholder="<?php echo hsc(__t('translations_new_label_ph')); ?>" maxlength="50" required style="min-width:220px;">
				</div>
				<input type="file" name="locale_zip" accept=".zip" required style="flex:1; max-width:400px;">
				<button type="submit" class="btn btn-outline"><?php echo admin_icon('upload'); ?> <?php _e('translations_import_btn'); ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>

<!-- ══════════════════ Scope + locale selectors ══════════════════ -->
<div class="site-settings-section">
	<div class="trl-toolbar">
		<div class="trl-toolbar-group">
			<label for="trl-scope"><?php _e('translations_scope'); ?></label>
			<select id="trl-scope" class="form-control">
				<option value="front"<?php echo $scope === 'front' ? ' selected' : ''; ?>><?php _e('translations_scope_front'); ?></option>
				<option value="admin"<?php echo $scope === 'admin' ? ' selected' : ''; ?>><?php _e('translations_scope_admin'); ?></option>
			</select>
		</div>

		<div class="trl-toolbar-group">
			<label for="trl-locale"><?php _e('translations_locale'); ?></label>
			<select id="trl-locale" class="form-control">
				<?php foreach ($availableLocales as $_code => $_label): ?>
				<option value="<?php echo hsc($_code); ?>"<?php echo $_code === $locale ? ' selected' : ''; ?>>
					<?php echo hsc($_label); ?> (<?php echo hsc($_code); ?>)
				</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="trl-toolbar-group trl-toolbar-group--actions">
			<button type="button" id="trl-new-locale-btn" class="btn btn-outline">
				<?php echo admin_icon('plus'); ?> <?php _e('translations_new_locale'); ?>
			</button>
		</div>
	</div>
</div>

<!-- ══════════════════ Filter + stats bar ══════════════════ -->
<div class="trl-statusbar">
	<input type="search" id="trl-filter" class="form-control" placeholder="<?php echo hsc(__t('translations_filter_placeholder')); ?>" autocomplete="off">
	<div class="trl-stats">
		<span class="trl-stat trl-stat--total">
			<strong id="trl-stat-total">0</strong> <?php _e('translations_stat_total'); ?>
		</span>
		<span class="trl-stat trl-stat--missing">
			<strong id="trl-stat-missing">0</strong> <?php _e('translations_stat_missing'); ?>
		</span>
		<span class="trl-stat trl-stat--dirty" id="trl-stat-dirty-wrap" hidden>
			<strong id="trl-stat-dirty">0</strong> <?php _e('translations_stat_dirty'); ?>
		</span>
	</div>
</div>

<!-- ══════════════════ Editable table ══════════════════ -->
<div class="table-wrap trl-table-wrap">
	<table class="trl-table">
		<thead>
			<tr>
				<th class="trl-col-key"><?php _e('translations_col_key'); ?></th>
				<th class="trl-col-ref"><?php _e('translations_col_reference'); ?></th>
				<th class="trl-col-val"><?php _e('translations_col_value'); ?></th>
			</tr>
		</thead>
		<tbody id="trl-tbody">
			<tr class="trl-row-empty">
				<td colspan="3" class="trl-empty-state"><?php _e('translations_loading'); ?></td>
			</tr>
		</tbody>
	</table>
</div>

<!-- ══════════════════ Sticky save bar ══════════════════ -->
<div class="trl-savebar" id="trl-savebar">
	<div class="trl-savebar-info">
		<span id="trl-savebar-msg"><?php _e('translations_no_changes'); ?></span>
	</div>
	<div class="trl-savebar-actions">
		<button type="button" id="trl-discard-btn" class="btn btn-ghost" disabled>
			<?php _e('translations_discard'); ?>
		</button>
		<button type="button" id="trl-save-btn" class="btn btn-primary" disabled>
			<?php echo admin_icon('save'); ?> <?php _e('translations_save'); ?>
		</button>
	</div>
</div>

<!-- ══════════════════ "New locale" modal ══════════════════ -->
<div id="trl-newlocale-modal" class="trl-modal" hidden>
	<div class="trl-modal-dialog">
		<div class="trl-modal-header">
			<h3><?php echo admin_icon('plus'); ?> <?php _e('translations_new_locale_title'); ?></h3>
			<button type="button" class="trl-modal-close" id="trl-newlocale-cancel">&#x2715;</button>
		</div>
		<div class="trl-modal-body">
			<p class="help-text"><?php _e('translations_new_locale_help'); ?></p>

			<div class="form-group">
				<label for="trl-new-code"><?php _e('translations_new_code'); ?></label>
				<input type="text" id="trl-new-code" class="form-control"
					placeholder="ko, ja, pt_BR…" maxlength="5" pattern="[a-z]{2}(_[A-Z]{2})?" autocomplete="off">
				<p class="help-text"><?php _e('translations_new_code_help'); ?></p>
			</div>

			<div class="form-group">
				<label for="trl-new-label"><?php _e('translations_new_label'); ?></label>
				<input type="text" id="trl-new-label" class="form-control"
					placeholder="<?php echo hsc(__t('translations_new_label_ph')); ?>" maxlength="50" autocomplete="off">
				<p class="help-text"><?php _e('translations_new_label_help'); ?></p>
			</div>

			<div class="form-group">
				<label class="checkbox-label">
					<input type="checkbox" id="trl-new-both-scopes" checked>
					<?php _e('translations_new_both_scopes'); ?>
				</label>
				<p class="help-text"><?php _e('translations_new_both_scopes_help'); ?></p>
			</div>
		</div>
		<div class="trl-modal-footer">
			<button type="button" class="btn btn-outline" id="trl-newlocale-cancel-2">
				<?php _e('cancel'); ?>
			</button>
			<button type="button" class="btn btn-primary" id="trl-newlocale-create">
				<?php _e('translations_new_create'); ?>
			</button>
		</div>
	</div>
</div>

<style>
/* =============================================================
 * Translation Editor — scoped styles (.trl-*)
 * Reuses admin design tokens: --surface --border --primary etc.
 * ============================================================= */
.trl-toolbar { display: flex; flex-wrap: wrap; gap: 20px 24px; align-items: flex-end; }
.trl-toolbar-group { display: flex; flex-direction: column; gap: 4px; min-width: 25%; }
.trl-toolbar-group label { margin: 0; font-weight: 500; font-size: .85em; color: var(--text-muted); }
.trl-toolbar-group select { width: 100%; }
.trl-toolbar-group--actions { margin-left: auto; align-self: flex-end; flex-direction: row; }

.trl-statusbar {
	display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
	padding: 12px 16px; margin-bottom: 14px; margin-top: 14px;
	background: var(--surface); border: 1px solid var(--border);
	border-radius: var(--radius-md);
}
.trl-statusbar #trl-filter { flex: 1; min-width: 220px; max-width: 420px; }
.trl-stats { display: flex; gap: 18px; flex-wrap: wrap; font-size: .85em; }
.trl-stat { color: var(--text-muted); }
.trl-stat strong { color: var(--text); font-weight: 700; }
.trl-stat--missing strong { color: var(--warning-text); }
.trl-stat--dirty strong { color: var(--primary-text); }

.trl-table-wrap { margin-bottom: 100px; }
.trl-table { table-layout: fixed; }
.trl-table .trl-col-key { width: 24%; }
.trl-table .trl-col-ref { width: 32%; }
.trl-table .trl-col-val { width: 44%; }
.trl-table td { vertical-align: top; }
.trl-key {
	font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
	font-size: .82em; color: var(--text-muted);
	word-break: break-all;
}
.trl-ref {
	color: var(--text-faint); font-size: .9em;
	white-space: pre-wrap; word-break: break-word;
}
.trl-input {
	width: 100%; min-height: 38px;
	padding: 7px 10px; resize: vertical;
	font-family: inherit; font-size: .92em; line-height: 1.45;
	background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm);
	transition: border-color var(--transition), box-shadow var(--transition);
}
.trl-input:focus { outline: none; border-color: var(--primary); box-shadow: var(--focus-ring); }
.trl-row--missing .trl-input { border-color: var(--warning); background: var(--warning-soft); }
.trl-row--dirty .trl-input { border-color: var(--primary); background: var(--primary-soft); }
.trl-row--placeholder-warn .trl-input { border-color: var(--danger); }
.trl-placeholder-warn {
	display: block; margin-top: 4px;
	font-size: .75em; color: var(--danger-text);
}
.trl-row-hidden { display: none; }
.trl-empty-state {
	padding: 40px 16px; text-align: center; color: var(--text-faint);
}

/* ── Sticky save bar ────────────────────────────────────────── */
.trl-savebar {
	position: fixed; bottom: 0; left: var(--sidebar-width-expanded); right: 0; z-index: 80;
	display: flex; align-items: center; justify-content: space-between; gap: 16px;
	padding: 12px 32px;
	background: color-mix(in srgb, var(--surface) 92%, transparent);
	backdrop-filter: blur(8px);
	border-top: 1px solid var(--border);
	box-shadow: 0 -4px 14px rgba(16, 24, 40, .08);
	transition: left var(--sidebar-transition, .2s ease);
}
.trl-savebar-info { color: var(--text-muted); font-size: .9em; }
.trl-savebar-actions { display: flex; gap: 8px; }
/* Sidebar state: JS toggles .sidebar-collapsed on <body> (sometimes on <html>) */
body.sidebar-collapsed .trl-savebar,
.sidebar-collapsed .trl-savebar { left: var(--sidebar-width-collapsed); }

/* ── Modal ──────────────────────────────────────────────────── */
.trl-modal {
	position: fixed; inset: 0; z-index: 9998;
	display: flex; align-items: center; justify-content: center;
	background: var(--overlay);
	animation: fadeIn .15s ease;
}
.trl-modal[hidden] { display: none; }
.trl-modal-dialog {
	width: 480px; max-width: 92vw;
	background: var(--surface); border: 1px solid var(--border);
	border-radius: var(--radius-md); box-shadow: var(--shadow-lg);
	display: flex; flex-direction: column;
}
.trl-modal-header {
	display: flex; justify-content: space-between; align-items: center;
	padding: 14px 20px; border-bottom: 1px solid var(--border);
}
.trl-modal-header h3 { margin: 0; font-size: 1.1em; display: inline-flex; align-items: center; gap: 8px; }
.trl-modal-close {
	background: none; border: none; font-size: 20px;
	cursor: pointer; color: var(--text-muted); line-height: 1;
}
.trl-modal-close:hover { color: var(--text); }
.trl-modal-body { padding: 18px 20px; }
.trl-modal-footer {
	display: flex; justify-content: flex-end; gap: 8px;
	padding: 12px 20px; border-top: 1px solid var(--border);
	background: var(--surface-2);
	border-bottom-left-radius: var(--radius-md);
	border-bottom-right-radius: var(--radius-md);
}

@media (max-width: 768px) {
	.trl-savebar { left: 0; padding: 10px 14px; flex-wrap: wrap; }
	.trl-toolbar-group--actions { margin-left: 0; width: 100%; }
	.trl-table .trl-col-key,
	.trl-table .trl-col-ref,
	.trl-table .trl-col-val { width: auto; }
}
</style>

<script type="application/json" id="translations-data"><?php echo json_encode([
	'i18n' => [
		'noChanges'       => __t('translations_no_changes'),
		'dirtyMsg'        => __t('translations_unsaved_changes'),
		'confirmDiscard'  => __t('translations_confirm_discard'),
		'confirmLeave'    => __t('translations_confirm_leave'),
		'placeholderWarn' => __t('translations_placeholder_warning'),
		'invalidCode'     => __t('translations_invalid_code'),
		'labelRequired'   => __t('translations_label_required'),
		'empty'           => __t('translations_empty'),
		'networkError'    => __t('translations_network_error'),
		'saveSuccess'     => __t('translations_save_success'),
		'createSuccess'   => __t('translations_create_success'),
		'alreadyExists'   => __t('translations_already_exists'),
	],
	'scope'  => $scope,
	'locale' => $locale,
], JSON_HEX_TAG); ?></script>
<script src="assets/js/translations.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/translations.js'); ?>"></script>
