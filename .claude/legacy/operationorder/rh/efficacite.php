<?php

// Libraries
require '../config.php';

// Translations
$langs->load('operationorder@operationorder');

 // Access control
if (! $user->hasRight("operationorder", "read")) {
	accessforbidden();
}

// Appel des class
dol_include_once('core/class/html.formother.class.php');
dol_include_once('core/class/html.form.class.php');
dol_include_once('user/class/user.class.php');
dol_include_once('core/lib/date.lib.php');
dol_include_once('absence/class/absence.class.php');

/*
 * Actions
 */
$fk_user = $user->id;
_fiche($fk_user);

/*
 * View
 */

//pr�paration de la requ�te avec un filtre qui r�cup�re les donn�es s�l�ctionn�es dans le tableau OPERATIONORDER_EXCLUDE_EFF
$filter = '';
$x = explode(',', getDolGlobalString("OPERATIONORDER_EXCLUDE_EFF"));
if (is_array($x)) {
	foreach ($x as $key => $val) {
		$filter .= '"'.$val.'",';
	}

	$filter=substr($filter, 0, -1);
}

$action=GETPOST("action");
$array_event = array();
if ($action=='search') {
	$slq_event = 'SELECT label FROM '.$db->prefix().'operationorderbarcode WHERE label NOT IN ('.$filter.') GROUP BY label ';
	$resql=$db->query($slq_event);
	if ($resql) {
		while ($object = $db->fetch_object($resql)) {
			$array_event[] = $object->label;
		}
	}
	$array_event[] = "Main d'oeuvre";


	$sql= 'SELECT DAY (op.task_datehour_d) as jour, MONTH (op.task_datehour_d) as mois, DAYOFWEEK(MIN(op.task_datehour_d)) as jour_semaine,';
	foreach ($array_event as $event) {
		if ($event == "Main d'oeuvre") {
			$sql .= 'SUM(IF(op.label LIKE "%'. $event .'%",IF(op.task_duration IS NULL,TIME_TO_SEC(TIMEDIFF(NOW(),op.task_datehour_d)),op.task_duration)/3600,0)) as "'. $event .'",';
		} else {
			$sql .= 'SUM(IF(op.label ="'. $event .'",IF(op.task_duration IS NULL,TIME_TO_SEC(TIMEDIFF(NOW(),op.task_datehour_d)),op.task_duration)/3600,0)) as "'. $event .'",';
		}
	}
	$sql .= ' SUM(IF(op.task_duration IS NULL,TIME_TO_SEC(TIMEDIFF(NOW(),op.task_datehour_d)),op.task_duration))/3600 as "dispo",';
	$sql .= ' COUNT(DISTINCT op.fk_user) as "nbtech"';
	$sql .= ' FROM '.$db->prefix().'operationordertasktime as op';
	$sql .= ' WHERE MONTH (op.task_datehour_d)='. GETPOST("month");
	$sql .= ' AND YEAR (op.task_datehour_d)='. GETPOST("year");
	if (!empty(GETPOST("user")) && GETPOST("user")<> -1) {
		$sql .= ' AND op.fk_user ='. GETPOST("user");
	}
	if (isModEnabled('multicompany') && GETPOST("entity")<> 1) {
		$sql .= ' AND op.entity ='. GETPOST("entity");
	}
	$sql .= ' AND op.label NOT IN ('.$filter.')';
	$sql .= ' GROUP BY YEAR (op.task_datehour_d), MONTH (op.task_datehour_d), DAY (op.task_datehour_d)';
	$sql .= ' ORDER BY  MONTH (op.task_datehour_d), DAY (op.task_datehour_d)';

	$resql=$db->query($sql);

	if ($resql) {
		$tab = array();
		$L = new DateTime(GETPOST('year').'-'.GETPOST("month").'-01');
		$dernierJour = $L->format('t');
		for ($i=1; $i<=$dernierJour; $i++) {
			$L= new DateTime(GETPOST('year').'-'.GETPOST("month").'-'.$i);

			$jourSemaine = $L->format('w');
			//DayOfWeek en SQL donne Dimanche = 1; Le format w donne Dimanche = 0 => +1 pour ajuster
			//          $tab[$i]['planning']=$planning[$jourSemaine+1];
			$tab[$i]['jour_semaine'] = $jourSemaine+1;
		}

		while ($object = $db->fetch_object($resql)) {
			$tab[$object->jour]['jour_semaine'] = $object->jour_semaine;
			foreach ($array_event as $event) {
				$tab[$object->jour][$event] = round($object->$event, 2, PHP_ROUND_HALF_UP);
			}
			$tab[$object->jour]['dispo'] = round($object->dispo, 2, PHP_ROUND_HALF_UP);
			$tab[$object->jour]['nbtech'] = $object->nbtech;
		}
		print '<div class="div-table-responsive">';
		print '<table class="tagtable liste listwithfilterbefore">';
		print '<thead>';
		print '<tr class="liste_titre_filter" >';
		print ' <th align="center" colspan="2" style="font-weight : bold; background-color: #0771ad;color: white;">' . $langs->trans('Day') . '</th>';
		foreach ($array_event as $event) {
			print ' <th align="center" style="font-weight : bold; background-color: #0771ad;color: white;">' . $event . '</th>';
		}
		print ' <th align="center" style="font-weight : bold; background-color: #0771ad;color: white;">' . $langs->trans('heure présence') . '</th>';
		print ' <th align="center" style="font-weight : bold; background-color: #0771ad;color: white;">' . $langs->trans('nb technicien') . '</th>';
		print ' <th align="center" style="font-weight : bold; background-color: #0771ad;color: white;">' . $langs->trans('efficacité') . '</th>';
		print '</tr>';
		print '</thead>';

		print '<tbody>';

		//pr�paration de 2 tableaux pour convertir avec str_replace les jours anglais rendus par le format date_format par des mots fran�ais lors de l'affichage
		$jourAnglais = array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun");
		$jourFrancais = array("Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche");

		ksort($tab);
		$tot=array(
			"dispo" => 0,
			"nbtech" => 0,
			"Main d'oeuvre" => 0
		);
		$totm=array(
			"dispo" => 0,
			"nbtech" => 0,
			"Main d'oeuvre" => 0
		);
		$jourprec = 0;
		$totalHSupW = 0;
		$totalHSupM = 0;
		foreach ($tab as $key=>$valjour) {
			$joursemaine = "";
			$jour = "";
			// affichage total semaine quand on repart sur une autre semaine (Dimanche= 1)
			if ($jourprec==1) {
				if ($totalHSupW<0) {
					$sign = "-";
				} else {
					$sign= "+";
				}
				print '<tr >';
				print ' <td align="center" colspan="2" style="font-weight : bold; background-color: #0771ad;color: white;">Semaine</td>';
				$mo = $tot["Main d'oeuvre"];
				foreach ($array_event as $event) {
					if (!array_key_exists($event, $tot)) {
						$tot[$event] = 0;
					}
					print '<td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot[$event].'</td>';
					$tot[$event]=0;
				}
				print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot['dispo'].'</td>';
				print '<td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot['nbtech'].'</td>';
				$tot['nbtech'] = 0;
				$mo_over_dispo = 0;
				if ($tot['dispo'] !== 0) {
					$mo_over_dispo = round(($mo/$tot['dispo'])*100, 1, PHP_ROUND_HALF_UP);
				}
				print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;;">' . $mo_over_dispo . '%' .'</td>';
				$tot['dispo'] = 0;
				$mo = 0;
				print '</tr>';
			}
			//mise � jour de la valeur de jourprec avant la boucle suivante
			$jourprec = $valjour['jour_semaine'];
			//calcul des totaux
			foreach ($array_event as $event) {
				if (array_key_exists($key, $tab) && array_key_exists($event, $tab[$key])) {
					if (!array_key_exists($event, $tot)) {
						$tot[$event] = 0;
					}
					if (!array_key_exists($event, $totm)) {
						$totm[$event] = 0;
					}
					$tot[$event] += $tab[$key][$event];
					$totm[$event] += $tab[$key][$event];
				}
			}


			//Utilisation de la methode date_format de la class DateTime pour avoir H:i
			$joursemaine = new  DateTime(GETPOST('year').'-'.GETPOST("month").'-' . $key);
			$jour = date_format($joursemaine, 'D');
			if (!empty($tab[$key]['dispo'])) {
				$eff = round(($tab[$key]["Main d'oeuvre"] / $tab[$key]['dispo']) * 100, 1, PHP_ROUND_HALF_UP);
			} else {
				$eff = 0;
			}
			if (is_nan($eff)) {
				$effstring = '';
			} else {
				$effstring =$eff . ' %';
			}

			print '<tr>';
			print ' <td  align="center"> ' . str_replace($jourAnglais, $jourFrancais, $jour). '</td>';
			print ' <td  align="center"> ' . $key. '</td>';
			foreach ($array_event as $event) {
				if (array_key_exists($key, $tab) && array_key_exists($event, $tab[$key])) {
					print ' <td  align="center"> ' .$tab[$key][$event] . '</td>';
				} else {
					print ' <td  align="center"></td>';
				}
			}

			if (array_key_exists($key, $tab) && array_key_exists('dispo', $tab[$key])) {
				$tot['dispo'] += $tab[$key]['dispo'];
				$totm['dispo'] += $tab[$key]['dispo'];
				print ' <td  align="center"> ' . $tab[$key]['dispo']. '</td>';
			} else {
				print ' <td  align="center"></td>';
			}

			if (array_key_exists($key, $tab) && array_key_exists('nbtech', $tab[$key])) {
				$tot['nbtech'] += $tab[$key]['nbtech'];
				$totm['nbtech'] += $tab[$key]['nbtech'];
				print ' <td  align="center"> ' . $tab[$key]['nbtech']. '</td>';
			} else {
				print ' <td  align="center"></td>';
			}

			print ' <td  align="center"> ' . $effstring . '</td>';
			print '</tr>';
		}

		// affichage total derni�re semaine
		print '<tr >';
		print ' <td align="center" colspan="2" style="font-weight : bold; background-color: #0771ad;color: white;">Semaine</td>';
		$mo = $tot["Main d'oeuvre"];
		foreach ($array_event as $event) {
			print '<td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot[$event].'</td>';
			$tot[$event]=0;
		}
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot['dispo'].'</td>';
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$tot['nbtech'].'</td>';
		$tot['nbtech'] = 0;
		if ($tot['dispo'] !== 0) {
			$mo_over_dispo = round(($mo /$tot['dispo'])*100, 1, PHP_ROUND_HALF_UP);
		}
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'. $mo_over_dispo . '%'.'</td>';
		$tot['dispo'] = 0;
		$mo = 0;
		print '</tr>';

		//affichage total mois
		if ($totalHSupM<0) {
			$sign = "-";
		} else {
			$sign= "+";
		}
		print '<tr >';
		print ' <td align="center" colspan="2" style="font-weight : bold; background-color: #0771ad;color: white;">Mois</td>';
		$mom = $totm["Main d'oeuvre"];
		foreach ($array_event as $event) {
			if (!array_key_exists($event, $totm)) {
				$totm[$event] = 0;
			}
			print '<td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$totm[$event].'</td>';
			$totm[$event]=0;
		}
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$totm['dispo'].'</td>';
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'.$totm['nbtech'].'</td>';
		$totm['nbtech'] = 0;
		if ($totm['dispo'] !== 0) {
			$mo_over_dispo = round(($mo /$totm['dispo'])*100, 1, PHP_ROUND_HALF_UP);
		}
		print ' <td align="center" style="font-weight : bold; background-color: #0771ad;color: white;">'. $mo_over_dispo . '%'.'</td>';
		$totm['dispo'] = 0;
		$mom = 0;
		print '</tr>';
	}
}

