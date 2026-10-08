---
name: skill-doli-i18n-backfill
description:
  Completes the en_US language files of a Dolibarr external module from its other locales (mostly fr_FR), and moves hardcoded user-facing strings into language keys.
  Use when the user asks to backfill, complete or fix translations, en_US files, language keys, or hardcoded strings of a module.
license: MIT
user-invocable: true
allowed-tools:
 - read_file
 - write_file
 - grep
---

# Skill: Backfill the en_US language files of a module

## Relationship with AGENTS.md

This file is complementary to `AGENTS.md`; if they conflict, `AGENTS.md` wins.
Exception for this skill: in these modules, `fr_FR` is often the only complete locale (it was written first). The keys missing from `en_US` must be added to `en_US` with an English translation. Never delete or rewrite existing `fr_FR` translations.


## Critical Rules (DO NOT VIOLATE)

- Never commit or push unless the user explicitly asks for it.
- Never remove a language key that is still used by the code.
- Work on one module at a time, and one language file at a time when the module has several.


## Steps

1. List the gaps with the helper script of this skill (run from the module root):
	`python3 .agents/skills/skill-doli-i18n-backfill/lang_diff.py .`
	It reports, per language file: keys only in another locale, keys used in the code but defined nowhere, duplicated keys, and keys not in PascalCase.
2. Add the missing keys to `langs/en_US/<file>.lang`:
	- translate the value of the other locale into short, plain English, consistent with the Dolibarr core wording (search `htdocs/langs/en_US/` for an existing equivalent first; if the core already has the key, use the core key in the code instead of a module duplicate);
	- keep the placeholders (`%s`, `%d`, `%1$s`) in the same order and number;
	- keep the order and the `#` section comments of the source file.
3. Keys used in the code but defined nowhere: add them to `en_US` (and to `fr_FR` only if the user agrees).
4. Hardcoded user-facing strings (`setEventMessages('...')`, `print '...'`, labels in arrays of fields, column titles, mail subjects/bodies):
	- create a PascalCase key prefixed by the module name when it is not generic (e.g. `TheobaldVehicleReceived`);
	- replace the literal by `$langs->trans('Key')` (or `transnoentities()` when the result is escaped later or used outside HTML);
	- add the key to `en_US`, and to `fr_FR` with the former French literal so that French users see no change.
5. Keys not in PascalCase: rename only when the user asks, because a rename touches the code, every locale, and sometimes the database (labels of dictionaries, extrafields, menus or rights stored by key).
6. Check that each modified `.lang` file has no duplicated key (the last one wins silently in Dolibarr) and no trailing spaces, then run `php -l` on the modified PHP files.


## Output

Report the number of keys added per file, the literals moved to keys (file:line), the keys left aside and why.


## Gotchas

- Keys of the module descriptor (`Module<id>Name`, `Module<id>Desc`, `Permission<id><n>`) are built from the module number.
- Labels of extrafields, dictionaries and menus are stored in the database: they are translated only if their label is a language key.
- Some modules load several language files (`$langs->loadLangs(array('mymodule@mymodule', 'other@mymodule'))`): search the key in all of them before adding it.
- A key defined in the core (`htdocs/langs/en_US/*.lang`) and in the module: the file loaded first wins. Do not redefine core keys.
