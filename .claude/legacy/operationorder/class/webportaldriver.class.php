<?php

/* Copyright (C) 2023-2024 	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2023-2024	Lionel Vessiller		<lvessiller@easya.solutions>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
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
 * \file        htdocs/webportal/class/webportalorder.class.php
 * \ingroup     webportal
 * \brief       This file is a CRUD class file for WebPortalOrder (Create/Read/Update/Delete)
 */
// Put here all includes required by your class file
dol_include_once('operationorder/class/operationorder.class.php');
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

/**
 * Class for WebPortalDriver
 */
class WebPortalDriver extends Contact
{

	/**
	 * @var string ID of module.
	 */
	public $module = 'webportal';

	/**
	 * Status list (short label)
	 */
	const ARRAY_STATUS_LABEL = array(
//		Contact::STATUS_DRAFT => 'StatusOrderDraftShort',
//		Contact::STATUS_VALIDATED => 'StatusOrderValidated',
//		Contact::STATUS_SHIPMENTONPROCESS => 'StatusOrderSentShort',
//		Contact::STATUS_CLOSED => 'StatusOrderDelivered',
//		Contact::STATUS_CANCELED => 'StatusOrderCanceledShort',
	);

	/**
	 *  'type' field format:
	 *    'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
	 *    'select' (list of values are in 'options'),
	 *    'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
	 *    'chkbxlst:...',
	 *    'varchar(x)',
	 *    'text', 'text:none', 'html',
	 *    'double(24,8)', 'real', 'price',
	 *    'date', 'datetime', 'timestamp', 'duration',
	 *    'boolean', 'checkbox', 'radio', 'array',
	 *    'mail', 'phone', 'url', 'password', 'ip'
	 *        Note: Filter must be a Dolibarr Universal Filter syntax string. Example: "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.status:!=:0) or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or 'getDolGlobalInt('MY_SETUP_PARAM') or 'isModEnabled("multicurrency")' ...)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'alwayseditable' says if field can be modified also when status is not draft ('1' or '0')
	 *  'default' is a default value for creation (can still be overwrote by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommended to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'help' and 'helplist' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *    'validate' is 1 if need to validate with $this->validateField()
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */
	// BEGIN MODULEBUILDER PROPERTIES

	/**
	 * @var array<string,array{type:string,label:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int<-5,5>|string,alwayseditable?:int<0,1>,noteditable?:int<0,1>,default?:string,index?:int,foreignkey?:string,searchall?:int<0,1>,isameasure?:int<0,1>,css?:string,csslist?:string,help?:string,showoncombobox?:int<0,4>,disabled?:int<0,1>,arrayofkeyval?:array<int|string,string>,autofocusoncreate?:int<0,1>,comment?:string,copytoclipboard?:int<1,2>,validate?:int<0,1>,showonheader?:int<0,1>}>  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
// affichage dans la baniere
		'rowid'		=> array('type'		  => 'int', 'label'		  => 'Id', 'enabled'	  => 0, 'position'	  => 110,
			'notnull'	  => 1, 'visible'	  => 0,
			'noteditable' => '1'),
		'lastname'	=> array(
			'type'	   => 'varchar(128)',
			'label'	   => 'Lastname',
			'enabled'  => 1,
			'position' => 130,
			'notnull'  => 1,
			'visible'  => 1
		),
		'firstname' => array(
			'type'	   => 'varchar(128)',
			'label'	   => 'Firstname',
			'enabled'  => 1,
			'position' => 135,
			'notnull'  => 1,
			'visible'  => 1
		),
		'datec'		=> array(
			'type'		  => 'datetime',
			'label'		  => 'DateCreation',
			'enabled'	  => 1,
			'position'	  => 1100,
			'notnull'	  => 1,
			'visible'	  => 4,
			'noteditable' => '1'
		),
		'civility' => array(
			'type' => 'varchar(6)',
			'label' => 'Civility',
			'enabled' => 1,
			'visible' => 3,
			'position' => 1400
		),
		'poste'		=> array(
			'type'	   => 'varchar(128)',
			'label'	   => 'Poste',
			'enabled'  => 1,
			'position' => 1435,
			'notnull'  => 1,
			'visible'  => 1
		),
		'phone_mobile'		=> array(
			'type'	   => 'varchar(128)',
			'label'	   => 'Phone',
			'enabled'  => 1,
			'position' => 1440,
			'notnull'  => 1,
			'visible'  => 1
		),
		'email'		=> array(
			'type'	   => 'varchar(128)',
			'label'	   => 'Email',
			'enabled'  => 1,
			'position' => 1450,
			'notnull'  => 1,
			'visible'  => 1
		),
		'statut'	=> array(
			'type'			=> 'sellist',
			'label'			=> 'DriverActive',
			'enabled'		=> 1,
			'position'		=> 1455,
			'arrayofkeyval' => [
				'DriverActive'	 => "1",
				'DriverInactive' => "0",
				''				 => "-1",
			],
			'default'		=> -1,
			'notnull'		=> 1,
			'visible'		=> 1
		),
	);
	//public $rowid;
	//public $ref;
	//public $date_commande;
	//public $date_livraison;
	//public $total_ht;
	//public $total_tva;
	//public $total_ttc;
	//public $multicurrency_total_ht;
	//public $multicurrency_total_tva;
	//public $multicurrency_total_ttc;

	/**
	 * @var int status
	 */
	public $fk_status;

