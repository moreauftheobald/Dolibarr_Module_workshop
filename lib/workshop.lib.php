<?php
/* Copyright (C) 2024 SuperAdmin <test@test.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    workshop/lib/workshop.lib.php
 * \ingroup workshop
 * \brief   Library files with common functions for Workshop
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function workshopAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabVehicules");
	$head[$h][2] = 'vehicules';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabOR");
	$head[$h][2] = 'ordres_reparation';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_divers.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabDivers");
	$head[$h][2] = 'divers';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_partage.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabPartageEntites");
	$head[$h][2] = 'partage_entites';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_objets_annexes.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabObjetsAnnexes");
	$head[$h][2] = 'objets_annexes';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshop@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshop@workshop', 'remove');

	return $head;
}


/**
 * Prepare sub-tabs header for the Objets Annexes admin page
 *
 * @return array
 */
function workshopObjetsAnnexesPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_marque.php", 1);
	$head[$h][1] = $langs->trans("VhSetupMarque");
	$head[$h][2] = 'marque';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_type.php", 1);
	$head[$h][1] = $langs->trans("VhSetupType");
	$head[$h][2] = 'type';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_typect.php", 1);
	$head[$h][1] = $langs->trans("VhSetupTypeCt");
	$head[$h][2] = 'typect';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_pneu.php", 1);
	$head[$h][1] = $langs->trans("VhSetupPneu");
	$head[$h][2] = 'pneu';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/operationorder/param/operationorder_setup_service_type.php", 1);
	$head[$h][1] = $langs->trans("WorkshopSetupServiceType");
	$head[$h][2] = 'service_type';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopobjetsannexes@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopobjetsannexes@workshop', 'remove');

	return $head;
}


/**
 * Prepare sub-tabs header for the OR admin page (setup_or.php and related pages)
 *
 * @return array
 */
function workshopORAdminPrepareHead(): array
{
	global $langs;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=general';
	$head[$h][1] = $langs->trans('WorkshopORSubTabGeneral');
	$head[$h][2] = 'general';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=planning';
	$head[$h][1] = $langs->trans('WorkshopORSubTabPlanning');
	$head[$h][2] = 'planning';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=statuts';
	$head[$h][1] = $langs->trans('WorkshopORSubTabStatuts');
	$head[$h][2] = 'statuts';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=commandes';
	$head[$h][1] = $langs->trans('WorkshopORSubTabCommandes');
	$head[$h][2] = 'commandes';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=facturation';
	$head[$h][1] = $langs->trans('WorkshopORSubTabFacturation');
	$head[$h][2] = 'facturation';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_or.php", 1).'?subtab=comptabilite';
	$head[$h][1] = $langs->trans('WorkshopORSubTabComptabilite');
	$head[$h][2] = 'comptabilite';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/admin/operationorder_extrafields.php", 1);
	$head[$h][1] = $langs->trans('WorkshopORSubTabExtrafields');
	$head[$h][2] = 'extrafields';
	$h++;

	return $head;
}


/**
 * Get registry of all workshop parameter objects.
 *
 * This is the single place to register parameter dictionary objects.
 * To add a new object, simply add an entry to the returned array.
 *
 * Each object entry supports:
 *   - class_file:     (string) Path for dol_include_once
 *   - class_name:     (string) PHP class name to instantiate
 *   - context:        (string) 'vehicule', 'atelier', or 'both'
 *   - tab_label:      (string) Translation key for the tab title
 *   - sort_field:     (string) Field name to sort the list by (default: 'label')
 *   - fields:         (array)  Field definitions (see below)
 *
 * Field definition keys:
 *   - type:           'text' | 'color' | 'select' | 'related_select' | 'societe'
 *   - label:          (string) Translation key for the field label
 *   - required:       (bool)   Field is required (default false)
 *   - values:         (array)  For 'select': static key => label array
 *   - related_class:  (string) For 'related_select': class name to load options from
 *   - related_file:   (string) For 'related_select': path to class file
 *   - allow_null:     (bool)   For 'related_select': allow null/0 choice
 *   - null_label:     (string) For 'related_select': translation key for null option
 *   - default:        (mixed)  Default value (for selects)
 *
 * @return array
 */
