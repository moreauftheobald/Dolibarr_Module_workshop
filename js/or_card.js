/**
 * or_card.js — JavaScript pour la fiche Ordre de Réparation (or_card.php)
 *
 * Ce fichier dépend des variables globales injectées en ligne par or_card.php :
 *
 *   Mode vue (id > 0) :
 *     window.workshopProdData     {Object}  map produit_id → { price, type }
 *     window.workshopOrCardAjaxUrl {string} URL de base vers ajax/or_card_ajax.php?id=X
 *
 *   Mode création (id == 0) :
 *     window.workshopOrCardSelf   {string}  valeur de PHP_SELF (pour les redirections)
 */

/* ============================================================================
 * MODE VUE — Dialog "Ajouter une ligne produit/service dans un Job"
 * ========================================================================== */

jQuery(function ($) {
	var $dlg = $('#dlg-add-det');

	/**
	 * Ouvre le dialog d'ajout de ligne de détail pour le job donné.
	 * @param {number} jobid
	 */
	window.workshopOpenAddDet = function (jobid) {
		$('#dlg_det_jobid').val(jobid);
		$('#det_fk_product').val('');
		$('#det_description').val('');
		$('#det_qty').val('1');
		$('#det_price').val('0');
		$('#det_remise_percent').val('0');
		$('#det_fk_warehouse').html('<option value=""></option>');
		$('#det_warehouse_row').hide();

		if ($dlg.hasClass('ui-dialog-content')) {
			$dlg.dialog('open');
		} else {
			$dlg.dialog({
				modal:     true,
				width:     620,
				height:    'auto',
				maxHeight: Math.floor($(window).height() * 0.92),
				resizable: true,
				buttons: [
					{
						text: document.documentElement.lang === 'fr' ? 'Ajouter' : 'Add',
						click: function () {
							var prodId = $('#det_fk_product').val();
							if (!prodId || prodId === '0' || prodId === '') {
								alert(document.documentElement.lang === 'fr'
									? 'Veuillez sélectionner un produit.'
									: 'Please select a product.');
								return;
							}
							$('#frm-add-det').submit();
						}
					},
					{
						text: document.documentElement.lang === 'fr' ? 'Annuler' : 'Cancel',
						click: function () { $(this).dialog('close'); }
					}
				]
			});
		}
	};

	// Changement de produit : auto-remplir prix + charger entrepôts via AJAX
	$(document).on('change', '#det_fk_product', function () {
		var prodId  = parseInt($(this).val(), 10);
		var prodData = window.workshopProdData || {};
		var info    = prodId ? prodData[prodId] : null;

		// Prix unitaire HT
		$('#det_price').val(info ? info.price.toFixed(2) : '0');

		// Entrepôts (produits physiques uniquement, pas les services)
		var $whSel = $('#det_fk_warehouse');
		if (info && info.type === 0) {
			$('#det_warehouse_row').show();
			$whSel.html('<option value=""></option>');
			$.getJSON(window.workshopOrCardAjaxUrl + '&action=get_warehouses_for_product', { product_id: prodId })
				.done(function (warehouses) {
					console.log('[workshop] warehouses for product ' + prodId + ':', warehouses);
					var defaultVal = '';
					$.each(warehouses, function (i, wh) {
						var label = wh.ref;
						if (wh.qty > 0) { label += ' (' + wh.qty + ')'; }
						var $opt = $('<option>').val(wh.rowid).text(label);
						if (wh.is_default) { defaultVal = wh.rowid; }
						$whSel.append($opt);
					});
					if (defaultVal) { $whSel.val(defaultVal); }
				});
		} else {
			$('#det_warehouse_row').hide();
			$whSel.html('<option value=""></option>');
		}
	});
});


/* ============================================================================
 * MODE VUE — Dialog "Sous-traitance d'un Job"
 * ========================================================================== */

