# CLAUDE.md — Module Workshop (Dolibarr 21)

> Ce fichier est le guide de référence pour tout développement sur ce module.
> Il doit être lu intégralement avant toute modification ou création de code.

---

## 1. Contexte du projet

Le module **Workshop** est un module de gestion d'atelier mécanique poids lourds complet pour Dolibarr 21.
Il est le résultat de la **fusion et refonte** de deux anciens modules :
- `dolifleet` (gestion de véhicules et contrats de maintenance)
- `operationorder` (ordres de réparation)

Il ne s'agit pas d'une simple migration : la logique métier a été profondément repensée.

---

## 2. Périmètre fonctionnel

Workshop couvre :
- Gestion complète des **véhicules poids lourds** (fiche, historique, planification d'entretiens)
- **Ordres de Réparation** (OR) avec workflow de statuts configurable
- **Jobs** comme objet intermédiaire indépendant entre l'OR et les lignes de détail
- **Planification dynamique** des entretiens et de la capacité atelier
- **Planning des mécaniciens** et gestion des groupes
- **Pointage** des temps travaillés et improductifs
- **Facturation complète** (client et fournisseur), lien avec devis/commandes Dolibarr
- **Commandes fournisseurs multi-société**
- **Migration des données** depuis dolifleet et operationorder via script PHP dédié

---

## 3. Hiérarchie des objets Workshop

```
Vehicule                          → table: llx_workshop_vehicule
  └── VehiculeActivity            → table: llx_workshop_vehicule_activity
  └── VehiculeOperation           → table: llx_workshop_vehicule_operation
  └── VehiculeLink                → table: llx_workshop_vehicule_link

Operationorder (OR)               → table: llx_workshop_operationorder
  └── Operationorder_jobs (Job)   → table: llx_workshop_operationorder_jobs
        └── Operationorderdet     → table: llx_workshop_operationorderdet

WorkshopOperationOrderStatus      → table: llx_workshop_operationorder_status
WorkshopPlanning                  → table: llx_workshop_planning
Conducteur                        → table: llx_workshop_conducteur
ServiceType                       → table: llx_workshop_c_servicetype
Tag / OperationorderTag           → tables: llx_workshop_tag / llx_workshop_operationorder_tag
```

### Relations avec les objets natifs Dolibarr

| Objet Workshop       | Objet Dolibarr natif                        | Champ de liaison         |
|----------------------|---------------------------------------------|--------------------------|
| Vehicule             | Societe (client/fournisseur)                | fk_soc                   |
| Operationorder       | Societe                                     | fk_soc                   |
| Operationorder       | Vehicule                                    | fk_vehicule              |
| Operationorder       | Conducteur (lié à User)                     | fk_conducteur            |
| Operationorder_jobs  | User (mécanicien assigné)                   | fk_user_assign           |
| Operationorder       | Facture / Devis / Commande Dolibarr         | via linkedObjects        |
| Operationorderdet    | Product (pièces détachées, MO, services)    | fk_product               |
| WorkshopPlanning     | User / UserGroup / Atelier global           | fk_object + object_type  |

---

## 4. Nommage des classes et fichiers (RESPECTER STRICTEMENT)

| Objet                    | Classe PHP                  | Fichier                                  |
|--------------------------|-----------------------------|------------------------------------------|
| Ordre de Réparation      | `Operationorder`            | `class/operationorder.class.php`         |
| Job (travaux OR)         | `Operationorder_jobs`       | `class/operationorder_jobs.class.php`    |
| Ligne de détail          | `Operationorderdet`         | `class/operationorderdet.class.php`      |
| Véhicule                 | `Vehicule`                  | `class/Vehicule.class.php`               |
| Conducteur               | `Conducteur`                | `class/Conducteur.class.php`             |
| Statut OR                | `WorkshopOperationOrderStatus` | `class/workshopoperationorderstatus.class.php` |
| Planning atelier         | `WorkshopPlanning`          | `class/workshopplanning.class.php`       |
| Type de service          | `ServiceType`               | `class/servicetype.class.php`            |
| Tag OR                   | `OperationorderTag`         | `class/OperationorderTag.class.php`      |

**Toutes les tables SQL sont préfixées `workshop_`** (ex: `llx_workshop_operationorder`).  
**Ne jamais utiliser d'autres préfixes** hérités des anciens modules (`dolifleet_`, `operationorder_`).

---

## 5. Conventions de développement

### 5.1 Compatibilité obligatoire
- **PHP 8.0+** minimum (typage strict bienvenu, éviter les constructions dépréciées en PHP 8)
- **Dolibarr 21** cible — utiliser les APIs et helpers de la v21
- Tester la compatibilité multi-entité (`$this->ismultientitymanaged = 1`)

### 5.2 Pattern des classes métier
Toutes les classes métier étendent `CommonObject` :

```php
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class MonObjet extends CommonObject
{
    public $module = 'workshop';
    public $element = 'monobjet';
    public $table_element = 'workshop_monobjet';
    public $picto = 'fa-xxx';
    public $ismultientitymanaged = 1;
    public $isextrafieldmanaged = 1;

    // Déclarer les statuts comme constantes de classe
    const STATUS_DRAFT  = 0;
    const STATUS_ACTIVE = 1;

    // Déclarer $fields avec tous les champs (pattern ModuleBuilder)
    public $fields = array( ... );
}
```

### 5.3 Accès base de données
Toujours utiliser `$this->db` — jamais PDO, mysqli, ou connexion directe :

```php
$sql = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."workshop_operationorder";
$sql .= " WHERE entity = ".(int) $conf->entity;
$resql = $this->db->query($sql);
if ($resql) {
    while ($obj = $this->db->fetch_object($resql)) { ... }
    $this->db->free($resql);
} else {
    $this->error = $this->db->lasterror();
}
```

### 5.4 Workflow des statuts OR
Les statuts sont **entièrement créés et configurés par l'utilisateur** via l'administration du module.  
Il n'existe pas de statuts hardcodés dans le code.

Le système repose sur :
- `WorkshopOperationOrderStatus` (`llx_workshop_operationorder_status`) — définition des statuts
- `llx_workshop_operationorder_status_target` — enchaînements autorisés entre statuts
- `llx_workshop_operationorder_status_usergroup_rights` — droits de transition par groupe utilisateur

**Ne jamais introduire de constantes de statuts codées en dur.** Toute logique liée aux statuts doit passer par le système de paramétrage.

### 5.5 Helpers Dolibarr à utiliser
Préférer systématiquement les helpers natifs :

```php
// Sécurisation des inputs
GETPOST('myparam', 'alphanohtml')
GETPOSTINT('myid')

// Logging
dol_syslog(__METHOD__." message", LOG_DEBUG);

// Inclure des fichiers de module
dol_include_once('/workshop/class/operationorder.class.php');

// Dates
dol_now()
dol_print_date($date, 'dayhour')

// Sécurité
restrictedArea($user, 'workshop', $object->id, 'workshop_operationorder');
```

### 5.6 Hooks
Toujours initialiser les hooks dans les pages qui le permettent :

```php
$hookmanager->initHooks(array('workshopoperationordercard', 'globalcard'));
```

Déclarer les hooks disponibles dans `modWorkshop.class.php`.

---

## 6. Structure des URLs et pages

```
/workshop/workshopindex.php                        → tableau de bord
/workshop/vehicule/vehicule_list.php               → liste des véhicules
/workshop/vehicule/vehicule_card.php               → fiche véhicule
/workshop/or_list.php                              → liste des OR
/workshop/operationorder/or_card.php               → fiche OR
/workshop/operationorder/operationorder_planning.php → planning OR
/workshop/workshop_planning.php                    → planning atelier global
/workshop/workshop_mecanics_planning.php           → planning mécaniciens
/workshop/operationorder/or_status_list.php        → gestion des statuts
```

---

## 7. Sources de référence — LECTURE SEULE

### 7.1 Repo officiel Dolibarr — branche `21.0`

**URL de référence :** `https://github.com/Dolibarr/dolibarr/tree/21.0`

Ce repo est la **source de vérité** pour toutes les conventions Dolibarr.  
Avant d'implémenter une nouvelle fonctionnalité, consulter les chemins suivants sur cette branche :

| Besoin                              | Chemin dans le repo                                              |
|-------------------------------------|------------------------------------------------------------------|
| Classe métier (pattern CommonObject)| `htdocs/modulebuilder/template/class/myobject.class.php`        |
| Descripteur de module               | `htdocs/modulebuilder/template/core/modules/modMyModule.class.php` |
| Trigger                             | `htdocs/modulebuilder/template/core/triggers/interface_99_...`  |
| API REST                            | `htdocs/modulebuilder/template/class/api_mymodule.class.php`    |
| Génération PDF                      | `htdocs/modulebuilder/template/core/modules/mymodule/doc/pdf_standard_myobject.modules.php` |
| Page card (fiche objet)             | `htdocs/modulebuilder/template/myobject_card.php`               |
| Page liste                          | `htdocs/modulebuilder/template/myobject_list.php`               |
| Lib helper module                   | `htdocs/modulebuilder/template/lib/mymodule_myobject.lib.php`   |
| CommonObject (classe de base)       | `htdocs/core/class/commonobject.class.php`                      |
| CommonObjectLine (lignes)           | `htdocs/core/class/commonobjectline.class.php`                  |
| CommonInvoice (facturation)         | `htdocs/core/class/commoninvoice.class.php`                     |
| HookManager                         | `htdocs/core/class/hookmanager.class.php`                       |

#### Classes HTML / Formulaires

Ces classes génèrent les éléments d'interface Dolibarr. **Toujours les utiliser plutôt que de générer du HTML brut.**

| Classe          | Fichier                                      | Usage principal dans Workshop                              |
|-----------------|----------------------------------------------|------------------------------------------------------------|
| `Form`          | `htdocs/core/class/html.form.class.php`      | Sélecteurs génériques, tiers, produits, users, dates — **la plus utilisée** |
| `FormCompany`   | `htdocs/core/class/html.formcompany.class.php` | Sélecteur de société/tiers, contacts                     |
| `FormOrder`     | `htdocs/core/class/html.formorder.class.php` | Liens avec commandes clients                               |
| `FormFile`      | `htdocs/core/class/html.formfile.class.php`  | Gestion des documents joints sur les OR et véhicules       |
| `FormActions`   | `htdocs/core/class/html.formactions.class.php` | Agenda, événements liés aux OR                           |
| `FormOther`     | `htdocs/core/class/html.formother.class.php` | Sélecteurs divers (pays, devises, unités...)               |
| `FormSetup`     | `htdocs/core/class/html.formsetup.class.php` | Pages d'administration du module                           |

Exemple d'usage type :
```php
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$form = new Form($db);
// Sélecteur de tiers
$form->select_company($object->fk_soc, 'fk_soc', '', 1);
// Sélecteur d'utilisateur (mécanicien)
$form->select_dolusers($object->fk_user_assign, 'fk_user_assign', 1);
// Sélecteur de produit (pièce détachée)
$form->select_produits($object->fk_product, 'fk_product', '', 0);
```

#### Librairies de fonctions

Ne jamais réimplémenter ce qui existe déjà dans ces libs. Les inclure avec `require_once` ou `dol_include_once`.

| Lib                  | Fichier                              | Fonctions clés pour Workshop                                      |
|----------------------|--------------------------------------|-------------------------------------------------------------------|
| `functions.lib`      | `htdocs/core/lib/functions.lib.php`  | `GETPOST`, `dol_now`, `dol_print_date`, `dol_syslog`, `img_picto`, `dol_htmlentities`, `price2num` — **référence absolue (240 fonctions)** |
| `functions2.lib`     | `htdocs/core/lib/functions2.lib.php` | `dol_include_once`, `dol_buildpath`, gestion des menus            |
| `price.lib`          | `htdocs/core/lib/price.lib.php`      | Calcul des prix HT/TTC, arrondis — utiliser pour les totaux OR    |
| `date.lib`           | `htdocs/core/lib/date.lib.php`       | Manipulation de dates, créneaux horaires — clé pour le planning   |
| `files.lib`          | `htdocs/core/lib/files.lib.php`      | Upload, suppression, listage de documents joints                  |
| `pdf.lib`            | `htdocs/core/lib/pdf.lib.php`        | Helpers pour la génération des PDF d'OR                           |
| `security.lib`       | `htdocs/core/lib/security.lib.php`   | `dol_hash`, vérification des droits                               |
| `invoice.lib`        | `htdocs/core/lib/invoice.lib.php`    | Helpers pour la facturation client depuis les OR                  |
| `fourn.lib`          | `htdocs/core/lib/fourn.lib.php`      | Helpers pour les commandes/factures fournisseurs                  |

**Usage :** pour fetch un fichier précis, construire l'URL raw :
```
https://raw.githubusercontent.com/Dolibarr/dolibarr/21.0/htdocs/[chemin]
```

Exemple :
```
https://raw.githubusercontent.com/Dolibarr/dolibarr/21.0/htdocs/modulebuilder/template/class/myobject.class.php
```

### 7.2 `.claude/legacy/dolifleet/` — Ancien module dolifleet (branche V5)
Contient la logique de gestion des **véhicules poids lourds** à réutiliser.  
**Logique à récupérer :** gestion véhicule, activités, dimensions pneus, marques, types.  
**À ignorer/abandonner :**
- Toute logique de contrat de maintenance (`dictionaryContractType`, logique multi-concessionnaire)
- Tables SQL préfixées `llx_dolifleet_*` (abandonnées, voir script de migration)
- Classe `actions_dolifleet.class.php` (remplacée par les hooks Workshop)

### 7.3 `.claude/legacy/operationorder/` — Ancien module operationorder (branche V4_21)
Contient la logique des **ordres de réparation et du planning**.  
**Logique à récupérer :** planning mécaniciens, pointage, jobs, OR card, PDF OR.  
**À ignorer/abandonner :**
- Système de facturation actuel (`operationorder_debit.php`, logique de débit existante)
- Tables SQL préfixées `llx_operationorder_*` (abandonnées, voir script de migration)
- Portail web conducteur (`webportal*` classes) — hors périmètre v1 Workshop
- `config.php` / `config.default.php` (remplacés par le système de constantes Dolibarr standard)

---

## 8. Ce qui est volontairement abandonné — NE PAS RESSUSCITER

Les éléments suivants existaient dans les anciens modules et ne doivent **jamais** être réintroduits dans Workshop :

| Élément abandonné                          | Raison                                             |
|--------------------------------------------|----------------------------------------------------|
| Logique de contrat de maintenance          | Hors périmètre — Workshop est un atelier ouvert    |
| Gestion multi-concessionnaire (dolifleet)  | Remplacée par le système multi-entité Dolibarr     |
| Tables `llx_dolifleet_*`                   | Migrées vers `llx_workshop_*`                      |
| Tables `llx_operationorder_*`              | Migrées vers `llx_workshop_*`                      |
| Système de facturation de operationorder   | Remplacé par intégration native Dolibarr           |
| Portail web conducteur                     | Hors périmètre v1                                  |
| `config.php` custom                        | Remplacé par constantes module standard            |

---

## 9. Script de migration des données

Un script PHP de migration est prévu dans `script/migration/` pour transférer :
- `llx_dolifleet_vehicule` → `llx_workshop_vehicule`
- `llx_dolifleet_vehicule_activity` → `llx_workshop_vehicule_activity`
- `llx_operationorder` → `llx_workshop_operationorder`
- `llx_operationorder_det` → `llx_workshop_operationorder_jobs` + `llx_workshop_operationorderdet`

**Avant toute modification du schéma SQL Workshop**, vérifier l'impact sur ce script de migration.  
Le script doit être idempotent (pouvoir être relancé sans dupliquer les données).

---

## 10. Règles de qualité de code

- Typage PHP 8 encouragé pour les nouvelles méthodes
- Toujours vérifier les droits utilisateur avec `$user->hasRight('workshop', ...)`
- Pas de `var_dump` ou `print_r` en production — utiliser `dol_syslog`
- Les requêtes SQL doivent utiliser `(int)` ou `$this->db->escape()` pour sécuriser les paramètres
- Respecter le format `$this->error` / `$this->errors[]` pour la gestion des erreurs
- Chaque nouvelle classe doit déclarer ses `$fields` complets (pattern ModuleBuilder)
- Poids PHP min requis dans `modWorkshop.class.php` : `$this->phpmin = array(8, 0)`
- Version Dolibarr min requise : `$this->need_dolibarr_version = array(21, 0)`
