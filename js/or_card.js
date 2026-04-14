/**
 * or_card.js — JavaScript pour la fiche Ordre de Réparation (or_card.php)
 *
 * Ce fichier dépend des variables globales injectées en ligne par or_card.php :
 *
 *   Mode création (id == 0) :
 *     window.workshopOrCardSelf   {string}  valeur de PHP_SELF (pour les redirections)
 */


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
 * COMMUN — Dialogs redimensionnables (formconfirm)
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