jQuery(function ($) {
	var $dlgSc = $('#dlg-subcontracting');

	/**
	 * Ouvre le dialog de sous-traitance pour le job donné.
	 * Pré-sélectionne les opérations de maintenance déjà liées au job.
	 * @param {number} jobid
	 */
	window.workshopOpenSubcontracting = function (jobid) {
		$('#dlg_sc_jobid').val(jobid);
		$('#sc_fk_soc').val('');
		$('#sc_amount').val('0');
		$('#sc_description').val('');

		// Pré-sélectionner les opérations de maintenance déjà liées à ce job
		var linkedIds = ((window.workshopJobLinkedOps || {})[jobid] || []).map(String);
		$('#sc_maintenance_ops').val(linkedIds).trigger('change');

		if ($dlgSc.hasClass('ui-dialog-content')) {
			$dlgSc.dialog('open');
		} else {
			$dlgSc.dialog({
				modal:     true,
				width:     640,
				height:    'auto',
				maxHeight: Math.floor($(window).height() * 0.92),
				resizable: true,
				buttons: [
					{
						text: document.documentElement.lang === 'fr' ? 'Enregistrer' : 'Save',
						click: function () {
							var socId = $('#sc_fk_soc').val();
							if (!socId || socId === '0' || socId === '') {
								alert(document.documentElement.lang === 'fr'
									? 'Veuillez sélectionner un fournisseur.'
									: 'Please select a supplier.');
								return;
							}
							$('#frm-subcontracting').submit();
						}
					},
					{
						text: document.documentElement.lang === 'fr' ? 'Annuler' : 'Cancel',
						click: function () { $(this).dialog('close'); }
					}
				]
			});
		}
	};
});


/* ============================================================================
 * MODE VUE — Dialog "Modifier une ligne produit/service (det)"
 * ========================================================================== */

jQuery(function ($) {
	var $dlgEdit = $('#dlg-edit-det');

	/**
	 * Ouvre le dialog d'édition d'une ligne de détail pré-remplie.
	 * @param {number} detid        rowid de la ligne
	 * @param {string} label        libellé produit/service (affiché en lecture seule)
	 * @param {string} description  description existante
	 * @param {string} qty          quantité
	 * @param {string} price        prix unitaire HT
	 * @param {string} remise       remise en %
	 */
	window.workshopOpenEditDet = function (detid, label, description, qty, price, remise) {
		$('#det_edit_id').val(detid);
		$('#det_edit_product_label').text(label);
		$('#det_edit_description').val(description);
		$('#det_edit_qty').val(qty);
		$('#det_edit_price').val(price);
		$('#det_edit_remise_percent').val(remise);

		if ($dlgEdit.hasClass('ui-dialog-content')) {
			$dlgEdit.dialog('open');
		} else {
			$dlgEdit.dialog({
				modal:     true,
				width:     560,
				height:    'auto',
				maxHeight: Math.floor($(window).height() * 0.92),
				resizable: true,
				buttons: [
					{
						text: document.documentElement.lang === 'fr' ? 'Enregistrer' : 'Save',
						click: function () {
							var qty = $('#det_edit_qty').val();
							if (!qty || parseFloat(qty) <= 0) {
								alert(document.documentElement.lang === 'fr'
									? 'La quantité doit être supérieure à 0.'
									: 'Quantity must be greater than 0.');
								return;
							}
							$('#frm-edit-det').submit();
						}
					},
					{
						text: document.documentElement.lang === 'fr' ? 'Annuler' : 'Cancel',
						click: function () { $(this).dialog('close'); }
					}
				]
			});
		}
	};
});


/* ============================================================================
 * MODE CRÉATION — Helpers de préservation de l'état du formulaire OR
 * ========================================================================== */

/**
 * Lit la valeur d'un champ FK (input hidden type Select2 Dolibarr) ou d'un input classique.
 * @param  {string} name  Nom du champ
 * @return {string}
 */
function orCardGetField(name) {
	var $hidden = jQuery("input[name='" + name + "'][type='hidden']");
	if ($hidden.length) return $hidden.val() || '';
	return jQuery("[name='" + name + "']").val() || '';
}

