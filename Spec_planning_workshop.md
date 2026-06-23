# Spécification — Refonte du planning atelier (module Workshop / Dolibarr)

**Destinataire :** Claude Code  
**Auteur :** Spécification issue d'une session de conception avec T-SERVICES  
**Date :** Juin 2025  
**Module concerné :** `workshop` — fichier principal `workshop_planning.php`

---

## 1. Contexte et objectif

Le module Workshop gère les ordres de réparation (OR), les véhicules et les mécaniciens d'un atelier. Le planning existant (`workshop_planning.php`) utilise FullCalendar 5 pour les modes "atelier" et "pointages", et une table HTML basique pour le mode "journée". Ce rendu est jugé insuffisant ergonomiquement.

**Objectif :** Remplacer l'ensemble du rendu du planning par une grille sur-mesure en HTML/CSS/JS pur (sans librairie externe payante), respectant strictement les standards de développement Dolibarr, offrant un rendu professionnel et une expérience "tour de contrôle" pour le chef d'atelier.

**FullCalendar peut être retiré** du projet — il ne sera plus utilisé pour les vues mécaniciens. Il peut être conservé temporairement pour la vue Gantt OR si souhaité, mais l'objectif final est de s'en affranchir complètement.

---

## 2. Structure des données existantes (à ne pas modifier)

### Tables SQL impliquées

```sql
-- Ordre de réparation
llx_workshop_operationorder
  rowid, ref, fk_soc, fk_vehicule, fk_conducteur,
  date_start, date_end, date_planned,
  status (integer → fk vers llx_workshop_operationorderstatus)
  km, total_ht, ...

-- Travaux (jobs) d'un OR
llx_workshop_operationorder_jobs
  rowid, fk_operationorder,
  label, description,
  fk_service_type, fk_user_assign,  ← mécanicien affecté au job
  qty_mo, prix_mo,
  time_planned (duration), time_spent (duration),
  date_start, date_end,
  rang, total_ht, ...

-- Statuts dynamiques des OR (workflow configurable)
llx_workshop_operationorderstatus
  rowid, code, label, color, rang,
  display_on_planning (bool),  ← contrôle la visibilité sur le planning
  or_pointable (bool),         ← pointage autorisé sur les OR à ce statut
  ...

-- Planning horaire (atelier / groupe / mécanicien)
llx_workshop_planning
  rowid, fk_object, object_type ('workshop'|'usergroup'|'user'),
  active,
  lundi_heuredam, lundi_heurefam, lundi_heuredpm, lundi_heurefpm,
  mardi_heuredam, ... (idem pour chaque jour)
  entity
```

### Table à créer : pointages mécaniciens

```sql
CREATE TABLE llx_workshop_pointage (
  rowid           integer AUTO_INCREMENT PRIMARY KEY,
  date_creation   datetime         DEFAULT NULL,
  tms             timestamp        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user         integer NOT NULL,               -- mécanicien
  fk_job          integer          DEFAULT NULL,  -- NULL si improductif
  fk_operationorder integer        DEFAULT NULL,  -- dénormalisé pour perf
  type            varchar(32)      NOT NULL DEFAULT 'job',
                                                  -- 'job' | 'impro' | 'absence'
  impro_code      varchar(64)      DEFAULT NULL,  -- code improductif si type='impro'
  date_start      datetime         NOT NULL,
  date_end        datetime         DEFAULT NULL,  -- NULL = pointage en cours
  note            varchar(255)     DEFAULT NULL,
  fk_user_creat   integer NOT NULL DEFAULT 0,
  fk_user_modif   integer          DEFAULT NULL,
  entity          integer NOT NULL DEFAULT 1
) ENGINE=innodb;

-- Index de performance
ALTER TABLE llx_workshop_pointage
  ADD INDEX idx_pointage_user_date (fk_user, date_start),
  ADD INDEX idx_pointage_job (fk_job),
  ADD INDEX idx_pointage_open (fk_user, date_end);
```

### Règles métier pointage (CRITIQUES — à implémenter strictement)

1. **Un nouveau pointage clôture automatiquement le précédent** : quand on crée un pointage pour le mécanicien X à l'heure H, si un pointage est ouvert (`date_end IS NULL`) pour X, il est automatiquement clôturé avec `date_end = H - 1 minute`.
2. **Fin de journée** : action spéciale qui clôture le pointage en cours sans en ouvrir un nouveau. `date_end = heure saisie`.
3. **Si aucun pointage en cours** : le nouveau pointage démarre simplement, sans clôture.
4. **Modification** : clic sur un bloc existant (en cours ou terminé) → formulaire de modification permettant de changer heure début, heure fin, type, OR/job, note.

Ces règles s'appliquent dans `ajax/planning_ajax.php` (endpoint backend).

---

## 3. Architecture de la page planning

### Fichier principal : `workshop_planning.php`

