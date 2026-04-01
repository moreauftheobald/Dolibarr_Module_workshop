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
 * \file       workshop/lib/workshop_or_card.lib.php
 * \ingroup    workshop
 * \brief      Fonctions utilitaires pour la fiche OR (or_card.php)
 *
 * Gestion de l'état du formulaire de création d'OR à travers les sous-dialogs
 * (création à la volée de conducteur, de tag…).
 *
 * État préservé : fk_vehicule (int), km (string), fk_conducteur (int), fk_tags (int[])
 */


/**
 * Construit le fragment query-string qui préserve l'état courant du formulaire OR.
 * Les valeurs sont lues depuis GETPOST afin de survivre aux redirections GET successives.
 *
 * @return string  Fragment encodé commençant par '&'
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
 * Construit le fragment d'URL qui transporte l'état du formulaire OR vers un sous-dialog.
 * Retourne une query string complète (débutant par '?') prête à être concaténée à PHP_SELF.
 *
 * @param  array $overrides  Valeurs à surcharger (ex: array('fk_conducteur' => $newId))
 * @return string             URL débutant par '?'
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
 * Construit l'URL de redirection qui restaure l'état du formulaire OR après un sous-dialog.
 *
 * @param  string $self       Valeur de $_SERVER['PHP_SELF']
 * @param  array  $overrides  Valeurs à surcharger (ex: array('fk_conducteur' => $newId))
 * @return string
 */
function orCardRestoreUrl($self, $overrides = array())
{
	$fk_vehicule   = isset($overrides['fk_vehicule'])   ? (int) $overrides['fk_vehicule']   : GETPOSTINT('fk_vehicule');
	$km            = isset($overrides['km'])             ? $overrides['km']                  : GETPOST('km', 'alpha');
	$fk_conducteur = isset($overrides['fk_conducteur']) ? (int) $overrides['fk_conducteur'] : GETPOSTINT('fk_conducteur');
	$fk_tags       = isset($overrides['fk_tags'])
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