	// END MODULEBUILDER PROPERTIES

	/**
	 * Get order for static method
	 *
	 * @return	Contact
	 */
	protected function getContactStatic()
	{
		if (!$this->contact_static) {
			$this->contact_static = new Contact($this->db);
		}

		return $this->contact_static;
	}

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		$this->isextrafieldmanaged = 0;

		$this->getContactStatic();
	}

	/**
	 * getTooltipContentArray
	 * @param array<string,mixed> $params params to construct tooltip data
	 * @since v18
	 * @return array{picto?:string,ref?:string,refsupplier?:string,label?:string,date?:string,date_echeance?:string,amountht?:string,total_ht?:string,totaltva?:string,amountlt1?:string,amountlt2?:string,amountrevenustamp?:string,totalttc?:string}|array{optimize:string}
	 */
	public function getTooltipContentArray($params)
	{
		global $conf, $langs;

		$datas = [];

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return ['optimize' => $langs->trans("WebPortalOrder")];
		}
		$datas['picto'] = img_picto('', $this->picto) . ' <u>' . $langs->trans("WebPortalContact") . '</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' ' . $this->getLibStatut(5);
		}
		$datas['ref'] .= '<br><b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;

		return $datas;
	}

	/**
	 * Return clickable link of object (with eventually picto)
	 *
	 * @param	int		$withpicto				Add picto into link
	 * @param	string	$option					Where point the link (0=> main card, 1,2 => shipment, 'nolink'=>No link)
	 * @param	int		$max					Max length to show
	 * @param	int		$short					Short
	 * @param	int		$notooltip				1=Disable tooltip
	 * @param	int		$save_lastsearch_value	-1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 * @param	int		$addlinktonotes			Add link to notes
	 * @param	string	$target					Attribute target for link
	 * @return	string	String with URL
	 */
	public function getNomUrl(
		$withpicto = 0, $option = '', $max = 0, $short = 0, $notooltip = 0, $save_lastsearch_value = -1, $addlinktonotes =
		0, $target = ''
	) {
		global $conf, $langs, $hookmanager;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';

		$url = '';

		$option = 'nolink';

		if ($short) {
			return $url;
		}
		$params = [
			'id'		 => $this->id,
			'objecttype' => $this->element,
			'option'	 => $option,
			'nofetch'	 => 1,
		];
		$classfortooltip = 'classfortooltip';
		$dataparams = '';
		if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
			$classfortooltip = 'classforajaxtooltip';
			$dataparams = ' data-params="' . dol_escape_htmltag(json_encode($params)) . '"';
			$label = '';
		} else {
			$label = implode($this->getTooltipContentArray($params));
		}

		$linkclose = '';

		$linkstart = '<a href="' . $url . '"';
		$linkstart .= $linkclose . '>';
		$linkend = '</a>';

		if ($option === 'nolink') {
			$linkstart = '';
			$linkend = '';
		}

		$result .= $linkstart;
		if ($withpicto) {
			$result .= img_object(
				($notooltip ? '' : $label), $this->picto, (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0,
				$notooltip ? 0 : 1);
		}
		if ($withpicto != 2) {
			$result .= $this->ref;
		}
		$result .= $linkend;

		global $action;
		$hookmanager->initHooks(array($this->element . 'dao'));
		$parameters = array('id' => $this->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}
		return $result;
	}

	/**
	 *  Return the label of the status
	 *
	 * @param	int		$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return	string	Label of status
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->fk_statut, $this->billed, $mode);
	}

	/**
	 *  Return the label of the status
	 *
	 * @param	int			$mode		0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 * @return	string		Label of status
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->fk_statut, $this->billed, $mode);
	}

	/**
	 *    Get object and children from database
	 *
	 * @param int $id Id of object to load
	 * @param bool $loadChild used to load children from database
	 * @param string $ref Ref
	 * @return     int                        >0 if OK, <0 if KO, 0 if not found
	 */
	public function fetchWebContact($id, $loadChild = true, $ref = null)
	{
		$static = new Contact($this->db);

		$this->fields = $static->fields;

		$res = parent::fetch($id, $loadChild, $ref);

		//TODO review exit from abricot
		if (isset($this->ToperationorderLine)) {
			$this->lines = $this->ToperationorderLine;
		} else {
			$this->lines = [];
		}

		$this->socid = $this->fk_soc;

		$this->fetch_thirdparty();
		$this->oldcopy = clone $this;
		return $res;
	}

	/**
	 *  On/off button to change a property status of an object
	 *  This uses the ajax service objectonoff.php (May be called when MAIN_DIRECT_STATUS_UPDATE is set for some pages)
	 *
	 *  @param  string  $code       Name of property in object : 'status' or 'status_buy' for product by example
	 *  @param  string  $field      Name of database field : 'tosell' or 'tobuy' for product by example
	 *  @param  string  $text_on    Text if on ('Text' or 'Text:Picto on:Css picto on')
	 *  @param  string  $text_off   Text if off ('Text' or 'Text:Picto off:Css picto off')
	 *  @param  array<string,string[]>   $input      Array of type->list of CSS element to switch. Example: array('disabled'=>array(0=>'cssid'))
	 *  @param	string	$morecss	More CSS
	 *  @param	string	$htmlname	Name of HTML component. Keep '' or use a different value if you need to use this component several time on the same page for the same field.
	 *  @param	int		$forcenojs	Force the component to work as link post (without javascript) instead of ajax call
	 *  @param	string	$moreparam	When $forcenojs=1 then we can add more parameters to the backtopage URL. String must url encoded. Example: 'abc=def&fgh=ijk'
	 *  @return string              html for button on/off
	 *  @see ajax_constantonoff() to update that value of a constant
	 */
	public function ajax_object_onoff(
		$field, $text_on, $text_off, $input = array(), $morecss = '', $htmlname = '', $forcenojs = 0, $moreparam = ''
	) {
		global $conf, $langs;

		if (empty($htmlname)) {
			$htmlname = $field;
		}

		$out = '';

		$ajax_url = dol_buildpath('/custom/operationorder/public/ajax/objectonoff.php', 1);
		if (!empty($conf->use_javascript_ajax) && empty($forcenojs)) {
			$out .= '<script>
			$(function() {
				var input = ' . json_encode($input) . ';
				console.log("here");
				// Set constant
				$("#set_' . $htmlname . '_' . $this->id . '").click(function() {
					console.log("Click managed by ajax_object_onoff");
					$.get( "' . $ajax_url . '", {
						action: \'set\',
						field: \'' . dol_escape_js($field) . '\',
						value: \'1\',
						element: \'' . dol_escape_js(
					(empty($this->module) || $this->module == $this->element) ? $this->element : $this->element . '@' . $this->module) . '\',
						id: \'' . ((int) $this->id) . '\',
						token: \'' . currentToken() . '\'
					},
					function() {
						$("#set_' . $htmlname . '_' . $this->id . '").hide();
						$("#del_' . $htmlname . '_' . $this->id . '").show();
						// Enable another element
						if (input.disabled && input.disabled.length > 0) {
							$.each(input.disabled, function(key,value) {
								$("#" + value).removeAttr("disabled");
								if ($("#" + value).hasClass("butActionRefused") == true) {
									$("#" + value).removeClass("butActionRefused");
									$("#" + value).addClass("butAction");
								}
							});
						// Show another element
						} else if (input.showhide && input.showhide.length > 0) {
							$.each(input.showhide, function(key,value) {
								$("#" + value).show();
							});
						}
					});
				});

				// Del constant
				$("#del_' . $htmlname . '_' . $this->id . '").click(function() {
					console.log("Click managed by ajax_object_onoff");
					$.get( "' . $ajax_url . '", {
						action: \'set\',
						field: \'' . dol_escape_js($field) . '\',
						value: \'0\',
						element: \'' . dol_escape_js(
					(empty($this->module) || $this->module == $this->element) ? $this->element : $this->element . '@' . $this->module) . '\',
						id: \'' . ((int) $this->id) . '\',
						token: \'' . currentToken() . '\'
					},
					function() {
						$("#del_' . $htmlname . '_' . $this->id . '").hide();
						$("#set_' . $htmlname . '_' . $this->id . '").show();
						// Disable another element
						if (input.disabled && input.disabled.length > 0) {
							$.each(input.disabled, function(key,value) {
								$("#" + value).prop("disabled", true);
								if ($("#" + value).hasClass("butAction") == true) {
									$("#" + value).removeClass("butAction");
									$("#" + value).addClass("butActionRefused");
								}
							});
						// Hide another element
						} else if (input.showhide && input.showhide.length > 0) {
							$.each(input.showhide, function(key,value) {
								$("#" + value).hide();
							});
						}
					});
				});
			});
		</script>';
		}

		$switchon = 'switch_on';
		$switchoff = 'switch_off';
		$cssswitchon = '';
		$cssswitchoff = '';
		$tmparray = explode(':', $text_on);
		if (!empty($tmparray[1])) {
			$text_on = $tmparray[0];
			$switchon = $tmparray[1];
			if (!empty($tmparray[2])) {
				$cssswitchon = $tmparray[2];
			}
		}
		$tmparray = explode(':', $text_off);
		if (!empty($tmparray[1])) {
			$text_off = $tmparray[0];
			$switchoff = $tmparray[1];
			if (!empty($tmparray[2])) {
				$cssswitchoff = $tmparray[2];
			}
		}

		$out .= '<span
			id="set_' . $htmlname . '_' . $this->id . '"
			class="linkobject ' . ($this->$field == 1 ? 'hideobject' : '') . ($morecss ? ' ' . $morecss : '') . '"
		>' . img_picto($langs->trans($text_off), $switchoff, '', 0, 0, 0, '', $cssswitchoff) . '</span>';

		$out .= '<span
			id="del_' . $htmlname . '_' . $this->id . '"
			class="linkobject ' . ($this->$field == 1 ? '' : 'hideobject') . ($morecss ? ' ' . $morecss : '') . '">
				' . img_picto($langs->trans($text_on), $switchon, '', 0, 0, 0, '', $cssswitchon) . '</span>';

		return $out;
	}
}
