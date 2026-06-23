# Plan d'implémentation — Refonte du planning atelier (module Workshop)

> Réf. spec : `Spec_planning_workshop.md`
> Statut : **CADRAGE — pas de code tant que ce plan n'est pas validé**

---

## A. Décisions de cadrage validées (session du 2026-06-23)

1. **Mécaniciens** = membres du **groupe utilisateur déjà paramétré** dans l'admin via la constante
   **`WORKSHOP_MECHANIC_GROUP`** (`admin/setup_or.php`, sélecteur de UserGroup existant).
   → **réutiliser tel quel**, aucune nouvelle constante ni champ de setup à créer.
   → NB : `WORKSHOP_OR_PLANNING_GROUPS` (multi-groupes) est un paramètre distinct (horaires planning),
     à ne pas confondre avec le groupe mécaniciens.
2. **Grille journée = 2 sous-lignes par mécanicien** :
   - sous-ligne **haut** = créneaux **planifiés** (jobs affectés : `fk_user_assign` + `date_start`/`date_end`)
   - sous-ligne **bas** = **pointages réels** (table `llx_workshop_pointage`)
   → adapte la structure CSS de la spec §9 (prévue pour 1 ligne).
3. **`time_spent`** du job = recalculé depuis la somme des pointages `type='job'`,
   **sans déclencher aucune action de facturation** (pas de recalcul `total_ht`).
4. **Improductifs** = liste de codes gérée dans l'admin, **reprise du modèle legacy `operationorder`**
   (table `llx_operationorderbarcode` : `label` + `code` auto `IMP00001`…), page admin de CRUD.
   - 2 codes **réservés codés en dur** (hors table, comme le legacy) :
     `IMPFin` = « Fin de journée » (clôture le pointage en cours sans réouverture)
     et `IMPAnnul` = « Annulation ».
   - Génération de **code-barres** (scan atelier) du legacy = **hors-scope**, reportée (optionnel).

## A bis. Écarts spec / existant à acter

- Table statut : la spec écrit `llx_workshop_operationorderstatus` → le réel est
  **`llx_workshop_operationorder_status`**. On s'aligne sur l'existant.
- **FullCalendar 5 + JSGantt** (actuellement dans `workshop_planning.php`) : **supprimés**,
  remplacés par grille CSS native. (autorisé par spec §1)
- `css/workshop.css` **n'existe pas** (seul `css/or_card.css`) → à créer + charger via `$TIncludeCSS`.

## A ter. Points ouverts à trancher avant / pendant le dev

- [ ] **Type `absence`** : source des types d'absence non figée (constante module ? réutilisation
      du module Congés/Holiday natif ?). Proposition par défaut : liste via constante module,
      intégration Holiday repoussée en phase 2. **À confirmer.**
- [ ] **Comportement exact du code réservé `IMPAnnul` (Annulation)** : dans le legacy c'est un code
      improductif « Annuler ». Proposition : clôture le pointage en cours en le marquant annulé
      (non comptabilisé dans `time_spent`). **À confirmer.**
- [ ] **Migration legacy** : vérifier si des données de temps/pointage des anciens modules
      (`operationorder`) doivent alimenter `llx_workshop_pointage` (cf. CLAUDE.md §9). À ce stade
      le script de migration n'existe pas encore dans `script/migration/`.
- [ ] **Phasage** : livrer un MVP d'abord (Phase 1+2 : table/classe + vue mécaniciens journée +
      pointage) puis le reste, ou tout d'un bloc ? **Préférence à confirmer.**

---

## B. Modèle de données

### B.1 Nouvelle table `llx_workshop_pointage`
Champs conformes spec §2 :
`rowid, date_creation, tms, fk_user, fk_job, fk_operationorder (dénormalisé),
type ('job'|'impro'|'absence'), impro_code, date_start, date_end (NULL = en cours),
note, fk_user_creat, fk_user_modif, entity`.
Index : `(fk_user, date_start)`, `(fk_job)`, `(fk_user, date_end)` (pointage ouvert).
- Fichiers : `sql/llx_workshop_pointage.sql` + `.key.sql`
- Classe : `class/workshoppointage.class.php` → `WorkshopPointage extends CommonObject`
  (`$module='workshop'`, `$element='workshoppointage'`, `$table_element='workshop_pointage'`,
  `$ismultientitymanaged=1`, `$fields` complets, constantes `TYPE_JOB/TYPE_IMPRO/TYPE_ABSENCE`).

### B.2 Codes improductifs — `llx_workshop_c_impro` (modèle legacy `llx_operationorderbarcode`)
Champs : `rowid, date_creation, tms, label, code (varchar, auto 'IMP00001'…), entity`.
(option future : `color`, données code-barres pour scan atelier.)
- 2 codes **réservés codés en dur** dans le code (non stockés) : `IMPFin` (Fin de journée),
  `IMPAnnul` (Annulation). Constantes de classe `CODE_FIN_JOURNEE='IMPFin'`, `CODE_ANNULATION='IMPAnnul'`.
- `impro_code` de la table pointage référence ce `code`.
- Fichiers : `sql/llx_workshop_c_impro.sql` + `.key.sql` (index unique `(code, entity)`),
  classe `class/workshopimpro.class.php` (CRUD, génération du prochain code `IMP…`).
- Page admin : `admin/setup_impro.php` — CRUD calqué sur `legacy/operationorder/admin/barcode_setup.php`
  (ajout par label → code auto, suppression), **sans** la partie génération/PDF code-barres pour l'instant.
- Entrée de menu setup à ajouter (cf. `core/modules/modWorkshop.class.php`).