/**
 * Collecte les tags sélectionnés dans le multi-select.
 * @return {Array}
 */
function orCardGetTags() {
	var vals = jQuery("select[name='fk_tags[]']").val();
	return vals ? vals : [];
}

/**
 * Construit la query-string d'état du formulaire OR pour les redirections de sous-dialogs.
 * @param  {Object} [overrides]  Valeurs à surcharger
 * @return {string}
 */
function orCardStateQS(overrides) {
	overrides = overrides || {};
	var fk_vehicule   = overrides.fk_vehicule   !== undefined ? overrides.fk_vehicule   : orCardGetField('fk_vehicule');
	var km            = overrides.km             !== undefined ? overrides.km            : orCardGetField('km');
	var fk_conducteur = overrides.fk_conducteur  !== undefined ? overrides.fk_conducteur : orCardGetField('fk_conducteur');
	var fk_tags       = overrides.fk_tags        !== undefined ? overrides.fk_tags       : orCardGetTags();

	var qs = '&fk_vehicule=' + encodeURIComponent(fk_vehicule);
	qs += '&km=' + encodeURIComponent(km);
	qs += '&fk_conducteur=' + encodeURIComponent(fk_conducteur);
	jQuery.each(fk_tags, function (i, v) { qs += '&fk_tags[]=' + encodeURIComponent(v); });
	return qs;
}

/** Redirige vers la création d'un conducteur en préservant l'état du formulaire. */
function orCardOpenNewConducteur() {
	window.location.href = (window.workshopOrCardSelf || '') + '?action=new_conducteur' + orCardStateQS();
}

/** Redirige vers la création d'un tag en préservant l'état du formulaire. */
function orCardOpenNewTag() {
	window.location.href = (window.workshopOrCardSelf || '') + '?action=new_tag' + orCardStateQS();
}


/* ============================================================================
 * COMMUN — Dialogs redimensionnables (formconfirm + dlg-add-det)
 * ========================================================================== */

jQuery(document).on('dialogopen', function (e) {
	var $dlg = jQuery(e.target);
	var maxH  = Math.floor(jQuery(window).height() * 0.92);

	$dlg.dialog('option', 'resizable', true);
	$dlg.dialog('option', 'maxHeight', maxH);

	// Passer en hauteur automatique si le contenu tient dans la fenêtre
	var currentH = $dlg.dialog('option', 'height');
	if (currentH !== 'auto' && currentH > maxH) {
		$dlg.dialog('option', 'height', maxH);
	}

	// Scroll interne si le contenu dépasse
	$dlg.css('overflow-y', 'auto');
	$dlg.dialog('widget').css('max-height', maxH + 'px');
});


/* ============================================================================
 * COMMUN — Amélioration visuelle des <select> de palette de couleurs
 * ========================================================================== */

(function () {
	/**
	 * Ajoute un swatch coloré à côté de chaque <select> dont les options
	 * sont des valeurs hexadécimales (#rrggbb).
	 */
	function enhanceColorSelects() {
		document.querySelectorAll('select').forEach(function (sel) {
			if (sel.dataset.wsColorEnhanced) return;
			if (!sel.options.length) return;
			if (!/^#[0-9a-fA-F]{6}$/i.test(sel.options[0].value)) return;

			sel.dataset.wsColorEnhanced = '1';
			var swatch = document.createElement('span');
			swatch.style.cssText = 'display:inline-block;width:18px;height:18px;border-radius:3px;'
				+ 'border:1px solid #999;vertical-align:middle;margin-left:6px;background-color:' + sel.value + ';';
			sel.parentNode.insertBefore(swatch, sel.nextSibling);
			sel.addEventListener('change', function () { swatch.style.backgroundColor = sel.value; });
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', enhanceColorSelects);
	} else {
		enhanceColorSelects();
	}
	if (typeof jQuery !== 'undefined') {
		jQuery(document).on('dialogopen', function () { setTimeout(enhanceColorSelects, 50); });
	}
})();
