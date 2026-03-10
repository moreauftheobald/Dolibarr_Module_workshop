<?php
/* Copyright (C) 2024 T-SERVICES <contact@t-services.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       operationorder/or_card.php
 * \ingroup    workshop
 * \brief      Création d'un Ordre de Réparation (OR)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/class/Conducteur.class.php');
dol_include_once('/workshop/class/Tag.class.php');
dol_include_once('/workshop/class/OperationorderTag.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');

if (!isModEnabled('workshop')) {
	accessforbidden();
}
if (!$user->hasRight('workshop', 'operationorders', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array('workshop@workshop'));

$action      = GETPOST('action', 'aZ09');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'alpha');
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);

$extrafields->fetch_name_optionals_label($object->table_element);
$hookmanager->initHooks(array('orcard', 'globalcard'));

$permissiontoadd = $user->hasRight('workshop', 'operationorders', 'write');


/*
 * Helpers — encode/decode form state through the creation sub-dialogs
 *
 * State: fk_vehicule (int), km (string), fk_conducteur (int), fk_tags (comma-separated ints)
 */

/**
 * Build the query-string fragment that preserves the current OR-form state.
 * Values are read from GETPOST so they survive successive GET redirects.
 *
 * @return string  URL-encoded fragment, starts with '&'
 */
function orCardStateQueryString()
{
	$fk_vehicule   = GETPOSTINT('fk_vehicule');
	$km            = GETPOST('km', 'alpha');
	$fk_conducteur = GETPOSTINT('fk_conducteur');
	$fk_tags       = array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));

	$qs = '&fk_vehicule='.((int) $fk_vehicule);
	$qs .= '&km='.urlencode($km);
	$qs .= '&fk_conducteur='.((int) $fk_conducteur);
	foreach ($fk_tags as $t) {
		$qs .= '&fk_tags[]='.((int) $t);
	}
	return $qs;
}

/**
 * Build the hidden-field list for a formconfirm dialog so the state
 * survives the POST + redirect cycle.
 *
 * @param  array $extra  Optional extra fields to add to the list
 * @return array         $formquestion array fragment
 */
function orCardStateHiddenFields($extra = array())
{
	$fk_vehicule   = GETPOSTINT('fk_vehicule');
	$km            = GETPOST('km', 'alpha');
	$fk_conducteur = GETPOSTINT('fk_conducteur');
	$fk_tags       = array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));

	$fields   = array();
	$fields[] = array('type' => 'hidden', 'name' => 'fk_vehicule',   'value' => (int) $fk_vehicule);
	$fields[] = array('type' => 'hidden', 'name' => 'km',            'value' => dol_escape_htmltag($km));
	$fields[] = array('type' => 'hidden', 'name' => 'fk_conducteur', 'value' => (int) $fk_conducteur);
	$fields[] = array('type' => 'hidden', 'name' => 'tags_state',    'value' => implode(',', $fk_tags));

	return array_merge($fields, $extra);
}

/**
 * Build the redirect URL that restores the OR-form state after a sub-dialog.
 *
 * @param  string $self       Value of $_SERVER['PHP_SELF']
 * @param  array  $overrides  Associative array of values to override (e.g. fk_conducteur => newId)
 * @return string
 */
function orCardRestoreUrl($self, $overrides = array())
{
	$fk_vehicule   = isset($overrides['fk_vehicule'])   ? (int) $overrides['fk_vehicule']   : GETPOSTINT('fk_vehicule');
	$km            = isset($overrides['km'])             ? $overrides['km']                  : GETPOST('km', 'alpha');
	$fk_conducteur = isset($overrides['fk_conducteur']) ? (int) $overrides['fk_conducteur'] : GETPOSTINT('fk_conducteur');

	// Tags: merge state string + overrides
	$tags_state = GETPOST('tags_state', 'alpha');
	$fk_tags    = isset($overrides['fk_tags'])
		? $overrides['fk_tags']
		: array_filter(array_map('intval', explode(',', (string) $tags_state)));

	$url  = $self.'?fk_vehicule='.$fk_vehicule;
	$url .= '&km='.urlencode((string) $km);
	$url .= '&fk_conducteur='.$fk_conducteur;
	foreach ($fk_tags as $t) {
		$url .= '&fk_tags[]='.((int) $t);
	}
	return $url;
}