function getWorkshopParamObjects(): array
{
	return array(
		// ── Véhicule context ────────────────────────────────────────────────
		'marque' => array(
			'class_file' => '/workshop/class/vehiculemark.class.php',
			'class_name' => 'VehiculeMark',
			'context'    => 'vehicule',
			'tab_label'  => 'VhSetupMarque',
			'fields'     => array(
				'code'   => array('type' => 'text',   'label' => 'code',   'required' => true),
				'label'  => array('type' => 'text',   'label' => 'label',  'required' => true),
				'active' => array('type' => 'select', 'label' => 'active', 'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
		'type' => array(
			'class_file' => '/workshop/class/vehiculetype.class.php',
			'class_name' => 'VehiculeType',
			'context'    => 'vehicule',
			'tab_label'  => 'VhSetupType',
			'fields'     => array(
				'code'   => array('type' => 'text',   'label' => 'code',   'required' => true),
				'label'  => array('type' => 'text',   'label' => 'label',  'required' => true),
				'active' => array('type' => 'select', 'label' => 'active', 'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
		'typect' => array(
			'class_file' => '/workshop/class/vehiculecontracttype.class.php',
			'class_name' => 'VehiculeContractType',
			'context'    => 'vehicule',
			'tab_label'  => 'VhSetupTypeCt',
			'fields'     => array(
				'code'             => array('type' => 'text',           'label' => 'code',          'required' => true),
				'label'            => array('type' => 'text',           'label' => 'label',         'required' => true),
				'fk_vehicule_mark' => array(
					'type'          => 'related_select',
					'label'         => 'VehiculeMarkId',
					'related_class' => 'VehiculeMark',
					'related_file'  => '/workshop/class/vehiculemark.class.php',
					'allow_null'    => true,
					'null_label'    => 'AllMarks',
				),
				'active'           => array('type' => 'select', 'label' => 'active', 'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
		'pneu' => array(
			'class_file' => '/workshop/class/vehiculedimpneu.class.php',
			'class_name' => 'VehiculeDimPneu',
			'context'    => 'vehicule',
			'tab_label'  => 'VhSetupPneu',
			'fields'     => array(
				'code'   => array('type' => 'text',   'label' => 'code',   'required' => true),
				'label'  => array('type' => 'text',   'label' => 'label',  'required' => true),
				'active' => array('type' => 'select', 'label' => 'active', 'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
		'conducteur' => array(
			'class_file' => '/workshop/class/Conducteur.class.php',
			'class_name' => 'Conducteur',
			'context'    => 'vehicule',
			'tab_label'  => 'ConducteurList',
			'sort_field' => 'nom',
			'fields'     => array(
				'nom'    => array('type' => 'text',    'label' => 'ConducteurNom',    'required' => true),
				'prenom' => array('type' => 'text',    'label' => 'ConducteurPrenom', 'required' => true),
				'fk_soc' => array('type' => 'societe', 'label' => 'ConducteurSociete'),
			),
		),
		// ── Atelier / OR context ─────────────────────────────────────────────
		'tag' => array(
			'class_file' => '/workshop/class/Tag.class.php',
			'class_name' => 'Tag',
			'context'    => 'atelier',
			'tab_label'  => 'TagList',
			'fields'     => array(
				'code'   => array('type' => 'text',   'label' => 'TagCode',    'required' => true),
				'label'  => array('type' => 'text',   'label' => 'TagLibelle', 'required' => true),
				'color'  => array('type' => 'color',  'label' => 'TagCouleur', 'default' => '#3c7dc4'),
				'active' => array('type' => 'select', 'label' => 'active',     'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
	);
}


/**
 * Prepare array of tabs for the unified parameter page.
 *
 * @param  string $context Filter: 'vehicule', 'atelier', or '' for all
 * @return array
 */
function workshopUnifiedParamPrepareHead(string $context = ''): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h       = 0;
	$head    = array();
	$objects = getWorkshopParamObjects();

	foreach ($objects as $key => $config) {
		if (!empty($context) && $config['context'] !== 'both' && $config['context'] !== $context) {
			continue;
		}
		$url = dol_buildpath("/workshop/param/workshop_param_unified.php", 1).'?tab='.$key;
		if (!empty($context)) {
			$url .= '&context='.urlencode($context);
		}
		$head[$h][0] = $url;
		$head[$h][1] = $langs->trans($config['tab_label']);
		$head[$h][2] = $key;
		$h++;
	}

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopunifiedparam@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopunifiedparam@workshop', 'remove');

	return $head;
}


/**
 * Build a formconfirm question entry for a single field.
 *
 * @param  string      $fieldName   Field name (key in $objConfig['fields'])
 * @param  array       $fieldConfig Field configuration array
 * @param  object|null $dataEdit    Existing object for edit mode (null = new)
 * @param  DoliDB      $db          Database handler
 * @param  Translate   $langs       Translation object
 * @return array                    Array element ready for formconfirm $formquestion
 */
function workshopBuildParamFormQuestion(string $fieldName, array $fieldConfig, $dataEdit, $db, $langs): array
{
	$value = ($dataEdit !== null && isset($dataEdit->$fieldName)) ? $dataEdit->$fieldName : '';

	if ($fieldConfig['type'] === 'text') {
		return array(
			'type'  => 'text',
			'label' => $langs->trans($fieldConfig['label']),
			'name'  => $fieldName,
			'value' => $value,
		);
	}

	if ($fieldConfig['type'] === 'select') {
		$default = ($dataEdit !== null) ? $value : (isset($fieldConfig['default']) ? $fieldConfig['default'] : '0');
		return array(
			'type'    => 'select',
			'label'   => $langs->trans($fieldConfig['label']),
			'name'    => $fieldName,
			'values'  => $fieldConfig['values'],
			'default' => $default,
		);
	}

	if ($fieldConfig['type'] === 'related_select') {
		dol_include_once($fieldConfig['related_file']);
		$relObj  = new $fieldConfig['related_class']($db);
		$options = $relObj->getAllActiveArray('label');
		if (!is_array($options)) {
			$options = array();
		}
		if (!empty($fieldConfig['allow_null'])) {
			$nullLabel = !empty($fieldConfig['null_label']) ? $langs->trans($fieldConfig['null_label']) : '-';
			$options   = array('0' => $nullLabel) + $options;
		}
		$default = ($dataEdit !== null) ? (int) $value : 0;
		return array(
			'type'    => 'select',
			'label'   => $langs->trans($fieldConfig['label']),
			'name'    => $fieldName,
			'values'  => $options,
			'default' => $default,
		);
	}

	if ($fieldConfig['type'] === 'color') {
		$colorVal = !empty($value) ? $value : (isset($fieldConfig['default']) ? $fieldConfig['default'] : '#000000');
		// Use type='text' so Dolibarr's formconfirm correctly includes the value in the GET submission.
		// A companion color picker (<input type="color">) is injected via JavaScript (see workshop_param_unified.php).
		return array(
			'type'  => 'text',
			'label' => $langs->trans($fieldConfig['label']),
			'name'  => $fieldName,
			'value' => dol_escape_htmltag($colorVal),
		);
	}

	if ($fieldConfig['type'] === 'societe') {
		global $form;
		if (!is_object($form)) {
			$form = new Form($db);
		}
		$selectedId = ($dataEdit !== null && !empty($dataEdit->$fieldName)) ? (int) $dataEdit->$fieldName : 0;
		$html = $form->select_company($selectedId, $fieldName, '', $langs->trans('SelectThirdParty'), 1, 0, array(), 0, 'minwidth300');
		return array(
			'type'  => 'other',
			'label' => $langs->trans($fieldConfig['label']),
			'name'  => $fieldName,
			'value' => $html,
		);
	}

	return array();
}


/**
 * Render a field value as HTML for display in a list row.
 *
 * @param  string    $fieldName   Field name
 * @param  array     $fieldConfig Field configuration array
 * @param  object    $data        Object instance with populated properties
 * @param  DoliDB    $db          Database handler
 * @param  Translate $langs       Translation object
 * @return string                 HTML string
 */
function workshopRenderParamFieldValue(string $fieldName, array $fieldConfig, $data, $db, $langs): string
{
	$value = isset($data->$fieldName) ? $data->$fieldName : '';

	if ($fieldConfig['type'] === 'text') {
		return dol_escape_htmltag($value);
	}

	if ($fieldConfig['type'] === 'select') {
		if ($fieldName === 'active') {
			return '<span>'.img_picto($langs->trans('off'), $value == 1 ? 'switch_on' : 'switch_off').'</span>';
		}
		return isset($fieldConfig['values'][$value]) ? dol_escape_htmltag($fieldConfig['values'][$value]) : dol_escape_htmltag($value);
	}

	if ($fieldConfig['type'] === 'related_select') {
		if (empty($value)) {
			return !empty($fieldConfig['null_label']) ? $langs->trans($fieldConfig['null_label']) : '-';
		}
		dol_include_once($fieldConfig['related_file']);
		$relObj = new $fieldConfig['related_class']($db);
		return dol_escape_htmltag($relObj->getValueFromId((int) $value, 'label'));
	}

	if ($fieldConfig['type'] === 'color') {
		if (empty($value)) {
			return '-';
		}
		// Normalize: ensure '#' prefix for valid CSS
		$hex = ($value[0] !== '#') ? '#'.$value : $value;
		$v   = dol_escape_htmltag($hex);
		return '<span style="display:inline-block;width:14px;height:14px;background-color:'.$v
			.';border:1px solid #999;vertical-align:middle;border-radius:2px;"></span>&nbsp;'.$v;
	}

	if ($fieldConfig['type'] === 'societe') {
		if (empty($value)) {
			return '-';
		}
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		if ($soc->fetch((int) $value) > 0) {
			return dol_escape_htmltag($soc->name);
		}
		return '-';
	}

	return dol_escape_htmltag($value);
}


/**
 * Prepare array of tabs for Vehicule Setup screen
 * @return    array                    Array of tabs
 */
function workshopSetupPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/workshop/operationorder/param/operationorder_setup_service_type.php", 1);
	$head[$h][1] = $langs->trans("WorkshopSetupServiceType");
	$head[$h][2] = 'service_type';
	$h++;


	complete_head_from_modules($conf, $langs,null, $head, $h, 'workshopsetup@workshop');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopsetup@workshop', 'remove');

	return $head;
}