Conserver la structure Dolibarr existante :
- Bootstrap Dolibarr (`main.inc.php`, `llxHeader`, `llxFooter`)
- Droits : `workshopplanning:read` pour la vue atelier, `workshopmecanicsplanning:read` pour les vues mécaniciens
- Onglets Dolibarr (`dol_get_fiche_head`) pour switcher entre les vues
- Navigation date via liens PHP (`$prev_date`, `$next_date`) — pas de navigation JS pure

### Trois vues principales (onglets)

| Onglet | Clé mode | Droit requis |
|--------|----------|--------------|
| Tableau de bord | `dashboard` | `workshopplanning:read` |
| Planning charge (Gantt OR) | `gantt` | `workshopplanning:read` |
| Planning mécaniciens | `mecaniciens` | `workshopmecanicsplanning:read` |

### Endpoint AJAX : `ajax/planning_ajax.php`

Toutes les interactions (chargement données, création pointage, affectation job) passent par cet endpoint. Paramètre `action` :
- `get_planning_day` → données grille journée
- `get_planning_week` → données grille semaine
- `get_gantt_data` → données Gantt OR
- `get_dashboard_data` → données tableau de bord
- `create_pointage` → créer un pointage (applique règles métier)
- `update_pointage` → modifier un pointage existant
- `assign_job` → affecter un job à un mécanicien (glissé-déposé)
- `create_or_quick` → créer un OR rapide depuis le planning

Format de réponse : JSON systématiquement. Utiliser `json_encode()` et `header('Content-Type: application/json')`.

---

## 4. Vue 1 — Tableau de bord

### Objectif
Vue synthétique "un coup d'œil suffit". Peut s'afficher sur un écran TV en fond d'atelier (pas de login requis si configuré ainsi, mais hors scope immédiat).

### Contenu

**Ligne de métriques (4 cartes)** :
```
┌─────────────────┐ ┌─────────────────┐ ┌──────────────────┐ ┌──────────────┐
│ Véhicules       │ │ OR non planifiés│ │ Jobs sans        │ │ Charge       │
│ présents        │ │                 │ │ mécanicien       │ │ atelier      │
│      12         │ │        4        │ │        7         │ │    78 %      │
└─────────────────┘ └─────────────────┘ └──────────────────┘ └──────────────┘
```