/*
 * Actions
 */

if ($cancel) {
	if (!empty($backtopageforcancel)) {
		header("Location: ".$backtopageforcancel);
		exit;
	} elseif (!empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}
	header("Location: ".$_SERVER['PHP_SELF']);
	exit;
}

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {

	// ── Création d'un conducteur à la volée ─────────────────────────────────
	if ($action == 'confirm_new_conducteur' && $confirm == 'yes' && $permissiontoadd) {
		$error = 0;

		$conducteur         = new Conducteur($db);
		$conducteur->nom    = GETPOST('conducteur_nom', 'alphanohtml');
		$conducteur->prenom = GETPOST('conducteur_prenom', 'alphanohtml');

		if (empty(trim((string) $conducteur->nom))) {
			setEventMessages($langs->trans('ErrConducteurNomRequired'), null, 'errors');
			$error++;
		}
		if (empty(trim((string) $conducteur->prenom))) {
			setEventMessages($langs->trans('ErrConducteurPrenomRequired'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$newId = $conducteur->create($user);
			if ($newId > 0) {
				setEventMessage($langs->trans('ConducteurCreated'));
				header('Location: '.orCardRestoreUrl($_SERVER['PHP_SELF'], array('fk_conducteur' => $newId)));
				exit;
			} else {
				setEventMessages($conducteur->error, $conducteur->errors, 'errors');
			}
		}
		$action = 'new_conducteur';
	}

	// ── Création d'un tag à la volée ────────────────────────────────────────
	if ($action == 'confirm_new_tag' && $confirm == 'yes' && $permissiontoadd) {
		$error = 0;

		$tag         = new Tag($db);
		$tag->code   = GETPOST('tag_code', 'alphanohtml');
		$tag->label  = GETPOST('tag_label', 'alphanohtml');
		$tag->color  = GETPOST('tag_color', 'alphanohtml');
		$tag->active = 1;

		if (empty(trim((string) $tag->code))) {
			setEventMessages($langs->trans('MissingCode'), null, 'errors');
			$error++;
		}
		if (empty(trim((string) $tag->label))) {
			setEventMessages($langs->trans('MissingLabel'), null, 'errors');
			$error++;
		}

		if (!$error) {
			$newId = $tag->create($user);
			if ($newId > 0) {
				setEventMessage($langs->trans('TagCreated'));
				// Add the new tag to the current selection
				$tags_state = GETPOST('tags_state', 'alpha');
				$fk_tags    = array_filter(array_map('intval', explode(',', (string) $tags_state)));
				$fk_tags[]  = $newId;
				header('Location: '.orCardRestoreUrl($_SERVER['PHP_SELF'], array('fk_tags' => $fk_tags)));
				exit;
			} else {
				setEventMessages($tag->error, $tag->errors, 'errors');
			}
		}
		$action = 'new_tag';
	}

	// ── Création de l'OR ────────────────────────────────────────────────────
	if ($action == 'add' && $permissiontoadd) {
		$error = 0;

		$object->fk_vehicule   = GETPOSTINT('fk_vehicule');
		$object->km            = GETPOST('km', 'alpha');
		$object->fk_conducteur = GETPOSTINT('fk_conducteur');
		$object->status        = Operationorder::STATUS_DRAFT;
		$object->fk_user_creat = $user->id;

		// Dériver le tiers depuis le véhicule sélectionné
		if ($object->fk_vehicule > 0) {
			dol_include_once('/workshop/class/Vehicule.class.php');
			$vehicule = new Vehicule($db);
			if ($vehicule->fetch($object->fk_vehicule) > 0) {
				$object->fk_soc = (int) $vehicule->fk_soc;
			}
		}

		if (empty($object->fk_vehicule)) {
			setEventMessages($langs->trans('ErrInvalidFkVehicule'), null, 'errors');
			$error++;
		}
		if (empty($object->fk_soc)) {
			setEventMessages($langs->trans('ErrInvalidSocid'), null, 'errors');
			$error++;
		}

		$ret = $extrafields->setOptionalsFromPost(null, $object, '@GETPOSTISSET');
		if ($ret < 0) {
			$error++;
		}

		if (!$error) {
			$id = $object->create($user);
			if ($id > 0) {
				$tagIds = GETPOST('fk_tags', 'array:int');
				if (!empty($tagIds)) {
					$orTag = new OperationorderTag($db);
					foreach ($tagIds as $tagId) {
						$orTag->addTag($id, (int) $tagId, $user);
					}
				}
				header("Location: ".$_SERVER['PHP_SELF'].'?id='.$id);
				exit;
			} else {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = 'create';
			}
		} else {
			$action = 'create';
		}
	}
}


/*
 * View
 */

llxHeader('', $langs->trans('NewOperationOrders'), '', '', 0, 0, array(), array(), '', 'mod-workshop page-orcard');

print load_fiche_titre($langs->trans('NewOperationOrders'), '', 'fa-tools');

// ── Dialogs formconfirm (hors du formulaire principal) ──────────────────────

// Dialog : Nouveau conducteur
if ($action == 'new_conducteur' || $action == 'confirm_new_conducteur') {
	$fq = orCardStateHiddenFields(array(
		array(
			'type'  => 'text',
			'label' => $langs->trans('ConducteurNom').' <span class="fieldrequired">*</span>',
			'name'  => 'conducteur_nom',
			'value' => GETPOST('conducteur_nom', 'alphanohtml'),
		),
		array(
			'type'  => 'text',
			'label' => $langs->trans('ConducteurPrenom').' <span class="fieldrequired">*</span>',
			'name'  => 'conducteur_prenom',
			'value' => GETPOST('conducteur_prenom', 'alphanohtml'),
		),
	));
	print $form->formconfirm(
		$_SERVER['PHP_SELF'],
		$langs->trans('NewConducteur'),
		'',
		'confirm_new_conducteur',
		$fq,
		'yes',
		1,
		250,
		500,
		0,
		$langs->trans('Create'),
		$langs->trans('Cancel')
	);
}

// Dialog : Nouveau tag
if ($action == 'new_tag' || $action == 'confirm_new_tag') {
	$fq = orCardStateHiddenFields(array(
		array(
			'type'  => 'text',
			'label' => $langs->trans('Code').' <span class="fieldrequired">*</span>',
			'name'  => 'tag_code',
			'value' => GETPOST('tag_code', 'alphanohtml'),
		),
		array(
			'type'  => 'text',
			'label' => $langs->trans('TagLibelle').' <span class="fieldrequired">*</span>',
			'name'  => 'tag_label',
			'value' => GETPOST('tag_label', 'alphanohtml'),
		),
		array(
			'type'  => 'other',
			'label' => $langs->trans('TagCouleur'),
			'value' => '<input type="color" name="tag_color" value="'.dol_escape_htmltag(GETPOST('tag_color', 'alphanohtml') ?: '#3c7dd4').'" style="height:28px;width:60px;cursor:pointer;">',
		),
	));
	print $form->formconfirm(
		$_SERVER['PHP_SELF'],
		$langs->trans('NewTag'),
		'',
		'confirm_new_tag',
		$fq,
		'yes',
		1,
		220,
		480,
		0,
		$langs->trans('Create'),
		$langs->trans('Cancel')
	);
}

// ── Formulaire principal de création ────────────────────────────────────────

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">'."\n";
print '<input type="hidden" name="action" value="add">'."\n";
if ($backtopage) {
	print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">'."\n";
}

print dol_get_fiche_head(array(), '', $langs->trans('OperationOrder'), -1, 'fa-tools');

print '<table class="border centpercent tableforfieldcreate">'."\n";

// ── Véhicule (obligatoire) ───────────────────────────────────────────────────
// Affichage : VIN + immatriculation (les deux ont showoncombobox=1)
// Recherche : VIN, immatriculation (les deux ont searchall=1)
// Le tiers (fk_soc) est déduit automatiquement lors de la création.
print '<tr>';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans('Vehicule').'</td>';
print '<td>';
print $object->showInputField($object->fields['fk_vehicule'], 'fk_vehicule', GETPOSTINT('fk_vehicule'), '', '', '', 'maxwidth500');
print '</td>';
print '</tr>'."\n";

// ── Kilométrage ──────────────────────────────────────────────────────────────
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Km').'</td>';
print '<td>';
print $object->showInputField($object->fields['km'], 'km', GETPOST('km', 'alpha'), '', '', '', 'maxwidth100');
print '</td>';
print '</tr>'."\n";

// ── Conducteur + bouton "+" ──────────────────────────────────────────────────
print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Conducteur').'</td>';
print '<td>';
print $object->showInputField($object->fields['fk_conducteur'], 'fk_conducteur', GETPOSTINT('fk_conducteur'), '', '', '', 'maxwidth500');
print ' <a href="#" class="butActionNew" onclick="orCardOpenNewConducteur(); return false;" title="'.dol_escape_htmltag($langs->trans('NewConducteur')).'">';
print '<span class="fa fa-plus-circle valignmiddle"></span>';
print '</a>';
print '</td>';
print '</tr>'."\n";

// ── Tag(s) + bouton "+" ──────────────────────────────────────────────────────
$tagObj  = new Tag($db);
$allTags = $tagObj->getAllActiveTags();

print '<tr>';
print '<td class="titlefieldcreate">'.$langs->trans('Tags').'</td>';
print '<td>';
if (is_array($allTags) && !empty($allTags)) {
	$tagArray       = array();
	foreach ($allTags as $tid => $tag) {
		$tagArray[$tid] = $tag->label;
	}
	$selectedTagIds = GETPOST('fk_tags', 'array:int');
	print $form->multiselectarray('fk_tags', $tagArray, $selectedTagIds, 0, 0, 'maxwidth500', 0, 0, '', '', 1);
} else {
	print '<span class="opacitymedium">'.$langs->trans('NoTagDefined').'</span>';
}
print ' <a href="#" class="butActionNew" onclick="orCardOpenNewTag(); return false;" title="'.dol_escape_htmltag($langs->trans('NewTag')).'">';
print '<span class="fa fa-plus-circle valignmiddle"></span>';
print '</a>';
print '</td>';
print '</tr>'."\n";

print '</table>'."\n";

print dol_get_fiche_end();

print $form->buttonsSaveCancel("Create");

print '</form>'."\n";

// ── JavaScript ───────────────────────────────────────────────────────────────
$jsself = dol_escape_js($_SERVER['PHP_SELF']);
print '<script type="text/javascript">
/**
 * Lit la valeur d\'un champ FK (input hidden) ou d\'un input classique.
 */
function orCardGetField(name) {
	// Champ caché FK (ex: select2 Dolibarr)
	var $hidden = jQuery("input[name=\'" + name + "\'][type=\'hidden\']");
	if ($hidden.length) return $hidden.val() || "";
	// Input classique
	return jQuery("[name=\'" + name + "\']").val() || "";
}

/**
 * Collecte les tags sélectionnés dans le multi-select.
 */
function orCardGetTags() {
	var vals = jQuery("select[name=\'fk_tags[]\']").val();
	return vals ? vals : [];
}

/**
 * Construit la query-string d\'état pour préserver le formulaire.
 */
function orCardStateQS(overrides) {
	overrides = overrides || {};
	var fk_vehicule   = overrides.fk_vehicule   !== undefined ? overrides.fk_vehicule   : orCardGetField("fk_vehicule");
	var km            = overrides.km             !== undefined ? overrides.km            : orCardGetField("km");
	var fk_conducteur = overrides.fk_conducteur  !== undefined ? overrides.fk_conducteur : orCardGetField("fk_conducteur");
	var fk_tags       = overrides.fk_tags        !== undefined ? overrides.fk_tags       : orCardGetTags();

	var qs = "&fk_vehicule=" + encodeURIComponent(fk_vehicule);
	qs += "&km=" + encodeURIComponent(km);
	qs += "&fk_conducteur=" + encodeURIComponent(fk_conducteur);
	jQuery.each(fk_tags, function(i, v) { qs += "&fk_tags[]=" + encodeURIComponent(v); });
	return qs;
}

function orCardOpenNewConducteur() {
	window.location.href = "' . $jsself . '?action=new_conducteur" + orCardStateQS();
}

function orCardOpenNewTag() {
	window.location.href = "' . $jsself . '?action=new_tag" + orCardStateQS();
}
</script>
';

llxFooter();
$db->close();