llxFooter();


function _fiche($fk_user)
{
	global $db, $langs, $user, $conf, $mc, $linkback;

	llxHeader();

	$sql = 'SELECT DISTINCT u.rowid as rowid, u.lastname as lastname, u.firstname as firstname ';
	$sql .= ' FROM `' . $db->prefix() . 'user` as u';
	$sql .= ' INNER JOIN `' . $db->prefix() . 'usergroup_user` as us ON u.rowid=us.fk_user';
	$sql .= ' INNER JOIN `' . $db->prefix() . 'usergroup` as g ON g.rowid=us.fk_usergroup';
	//$sql .= ' WHERE g.rowid IN (7, 8, 9) ';
	$sql .= ' ORDER BY u.lastname';
	$resql = $db->query($sql);
	$userlist = array();

	if ($resql) {
		while ($object = $db->fetch_object($resql)) {
			$userlist[$object->rowid] = $object->lastname . " " . $object->firstname;
		}
	}

	$sql = 'SELECT DISTINCT e.rowid as rowid, e.label as name';
	$sql .= ' FROM `' . $db->prefix() . 'entity` as e';
	$sql .= ' WHERE e.active =1 AND e.visible =1 AND rowid IN (' . $mc->entities['operationorder'] .')';
	$sql .= ' ORDER BY e.rowid';

	$resql = $db->query($sql);
	$entitylist = array();

	if ($resql) {
		while ($object = $db->fetch_object($resql)) {
			$entitylist[$object->rowid] = $object->name;
		}
	}

	$page_name = "Pointage";
	print load_fiche_titre($langs->trans($page_name), $linkback, "title_externalaccess@externalaccess");
	print '<form role="form" autocomplete="off" class="form" method="post"  action="' . $_SERVER['PHP_SELF'] . '" >';
	print '<input type="hidden" name="token" value="' . newToken() . '">' . "\n";

	$form = new Form($db);
	$formother = new FormOther($db);

	$selectmonth = __get('month', date('m', strtotime('-1month')), 'integer');
	$selectyear = __get('year', date('Y', strtotime('-1month')), 'integer');

	print '<label for="month">' . $langs->transnoentities('Month') . '</label>';
	print $formother->select_month($selectmonth, 'month', 1);

	print '<label for="year">' . $langs->transnoentities('Year') . '</label>';
	print $formother->selectyear($selectyear, 'year', 1, 20, 1);

	print '<label for="user">' . $langs->transnoentities('Name') . '</label>';
	print $form->selectarray('user', $userlist, GETPOST('user'), 1, 0, 0, '', 0, 0, 0, '', '', 0);

	if (isModEnabled('multicompany')) {
		print '<label for="entity">' . $langs->transnoentities('Entity') . '</label>';
		print $mc->select_entities(empty(GETPOST('entity')) ? $conf->entity : GETPOST('entity'), 'entity');
	}

	print '<button type="submit" class="btn btn-success btn-strong" name="action" value="search" >' . $langs->transnoentities('PointageBtnSubmitSearch') . '</button>';

	print '</form>';
}