Couleurs des cartes : bleu / amber / coral / vert. Chiffres calculés côté PHP au chargement de page (pas d'AJAX pour le tableau de bord, sauf si rafraîchissement automatique activé).

**Formules de calcul** :
- Véhicules présents : OR avec `date_start <= NOW()` ET (`date_end IS NULL` OU `date_end >= NOW()`) ET statut actif
- OR non planifiés : OR sans `date_planned` ET statut `display_on_planning = 1`
- Jobs sans mécanicien : jobs avec `fk_user_assign IS NULL` appartenant à des OR actifs
- Charge atelier : `(somme time_planned des jobs affectés aujourd'hui) / (nb_mécaniciens × heures_journée × 3600) × 100`

**Liste OR du jour** (tableau Dolibarr standard) : OR ayant `date_planned = aujourd'hui` ou en cours, avec colonnes : Ref, Véhicule, Client, Statut (badge coloré), Avancement (barre de progression), Actions.

---

## 5. Vue 2 — Planning charge atelier (Gantt OR)

### Structure HTML

Grille HTML/CSS pure. **Pas de librairie Gantt externe**.

```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│              │   Lun 16     │   Mar 17     │   Mer 18 ▼   │
├──────────────┼──────────────┼──────────────┼──────────────┤
│ OR-2024-001  │ ████████████████████         │              │
│ OR-2024-002  │              │              │ ████████████ │
│ OR-2024-003  │ █████        │              │              │
│ OR-2024-004  │              │              │ ████ ⚠       │
│ OR-2024-005  │ - - - - A PLANIFIER - - - - │              │
└──────────────┴──────────────┴──────────────┴──────────────┘
```

### Implémentation

- Grille CSS `display: grid` avec `grid-template-columns: 130px repeat(N, 1fr)` où N = nombre de jours affichés (7 par défaut)
- Colonne gauche : ref OR + immatriculation véhicule (tronqué avec `title` pour tooltip)
- Chaque barre OR = `<div>` positionné via `grid-column-start` et `grid-column-end` calculés en PHP
- La largeur d'une barre = nb jours × (100 / N) % — calculé PHP, rendu CSS inline
- Barre avec barre de progression interne : `<div class="or-bar"><div class="or-progress" style="width:60%"></div></div>`

### Code couleur des barres OR

| État | Couleur CSS var | Condition |
|------|----------------|-----------|
| En cours | `--workshop-blue` | statut actif + date_start passée |
| Planifié | `--workshop-teal` | date_planned définie, pas démarré |
| Terminé | `--workshop-green` | statut terminé |
| Sans mécanicien | `--workshop-amber` | jobs sans fk_user_assign |
| Non planifié | `--workshop-coral` + bordure pointillée | date_planned NULL |
| Multi-mécaniciens | `--workshop-purple` | plusieurs fk_user_assign distincts |

### Variables CSS à déclarer dans le fichier CSS du module

```css
:root {
  --workshop-blue:   #378ADD;
  --workshop-teal:   #1D9E75;
  --workshop-green:  #639922;
  --workshop-amber:  #BA7517;
  --workshop-coral:  #D85A30;
  --workshop-purple: #7F77DD;
  --workshop-gray:   #888780;
  --workshop-row-height: 36px;
  --workshop-header-height: 32px;
}
```

### Interactions sur le Gantt

- **Clic sur une barre OR** : redirige vers `operationorder/or_card.php?id=X` (lien PHP simple, pas d'AJAX)
- **Clic sur "A PLANIFIER"** : ouvre la modale de planification OR (voir §8)
- **Colonne "aujourd'hui"** : fond légèrement coloré (`rgba(55,138,221,0.07)`)

### Navigation

- Boutons Prev / Next : liens PHP `?mode=gantt&date=YYYY-MM-DD`
- Sélecteur de période : semaine (défaut) / mois (optionnel phase 2)

---

## 6. Vue 3 — Planning mécaniciens

C'est la vue la plus complexe et la plus importante. Elle possède **deux sous-modes** accessibles par boutons toggle (pas d'onglets séparés) :

### 6.1 Sous-mode Journée (défaut)

**Structure HTML de la grille** :

```
┌─────────────┬───────────────────────────────────────────────────┐
│             │  08:00  08:30  09:00  09:30  10:00  10:30 ...     │
├─────────────┼───────────────────────────────────────────────────┤
│ Jean-Pierre │ [OR-001 · Vidange        ][OR-006 · Freins AV  ] │
├─────────────┼───────────────────────────────────────────────────┤
│ Marc        │ [IMPRO][              ][OR-006 · Révision       ] │
├─────────────┼───────────────────────────────────────────────────┤
│ Sophie      │        [OR-002 · Contrôle courroie          ]     │
├─────────────┼───────────────────────────────────────────────────┤
│ Thomas      │ [OR-001][ ⏸ ][OR-001][                          ] │
├─────────────┼───────────────────────────────────────────────────┤
│ Karim       │ ░░░░░░░░░░░ Absent — Congé ░░░░░░░░░░░░░░░░░░░░ │
├─────────────┼───────────────────────────────────────────────────┤
│ ⚠ Non aff.  │ [OR-004 · Plaquettes][OR-006 · Géométrie][OR-003]│
└─────────────┴───────────────────────────────────────────────────┘
```

**Implémentation technique** :

La grille est un `<div>` en CSS Grid :
```css
.workshop-day-grid {
  display: grid;
  grid-template-columns: 130px 1fr;
  border: 0.5px solid var(--color-border-tertiary);
}
.workshop-day-timeline {
  position: relative;
  height: var(--workshop-row-height);
  overflow: hidden;
}
```

La timeline horizontale est un `<div>` en `position: relative`. Chaque bloc pointage/job est un `<div>` en `position: absolute` avec `left` et `width` calculés en pourcentage :

```php
// Calcul PHP → données JSON pour JS
$slot_start_ts = strtotime($slot_min); // ex: 07:00
$slot_end_ts   = strtotime($slot_max); // ex: 18:00
$total_seconds = $slot_end_ts - $slot_start_ts;

$left_pct  = ($pointage_start_ts - $slot_start_ts) / $total_seconds * 100;
$width_pct = ($pointage_duration_seconds) / $total_seconds * 100;
```

Les données sont injectées en JSON depuis PHP dans une variable JS `window.workshopPlanningData = {...}` et le rendu des blocs est fait par une fonction JS `renderDayGrid(data)`.

**Affichage d'un bloc pointage** :
```html
<div class="wp-block wp-block--job wp-block--inprogress"
     style="left: 23.5%; width: 18.2%;"
     data-pointage-id="42"
     data-job-id="7"
     title="OR-001 · Vidange moteur — 08:00 → en cours">
  <span class="wp-block-label">OR-001 · Vidange</span>
  <span class="wp-block-time">08:00</span>
</div>
```

Classes CSS des blocs :
- `wp-block--job` : pointage sur OR/job (couleur de l'OR ou du statut)
- `wp-block--impro` : improductif (coral)
- `wp-block--absence` : absence (gris rayé)
- `wp-block--inprogress` : pointage en cours (animation de bordure pulsante légère)
- `wp-block--interrupted` : bloc interrompu (opacité réduite + icône pause)

**Ligne "Jobs non affectés"** : dernière ligne de la grille, fond amber clair, affiche les jobs sans mécanicien comme des chips draggables. Pas de timeline horizontale — disposition en `flex-wrap`.

```html
<div class="wp-unassigned-row">
  <span class="wp-unassigned-label">⚠ Non affectés</span>
  <div class="wp-unassigned-jobs">
    <div class="wp-job-chip" draggable="true" data-job-id="15" data-or-id="4">
      OR-004 · Plaquettes
    </div>
    ...
  </div>
</div>
```

**Indicateur "maintenant"** : ligne verticale rouge en `position: absolute` sur toutes les lignes mécaniciens, calculée en JS (`new Date()` → pourcentage) et mise à jour toutes les 60 secondes.

### 6.2 Sous-mode Semaine

Bascule via bouton toggle dans la barre de navigation de la vue :
```html
<div class="wp-mode-toggle">
  <button class="wp-mode-btn active" data-mode="day">Journée</button>
  <button class="wp-mode-btn" data-mode="week">Semaine</button>
</div>
```

En mode semaine, la grille change d'axe horizontal :
- Colonnes = 7 jours (lundi → dimanche, ou lundi → samedi selon config)
- Lignes = mécaniciens (idem mode journée)
- Chaque cellule = récapitulatif des pointages du jour pour ce mécanicien

```
┌─────────────┬─────────┬─────────┬─────────┬─────────┬─────────┐
│             │ Lun 16  │ Mar 17  │ Mer 18▼ │ Jeu 19  │ Ven 20  │
├─────────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ Jean-Pierre │OR-001   │OR-001   │OR-006   │         │OR-002   │
│             │OR-006   │         │         │         │         │
├─────────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ Marc        │OR-006   │IMPRO    │OR-006   │OR-004   │         │
├─────────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ Karim       │ Absent  │ Absent  │OR-003   │OR-003   │OR-005   │
├─────────────┼─────────┼─────────┼─────────┼─────────┼─────────┤
│ Charge %    │  85%    │  90%    │  70%    │  88%    │  60%    │
└─────────────┴─────────┴─────────┴─────────┴─────────┴─────────┘
```

**Comportement clé** : clic sur une cellule de jour → bascule automatiquement en mode Journée sur ce jour (`?mode=mecaniciens&submode=day&date=YYYY-MM-DD`).

**Ligne "Charge %"** : dernière ligne, cellules colorées (vert si > 75%, amber si 50-75%, rouge si < 50%). Calcul : `(somme pointages du jour) / (nb mécaniciens présents × heures journée) × 100`.

**Aujourd'hui surligné** : colonne du jour courant avec fond `rgba(55,138,221,0.07)` et indicateur "▼ auj." dans l'en-tête.

---

## 7. Interactions — détail des trois actions

### 7.1 Clic sur un créneau vide (création pointage)

**Déclencheur** : clic sur zone vide de la timeline d'un mécanicien (mode journée).

**Comportement** :
1. Calcul de l'heure cliquée depuis la position X du clic et les dimensions de la timeline
2. Ouverture d'une modale Bootstrap (utiliser `$('#workshopModal').modal('show')` — jQuery est disponible dans Dolibarr)
3. La modale contient un formulaire avec **3 onglets** :
    - **Sur OR / Job** : select OR (AJAX autocomplete `?action=search_or`) → select Job (chargé dynamiquement selon OR choisi) → heure début (préremplie) → heure fin (vide = pointage ouvert)
    - **Improductif** : select type improductif (depuis `llx_workshop_c_servicetype` avec `active=1`) → heure début → heure fin → note
    - **Absence** : type absence (select) → toute la journée (checkbox) ou heure début/fin → note

**Soumission** : `jQuery.post('ajax/planning_ajax.php', {action:'create_pointage', ...})` → réponse JSON → fermeture modale → rechargement de la ligne mécanicien concernée (AJAX partiel, pas rechargement page entière).

### 7.2 Clic sur un bloc existant (modification)

**Déclencheur** : clic sur un `wp-block` existant.

**Comportement** : même modale, préremplie avec les données du pointage. L'onglet actif correspond au type du pointage. Champ supplémentaire : heure de fin (peut être renseignée même si le pointage est en cours).

**Suppression** : bouton "Supprimer" dans la modale (rouge, confirmation requise).

### 7.3 Glissé-déposé d'un job vers un mécanicien

**Implémentation** : HTML5 Drag and Drop API (pas de librairie jQuery UI — éviter les dépendances).

```javascript
// Sur les chips de la zone "non affectés"
jobChip.addEventListener('dragstart', function(e) {
  e.dataTransfer.setData('job_id', this.dataset.jobId);
  e.dataTransfer.setData('or_id', this.dataset.orId);
  this.classList.add('wp-dragging');
});

// Sur les lignes mécaniciens (drop targets)
mecLine.addEventListener('dragover', function(e) {
  e.preventDefault(); // autoriser le drop
  this.classList.add('wp-drop-target');
});

mecLine.addEventListener('drop', function(e) {
  e.preventDefault();
  const jobId = e.dataTransfer.getData('job_id');
  const userId = this.dataset.userId;
  // Calcul heure depuis position X
  const hour = computeHourFromX(e.clientX, this);
  openAssignModal(jobId, userId, hour);
});
```

**Modale d'affectation** (ouverte après le drop) :
- Récapitulatif du job (lecture seule) : OR, libellé job
- Mécanicien (prérempli, modifiable)
- Date prévue (préremplie = date courante)
- Créneau : Matin / Après-midi / Journée (select) + heure début (préremplie depuis position drop)
- Durée estimée (h) : préremplie depuis `time_planned` du job

**Soumission** : `ajax/planning_ajax.php?action=assign_job` → met à jour `fk_user_assign` + `date_start` + `date_end` sur le job → retour JSON → retrait du chip de la zone "non affectés" + ajout du bloc sur la ligne mécanicien.

### 7.4 Bouton "Nouvel OR" depuis le planning

**Position** : bouton dans la barre de navigation de la vue mécaniciens, à droite.

```php
print '<a class="butAction" href="#" id="btn-new-or-planning">';
print img_picto('', 'fa-plus') . ' ' . $langs->trans('NewOR');
print '</a>';
```

**Modale "OR rapide"** : formulaire minimaliste :
- Client / Tiers (autocomplete Dolibarr standard `select_thirdparty_list` ou AJAX)
- Véhicule (select filtré sur le tiers choisi, chargé dynamiquement)
- Description courte (input text)
- **Section "Planification immédiate"** (optionnelle, repliable) :
    - Mécanicien (select parmi les mécaniciens configurés)
    - Heure de début

**Bouton principal** : "Créer et ouvrir l'OR" → `ajax/planning_ajax.php?action=create_or_quick` → création en base → retour JSON avec `or_id` → redirection JS vers `operationorder/or_card.php?id=X`.

---

## 8. Barre de navigation commune aux vues planning

```php
// Structure PHP à reproduire
print '<div class="workshop-planning-nav">';

// Prev
print '<a class="butAction wp-nav-btn" href="'.dol_buildpath(...).'?mode='.$mode.'&date='.$prev_date.'">';
print img_picto('', 'fa-chevron-left');
print '</a>';

// Sélecteur date
print '<form method="GET" ...>'; // input date natif

// Next
print '<a class="butAction wp-nav-btn" href="...?date='.$next_date.'">';
print img_picto('', 'fa-chevron-right');
print '</a>';

// Label période
print '<span class="wp-period-label">'.$period_label.'</span>';

// Bouton Aujourd'hui
print '<a class="butAction" href="...?date='.date('Y-m-d').'">'.$langs->trans('Today').'</a>';

// Toggle Journée / Semaine (uniquement vue mécaniciens)
if ($mode === 'mecaniciens') {
  print '<div class="wp-mode-toggle">...';
}

// Bouton Nouvel OR (uniquement vue mécaniciens)
if ($mode === 'mecaniciens' && $user->hasRight('workshop','operationorders','write')) {
  print '<a class="butActionNew" href="#" id="btn-new-or-planning">...';
}

print '</div>';
```

---

## 9. CSS — règles à ajouter dans `css/workshop.css`

```css
/* === Planning général === */
.workshop-planning-nav {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 12px 0 8px 0;
  flex-wrap: wrap;
}
.wp-period-label {
  font-weight: 500;
  font-size: 14px;
  color: var(--colortext);
}
.wp-mode-toggle {
  display: flex;
  border: 0.5px solid var(--inputbordercolor);
  border-radius: 4px;
  overflow: hidden;
}
.wp-mode-btn {
  padding: 4px 14px;
  font-size: 13px;
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--colortext);
}
.wp-mode-btn.active {
  background: var(--colorbackbuttonedit);
  color: var(--colorbackbuttonedittext);
}

/* === Grille mécaniciens === */
.workshop-day-grid {
  border: 0.5px solid var(--inputbordercolor);
  border-radius: 4px;
  overflow: hidden;
  margin-top: 8px;
}
.wp-grid-header {
  display: grid;
  grid-template-columns: 130px 1fr;
  background: var(--colorbacktitle1);
  border-bottom: 0.5px solid var(--inputbordercolor);
  min-height: var(--workshop-header-height, 32px);
}
.wp-grid-row {
  display: grid;
  grid-template-columns: 130px 1fr;
  border-bottom: 0.5px solid var(--inputbordercolor);
  min-height: var(--workshop-row-height, 40px);
}
.wp-grid-row:last-child { border-bottom: none; }
.wp-grid-row:hover { background: rgba(0,0,0,0.02); }
.wp-mechanic-name {
  padding: 0 8px;
  display: flex;
  align-items: center;
  font-size: 13px;
  font-weight: 500;
  border-right: 0.5px solid var(--inputbordercolor);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.wp-timeline {
  position: relative;
  overflow: hidden;
}

/* === Blocs pointage === */
.wp-block {
  position: absolute;
  top: 3px;
  height: calc(100% - 6px);
  border-radius: 3px;
  cursor: pointer;
  overflow: hidden;
  white-space: nowrap;
  font-size: 11px;
  display: flex;
  align-items: center;
  padding: 0 4px;
  gap: 4px;
  transition: opacity 0.15s;
  border: 0.5px solid rgba(0,0,0,0.15);
}
.wp-block:hover { opacity: 0.85; }
.wp-block--job       { background: #B5D4F4; color: #0C447C; }
.wp-block--impro     { background: #F5C4B3; color: #712B13; }
.wp-block--absence   { background: repeating-linear-gradient(45deg, #e0e0e0, #e0e0e0 4px, #f5f5f5 4px, #f5f5f5 8px); color: #5F5E5A; }
.wp-block--inprogress {
  background: #85B7EB; color: #042C53;
  animation: wp-pulse-border 2s ease-in-out infinite;
}
.wp-block--interrupted { opacity: 0.6; }
@keyframes wp-pulse-border {
  0%,100% { box-shadow: 0 0 0 1px rgba(55,138,221,0.4); }
  50%      { box-shadow: 0 0 0 2px rgba(55,138,221,0.8); }
}
.wp-block-label { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.wp-block-time  { font-size: 10px; opacity: 0.75; flex-shrink: 0; }

/* === Ligne "non affectés" === */
.wp-unassigned-row {
  background: #FAEEDA;
  border-top: 1.5px dashed #BA7517;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  flex-wrap: wrap;
}
.wp-unassigned-label {
  font-size: 12px;
  color: #633806;
  font-weight: 500;
  white-space: nowrap;
}
.wp-job-chip {
  background: #FAC775;
  color: #412402;
  border-radius: 3px;
  padding: 3px 8px;
  font-size: 12px;
  cursor: grab;
  border: 0.5px solid #BA7517;
}
.wp-job-chip:active { cursor: grabbing; }
.wp-job-chip.wp-dragging { opacity: 0.5; }

/* === Drop target === */
.wp-drop-target .wp-timeline {
  background: rgba(55,138,221,0.08);
  outline: 1.5px dashed #378ADD;
}

/* === Indicateur "maintenant" === */
.wp-now-indicator {
  position: absolute;
  top: 0; bottom: 0;
  width: 1.5px;
  background: #E24B4A;
  z-index: 10;
  pointer-events: none;
}

/* === Vue semaine === */
.workshop-week-grid {
  border: 0.5px solid var(--inputbordercolor);
  border-radius: 4px;
  overflow: hidden;
  margin-top: 8px;
}
.wp-week-row {
  display: grid;
  grid-template-columns: 130px repeat(var(--wp-day-count, 5), 1fr);
  border-bottom: 0.5px solid var(--inputbordercolor);
  min-height: 52px;
}
.wp-week-cell {
  border-right: 0.5px solid var(--inputbordercolor);
  padding: 3px 4px;
  font-size: 11px;
  cursor: pointer;
  transition: background 0.1s;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow: hidden;
}
.wp-week-cell:hover { background: rgba(55,138,221,0.06); }
.wp-week-cell--today { background: rgba(55,138,221,0.05); }
.wp-week-cell--today-header { font-weight: 700; color: #185FA5; }
.wp-week-tag {
  border-radius: 2px;
  padding: 1px 4px;
  font-size: 10px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.wp-week-tag--job    { background: #B5D4F4; color: #0C447C; }
.wp-week-tag--impro  { background: #F5C4B3; color: #712B13; }
.wp-week-tag--absent { background: #D3D1C7; color: #2C2C2A; }
.wp-charge-row .wp-week-cell {
  align-items: center; justify-content: center;
  font-weight: 600; font-size: 12px;
}
.wp-charge--high   { background: #C0DD97; color: #27500A; }
.wp-charge--medium { background: #FAC775; color: #412402; }
.wp-charge--low    { background: #F09595; color: #501313; }
```

---

## 10. Structure JS — `js/workshop_planning.js`

Fichier à créer. Chargé via `$TIncludeJS` dans `workshop_planning.php`.

```javascript
/* workshop_planning.js — Planning atelier T-SERVICES */
var WorkshopPlanning = (function($) {
  'use strict';

  var cfg = {
    slotMin: '07:00',
    slotMax: '18:00',
    ajaxUrl: '',          // injecté depuis PHP
    currentDate: '',      // injecté depuis PHP
    currentMode: 'day',   // 'day' | 'week'
    refreshRate: 30,      // secondes, 0 = désactivé
    users: [],            // [{id, name}]
    csrfToken: ''         // newToken() depuis PHP
  };

  // --- Init ---
  function init(options) {
    $.extend(cfg, options);
    bindEvents();
    renderNowIndicator();
    if (cfg.refreshRate > 0) {
      setInterval(refresh, cfg.refreshRate * 1000);
    }
  }

  // --- Rendu grille journée ---
  function renderDayGrid(data) {
    // data = { users: [{id, name, pointages: [{id, type, label, start_pct, width_pct, classes}]}],
    //          unassigned_jobs: [{id, or_ref, label}] }
    // ... construction du HTML et injection dans .workshop-day-grid
  }

  // --- Rendu grille semaine ---
  function renderWeekGrid(data) {
    // data = { days: ['2025-06-16'...], users: [{id, name, days: {date: [tags]}}], charges: {date: pct} }
  }

  // --- Indicateur "maintenant" ---
  function renderNowIndicator() {
    var now = new Date();
    var slotStartMin = timeToMinutes(cfg.slotMin);
    var slotEndMin   = timeToMinutes(cfg.slotMax);
    var nowMin       = now.getHours() * 60 + now.getMinutes();
    var pct = (nowMin - slotStartMin) / (slotEndMin - slotStartMin) * 100;
    if (pct < 0 || pct > 100) { $('.wp-now-indicator').hide(); return; }
    $('.wp-now-indicator').css('left', pct.toFixed(2) + '%').show();
  }

  // --- Calcul heure depuis position X ---
  function computeHourFromX(clientX, timelineEl) {
    var rect     = timelineEl.getBoundingClientRect();
    var pct      = (clientX - rect.left) / rect.width;
    var slotMin  = timeToMinutes(cfg.slotMin);
    var slotMax  = timeToMinutes(cfg.slotMax);
    var minutes  = slotMin + pct * (slotMax - slotMin);
    return minutesToTime(Math.round(minutes / 15) * 15); // arrondi au quart d'heure
  }

  // --- Drag and Drop ---
  function bindDragDrop() {
    $(document).on('dragstart', '.wp-job-chip', function(e) {
      e.originalEvent.dataTransfer.setData('job_id', $(this).data('job-id'));
      e.originalEvent.dataTransfer.setData('or_id',  $(this).data('or-id'));
      $(this).addClass('wp-dragging');
    });
    $(document).on('dragend', '.wp-job-chip', function() {
      $(this).removeClass('wp-dragging');
    });
    $(document).on('dragover', '.wp-timeline', function(e) {
      e.preventDefault();
      $(this).closest('.wp-grid-row').addClass('wp-drop-target');
    });
    $(document).on('dragleave', '.wp-timeline', function() {
      $(this).closest('.wp-grid-row').removeClass('wp-drop-target');
    });
    $(document).on('drop', '.wp-timeline', function(e) {
      e.preventDefault();
      $(this).closest('.wp-grid-row').removeClass('wp-drop-target');
      var jobId  = e.originalEvent.dataTransfer.getData('job_id');
      var userId = $(this).closest('.wp-grid-row').data('user-id');
      var hour   = computeHourFromX(e.originalEvent.clientX, this);
      openAssignModal(jobId, userId, hour);
    });
  }

  // --- Modales ---
  function openPointageModal(userId, hour, pointageId) {
    // Si pointageId fourni → mode édition, sinon → mode création
    // Charge les données via AJAX si édition, prérempli heure si création
    $('#workshopModalPointage').modal('show');
  }
  function openAssignModal(jobId, userId, hour) {
    $('#workshopModalAssign').modal('show');
  }
  function openNewOrModal() {
    $('#workshopModalNewOR').modal('show');
  }

  // --- AJAX helpers ---
  function ajaxPost(action, data, callback) {
    data.token  = cfg.csrfToken;
    data.action = action;
    $.post(cfg.ajaxUrl, data, function(resp) {
      if (resp.success) {
        callback(null, resp);
      } else {
        callback(resp.error || 'Erreur inconnue');
      }
    }, 'json').fail(function() { callback('Erreur réseau'); });
  }

  // --- Utilitaires ---
  function timeToMinutes(t) {
    var p = t.split(':');
    return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
  }
  function minutesToTime(m) {
    var h = Math.floor(m / 60), mn = m % 60;
    return (h < 10 ? '0' : '') + h + ':' + (mn < 10 ? '0' : '') + mn;
  }
  function refresh() {
    renderNowIndicator();
    // Recharger données AJAX silencieusement
  }
  function bindEvents() {
    bindDragDrop();
    $(document).on('click', '.wp-timeline', function(e) {
      if ($(e.target).hasClass('wp-block')) return; // géré séparément
      var userId = $(this).closest('.wp-grid-row').data('user-id');
      var hour   = computeHourFromX(e.clientX, this);
      openPointageModal(userId, hour, null);
    });
    $(document).on('click', '.wp-block', function(e) {
      e.stopPropagation();
      openPointageModal(null, null, $(this).data('pointage-id'));
    });
    $(document).on('click', '.wp-week-cell', function() {
      var date = $(this).data('date');
      if (date) window.location = cfg.ajaxUrl.replace('ajax/planning_ajax.php','workshop_planning.php')
        + '?mode=mecaniciens&submode=day&date=' + date;
    });
    $(document).on('click', '#btn-new-or-planning', function(e) {
      e.preventDefault();
      openNewOrModal();
    });
    $(document).on('click', '.wp-mode-btn', function() {
      $('.wp-mode-btn').removeClass('active');
      $(this).addClass('active');
      cfg.currentMode = $(this).data('mode');
      // Recharger la grille en mode approprié
    });
  }

  return { init: init };

})(jQuery);
```

Ce fichier est initialisé depuis PHP en fin de page :
```php
print '<script type="text/javascript">';
print 'WorkshopPlanning.init({';
print '  slotMin: '.json_encode($slot_min).',';
print '  slotMax: '.json_encode($slot_max).',';
print '  ajaxUrl: "'.dol_buildpath('/workshop/ajax/planning_ajax.php', 1).'",';
print '  currentDate: "'.dol_escape_js($date_str).'",';
print '  csrfToken: "'.newToken().'",';
print '  refreshRate: '.intval(getDolGlobalString('WORKSHOP_OR_PLANNING_REFRESH_RATE', 30)).',';
print '  users: '.json_encode($users_json).'';
print '});';
print '</script>';
```

---

## 11. Standards Dolibarr à respecter impérativement

- **Sécurité** : tout input validé avec `GETPOST()`, `GETPOSTINT()`. Toutes les sorties HTML via `dol_escape_htmltag()`. Token CSRF via `newToken()` / `checkToken()` dans l'AJAX.
- **Base de données** : toujours `$db->escape()` sur les chaînes. Utiliser `$db->prefix()` pour les noms de tables. Transactions pour les opérations multi-tables (création pointage + clôture précédent).
- **Droits** : vérifier `$user->hasRight('workshop', ...)` avant chaque action AJAX.
- **Traductions** : toutes les chaînes via `$langs->trans('CleDeTraduction')` dans `langs/fr_FR/workshop.lang` et `langs/en_US/workshop.lang`.
- **Logs** : `dol_syslog(__METHOD__.' message', LOG_DEBUG)` sur les opérations importantes.
- **CSS/JS** : charger via `$TIncludeCSS` et `$TIncludeJS` dans `llxHeader()`, jamais en inline dans le HTML sauf pour les variables de configuration JS.
- **Hooks** : implémenter `$hookmanager->executeHooks('planningView', ...)` pour permettre aux autres modules de s'intégrer.
- **Multi-entités** : toujours filtrer sur `entity = $conf->entity` (ou `IN (0, $conf->entity)` pour les objets partagés).

---

## 12. Fichiers à créer ou modifier

| Fichier | Action |
|---------|--------|
| `workshop_planning.php` | Modifier — remplacer le rendu par la nouvelle grille |
| `ajax/planning_ajax.php` | Créer — endpoint AJAX centralisé |
| `js/workshop_planning.js` | Créer — logique JS du planning |
| `css/workshop.css` | Modifier — ajouter les règles CSS planning |
| `sql/llx_workshop_pointage.sql` | Créer — table pointages |
| `sql/llx_workshop_pointage.key.sql` | Créer — index et clés |
| `class/workshoppointage.class.php` | Créer — classe CRUD pointage |
| `langs/fr_FR/workshop.lang` | Modifier — ajouter clés planning |
| `langs/en_US/workshop.lang` | Modifier — ajouter clés planning |

---

## 13. Ordre de développement recommandé

1. **SQL + classe** : créer `llx_workshop_pointage` et `WorkshopPointage` (CRUD standard Dolibarr)
2. **Endpoint AJAX** : squelette `ajax/planning_ajax.php` avec `get_planning_day` fonctionnel
3. **CSS** : toutes les règles de §9 dans `css/workshop.css`
4. **Grille journée** : rendu PHP + JS `renderDayGrid()` avec données statiques de test
5. **Interactions clic** : modale pointage (création + modification)
6. **Drag and drop** : modale affectation job
7. **Grille semaine** : rendu + switch de mode
8. **Vue Gantt OR** : remplacer FullCalendar par grille PHP native
9. **Tableau de bord** : métriques + liste OR du jour
10. **Bouton OR rapide** : modale + endpoint create_or_quick
11. **Tests** : cas limites pointage (clôture auto, fin journée, chevauchements)
12. **Rafraîchissement automatique** : polling AJAX silencieux
