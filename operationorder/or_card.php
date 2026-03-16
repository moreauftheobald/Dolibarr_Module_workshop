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
 * \brief      Création et affichage (vue) d'un Ordre de Réparation (OR)
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
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/workshop/class/operationorder.class.php');
dol_include_once('/workshop/class/Vehicule.class.php');
dol_include_once('/workshop/class/Conducteur.class.php');
dol_include_once('/workshop/class/Tag.class.php');
dol_include_once('/workshop/class/OperationorderTag.class.php');
dol_include_once('/workshop/lib/workshop_operationorder.lib.php');
dol_include_once('/workshop/lib/workshop.lib.php');

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
$id          = GETPOSTINT('id');
$backtopage  = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

$object      = new Operationorder($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);

$extrafields->fetch_name_optionals_label($object->table_element);
$hookmanager->initHooks(array('orcard', 'globalcard'));

$permissiontoadd = $user->hasRight('workshop', 'operationorders', 'write');

// En mode vue : chargement de l'objet depuis la base
if ($id > 0) {
	$ret = $object->fetch($id);
	if ($ret <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}


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
 * Build the URL fragment that carries the OR-form state for a sub-dialog.
 * Returns a full query string (starting with '?') ready to be appended to PHP_SELF.
 *
 * @param  array $overrides  Associative array of values to override
 * @return string             URL starting with '?'
 */
function orCardStatePageUrl($overrides = array())
{
	$fk_vehicule   = isset($overrides['fk_vehicule'])   ? (int) $overrides['fk_vehicule']   : GETPOSTINT('fk_vehicule');
	$km            = isset($overrides['km'])             ? $overrides['km']                  : GETPOST('km', 'alpha');
	$fk_conducteur = isset($overrides['fk_conducteur']) ? (int) $overrides['fk_conducteur'] : GETPOSTINT('fk_conducteur');
	$fk_tags       = isset($overrides['fk_tags'])
		? $overrides['fk_tags']
		: array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));

	$url  = '?fk_vehicule='.$fk_vehicule;
	$url .= '&km='.urlencode((string) $km);
	$url .= '&fk_conducteur='.$fk_conducteur;
	foreach ($fk_tags as $t) {
		$url .= '&fk_tags[]='.((int) $t);
	}
	return $url;
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

	// Tags: use overrides or read fk_tags[] from request (GET or POST)
	$fk_tags = isset($overrides['fk_tags'])
		? $overrides['fk_tags']
		: array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));

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
	if ($id > 0) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
	} elseif (!empty($backtopageforcancel)) {
		header('Location: '.$backtopageforcancel);
	} elseif (!empty($backtopage)) {
		header('Location: '.$backtopage);
	} else {
		header('Location: '.$_SERVER['PHP_SELF']);
	}
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
		$conducteur->fk_soc = GETPOSTINT('conducteur_fk_soc') ?: null;

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
		$rawColor    = GETPOST('tag_color', 'nohtml');
		$palette     = getWorkshopColorPalette();
		$tag->color  = isset($palette[$rawColor]) ? $rawColor : '';
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
				$fk_tags    = array_filter(array_map('intval', (array) GETPOST('fk_tags', 'array:int')));
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

		$object->entity =$conf->entity;
		$object->fk_vehicule   = GETPOSTINT('fk_vehicule');
		$object->km            = GETPOST('km', 'alpha');
		$object->fk_conducteur = GETPOSTINT('fk_conducteur');
		$object->fk_user_creat = $user->id;

		// Statut défini dans le paramètre admin, fallback STATUS_DRAFT
		$statusOnCreate = getDolGlobalInt('WORKSHOP_OR_STATUS_ON_CREATE');
		$object->status = $statusOnCreate > 0 ? $statusOnCreate : Operationorder::STATUS_DRAFT;

		// Totaux à 0
		$object->total_ht          = 0;
		$object->total_ht_part     = 0;
		$object->total_ht_mo       = 0;
		$object->total_ht_service  = 0;
		$object->total_ht_external = 0;
		$object->total_ht_refund   = 0;

		// Dates de planification et clôture à NULL, dates techniques à NULL
		$object->date_planned = null;
		$object->date_valid   = null;
		$object->date_start   = null;
		$object->date_end     = null;

		// Dériver le tiers depuis le véhicule sélectionné
		if ($object->fk_vehicule > 0) {
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

		// Référence via le module de numérotation configuré dans l'admin
		if (!$error) {
			$nextRef = $object->getNextNumRef();
			if (!empty($nextRef)) {
				$object->ref = $nextRef;
			}
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

	// ── Mode vue : sauvegarde des éditions en ligne ──────────────────────────

	if ($action == 'save_fk_soc' && $id > 0 && $permissiontoadd) {
		$object->fk_soc = GETPOSTINT('fk_soc');
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_ref_client' && $id > 0 && $permissiontoadd) {
		$object->ref_client = GETPOST('ref_client', 'alphanohtml');
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_fk_vehicule' && $id > 0 && $permissiontoadd) {
		$object->fk_vehicule = GETPOSTINT('fk_vehicule');
		// Re-synchroniser le tiers avec le nouveau véhicule
		if ($object->fk_vehicule > 0) {
			$vehicule = new Vehicule($db);
			if ($vehicule->fetch($object->fk_vehicule) > 0) {
				$object->fk_soc = (int) $vehicule->fk_soc;
			}
		}
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_date_or' && $id > 0 && $permissiontoadd) {
		$object->date_valid = dol_mktime(
			GETPOSTINT('date_orhour'), GETPOSTINT('date_ormin'), 0,
			GETPOSTINT('date_ormonth'), GETPOSTINT('date_orday'), GETPOSTINT('date_oryear')
		);
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_date_planned' && $id > 0 && $permissiontoadd) {
		$object->date_planned = dol_mktime(
			GETPOSTINT('date_plannedhour'), GETPOSTINT('date_plannedmin'), 0,
			GETPOSTINT('date_plannedmonth'), GETPOSTINT('date_plannedday'), GETPOSTINT('date_plannedyear')
		);
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_fk_conducteur' && $id > 0 && $permissiontoadd) {
		$object->fk_conducteur = GETPOSTINT('fk_conducteur') ?: null;
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_km' && $id > 0 && $permissiontoadd) {
		$object->km = price2num(GETPOST('km', 'alpha'));
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}

	if ($action == 'save_check_or' && $id > 0 && $user->admin) {
		$object->check_or = GETPOSTINT('check_or');
		if ($object->update($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
		exit;
	}
}


/*
 * View
 */

// ═══════════════════════════════════════════════════════════════════════════
// MODE VUE : affichage de la fiche OR existante
// ═══════════════════════════════════════════════════════════════════════════
if ($id > 0) {

	llxHeader('', $object->ref.' — '.$langs->trans('OperationOrder'), '', '', 0, 0, array(), array(), '', 'mod-workshop page-orcard');

	$head = operationorderPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('OperationOrder'), -1, 'fa-tools', 0, '', '', 0, '', 1);

	$linkback = '<a href="'.dol_buildpath('/workshop/operationorder/or_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';

	// ── Construction du morehtmlref (lignes sous le numéro OR dans le bandeau)

	// Tags de l'OR — pastilles colorées juste sous le numéro
	$orTagObj  = new OperationorderTag($db);
	$orTagList = $orTagObj->getTagsForOR($object->id);
	$morehtmlref = '';
	if (is_array($orTagList) && !empty($orTagList)) {
		$morehtmlref .= '<div style="margin-bottom:6px;">';
		foreach ($orTagList as $tag) {
			$morehtmlref .= $tag->getNomUrl().' ';
		}
		$morehtmlref .= '</div>';
	}

	$morehtmlref .= '<div class="refidno">';

	// Société (modifiable en ligne)
	$morehtmlref .= $langs->trans('ThirdParty').': ';
	if ($action == 'editfk_soc' && $permissiontoadd) {
		$morehtmlref .= '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block">';
		$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
		$morehtmlref .= '<input type="hidden" name="action" value="save_fk_soc">';
		$morehtmlref .= '<input type="hidden" name="id" value="'.$object->id.'">';
		$morehtmlref .= $object->showInputField($object->fields['fk_soc'], 'fk_soc', $object->fk_soc, '', '', '', 'minwidth200');
		$morehtmlref .= ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		$morehtmlref .= ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		$morehtmlref .= '</form>';
	} else {
		if ($object->fk_soc > 0) {
			$soc = new Societe($db);
			$soc->fetch($object->fk_soc);
			$morehtmlref .= $soc->getNomUrl(1);
		} else {
			$morehtmlref .= '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		if ($permissiontoadd) {
			$morehtmlref .= ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editfk_soc">'.img_edit().'</a>';
		}
	}

	$morehtmlref .= '<br>';

	// Référence client (modifiable en ligne)
	$morehtmlref .= $langs->trans('RefClient').': ';
	if ($action == 'editref_client' && $permissiontoadd) {
		$morehtmlref .= '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block">';
		$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
		$morehtmlref .= '<input type="hidden" name="action" value="save_ref_client">';
		$morehtmlref .= '<input type="hidden" name="id" value="'.$object->id.'">';
		$morehtmlref .= '<input type="text" name="ref_client" class="minwidth200" value="'.dol_escape_htmltag((string) $object->ref_client).'">';
		$morehtmlref .= ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		$morehtmlref .= ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		$morehtmlref .= '</form>';
	} else {
		$morehtmlref .= (!empty($object->ref_client) ? dol_escape_htmltag($object->ref_client) : '<span class="opacitymedium">'.$langs->trans('None').'</span>');
		if ($permissiontoadd) {
			$morehtmlref .= ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editref_client">'.img_edit().'</a>';
		}
	}

	$morehtmlref .= '<br>';

	// Véhicule (modifiable en ligne)
	$morehtmlref .= $langs->trans('Vehicule').': ';
	if ($action == 'editfk_vehicule' && $permissiontoadd) {
		$morehtmlref .= '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline-block">';
		$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
		$morehtmlref .= '<input type="hidden" name="action" value="save_fk_vehicule">';
		$morehtmlref .= '<input type="hidden" name="id" value="'.$object->id.'">';
		$morehtmlref .= $object->showInputField($object->fields['fk_vehicule'], 'fk_vehicule', $object->fk_vehicule, '', '', '', 'minwidth200');
		$morehtmlref .= ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		$morehtmlref .= ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		$morehtmlref .= '</form>';
	} else {
		if ($object->fk_vehicule > 0) {
			$vehicule = new Vehicule($db);
			if ($vehicule->fetch($object->fk_vehicule) > 0) {
				$morehtmlref .= $vehicule->getNomUrl(1);
			}
		} else {
			$morehtmlref .= '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		if ($permissiontoadd) {
			$morehtmlref .= ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editfk_vehicule">'.img_edit().'</a>';
		}
	}

	$morehtmlref .= '</div>';

	// ── Bandeau ──────────────────────────────────────────────────────────────
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', $object->getLibStatut(4));

	// ── Chargement du véhicule pour la colonne droite ─────────────────────
	$vehiculeObj = null;
	if ($object->fk_vehicule > 0) {
		$vehiculeObj = new Vehicule($db);
		if ($vehiculeObj->fetch($object->fk_vehicule) <= 0) {
			$vehiculeObj = null;
		}
	}

	// ── Labels check_or ───────────────────────────────────────────────────
	$checkOrLabels = array(
		0 => $langs->trans('CheckORNonVerifie'),
		1 => $langs->trans('CheckOREnCours'),
		2 => $langs->trans('CheckORVerifieOK'),
		3 => $langs->trans('CheckORVerifieReserves'),
	);
	$checkOrBadgeTypes = array(
		0 => 'secondary',
		1 => 'warning',
		2 => 'success',
		3 => 'info',
	);

	// ── Layout deux colonnes ───────────────────────────────────────────────
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';

	// ── Colonne gauche : informations de l'OR ─────────────────────────────
	print '<table class="border centpercent tableforfield">'."\n";

	// ── Date création (calculée !) ────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('DateCreation').'</td>';
	print '<td>';
	print dol_print_date($object->date_creation, 'dayhour');
	print '</td></tr>'."\n";

	// ── Date OR (date_valid) — éditable * ─────────────────────────────────
	print '<tr><td>';
	print $form->editfieldkey($langs->trans('DateOR'), 'date_or', $object->date_valid, $object, $permissiontoadd);
	print '</td><td>';
	if ($action == 'editdate_or' && $permissiontoadd) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_date_or">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print $form->selectDate($object->date_valid ?: -1, 'date_or', 1, 1, 0, '', 1, 1);
		print ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		print '</form>';
	} else {
		print $form->editfieldval($langs->trans('DateOR'), 'date_or', $object->date_valid, $object, $permissiontoadd, 'dayhour');
	}
	print '</td></tr>'."\n";

	// ── Date planification — éditable * ───────────────────────────────────
	print '<tr><td>';
	print $form->editfieldkey($langs->trans('DatePlanned'), 'date_planned', $object->date_planned, $object, $permissiontoadd);
	print '</td><td>';
	if ($action == 'editdate_planned' && $permissiontoadd) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_date_planned">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print $form->selectDate($object->date_planned ?: -1, 'date_planned', 1, 1, 0, '', 1, 1);
		print ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		print '</form>';
	} else {
		print $form->editfieldval($langs->trans('DatePlanned'), 'date_planned', $object->date_planned, $object, $permissiontoadd, 'dayhour');
	}
	print '</td></tr>'."\n";

	// ── Temps d'immobilisation théorique (calculé !) ───────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('TempsImmobilisationTheo').'</td>';
	print '<td>';
	if (!empty($object->temps_immobilisation)) {
		print number_format((float) $object->temps_immobilisation, 2).' '.$langs->trans('Hours');
	} else {
		print '<span class="opacitymedium">'.$langs->trans('NA').'</span>';
	}
	print '</td></tr>'."\n";

	// ── Mécanicien (calculé !) ────────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('Mechanic').'</td>';
	print '<td>';
	if ($object->fk_user_assign > 0) {
		$mechUser = new User($db);
		if ($mechUser->fetch($object->fk_user_assign) > 0) {
			print $mechUser->getNomUrl(1);
		} else {
			print $object->fk_user_assign;
		}
	} else {
		print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
	}
	print '</td></tr>'."\n";

	// ── Totaux HT (calculés !) ────────────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('TotalHTMO').'</td>';
	print '<td>'.price($object->total_ht_mo).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTPart').'</td>';
	print '<td>'.price($object->total_ht_part).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTService').'</td>';
	print '<td>'.price($object->total_ht_service).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTExternal').'</td>';
	print '<td>'.price($object->total_ht_external).'</td></tr>'."\n";

	print '<tr><td class="titlefield">'.$langs->trans('TotalHTRefund').'</td>';
	print '<td>'.price($object->total_ht_refund).'</td></tr>'."\n";

	print '<tr><td class="titlefield"><strong>'.$langs->trans('TotalHT').'</strong></td>';
	print '<td><strong>'.price($object->total_ht).'</strong></td></tr>'."\n";

	// ── Statut du Check OR (++ : éditable par admin uniquement) ───────────
	print '<tr><td class="titlefield">'.$langs->trans('CheckOR');
	if ($user->admin) {
		print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editcheck_or">'.img_edit().'</a>';
	}
	print '</td><td>';
	if ($action == 'editcheck_or' && $user->admin) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_check_or">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print $form->selectarray('check_or', $checkOrLabels, (int) $object->check_or, 0);
		print ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		print '</form>';
	} else {
		$checkOrVal = (int) $object->check_or;
		$checkOrLabel = isset($checkOrLabels[$checkOrVal]) ? $checkOrLabels[$checkOrVal] : $checkOrLabels[0];
		$checkOrBadge = isset($checkOrBadgeTypes[$checkOrVal]) ? $checkOrBadgeTypes[$checkOrVal] : 'secondary';
		print dolGetBadge($checkOrLabel, '', $checkOrBadge);
	}
	print '</td></tr>'."\n";

	print '</table>'."\n";
	print '</div>'; // fichehalfleft

	// ── Colonne droite : informations du véhicule ─────────────────────────
	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">'."\n";

	if ($vehiculeObj) {

		// ── Opérations disponibles (calculé !) ───────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('OperationsDisponibles').'</td>';
		print '<td>';
		dol_include_once('/workshop/class/vehiculeOperation.class.php');
		$vehiculeObj->getOperations();
		if (!empty($vehiculeObj->operations)) {
			$opLabels = array();
			foreach ($vehiculeObj->operations as $ope) {
				if (!empty($ope->fk_product)) {
					require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
					$prod = new Product($db);
					if ($prod->fetch($ope->fk_product) > 0) {
						$opLabels[] = $prod->getNomUrl(1);
					}
				}
			}
			if ($opLabels) {
				print implode(', ', $opLabels);
			} else {
				print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		print '</td></tr>'."\n";

		// ── Date première mise en circulation (calculé !) ─────────────────
		print '<tr><td class="titlefield">'.$langs->trans('immatriculation_date').'</td>';
		print '<td>';
		print dol_print_date($vehiculeObj->date_immat, 'day');
		print '</td></tr>'."\n";

		// ── Type de véhicule (calculé !) ──────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('vehiculeType').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_vehicule_type'], 'fk_vehicule_type', $vehiculeObj->fk_vehicule_type, '');
		print '</td></tr>'."\n";

		// ── Marque du véhicule (calculé !) ────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('vehiculeMark').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_vehicule_mark'], 'fk_vehicule_mark', $vehiculeObj->fk_vehicule_mark, '');
		print '</td></tr>'."\n";

		// ── Modèle (calculé !) ────────────────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('modele').'</td>';
		print '<td>';
		print dol_escape_htmltag((string) $vehiculeObj->modele);
		print '</td></tr>'."\n";

		// ── Type de contrat (calculé !) ───────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('contractType').'</td>';
		print '<td>';
		print $vehiculeObj->showOutputField($vehiculeObj->fields['fk_contract_type'], 'fk_contract_type', $vehiculeObj->fk_contract_type, '');
		print '</td></tr>'."\n";

		// ── Véhicule lié (calculé !) ──────────────────────────────────────
		print '<tr><td class="titlefield">'.$langs->trans('VehiculeLie').'</td>';
		print '<td>';
		$linkedVehicules = $vehiculeObj->getLinkedVehicules();
		if (is_array($linkedVehicules) && !empty($linkedVehicules)) {
			$lv = reset($linkedVehicules); // Véhicule lié courant
			$linkedVeh = new Vehicule($db);
			if ($linkedVeh->fetch($lv->fk_vehicule_linked) > 0) {
				print $linkedVeh->getNomUrl(1);
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		print '</td></tr>'."\n";

	} else {
		print '<tr><td colspan="2"><span class="opacitymedium">'.$langs->trans('NoVehiculeLinked').'</span></td></tr>'."\n";
	}

	// ── Conducteur (depuis l'OR) — éditable * ────────────────────────────
	print '<tr><td class="titlefield">';
	print $form->editfieldkey($langs->trans('Conducteur'), 'fk_conducteur', $object->fk_conducteur, $object, $permissiontoadd);
	print '</td><td>';
	if ($action == 'editfk_conducteur' && $permissiontoadd) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_fk_conducteur">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print $object->showInputField($object->fields['fk_conducteur'], 'fk_conducteur', $object->fk_conducteur, '', '', '', 'maxwidth300');
		print ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		print '</form>';
	} else {
		if ($object->fk_conducteur > 0) {
			$conducteurObj = new Conducteur($db);
			if ($conducteurObj->fetch($object->fk_conducteur) > 0) {
				print $conducteurObj->getNomUrl(1);
			} else {
				print $object->fk_conducteur;
			}
		} else {
			print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
		}
		if ($permissiontoadd) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editfk_conducteur">'.img_edit().'</a>';
		}
	}
	print '</td></tr>'."\n";

	// ── Kilométrage à la création de l'OR — éditable * ───────────────────
	print '<tr><td class="titlefield">';
	print $form->editfieldkey($langs->trans('KmCreationOR'), 'km', $object->km, $object, $permissiontoadd);
	print '</td><td>';
	if ($action == 'editkm' && $permissiontoadd) {
		print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="save_km">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="text" name="km" class="maxwidth100" value="'.dol_escape_htmltag((string) $object->km).'">';
		print ' <input type="submit" class="button buttongen smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'">';
		print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">'.img_picto($langs->trans('Cancel'), 'undo').'</a>';
		print '</form>';
	} else {
		print (!empty($object->km) ? dol_escape_htmltag(number_format((float) $object->km, 0, ',', ' ')).' km' : '<span class="opacitymedium">'.$langs->trans('NA').'</span>');
		if ($permissiontoadd) {
			print ' <a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=editkm">'.img_edit().'</a>';
		}
	}
	print '</td></tr>'."\n";

	// ── Date de clôture (calculé !) ───────────────────────────────────────
	print '<tr><td class="titlefield">'.$langs->trans('DateCloture').'</td>';
	print '<td>';
	if ($object->date_end) {
		print dol_print_date($object->date_end, 'dayhour');
	} else {
		print '<span class="opacitymedium">'.$langs->trans('NA').'</span>';
	}
	print '</td></tr>'."\n";

	print '</table>'."\n";
	print '</div>'; // fichehalfright
	print '</div>'; // fichecenter

	print dol_get_fiche_end();

	llxFooter();
	$db->close();
	exit;
}


// ═══════════════════════════════════════════════════════════════════════════
// MODE CRÉATION : formulaire de saisie d'un nouvel OR
// ═══════════════════════════════════════════════════════════════════════════

llxHeader('', $langs->trans('NewOperationOrders'), '', '', 0, 0, array(), array(), '', 'mod-workshop page-orcard');

print load_fiche_titre($langs->trans('NewOperationOrders'), '', 'fa-tools');

// ── Dialogs formconfirm (hors du formulaire principal) ──────────────────────

// Dialog : Nouveau conducteur
if ($action == 'new_conducteur' || $action == 'confirm_new_conducteur') {
	// L'état du formulaire OR est passé dans l'URL (GET) — pas de hidden dans formquestion
	$pageUrl = $_SERVER['PHP_SELF'].orCardStatePageUrl();
	$fq = array(
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
		array(
			'type'  => 'other',
			'label' => $langs->trans('ConducteurSociete'),
			'name'  => 'conducteur_fk_soc',
			'value' => $form->select_company(GETPOSTINT('conducteur_fk_soc'), 'conducteur_fk_soc', '', $langs->trans('SelectThirdParty'), 1, 0, array(), 0, 'minwidth200'),
		),
	);
	print $form->formconfirm(
		$pageUrl,
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
	// L'état du formulaire OR est passé dans l'URL (GET) — pas de hidden dans formquestion
	$pageUrl = $_SERVER['PHP_SELF'].orCardStatePageUrl();
	$fq = array(
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
		(function () use ($langs) {
			$palette  = getWorkshopColorPalette();
			$rawColor = GETPOST('tag_color', 'nohtml');
			$selected = isset($palette[$rawColor]) ? $rawColor : '#3c7dc4';
			if (!isset($palette[$selected])) {
				$selected = key($palette);
			}
			return array(
				'type'    => 'select',
				'label'   => $langs->trans('TagCouleur'),
				'name'    => 'tag_color',
				'values'  => $palette,
				'default' => $selected,
			);
		})(),
	);
	print $form->formconfirm(
		$pageUrl,
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
	print $form->multiselectarray('fk_tags', $tagArray, $selectedTagIds, 0, 0, 'minwidth300 widthcentpercent', 0, 0, '', '', 1);
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

// Colour select enhancement: show a coloured swatch next to palette <select> elements.
(function () {
	function enhanceColorSelects() {
		document.querySelectorAll("select").forEach(function (sel) {
			if (sel.dataset.wsColorEnhanced) return;
			if (!sel.options.length) return;
			if (!/^#[0-9a-fA-F]{6}$/i.test(sel.options[0].value)) return;
			sel.dataset.wsColorEnhanced = "1";
			var swatch = document.createElement("span");
			swatch.style.cssText = "display:inline-block;width:18px;height:18px;border-radius:3px;"
				+ "border:1px solid #999;vertical-align:middle;margin-left:6px;background-color:" + sel.value + ";";
			sel.parentNode.insertBefore(swatch, sel.nextSibling);
			sel.addEventListener("change", function () { swatch.style.backgroundColor = sel.value; });
		});
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", enhanceColorSelects);
	} else {
		enhanceColorSelects();
	}
	if (typeof jQuery !== "undefined") {
		jQuery(document).on("dialogopen", function () { setTimeout(enhanceColorSelects, 50); });
	}
})();
</script>
';

llxFooter();
$db->close();
