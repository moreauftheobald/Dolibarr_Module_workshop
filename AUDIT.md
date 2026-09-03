# AUDIT.md — Méthodologie de l'audit de conformité/qualité/sécurité

Ce fichier documente la méthode utilisée pour l'audit initial du module (septembre 2026) afin de pouvoir **relancer facilement les mêmes vérifications** au fil du développement, sans repartir de zéro à chaque fois.

Il décrit le *comment* (catégories, méthode de détection, pièges déjà rencontrés) et tient un journal des exécutions. Il ne documente pas les conventions du module elles-mêmes — celles-ci sont dans `CLAUDE.md`.

---

## Comment relancer l'audit

Donner à Claude Code une instruction du type :

> « Relance l'audit du module workshop en suivant AUDIT.md, sur [tout le module / tel dossier / les fichiers modifiés depuis tel commit]. »

Pour un audit ciblé (plus rapide), préciser une seule catégorie, ex. « relance uniquement la catégorie N+1 de AUDIT.md » ou « vérifie les clés de traduction manquantes (catégorie i18n) ».

Chaque catégorie ci-dessous est autonome : elle peut être relancée seule.

---

## Catégories d'audit

### 1. Contrôle d'accès / IDOR / multi-entité
- Chaque page (`*.php` sous `admin/`, `operationorder/`, `vehicule/`, `ajax/`) doit vérifier `$user->hasRight('workshop', <module>, <perm>)` ou `accessforbidden()` avant toute lecture/écriture, cohérent avec les droits déclarés dans `core/modules/modWorkshop.class.php`.
- Toute requête SQL sur une table métier doit filtrer par entité (`entity IN (getEntity(...))` ou équivalent) — sauf tables volontairement partagées (statuts, dictionnaires véhicule : `entity = 0`, cf. §5.4/§8 de CLAUDE.md).
- Tout `fetch($id)` par identifiant ne filtre **jamais** par entité dans Dolibarr (`CommonObject::fetchCommon()` ne teste l'entité que si `$id` est vide) — un objet chargé par id doit être re-vérifié explicitement (`(int) $object->entity !== (int) $conf->entity`) avant d'en exposer le contenu, notamment quand l'id vient d'un paramètre utilisateur.
- CSRF : tout formulaire POST a un `token`, tout lien GET avec `action=` porte `&token='.newToken()`.

### 2. Injection SQL / XSS
- `$_GET`/`$_POST` lus uniquement via `GETPOST()`/`GETPOSTINT()`/`GETPOSTARRAY()` (jamais directement).
- Sortie HTML échappée via `dol_escape_htmltag()` — attention en particulier à `$_SERVER['PHP_SELF']` concaténé dans un attribut, et à tout champ utilisateur affiché dans `getNomUrl()`.
- SQL : `$db->escape()` pour une **valeur** (à l'intérieur de guillemets), `$db->sanitize()` pour un **identifiant** (nom de colonne/table dynamique) — ce sont deux fonctions différentes, une confusion entre les deux est un piège classique.

### 3. Performance — anti-pattern N+1
Rechercher le motif : une requête qui liste des `rowid`, puis une boucle qui fait `new X($db); $x->fetch($rowid);` pour chacun.
- Vérifier d'abord si une méthode `fetchAllByX()`/`fetchAll()` existe déjà dans la classe (souvent déjà écrite mais pas branchée sur le bon appelant).
- Sinon, batcher : une requête `SELECT <liste de champs> FROM ... WHERE id IN (...)`, hydrater via `setVarsFromFetchObj()` plutôt qu'un `fetch()` par ligne.
- Mesurer avant/après avec `SHOW SESSION STATUS LIKE 'Questions'` (cf. §9) plutôt que de se fier uniquement à la relecture de code.
- Attention aux méthodes qui chargent des enfants en cascade (ex. `fetch()` qui rappelle `fetchLines()` qui refait un `fetch()` par ligne enfant) — le problème est parfois à deux niveaux.

### 4. Conventions Dolibarr (styles/API)
- `MAIN_DB_PREFIX` → doit être `$db->prefix()` / `$this->db->prefix()` dans le code métier (pages, classes). **Exception légitime** : les descripteurs de module (`core/modules/modWorkshop.class.php`, blocs `export_sql_end`/`import_tables_array`/`tabsql`) et les classes de numérotation (`core/modules/*/mod_*_standard.php`, `mod_*_advanced.php`) utilisent `MAIN_DB_PREFIX` par convention Dolibarr — vérifié contre le core lui-même (`modUser.class.php`, `mod_facture_terre.php`) avant de les exclure.
- `$conf->global->X` en **lecture** → `getDolGlobalString()`/`Int()`/`Bool()`/`Float()`. Les **écritures** juste après un `dolibarr_set_const()` (pour rafraîchir la config en mémoire pour le reste de la requête) restent légitimes en `$conf->global->X = valeur`, ce n'est pas la même chose.
- `require_once '../lib/...'` (chemin relatif) → `dol_include_once('/workshop/lib/...')`. Ne pas toucher au bloc de bootstrap qui localise `main.inc.php` en tout début de fichier — c'est un mécanisme différent, il doit forcément fonctionner avant que Dolibarr soit chargé.
- Indentation : tabs, pas espaces. **Piège** : le ruleset PHPCS du core Dolibarr (`dev/setup/codesniffer/ruleset.xml`, à la racine du dépôt `dolibarr/`) a `<exclude-pattern>/htdocs/(custom|includes)/</exclude-pattern>` — `pre-commit run php-cbf`/`php-cs` sont donc des no-op silencieux sur ce module. Pour auditer/corriger réellement : faire une copie du ruleset sans cette ligne, puis `phpcbf --standard=<copie> <fichier>`.

### 5. Code mort / reliquats ModuleBuilder
- Blocs commentés `/* BEGIN MODULEBUILDER ... END MODULEBUILDER */` jamais adaptés.
- Boucles/conditions mortes (ex. `foreach` dont la seule itération fait `continue` immédiat).
- Fichiers non référencés ailleurs dans le module (`grep -rln` du nom de classe/fonction) — vérifier avant suppression qu'ils ne sont pas découverts dynamiquement par Dolibarr (ex. les classes de numérotation `mod_*.php` sont listées par scan de dossier dans l'admin, pas par référence explicite dans le code).
- Un fichier de numérotation "advanced" scaffoldé peut être **cassé plus profondément** qu'un simple résidu cosmétique (include vers un chemin inexistant, mauvaise table/constante) — le vérifier en le chargeant réellement (§9), pas seulement à la lecture.

### 6. i18n — chaînes affichées à l'utilisateur
**Règle du projet** (décision explicite, prévaut sur la convention générale « toujours anglais ») : seules les chaînes réellement affichées à l'écran passent par `$langs->trans()`/fichiers de langue. Les commentaires et docblocks PHP restent en français, ne pas les toucher.

Méthode pour un audit exhaustif de couverture des clés :
1. Extraire toutes les clés littérales passées à `->trans(`, `->transnoentities(`, `->transnoentitiesnoconv(`.
2. Extraire aussi les valeurs `'label'`/`'help'`/`'tab_label'`/`'null_label'` des tableaux `$fields` (elles sont traduites dynamiquement ailleurs, ex. `$langs->trans($fieldConfig['label'])`) — **et** les valeurs d'`'arrayofkeyval'` imbriqué (piège : un audit précédent les a ratées car pas au premier niveau de la déclaration de champ).
3. Vérifier chaque clé contre un vrai objet `Translate` chargé avec **tous** les fichiers de langue que le module charge quelque part (`workshop`, `admin`, `main`, `other`, `products`, `companies`, `mails`, `errors`, `bills`, `dict`, `stocks`, `languages`, `multicompany`, etc. — regarder tous les `$langs->load()`/`loadLangs()` du module pour la liste à jour), **en lisant `tab_translate` directement** (`!empty($l->tab_translate[$key])`), jamais en comparant la sortie de `trans()` à la clé — une traduction identique à sa clé (ex. `Date=Date`) donnerait un faux « manquant ».
4. Avant d'ajouter une clé « manquante », vérifier par `grep` qu'elle est bien utilisée dans du code atteignable (pas dans une ligne commentée, pas dans un script de migration one-shot).

### 7. Résidus de nommage (constantes legacy / placeholders / client)
Rechercher les préfixes hérités des anciens modules (`OPERATIONORDER_`, `OPODER_`, `OORDER_`, `OPORDER_`, `DOLIFLEET_`) et les placeholders ModuleBuilder jamais renommés (`MYOBJECT`, `MyObject`, `MY_SETUP_PARAM`).

**Piège majeur** : `.claude/legacy/` contient les anciens modules `dolifleet`/`operationorder` conservés en lecture seule pour référence (cf. §7.2/7.3 de CLAUDE.md) — ils regorgent de ces préfixes *à dessein*. Toujours exclure ce dossier (`grep -v '\.claude/legacy'`), et vérifier que l'exclusion s'applique bien : `grep -rho ... | grep -v '\.claude/legacy'` ne filtre **rien** car `-h` a déjà supprimé les noms de fichiers avant le filtre — il faut filtrer sur une sortie qui contient encore le chemin (`grep -rn` sans `-h`, ou filtrer les fichiers avant l'extraction).

Avant de renommer une constante repérée : vérifier si une constante « propre » existe déjà en parallèle (souvent exposée dans une page admin) mais jamais lue par le code — dans ce cas, réutiliser ce nom plutôt qu'en inventer un troisième corrige le résidu *et* un réglage admin mort en un seul renommage (rencontré sur `THEO_NB_MONTH_CHECKING_VEHICULE_BY_ANTICIPATION` → `WORKSHOP_OPERATION_SEARCH_DELAY_MONTHS`).

### 8. Séparation métier / vue
Une méthode de classe métier (`class/*.php`) qui exécute une requête ne doit pas appeler `setEventMessages()` directement (ça mélange logique et affichage, et ça empêche de réutiliser la méthode dans un contexte sans UI, ex. AJAX/CLI). Convention : en cas d'échec, poser `$this->error`/`$this->errors[]` et retourner une valeur négative ; c'est à l'appelant (page/vue) d'appeler `setEventMessages()` selon le contexte.

### 9. Fonctions PHP natives remplaçables par les fonctions core Dolibarr
Dolibarr fournit des équivalents à préférer aux fonctions PHP natives — plus robustes (charset, fuseau horaire de l'utilisateur plutôt que celui du serveur) et cohérents avec le reste de l'application :
- `date()`, `strtotime()`, `mktime()` → `dol_print_date()`, `dol_stringtotime()`, `dol_mktime()` (calculs de date/heure sensibles au fuseau de l'utilisateur, pas seulement au fuseau serveur).
- `number_format()` → `price($montant, 0, '', 1, $rounding, $forcerounding)` (formatage numérique respectant la locale de l'utilisateur au lieu d'un format codé en dur ; `$currency_code=''` par défaut n'ajoute pas de symbole monétaire, utilisable pour n'importe quelle valeur numérique, pas seulement des prix).
- `basename()` → `dol_basename()` (gère correctement les jeux de caractères où `basename()` natif échoue, ex. cyrillique) ; si le nom de fichier obtenu est ensuite stocké/réutilisé, le passer aussi dans `dol_sanitizeFileName()`. `dol_basename()` ne supporte pas la suppression de suffixe (2ème argument de `basename()` natif) — combiner avec un `preg_replace()` si besoin.
- `unserialize()` → `json_decode(..., true)` **uniquement si les données stockées ne sont pas déjà au format PHP `serialize()`** — sinon il faudrait migrer les données existantes, ne pas faire ce changement à la légère (cf. `tpl/vehicule_links.tpl.php`, laissé tel quel pour cette raison).

**Piège** : ces remplacements touchent souvent des fichiers de fonctionnalités actives (planning, cartes objet) — vérifier en premier qu'aucun développement n'est en cours dessus (catégorie 10) avant de s'y lancer.

### 10. Vérification en conditions réelles (conteneur Docker)
Le conteneur `theobald22_8_dolibarr_web` monte ce dépôt en direct (`/var/www/html/custom/workshop` = ce dossier). Pour vérifier un correctif sur de vraies données plutôt que sur la seule lecture de code :

```bash
docker exec theobald22_8_dolibarr_web sh -c 'php <<"EOPHP"
<?php
require_once "/var/www/html/master.inc.php";
global $db, $conf, $langs, $user;
dol_include_once("/workshop/class/....class.php");
// ... exercer le code, comparer avant/après, afficher les résultats
EOPHP'
```

- Compter les requêtes d'un bloc de code : `SHOW SESSION STATUS LIKE 'Questions'` avant/après (cf. catégorie 3).
- Pour comparer deux implémentations (ex. avant/après une optimisation N+1) : faire tourner les deux sur les mêmes données réelles et diffuser un `json_encode()` des structures obtenues plutôt qu'un `var_dump` à l'œil.
- Un fichier lint-propre (`php -l`) peut quand même planter à l'exécution (méthode manquante sur une classe parente, colonne SQL inexistante, etc.) — le charger réellement est la seule vérification fiable pour du code jamais exercé (numérotation « advanced », par ex.).

### 11. Fusion Git avec le développement en cours
Un collègue peut pousser en parallèle sur la même branche (`V1_CLAUDE`) du même dépôt (remote `github`, actif — `origin`/Framagit est un miroir à part, à ne pas confondre). Avant de pousser :
1. `git fetch github` puis comparer `HEAD..github/V1_CLAUDE`.
2. S'il y a du nouveau, faire un essai à blanc (`git merge-tree $(git merge-base HEAD github/V1_CLAUDE) HEAD github/V1_CLAUDE`) pour repérer un conflit textuel.
3. **Un merge-tree sans conflit ne prouve que l'absence de conflit textuel, pas la correction sémantique.** Si le collègue et l'audit ont touché la même méthode, relire à la main le corps de la méthode fusionnée, et rejouer la suite de vérification de la catégorie concernée (§9) avant de committer/pousser. Un cas réel rencontré : deux `else` de blocs `if` différents fusionnés proprement au niveau texte, mais rattachés au mauvais `if` après fusion — aucun conflit signalé, comportement cassé silencieusement.
4. Ne pousser que sur confirmation explicite de l'utilisateur.

---

## Journal des exécutions

| Date | Portée | Résumé | Commits (V1_CLAUDE) |
|---|---|---|---|
| 2026-09-01/02 | Audit initial complet (3 agents parallèles, 85 fichiers, ~31k lignes) | Inventaire CRITIQUE/ÉLEVÉE/MOYENNE/FAIBLE | `dd71f3d`, `6627085` (partiel) |
| 2026-09-03 | 🔴 CRITIQUE (12/12) | Upload arbitraire, IDOR permissions, statuts codés en dur, XSS, bugs bloquants | `e5dbe42`, `1750d46` |
| 2026-09-03 | 🟠 ÉLEVÉE (16/16) | Accès/IDOR, robustesse, XSS restant, N+1 SQL (Operationorder + statuts) | `f9d343a`, `070b249`, `cdb6a53`, `b044f55`, `63ad8e2`, `13cad1a` |
| 2026-09-03 | 🟡 MOYENNE | `MAIN_DB_PREFIX`, `$conf->global->`, includes, indentation, code mort, i18n affiché-écran + audit exhaustif des clés | `4d62cd4`, `4ed86f8`, `3a0199b`, `60dbc17`, `0fa98a7`, `ee39322`, `f2c5403` |
| 2026-09-03 | ⚪ FAIBLE | PHPDoc, accolades, typage, TODO, casse des droits (+ bug i18n réel : 12/20 libellés sans traduction anglaise), échappement | `c654e27` |
| 2026-09-03 | Étape 7 | Constantes résiduelles, escape/sanitize, setEventMessages hors métier, JS inline | `70a1baf`, `1d85094` |
| 2026-09-03 | Catégorie 9 (fonctions natives), partiel | `number_format()`→`price()`, `basename()`→`dol_basename()`/`dol_sanitizeFileName()`. Reste à faire : `date()`/`strtotime()`/`mktime()` dans `workshop_planning.php`/`ajax/planning_ajax.php`/`lib/workshop_planning.lib.php` — différé, Florian travaille sur ces fichiers | `fb406e9` |

*Ajouter une ligne à chaque nouvelle exécution (date, portée, résumé, commits).*