### B.3 Groupe mécaniciens — RÉUTILISER L'EXISTANT (rien à créer)
- Constante existante **`WORKSHOP_MECHANIC_GROUP`** (déjà saisie via `admin/setup_or.php`).
- Helper PHP `workshop_get_mechanics($entity)` → users actifs du groupe `WORKSHOP_MECHANIC_GROUP`,
  triés. (lib `lib/workshop_planning.lib.php` à créer)

---

## C. Règles métier pointage (endpoint, critiques spec §2)

À implémenter dans `WorkshopPointage` (méthodes) + appelées par `ajax/planning_ajax.php`,
sous **transaction** :
1. Création pointage → si un pointage ouvert existe pour le user, le clôturer
   `date_end = H - 1 min` avant d'ouvrir le nouveau.
2. `FIN_JOURNEE` → clôture le pointage en cours (`date_end = H`), **sans** réouverture.
3. Aucun pointage ouvert → simple démarrage.
4. Modification / suppression d'un pointage existant (recalc `time_spent` après coup).
5. Après tout create/update/delete touchant un pointage `type='job'` :
   recalcul `time_spent` du job concerné = Σ durées pointages `job` (closes) — **isolé**,
   pas de recalcul des totaux facturation.

---

## D. Endpoint AJAX `ajax/planning_ajax.php` (à créer)

Réponses JSON, `header('Content-Type: application/json')`, **CSRF** (`newToken()`/`checkToken()`),
contrôle de droits par action, filtre `entity`.
Actions :
- `get_planning_day`, `get_planning_week` (données grille 2 sous-lignes)
- `get_gantt_data`, `get_dashboard_data`
- `create_pointage`, `update_pointage`, `delete_pointage`
- `assign_job` (drag&drop : maj `fk_user_assign` + `date_start`/`date_end` du job)
- `create_or_quick`
- helpers autocomplete : `search_or`, `get_jobs_for_or`, `get_vehicules_for_soc`

---

## E. Front — `workshop_planning.php` (réécriture rendu) + assets

- Conserver le bootstrap Dolibarr, droits (`workshopplanning:read` / `workshopmecanicsplanning:read`),
  onglets `dol_get_fiche_head`, navigation date par liens PHP.
- 3 vues : `dashboard`, `gantt` (Gantt OR CSS natif), `mecaniciens` (sous-modes `day`/`week`).
- `css/workshop.css` (créer) : variables `--workshop-*`, grille, blocs, double sous-ligne,
  zone non-affectés, indicateur « maintenant », vue semaine, charge %.
- `js/workshop_planning.js` (créer) : module `WorkshopPlanning` (init injecté depuis PHP),
  `renderDayGrid` (2 sous-lignes), `renderWeekGrid`, drag&drop HTML5, modales (pointage / affectation /
  OR rapide), indicateur « maintenant », refresh optionnel.
- Charger CSS/JS via `$TIncludeCSS`/`$TIncludeJS`.
- Hook `executeHooks('planningView', ...)`.

---

## F. modWorkshop.class.php

- Déclarer la table `llx_workshop_pointage` et le dictionnaire `llx_workshop_c_impro`.
- (Constante `WORKSHOP_MECHANIC_GROUP` déjà déclarée/gérée → rien à ajouter.)
- Déclarer le hook `planningView`.
- (Aucun nouveau droit requis : `workshopplanning` / `workshopmecanicsplanning` / `operationorders`
  couvrent les besoins ; à confirmer pour l'écriture de pointage → `workshopmecanicsplanning:write`.)

---

## G. Traductions
Ajouter les clés planning manquantes dans `langs/fr_FR/workshop.lang` et `langs/en_US/workshop.lang`
(modale pointage, impro, absence, dashboard, gantt, charge, drag&drop, OR rapide…).

---

## H. Ordre de développement proposé (phasé)

**Phase 1 — Socle données**
1. SQL `llx_workshop_pointage` + classe `WorkshopPointage` (CRUD + règles métier C).
2. SQL/dico `llx_workshop_c_impro` + classe + page admin `setup_impro.php` + codes réservés (IMPFin/IMPAnnul).
3. Helper `workshop_get_mechanics()` basé sur `WORKSHOP_MECHANIC_GROUP` (groupe déjà paramétré).

**Phase 2 — Vue mécaniciens journée (cœur)**
4. CSS `workshop.css` (base + grille double sous-ligne).
5. `ajax/planning_ajax.php` : `get_planning_day`.
6. `renderDayGrid` + indicateur « maintenant ».
7. Création/modif pointage (modale + endpoints + recalc `time_spent`).
8. Drag&drop affectation job (`assign_job` + modale).

**Phase 3 — Semaine + Gantt + Dashboard**
9. Vue semaine mécaniciens + switch de mode.
10. Gantt OR CSS natif (retrait JSGantt).
11. Dashboard (métriques PHP + liste OR du jour).
12. Bouton « OR rapide » (`create_or_quick`).

**Phase 4 — Finitions**
13. Retrait complet FullCalendar.
14. Tests cas limites pointage (clôture auto, fin de journée, annulation, chevauchements).
15. Rafraîchissement automatique (polling AJAX).

---

## I. Checklist qualité (avant « terminé », cf. CLAUDE.md §11)
- [ ] Droits vérifiés sur chaque action (`$user->hasRight('workshop', ...)`)
- [ ] Aucun statut OR hardcodé (passage par le système paramétré)
- [ ] Filtre `entity` sur toutes les requêtes
- [ ] `GETPOST`/`GETPOSTINT` + `dol_escape_*` + `$db->escape()` / `(int)`
- [ ] CSRF sur l'AJAX
- [ ] Cohérence avec un futur script de migration (table pointage)
- [ ] `time_spent` isolé de la facturation (pas d'effet de bord `total_ht`)
