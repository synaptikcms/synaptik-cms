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
 * Read/write operations call admin/translations.php via fetch().
 * Backend is not yet implemented — UI is wired with stub data so the
 * layout can be validated before plugging the handlers in.
 *
 * URL: index.php?action=translations[&scope=front|admin][&locale=xx]
 */

$appSettings = isset($appSettings) ? $appSettings : admin_load_settings();

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
				<option value="<?php echo htmlspecialchars($_code); ?>"<?php echo $_code === $locale ? ' selected' : ''; ?>>
					<?php echo htmlspecialchars($_label); ?> (<?php echo htmlspecialchars($_code); ?>)
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
	<input type="search" id="trl-filter" class="form-control" placeholder="<?php echo htmlspecialchars(__t('translations_filter_placeholder')); ?>" autocomplete="off">
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
					placeholder="<?php echo htmlspecialchars(__t('translations_new_label_ph')); ?>" maxlength="50" autocomplete="off">
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

<script>
/* eslint-disable */
/**
 * Translation Editor — UI mockup wiring.
 * Backend (fetch to translations.php) not implemented yet — table is
 * seeded with a stub payload so the layout can be validated.
 */
(function () {
	'use strict';

	var i18n = {
		noChanges:       <?php echo json_encode(__t('translations_no_changes')); ?>,
		dirtyMsg:        <?php echo json_encode(__t('translations_unsaved_changes')); ?>,
		confirmDiscard:  <?php echo json_encode(__t('translations_confirm_discard')); ?>,
		confirmLeave:    <?php echo json_encode(__t('translations_confirm_leave')); ?>,
		placeholderWarn: <?php echo json_encode(__t('translations_placeholder_warning')); ?>,
		invalidCode:     <?php echo json_encode(__t('translations_invalid_code')); ?>,
		labelRequired:   <?php echo json_encode(__t('translations_label_required')); ?>,
		empty:           <?php echo json_encode(__t('translations_empty')); ?>,
		networkError:    <?php echo json_encode(__t('translations_network_error')); ?>,
		saveSuccess:     <?php echo json_encode(__t('translations_save_success')); ?>,
		createSuccess:   <?php echo json_encode(__t('translations_create_success')); ?>,
		alreadyExists:   <?php echo json_encode(__t('translations_already_exists')); ?>,
	};

	var CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;

	var state = {
		scope:     <?php echo json_encode($scope); ?>,
		locale:    <?php echo json_encode($locale); ?>,
		reference: {},
		current:   {},
		dirty:     {},
	};

	var $tbody     = document.getElementById('trl-tbody');
	var $filter    = document.getElementById('trl-filter');
	var $scope     = document.getElementById('trl-scope');
	var $locale    = document.getElementById('trl-locale');
	var $saveBtn   = document.getElementById('trl-save-btn');
	var $discBtn   = document.getElementById('trl-discard-btn');
	var $saveMsg   = document.getElementById('trl-savebar-msg');
	var $statTotal = document.getElementById('trl-stat-total');
	var $statMiss  = document.getElementById('trl-stat-missing');
	var $statDirty = document.getElementById('trl-stat-dirty');
	var $statDirtyWrap = document.getElementById('trl-stat-dirty-wrap');

	$scope.addEventListener('change',  function () { _reload({ scope: this.value, locale: state.locale }); });
	$locale.addEventListener('change', function () { _reload({ scope: state.scope, locale: this.value }); });

	function _reload(params) {
		if (Object.keys(state.dirty).length && !confirm(i18n.confirmLeave)) {
			$scope.value  = state.scope;
			$locale.value = state.locale;
			return;
		}
		var qs = new URLSearchParams({ action: 'translations', scope: params.scope, locale: params.locale });
		window.location.search = '?' + qs.toString();
	}

	$filter.addEventListener('input', function () {
		var q = this.value.trim().toLowerCase();
		$tbody.querySelectorAll('tr[data-key]').forEach(function (tr) {
			var key = tr.dataset.key.toLowerCase();
			var ref = (tr.dataset.ref || '').toLowerCase();
			tr.classList.toggle('trl-row-hidden', q !== '' && key.indexOf(q) === -1 && ref.indexOf(q) === -1);
		});
	});

	function renderTable() {
		var keys = Object.keys(state.reference).filter(function (k) { return k !== '_meta'; });
		keys.sort();

		if (keys.length === 0) {
			$tbody.innerHTML = '<tr class="trl-row-empty"><td colspan="3" class="trl-empty-state">' + _esc(i18n.empty) + '</td></tr>';
			$statTotal.textContent = '0'; $statMiss.textContent = '0';
			return;
		}

		var html = '';
		var missing = 0;
		keys.forEach(function (key) {
			var ref = state.reference[key] || '';
			var val = state.current[key] !== undefined ? state.current[key] : '';
			var isMissing = (val === '' && ref !== '');
			if (isMissing) missing++;
			html += '<tr data-key="' + _esc(key) + '" data-ref="' + _esc(ref) + '"' +
				(isMissing ? ' class="trl-row--missing"' : '') + '>' +
				'<td><code class="trl-key">' + _esc(key) + '</code></td>' +
				'<td><div class="trl-ref">' + _esc(ref) + '</div></td>' +
				'<td>' + _inputFor(key, val, ref) + '</td>' +
				'</tr>';
		});
		$tbody.innerHTML = html;
		$statTotal.textContent = String(keys.length);
		$statMiss.textContent  = String(missing);
		_bindInputs();
	}

	function _inputFor(key, val, ref) {
		var multi = (val.indexOf('\n') !== -1) || (ref.indexOf('\n') !== -1);
		if (multi) {
			var rows = Math.min(6, Math.max(2, val.split('\n').length + 1));
			return '<textarea rows="' + rows + '" class="trl-input" data-key="' + _esc(key) + '">' + _esc(val) + '</textarea>';
		}
		return '<input type="text" class="trl-input" data-key="' + _esc(key) + '" value="' + _esc(val) + '">';
	}

	function _bindInputs() {
		$tbody.querySelectorAll('.trl-input').forEach(function (el) {
			el.addEventListener('input', _onEdit);
		});
	}

	function _onEdit(e) {
		var key  = e.target.dataset.key;
		var val  = e.target.value;
		var orig = state.current[key] !== undefined ? state.current[key] : '';
		var tr   = e.target.closest('tr');

		if (val === orig) { delete state.dirty[key]; tr.classList.remove('trl-row--dirty'); }
		else              { state.dirty[key] = val;  tr.classList.add('trl-row--dirty'); }

		// Placeholder mismatch (%s, %d)
		var ref       = state.reference[key] || '';
		var refTokens = (ref.match(/%[sd]/g) || []).sort().join('');
		var valTokens = (val.match(/%[sd]/g) || []).sort().join('');
		var warn      = tr.querySelector('.trl-placeholder-warn');
		if (refTokens !== valTokens && val !== '') {
			tr.classList.add('trl-row--placeholder-warn');
			if (!warn) {
				warn = document.createElement('span');
				warn.className = 'trl-placeholder-warn';
				warn.textContent = i18n.placeholderWarn.replace('{tokens}', refTokens || '—');
				e.target.parentNode.appendChild(warn);
			}
		} else {
			tr.classList.remove('trl-row--placeholder-warn');
			if (warn) warn.remove();
		}

		if (val === '' && ref !== '') tr.classList.add('trl-row--missing');
		else                          tr.classList.remove('trl-row--missing');

		_refreshDirtyUI();
	}

	function _refreshDirtyUI() {
		var n = Object.keys(state.dirty).length;
		$saveBtn.disabled = n === 0;
		$discBtn.disabled = n === 0;
		$statDirtyWrap.hidden = n === 0;
		$statDirty.textContent = String(n);
		$saveMsg.textContent = n === 0 ? i18n.noChanges : i18n.dirtyMsg.replace('{n}', n);
	}

	$saveBtn.addEventListener('click', function () {
		if (Object.keys(state.dirty).length === 0) return;
		$saveBtn.disabled = true;
		$discBtn.disabled = true;
		$saveMsg.textContent = '…';

		fetch('translations-api.php?op=save&scope=' + encodeURIComponent(state.scope), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				csrf_token: CSRF,
				scope:  state.scope,
				locale: state.locale,
				strings: state.dirty,
			}),
		})
		.then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
		.then(function (resp) {
			if (!resp.body.ok) {
				alert((resp.body.error || 'error') + ' (HTTP ' + resp.status + ')');
				_refreshDirtyUI();
				return;
			}
			// Merge saved values into state.current, clear dirty
			Object.keys(state.dirty).forEach(function (k) { state.current[k] = state.dirty[k]; });
			state.dirty = {};
			// Strip dirty class on rows, keep missing class accurate
			$tbody.querySelectorAll('tr.trl-row--dirty').forEach(function (tr) { tr.classList.remove('trl-row--dirty'); });
			_refreshDirtyUI();
			$saveMsg.textContent = i18n.saveSuccess.replace('{n}', String(resp.body.applied || 0));
		})
		.catch(function () {
			alert(i18n.networkError);
			_refreshDirtyUI();
		});
	});

	$discBtn.addEventListener('click', function () {
		if (!confirm(i18n.confirmDiscard)) return;
		state.dirty = {};
		renderTable();
		_refreshDirtyUI();
	});

	window.addEventListener('beforeunload', function (e) {
		if (Object.keys(state.dirty).length === 0) return;
		e.preventDefault();
		e.returnValue = '';
	});

	// ── "New locale" modal ──────────────────────────────────────
	var $modal = document.getElementById('trl-newlocale-modal');
	document.getElementById('trl-new-locale-btn').addEventListener('click',   function () { $modal.hidden = false; });
	document.getElementById('trl-newlocale-cancel').addEventListener('click', function () { $modal.hidden = true; });
	document.getElementById('trl-newlocale-cancel-2').addEventListener('click', function () { $modal.hidden = true; });
	document.getElementById('trl-newlocale-create').addEventListener('click', function () {
		var code  = document.getElementById('trl-new-code').value.trim();
		var label = document.getElementById('trl-new-label').value.trim();
		var both  = document.getElementById('trl-new-both-scopes').checked;
		if (!/^[a-z]{2}(_[A-Z]{2})?$/.test(code)) { alert(i18n.invalidCode); return; }
		if (!label)                                { alert(i18n.labelRequired); return; }

		var $btn = this;
		$btn.disabled = true;

		fetch('translations-api.php?op=create&scope=' + encodeURIComponent(state.scope), {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				csrf_token:  CSRF,
				locale:      code,
				label:       label,
				both_scopes: both,
			}),
		})
		.then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
		.then(function (resp) {
			$btn.disabled = false;
			if (!resp.body.ok) {
				if (resp.body.error === 'already_exists') { alert(i18n.alreadyExists.replace('{locale}', code)); }
				else { alert((resp.body.error || 'error') + ' (HTTP ' + resp.status + ')'); }
				return;
			}
			$modal.hidden = true;
			alert(i18n.createSuccess.replace('{locale}', code));
			// Reload the page on the newly created locale
			var qs = new URLSearchParams({ action: 'translations', scope: state.scope, locale: code });
			window.location.search = '?' + qs.toString();
		})
		.catch(function () {
			$btn.disabled = false;
			alert(i18n.networkError);
		});
	});

	// ── Initial load from backend ───────────────────────────────
	function loadFromBackend() {
		var qs = new URLSearchParams({ op: 'load', scope: state.scope, locale: state.locale });
		fetch('translations-api.php?' + qs.toString(), { credentials: 'same-origin' })
			.then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
			.then(function (resp) {
				if (!resp.body.ok) {
					$tbody.innerHTML = '<tr><td colspan="3" class="trl-empty-state">' +
						_esc(resp.body.error || 'error') + '</td></tr>';
					return;
				}
				state.reference = resp.body.reference || {};
				state.current   = resp.body.current   || {};
				renderTable();
				_refreshDirtyUI();
			})
			.catch(function () {
				$tbody.innerHTML = '<tr><td colspan="3" class="trl-empty-state">' + _esc(i18n.networkError) + '</td></tr>';
			});
	}
	loadFromBackend();

	function _esc(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
})();
</script>
