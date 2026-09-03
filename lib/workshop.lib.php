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

	$head[$h][0] = dol_buildpath("/workshop/vehicule/param/vh_setup_marque.php", 1);
	$head[$h][1] = $langs->trans("WorkshopAdminTabDictVehicule");
	$head[$h][2] = 'objets_annexes';
	$h++;

	$head[$h][0] = dol_buildpath("/workshop/param/workshop_param_unified.php", 1).'?context=atelier';
	$head[$h][1] = $langs->trans("WorkshopAdminTabDictAtelier");
	$head[$h][2] = 'dict_atelier';
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

	$head[$h][0] = dol_buildpath("/workshop/admin/setup_impro.php", 1);
	$head[$h][1] = $langs->trans('WorkshopORSubTabImpro');
	$head[$h][2] = 'improductifs';
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
		'maintenance_op' => array(
			'class_file' => '/workshop/class/vehiculemaintenanceoperation.class.php',
			'class_name' => 'VehiculeMaintenanceOperation',
			'context'    => 'vehicule',
			'tab_label'  => 'MaintenanceOperationList',
			'fields'     => array(
				'code'             => array('type' => 'text',   'label' => 'Code',         'required' => true),
				'label'            => array('type' => 'text',   'label' => 'Label',        'required' => true),
				'fk_vehicule_type' => array(
					'type'          => 'related_select',
					'label'         => 'VehiculeType',
					'related_class' => 'VehiculeType',
					'related_file'  => '/workshop/class/vehiculetype.class.php',
					'allow_null'    => true,
					'null_label'    => 'AllTypes',
				),
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
		// ── Atelier / OR context ─────────────────────────────────────────────
		'service_type' => array(
			'class_file' => '/workshop/class/servicetype.class.php',
			'class_name' => 'ServiceType',
			'context'    => 'atelier',
			'tab_label'  => 'WorkshopSetupServiceType',
			'fields'     => array(
				'code'      => array('type' => 'text',   'label' => 'Code',               'required' => true),
				'label'     => array('type' => 'text',   'label' => 'Label',              'required' => true),
				'fk_soc'    => array('type' => 'societe', 'label' => 'BillingThirdParty'),
				'prix_mo'   => array('type' => 'number', 'label' => 'PrixMO'),
				'tva_tx_mo' => array('type' => 'tva',    'label' => 'TvaTxMO'),
				'tva_tx_st' => array('type' => 'tva',    'label' => 'TvaTxST'),
				'plannable' => array('type' => 'select', 'label' => 'JobTypePlannable',   'values' => array('0' => 'No', '1' => 'Yes'), 'default' => '0'),
				'doc_obl'   => array('type' => 'file',   'label' => 'WorkshopDocObl',     'file_accept' => 'application/pdf'),
				'active'    => array('type' => 'select', 'label' => 'active',             'values' => array('0' => 'Non', '1' => 'Oui'), 'default' => '1'),
			),
		),
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
 * Returns the predefined colour palette available for tag/label colour fields.
 * Keys are lowercase 7-character hex codes (#rrggbb); values are translated labels.
 *
 * @return array<string,string>
 */
function getWorkshopColorPalette(): array
{
	global $langs;
	$langs->load('workshop@workshop');

	return array(
		'#dc3545' => $langs->trans('WorkshopColorRed'),
		'#e8561a' => $langs->trans('WorkshopColorRedOrange'),
		'#fd7e14' => $langs->trans('WorkshopColorOrange'),
		'#ffc107' => $langs->trans('WorkshopColorYellow'),
		'#8bc34a' => $langs->trans('WorkshopColorLightGreen'),
		'#28a745' => $langs->trans('WorkshopColorGreen'),
		'#20c997' => $langs->trans('WorkshopColorTurquoise'),
		'#17a2b8' => $langs->trans('WorkshopColorCyan'),
		'#3c7dc4' => $langs->trans('WorkshopColorBlue'),
		'#007bff' => $langs->trans('WorkshopColorBrightBlue'),
		'#6f42c1' => $langs->trans('WorkshopColorPurple'),
		'#e83e8c' => $langs->trans('WorkshopColorPink'),
		'#6c757d' => $langs->trans('WorkshopColorGrey'),
		'#343a40' => $langs->trans('WorkshopColorDarkGrey'),
		'#212529' => $langs->trans('WorkshopColorBlack'),
	);
}


/**
 * Load active VAT rates from Dolibarr dictionary (llx_c_tva).
 * Returns an array suitable for a select : '' => '—' + 'rate' => 'rate %'.
 *
 * @param  DoliDB $db  Database handler
 * @return array<string,string>
 */
function getWorkshopVatRateOptions(DoliDB $db): array
{
	$options = array('' => '—');
	$sql  = 'SELECT DISTINCT taux FROM '.$db->prefix().'c_tva';
	$sql .= ' WHERE active = 1';
	$sql .= ' ORDER BY taux ASC';
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rate = price2num($obj->taux);
			$options[(string) $rate] = $rate.' %';
		}
		$db->free($resql);
	}
	return $options;
}


/**
 * Check that a path submitted by the client for a 'file' type field really points
 * to a file inside the current user's Dolibarr temp directory.
 *
 * The 'ws_file_uploaded_{fieldName}' hidden field is plain client-controlled input:
 * without this check, a tampered value would let dol_move() (called by the caller
 * once this path is accepted) move/read any file reachable by the web process,
 * regardless of what was actually uploaded via scripts/upload_files.php.
 *
 * @param  string $tmpPath Path submitted by the client
 * @param  Conf   $conf    Dolibarr conf object
 * @return bool            True if the path is a real file directly inside the user's temp dir
 */
function workshopIsValidUploadedTempFile(string $tmpPath, $conf): bool
{
	$tempdir = realpath($conf->user->multidir_temp[$conf->entity]);
	$realfile = realpath($tmpPath);
	if ($tempdir === false || $realfile === false || !is_file($realfile)) {
		return false;
	}
	return dirname($realfile) === $tempdir;
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

	if ($fieldConfig['type'] === 'number') {
		$numVal = ($dataEdit !== null) ? price2num($value) : (isset($fieldConfig['default']) ? $fieldConfig['default'] : '0');
		return array(
			'type'  => 'text',
			'label' => $langs->trans($fieldConfig['label']),
			'name'  => $fieldName,
			'value' => $numVal,
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
		$palette = getWorkshopColorPalette();
		$default = isset($fieldConfig['default']) ? $fieldConfig['default'] : key($palette);
		// Pre-select the stored value; fall back to the config default or the first palette entry.
		$selected = !empty($value) && isset($palette[$value]) ? $value : $default;
		if (!isset($palette[$selected])) {
			$selected = key($palette);
		}
		return array(
			'type'    => 'select',
			'label'   => $langs->trans($fieldConfig['label']),
			'name'    => $fieldName,
			'values'  => $palette,
			'default' => $selected,
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

	if ($fieldConfig['type'] === 'tva') {
		$vatOptions = getWorkshopVatRateOptions($db);
		// Current value: stored as float or null
		$currentRate = ($dataEdit !== null && $dataEdit->$fieldName !== null && $dataEdit->$fieldName !== '')
			? (string) price2num($dataEdit->$fieldName)
			: '';
		return array(
			'type'    => 'select',
			'label'   => $langs->trans($fieldConfig['label']),
			'name'    => $fieldName,
			'values'  => $vatOptions,
			'default' => $currentRate,
		);
	}

	if ($fieldConfig['type'] === 'file') {
		global $conf;
		$accept = !empty($fieldConfig['file_accept']) ? $fieldConfig['file_accept'] : 'application/pdf';
		$upload_dir = $conf->user->multidir_temp[$conf->entity];

		$currentDoc = ($dataEdit !== null && !empty($dataEdit->$fieldName)) ? $dataEdit->$fieldName : '';

		$out = '';
		if ($currentDoc !== '') {
			$out .= '<div style="margin-bottom:4px"><strong>'.$langs->trans('WorkshopDocOblCurrent').' :</strong> '.dol_escape_htmltag($currentDoc).'.pdf</div>';
		}
		$out .= '<input class="flat minwidth300" id="ws_file_'.$fieldName.'" type="file" name="'.$fieldName.'" accept="'.$accept.'"/>';
		$out .= '<button type="button" class="ws-upload-btn" id="ws_upload_'.$fieldName.'"'
			.' data-field="'.dol_escape_htmltag($fieldName).'"'
			.' data-uploaddir="'.dol_escape_htmltag($upload_dir).'"'
			.' data-token="'.dol_escape_htmltag(newToken()).'"'
			.' data-url="'.dol_escape_htmltag(dol_buildpath('/workshop/scripts/upload_files.php', 3)).'">'
			.img_picto('', 'fontawesome_fa-save', 'class="paddingright pictofixedwidth valignmiddle"')
			.'</button>';

		// The hidden field that receives the temp file path after AJAX upload
		return array(
			array('type' => 'hidden', 'name' => 'ws_file_uploaded_'.$fieldName, 'value' => ''),
			array('type' => 'other',  'label' => $langs->trans($fieldConfig['label']), 'value' => $out),
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

	if ($fieldConfig['type'] === 'number') {
		return dol_escape_htmltag(price2num($value));
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
		$v       = dol_escape_htmltag($value);
		$palette = getWorkshopColorPalette();
		$label   = isset($palette[$value]) ? dol_escape_htmltag($palette[$value]) : $v;
		return '<span style="display:inline-block;width:14px;height:14px;background-color:'.$v
			.';border:1px solid #999;vertical-align:middle;border-radius:2px;"></span>&nbsp;'.$label;
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

	if ($fieldConfig['type'] === 'tva') {
		if ($value === null || $value === '') {
			return '-';
		}
		return dol_escape_htmltag(price2num($value)).' %';
	}

	if ($fieldConfig['type'] === 'file') {
		if (empty($value)) {
			return '-';
		}
		return dol_escape_htmltag($value).'.pdf';
	}

	return dol_escape_htmltag($value);
}


/**
 * Prepare array of tabs for Workshop Setup (Atelier / OR section).
 * Points to the unified parameter page filtered on the 'atelier' context.
 *
 * @return array
 */
function workshopSetupPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("workshop@workshop");

	$h    = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/workshop/param/workshop_param_unified.php', 1).'?context=atelier&tab=service_type';
	$head[$h][1] = $langs->trans('WorkshopSetupServiceType');
	$head[$h][2] = 'service_type';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopsetup@workshop');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'workshopsetup@workshop', 'remove');

	return $head;
}
