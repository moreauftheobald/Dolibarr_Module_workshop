/* workshop_planning.js — Workshop mechanics planning (Phase 2, day view).
 * Pure HTML/CSS/JS grid, no external calendar library. Relies on jQuery
 * (bundled with Dolibarr) for AJAX and DOM helpers only.
 */
var WorkshopPlanning = (function ($) {
	'use strict';

	var cfg = {
		ajaxUrl: '',
		pageUrl: '',
		date: '',
		submode: 'day',      // 'day' | 'week'
		weekStart: '',
		slotMin: '07:00',
		slotMax: '18:00',
		refreshRate: 0,      // seconds, 0 = disabled
		canWrite: false,
		canWriteOR: false,
		csrfToken: '',
		improCodes: [],      // [{code, label}]
		users: [],           // [{id, name}]
		lang: {}
	};

	var dayData = null;      // last loaded data
	var pointageMap = {};    // id -> pointage object (for edit modal)

	// ---- Utilities -----------------------------------------------------------
	function t(key, fallback) {
		return (cfg.lang && cfg.lang[key]) ? cfg.lang[key] : (fallback || key);
	}
	function timeToMinutes(s) {
		var p = String(s).split(':');
		return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
	}
	function minutesToTime(m) {
		m = Math.max(0, m);
		var h = Math.floor(m / 60), mn = m % 60;
		return (h < 10 ? '0' : '') + h + ':' + (mn < 10 ? '0' : '') + mn;
	}
	function esc(s) {
		return $('<div>').text(s == null ? '' : s).html();
	}

	// Heure (HH:MM, arrondie au quart d'heure) depuis la position X d'un clic.
	function computeTimeFromX(clientX, el) {
		var rect = el.getBoundingClientRect();
		var pct = (clientX - rect.left) / rect.width;
		pct = Math.max(0, Math.min(1, pct));
		var smin = timeToMinutes(cfg.slotMin);
		var smax = timeToMinutes(cfg.slotMax);
		var minutes = smin + pct * (smax - smin);
		return minutesToTime(Math.round(minutes / 15) * 15);
	}

	// ---- AJAX ----------------------------------------------------------------
	function ajaxGet(action, data, cb) {
		data = data || {};
		data.action = action;
		$.get(cfg.ajaxUrl, data, null, 'json')
			.done(function (resp) { cb(resp); })
			.fail(function () { cb({ success: false, error: t('NetworkError', 'Erreur réseau') }); });
	}
	function ajaxPost(action, data, cb) {
		data = data || {};
		data.action = action;
		data.token = cfg.csrfToken;
		$.post(cfg.ajaxUrl, data, null, 'json')
			.done(function (resp) { cb(resp); })
			.fail(function () { cb({ success: false, error: t('NetworkError', 'Erreur réseau') }); });
	}

	// ---- Day grid rendering --------------------------------------------------
	function loadDay() {
		ajaxGet('get_planning_day', { date: cfg.date }, function (resp) {
			if (!resp || !resp.success) {
				$('#workshop-day-grid-container').html('<div class="opacitymedium" style="padding:16px;">' + esc(resp && resp.error ? resp.error : 'Error') + '</div>');
				return;
			}
			dayData = resp;
			cfg.slotMin = resp.slot_min;
			cfg.slotMax = resp.slot_max;
			renderDayGrid(resp);
			renderNowIndicator();
		});
	}

	function buildHourTicks() {
		var smin = timeToMinutes(cfg.slotMin);
		var smax = timeToMinutes(cfg.slotMax);
		var total = smax - smin;
		var html = '';
		var firstHour = Math.ceil(smin / 60);
		for (var h = firstHour; h * 60 <= smax; h++) {
			var pct = ((h * 60) - smin) / total * 100;
			html += '<span class="wp-hour-tick" style="left:' + pct.toFixed(2) + '%;">' + (h < 10 ? '0' : '') + h + 'h</span>';
		}
		return html;
	}

	function renderBlock(b, isPlanned) {
		var cls = isPlanned ? 'wp-block wp-block--planned' : b.classes;
		var attrs = 'style="left:' + b.left_pct + '%;width:' + b.width_pct + '%;"';
		if (isPlanned) {
			attrs += ' data-job-id="' + b.job_id + '" data-or-id="' + b.or_id + '"';
			attrs += ' data-duration="' + (b.duration_hours || 1) + '" data-label="' + esc(b.label) + '"';
			if (cfg.canWrite) { attrs += ' draggable="true"'; }
		} else {
			attrs += ' data-pointage-id="' + b.id + '"';
		}
		var title = esc(b.label) + ' — ' + esc(b.start) + (b.end ? ' → ' + esc(b.end) : '');
		return '<div class="' + cls + '" ' + attrs + ' title="' + title + '">' +
			'<span class="wp-block-label">' + esc(b.label) + '</span>' +
			'<span class="wp-block-time">' + esc(b.start) + '</span></div>';
	}

	function renderDayGrid(data) {
		pointageMap = {};
		var html = '';

		// Header
		html += '<div class="workshop-day-grid">';
		html += '<div class="wp-grid-header">';
		html += '<div class="wp-grid-header-name">' + esc(t('Mechanics', 'Mécaniciens')) + '</div>';
		html += '<div class="wp-grid-header-timeline">' + buildHourTicks() + '</div>';
		html += '</div>';

		if (!data.mechanics.length) {
			html += '<div class="opacitymedium" style="padding:16px;">' + esc(t('NoMechanics', 'Aucun mécanicien configuré.')) + '</div>';
		}

		// Mechanic rows (two lanes each)
		data.mechanics.forEach(function (m) {
			html += '<div class="wp-grid-row" data-user-id="' + m.id + '">';
			html += '<div class="wp-mechanic-name">' + esc(m.name) + '</div>';
			html += '<div class="wp-mechanic-lanes">';

			// Lane: planned
			html += '<div class="wp-lane wp-lane--planned" data-lane="planned">';
			html += '<span class="wp-lane-tag">' + esc(t('Planned', 'Prévu')) + '</span>';
			m.planned.forEach(function (b) { html += renderBlock(b, true); });
			html += '</div>';

			// Lane: pointage
			html += '<div class="wp-lane wp-lane--pointage" data-lane="pointage">';
			html += '<span class="wp-lane-tag">' + esc(t('Clocked', 'Pointé')) + '</span>';
			m.pointages.forEach(function (b) {
				pointageMap[b.id] = b;
				b.user_id = m.id;
				html += renderBlock(b, false);
			});
			html += '</div>';

			html += '<div class="wp-now-indicator" style="display:none;"></div>';
			html += '</div>'; // lanes
			html += '</div>'; // row
		});

		// Unassigned row
		html += '<div class="wp-unassigned-row">';
		html += '<span class="wp-unassigned-label">⚠ ' + esc(t('Unassigned', 'Non affectés')) + '</span>';
		html += '<div class="wp-unassigned-jobs">';
		data.unassigned.forEach(function (j) {
			html += '<div class="wp-job-chip" draggable="' + (cfg.canWrite ? 'true' : 'false') + '" data-job-id="' + j.job_id + '" data-or-id="' + j.or_id + '">' + esc(j.label) + '</div>';
		});
		if (!data.unassigned.length) {
			html += '<span class="opacitymedium" style="font-size:12px;">' + esc(t('NoUnassigned', 'Aucun')) + '</span>';
		}
		html += '</div></div>';

		html += '</div>'; // grid
		$('#workshop-day-grid-container').html(html);
	}

	// ---- "Now" indicator -----------------------------------------------------
	function renderNowIndicator() {
		var now = new Date();
		var today = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
		if (today !== cfg.date) {
			$('.wp-now-indicator').hide();
			return;
		}
		var smin = timeToMinutes(cfg.slotMin);
		var smax = timeToMinutes(cfg.slotMax);
		var nowMin = now.getHours() * 60 + now.getMinutes();
		var pct = (nowMin - smin) / (smax - smin) * 100;
		if (pct < 0 || pct > 100) { $('.wp-now-indicator').hide(); return; }
		$('.wp-now-indicator').css('left', pct.toFixed(2) + '%').show();
	}

	// ---- Week grid -----------------------------------------------------------
	function loadWeek() {
		ajaxGet('get_planning_week', { date: cfg.date }, function (resp) {
			if (!resp || !resp.success) {
				$('#workshop-day-grid-container').html('<div class="opacitymedium" style="padding:16px;">' + esc(resp && resp.error ? resp.error : 'Error') + '</div>');
				return;
			}
			renderWeekGrid(resp);
		});
	}

	function chargeClass(pct) {
		if (pct > 75) { return 'wp-charge--high'; }
		if (pct >= 50) { return 'wp-charge--medium'; }
		return 'wp-charge--low';
	}

	function renderWeekGrid(data) {
		var n = data.days.length;
		var rowStyle = 'style="--wp-day-count:' + n + ';"';
		var html = '<div class="workshop-week-grid">';

		// Header
		html += '<div class="wp-week-row wp-week-head" ' + rowStyle + '>';
		html += '<div class="wp-week-cell wp-week-namecell">' + esc(t('Mechanics', 'Mécaniciens')) + '</div>';
		data.days.forEach(function (d, i) {
			var cls = (d === data.today) ? ' wp-week-cell--today-header' : '';
			html += '<div class="wp-week-cell' + cls + '">' + esc(data.day_labels[i]) + (d === data.today ? ' ▼' : '') + '</div>';
		});
		html += '</div>';

		// Mechanics
		data.mechanics.forEach(function (m) {
			html += '<div class="wp-week-row" ' + rowStyle + '>';
			html += '<div class="wp-week-cell wp-week-namecell">' + esc(m.name) + '</div>';
			data.days.forEach(function (d) {
				var cls = (d === data.today) ? ' wp-week-cell--today' : '';
				html += '<div class="wp-week-cell' + cls + '" data-date="' + d + '">';
				(m.days[d] || []).forEach(function (tag) {
					html += '<span class="wp-week-tag ' + tag.cls + '">' + esc(tag.label) + '</span>';
				});
				html += '</div>';
			});
			html += '</div>';
		});

		// Charge row
		html += '<div class="wp-week-row wp-charge-row" ' + rowStyle + '>';
		html += '<div class="wp-week-cell wp-week-namecell">' + esc(t('Charge', 'Charge %')) + '</div>';
		data.days.forEach(function (d) {
			var pct = data.charges[d] || 0;
			html += '<div class="wp-week-cell ' + chargeClass(pct) + '">' + pct + '%</div>';
		});
		html += '</div>';

		html += '</div>';
		$('#workshop-day-grid-container').html(html);
	}

	// ---- Modals --------------------------------------------------------------
	function buildModals() {
		if ($('#wp-modal-pointage').length) { return; }

		var improOpts = '';
		(cfg.improCodes || []).forEach(function (c) {
			improOpts += '<option value="' + esc(c.code) + '">' + esc(c.label) + '</option>';
		});

		var html = '' +
		'<div class="wp-modal-backdrop" id="wp-modal-backdrop"></div>' +
		'<div class="wp-modal" id="wp-modal-pointage">' +
			'<div class="wp-modal-header"><span id="wp-pt-title">' + esc(t('Pointage', 'Pointage')) + '</span>' +
				'<button type="button" class="wp-modal-close" data-close>&times;</button></div>' +
			'<div class="wp-modal-body">' +
				'<input type="hidden" id="wp-pt-id"><input type="hidden" id="wp-pt-user">' +
				'<div class="wp-modal-tabs">' +
					'<button type="button" class="wp-modal-tab active" data-pane="job">' + esc(t('OnJob', 'Sur OR / Job')) + '</button>' +
					'<button type="button" class="wp-modal-tab" data-pane="impro">' + esc(t('Improductive', 'Improductif')) + '</button>' +
				'</div>' +
				'<div class="wp-modal-pane active" data-pane="job">' +
					'<div class="wp-field-row"><label>' + esc(t('OR', 'OR')) + '</label>' +
						'<input type="text" id="wp-or-search" placeholder="' + esc(t('SearchOR', 'Rechercher un OR...')) + '">' +
						'<select id="wp-or-select" size="1"></select></div>' +
					'<div class="wp-field-row"><label>' + esc(t('Job', 'Travail')) + '</label><select id="wp-job-select"></select></div>' +
				'</div>' +
				'<div class="wp-modal-pane" data-pane="impro">' +
					'<div class="wp-field-row"><label>' + esc(t('ImproCode', 'Code improductif')) + '</label>' +
						'<select id="wp-impro-select">' + improOpts + '</select></div>' +
				'</div>' +
				'<div class="wp-field-row"><label>' + esc(t('Start', 'Heure début')) + '</label><input type="time" id="wp-pt-start"></div>' +
				'<div class="wp-field-row"><label>' + esc(t('End', 'Heure fin')) + ' <span class="opacitymedium">(' + esc(t('EmptyOpen', 'vide = en cours')) + ')</span></label><input type="time" id="wp-pt-end"></div>' +
				'<div class="wp-field-row"><label>' + esc(t('Note', 'Note')) + '</label><input type="text" id="wp-pt-note" maxlength="255"></div>' +
			'</div>' +
			'<div class="wp-modal-footer">' +
				'<button type="button" class="button buttonDelete" id="wp-pt-delete" style="display:none;">' + esc(t('Delete', 'Supprimer')) + '</button>' +
				'<button type="button" class="button" id="wp-pt-save">' + esc(t('Save', 'Enregistrer')) + '</button>' +
			'</div>' +
		'</div>' +
		'<div class="wp-modal" id="wp-modal-assign">' +
			'<div class="wp-modal-header"><span>' + esc(t('AssignJob', 'Affecter le travail')) + '</span>' +
				'<button type="button" class="wp-modal-close" data-close>&times;</button></div>' +
			'<div class="wp-modal-body">' +
				'<input type="hidden" id="wp-as-job"><input type="hidden" id="wp-as-user">' +
				'<div class="wp-field-row"><label>' + esc(t('Job', 'Travail')) + '</label><div id="wp-as-joblabel" class="opacitymedium"></div></div>' +
				'<div class="wp-field-row"><label>' + esc(t('Start', 'Heure début')) + '</label><input type="time" id="wp-as-start"></div>' +
				'<div class="wp-field-row"><label>' + esc(t('DurationHours', 'Durée estimée (h)')) + '</label><input type="number" id="wp-as-duration" min="0.25" step="0.25" value="1"></div>' +
			'</div>' +
			'<div class="wp-modal-footer">' +
				'<span></span>' +
				'<button type="button" class="button" id="wp-as-save">' + esc(t('Assign', 'Affecter')) + '</button>' +
			'</div>' +
		'</div>';

		// Quick OR modal
		var mecOpts = '<option value="0">--</option>';
		(cfg.users || []).forEach(function (u) {
			mecOpts += '<option value="' + u.id + '">' + esc(u.name) + '</option>';
		});
		html += '' +
		'<div class="wp-modal" id="wp-modal-newor">' +
			'<div class="wp-modal-header"><span>' + esc(t('NewOR', 'Nouvel OR')) + '</span>' +
				'<button type="button" class="wp-modal-close" data-close>&times;</button></div>' +
			'<div class="wp-modal-body">' +
				'<div class="wp-field-row"><label>' + esc(t('ThirdParty', 'Tiers')) + '</label>' +
					'<input type="text" id="wp-or-soc-search" placeholder="' + esc(t('SearchThirdParty', 'Rechercher un tiers...')) + '">' +
					'<select id="wp-or-soc"></select></div>' +
				'<div class="wp-field-row"><label>' + esc(t('Vehicle', 'Véhicule')) + '</label><select id="wp-or-veh"></select></div>' +
				'<div class="wp-field-row"><label>' + esc(t('Description', 'Description')) + '</label><input type="text" id="wp-or-descr" maxlength="255"></div>' +
				'<div class="wp-field-row"><label><input type="checkbox" id="wp-or-plannow"> ' + esc(t('PlanNow', 'Planification immédiate')) + '</label></div>' +
				'<div id="wp-or-plan-block" style="display:none;">' +
					'<div class="wp-field-row"><label>' + esc(t('Mechanic', 'Mécanicien')) + '</label><select id="wp-or-mec">' + mecOpts + '</select></div>' +
					'<div class="wp-field-row"><label>' + esc(t('Start', 'Heure début')) + '</label><input type="time" id="wp-or-start"></div>' +
				'</div>' +
			'</div>' +
			'<div class="wp-modal-footer">' +
				'<span></span>' +
				'<button type="button" class="button" id="wp-or-create">' + esc(t('CreateAndOpen', 'Créer et ouvrir l\'OR')) + '</button>' +
			'</div>' +
		'</div>';

		$('body').append(html);
	}

	function openModal(id) {
		$('#wp-modal-backdrop').addClass('wp-open');
		$('#' + id).addClass('wp-open');
	}
	function closeModals() {
		$('.wp-modal, .wp-modal-backdrop').removeClass('wp-open');
	}
	function switchPane(pane) {
		$('#wp-modal-pointage .wp-modal-tab').removeClass('active').filter('[data-pane="' + pane + '"]').addClass('active');
		$('#wp-modal-pointage .wp-modal-pane').removeClass('active').filter('[data-pane="' + pane + '"]').addClass('active');
	}

	function openPointageModal(userId, time, pointageId) {
		buildModals();
		$('#wp-or-search').val('');
		$('#wp-or-select').empty();
		$('#wp-job-select').empty();
		$('#wp-pt-note').val('');
		$('#wp-pt-end').val('');

		if (pointageId && pointageMap[pointageId]) {
			var p = pointageMap[pointageId];
			$('#wp-pt-id').val(pointageId);
			$('#wp-pt-user').val(p.user_id);
			$('#wp-pt-title').text(t('EditPointage', 'Modifier le pointage'));
			$('#wp-pt-start').val(p.start);
			$('#wp-pt-end').val(p.end || '');
			$('#wp-pt-delete').show();
			if (p.type === 'impro') {
				switchPane('impro');
				$('#wp-impro-select').val(p.impro_code);
			} else {
				switchPane('job');
				if (p.or_id) {
					$('#wp-or-select').append('<option value="' + p.or_id + '" selected>' + esc(p.label) + '</option>');
					loadJobs(p.or_id, p.job_id);
				}
			}
		} else {
			$('#wp-pt-id').val('');
			$('#wp-pt-user').val(userId);
			$('#wp-pt-title').text(t('NewPointage', 'Nouveau pointage'));
			$('#wp-pt-start').val(time || '');
			$('#wp-pt-delete').hide();
			switchPane('job');
		}
		openModal('wp-modal-pointage');
	}

	function loadJobs(orId, selectJobId) {
		ajaxGet('get_jobs_for_or', { or_id: orId }, function (resp) {
			var sel = $('#wp-job-select').empty();
			if (resp && resp.success) {
				resp.results.forEach(function (j) {
					sel.append('<option value="' + j.id + '"' + (selectJobId == j.id ? ' selected' : '') + '>' + esc(j.label) + '</option>');
				});
			}
		});
	}

	function openAssignModal(jobId, userId, time, jobLabel, duration) {
		buildModals();
		$('#wp-as-job').val(jobId);
		$('#wp-as-user').val(userId);
		$('#wp-as-joblabel').text(jobLabel || '');
		$('#wp-as-start').val(time || '');
		$('#wp-as-duration').val(duration && duration > 0 ? duration : '1');
		openModal('wp-modal-assign');
	}

	function openNewOrModal() {
		buildModals();
		$('#wp-or-soc-search').val('');
		$('#wp-or-soc').empty();
		$('#wp-or-veh').empty();
		$('#wp-or-descr').val('');
		$('#wp-or-plannow').prop('checked', false);
		$('#wp-or-plan-block').hide();
		$('#wp-or-start').val('');
		openModal('wp-modal-newor');
	}

	function loadVehicules(socId) {
		ajaxGet('get_vehicules_for_soc', { fk_soc: socId }, function (resp) {
			var sel = $('#wp-or-veh').empty();
			sel.append('<option value="0">--</option>');
			if (resp && resp.success) {
				resp.results.forEach(function (v) { sel.append('<option value="' + v.id + '">' + esc(v.label) + '</option>'); });
			}
		});
	}

	function saveNewOr() {
		var data = {
			fk_soc: $('#wp-or-soc').val() || 0,
			fk_vehicule: $('#wp-or-veh').val() || 0,
			description: $('#wp-or-descr').val(),
			date: cfg.date
		};
		if (!data.fk_soc || data.fk_soc === '0') { alert(t('ThirdPartyRequired', 'Tiers obligatoire')); return; }
		if ($('#wp-or-plannow').is(':checked')) {
			data.fk_user_assign = $('#wp-or-mec').val() || 0;
			data.start = $('#wp-or-start').val();
		}
		ajaxPost('create_or_quick', data, function (resp) {
			if (!resp || !resp.success) { alert(resp && resp.error ? resp.error : 'Error'); return; }
			window.location = resp.url;
		});
	}

	// ---- Save handlers -------------------------------------------------------
	function savePointage() {
		var id = $('#wp-pt-id').val();
		var activePane = $('#wp-modal-pointage .wp-modal-tab.active').data('pane');
		var data = {
			fk_user: $('#wp-pt-user').val(),
			date: cfg.date,
			p_type: activePane === 'impro' ? 'impro' : 'job',
			start: $('#wp-pt-start').val(),
			end: $('#wp-pt-end').val(),
			note: $('#wp-pt-note').val()
		};
		if (!data.start) { alert(t('StartRequired', 'Heure de début obligatoire')); return; }
		if (activePane === 'impro') {
			data.impro_code = $('#wp-impro-select').val();
		} else {
			data.fk_job = $('#wp-job-select').val() || 0;
		}
		var action = id ? 'update_pointage' : 'create_pointage';
		if (id) { data.pointage_id = id; }
		ajaxPost(action, data, function (resp) {
			if (!resp || !resp.success) { alert(resp && resp.error ? resp.error : 'Error'); return; }
			closeModals();
			loadDay();
		});
	}
	function deletePointage() {
		var id = $('#wp-pt-id').val();
		if (!id) { return; }
		if (!confirm(t('ConfirmDelete', 'Supprimer ce pointage ?'))) { return; }
		ajaxPost('delete_pointage', { pointage_id: id }, function (resp) {
			if (!resp || !resp.success) { alert(resp && resp.error ? resp.error : 'Error'); return; }
			closeModals();
			loadDay();
		});
	}
	function saveAssign() {
		var data = {
			fk_job: $('#wp-as-job').val(),
			fk_user: $('#wp-as-user').val(),
			date: cfg.date,
			start: $('#wp-as-start').val(),
			duration: $('#wp-as-duration').val()
		};
		if (!data.start) { alert(t('StartRequired', 'Heure de début obligatoire')); return; }
		ajaxPost('assign_job', data, function (resp) {
			if (!resp || !resp.success) { alert(resp && resp.error ? resp.error : 'Error'); return; }
			closeModals();
			loadDay();
		});
	}

	// ---- Events --------------------------------------------------------------
	var orSearchTimer = null;
	function bindEvents() {
		// Click empty pointage lane → create modal
		$(document).on('click', '.wp-lane--pointage', function (e) {
			if (!cfg.canWrite) { return; }
			if ($(e.target).closest('.wp-block').length) { return; }
			var userId = $(this).closest('.wp-grid-row').data('user-id');
			var time = computeTimeFromX(e.clientX, this);
			openPointageModal(userId, time, null);
		});
		// Click pointage block → edit modal
		$(document).on('click', '.wp-lane--pointage .wp-block', function (e) {
			e.stopPropagation();
			if (!cfg.canWrite) { return; }
			openPointageModal(null, null, $(this).data('pointage-id'));
		});
		// Click planned block → open OR card
		$(document).on('click', '.wp-block--planned', function (e) {
			e.stopPropagation();
			var orId = $(this).data('or-id');
			if (orId) { window.location = cfg.pageUrl.replace('workshop_planning.php', 'operationorder/or_card.php') + '?id=' + orId; }
		});

		// Modal generic
		$(document).on('click', '#wp-modal-backdrop, .wp-modal-close', closeModals);
		$(document).on('click', '#wp-modal-pointage .wp-modal-tab', function () { switchPane($(this).data('pane')); });
		$(document).on('click', '#wp-pt-save', savePointage);
		$(document).on('click', '#wp-pt-delete', deletePointage);
		$(document).on('click', '#wp-as-save', saveAssign);

		// OR autocomplete
		$(document).on('input', '#wp-or-search', function () {
			var term = $(this).val();
			clearTimeout(orSearchTimer);
			orSearchTimer = setTimeout(function () {
				ajaxGet('search_or', { term: term }, function (resp) {
					var sel = $('#wp-or-select').empty();
					if (resp && resp.success) {
						resp.results.forEach(function (o) { sel.append('<option value="' + o.id + '">' + esc(o.ref) + '</option>'); });
						if (resp.results.length) { sel.val(resp.results[0].id); loadJobs(resp.results[0].id); }
					}
				});
			}, 300);
		});
		$(document).on('change', '#wp-or-select', function () { loadJobs($(this).val()); });

		// Drag & drop — unassigned job chips
		$(document).on('dragstart', '.wp-job-chip', function (e) {
			e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({
				job_id: $(this).data('job-id'),
				or_id: $(this).data('or-id'),
				label: $(this).text()
			}));
			$(this).addClass('wp-dragging');
		});
		$(document).on('dragend', '.wp-job-chip', function () { $(this).removeClass('wp-dragging'); });

		// Drag & drop — already-planned blocks (reschedule / reassign)
		$(document).on('dragstart', '.wp-block--planned', function (e) {
			if (!cfg.canWrite) { e.preventDefault(); return; }
			e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify({
				job_id: $(this).data('job-id'),
				or_id: $(this).data('or-id'),
				label: $(this).data('label'),
				duration: $(this).data('duration')
			}));
			$(this).addClass('wp-dragging');
		});
		$(document).on('dragend', '.wp-block--planned', function () { $(this).removeClass('wp-dragging'); });
		$(document).on('dragover', '.wp-mechanic-lanes', function (e) {
			e.preventDefault();
			$(this).closest('.wp-grid-row').addClass('wp-drop-target');
		});
		$(document).on('dragleave', '.wp-mechanic-lanes', function () {
			$(this).closest('.wp-grid-row').removeClass('wp-drop-target');
		});
		$(document).on('drop', '.wp-mechanic-lanes', function (e) {
			e.preventDefault();
			var row = $(this).closest('.wp-grid-row');
			row.removeClass('wp-drop-target');
			if (!cfg.canWrite) { return; }
			var payload;
			try { payload = JSON.parse(e.originalEvent.dataTransfer.getData('text/plain')); } catch (err) { return; }
			var userId = row.data('user-id');
			var time = computeTimeFromX(e.originalEvent.clientX, this);
			openAssignModal(payload.job_id, userId, time, payload.label, payload.duration);
		});

		// Week cell click → jump to the day view of that date
		$(document).on('click', '.wp-week-cell[data-date]', function () {
			var d = $(this).data('date');
			if (d) { window.location = cfg.pageUrl + '?mode=mecaniciens&submode=day&date=' + d; }
		});

		// "Nouvel OR" button
		$(document).on('click', '#btn-new-or-planning', function (e) {
			e.preventDefault();
			if (cfg.canWriteOR) { openNewOrModal(); }
		});
		$(document).on('click', '#wp-or-create', saveNewOr);
		$(document).on('change', '#wp-or-plannow', function () {
			$('#wp-or-plan-block').toggle($(this).is(':checked'));
		});
		$(document).on('change', '#wp-or-soc', function () { loadVehicules($(this).val()); });
		$(document).on('input', '#wp-or-soc-search', function () {
			var term = $(this).val();
			clearTimeout(orSearchTimer);
			orSearchTimer = setTimeout(function () {
				ajaxGet('search_thirdparty', { term: term }, function (resp) {
					var sel = $('#wp-or-soc').empty();
					if (resp && resp.success) {
						resp.results.forEach(function (s) { sel.append('<option value="' + s.id + '">' + esc(s.name) + '</option>'); });
						if (resp.results.length) { sel.val(resp.results[0].id); loadVehicules(resp.results[0].id); }
					}
				});
			}, 300);
		});
	}

	// ---- Init ----------------------------------------------------------------
	function reload() {
		if (cfg.submode === 'week') { loadWeek(); } else { loadDay(); }
	}

	function init(options) {
		$.extend(cfg, options || {});
		buildModals();
		bindEvents();
		reload();
		setInterval(renderNowIndicator, 60000);
		if (cfg.refreshRate > 0) {
			setInterval(reload, cfg.refreshRate * 1000);
		}
	}

	return { init: init };

})(jQuery);
