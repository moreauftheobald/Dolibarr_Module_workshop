#!/usr/bin/env python3
"""Compare the language files of a Dolibarr module and the keys used by its code.

Usage: python3 lang_diff.py <module_dir> [reference_locale]   (reference_locale defaults to en_US)
"""
import os
import re
import sys

KEY_RE = re.compile(r'^\s*([^#=\s][^=]*?)\s*=')
TRANS_RE = re.compile(r"""(?:->trans(?:noentities(?:noconv)?)?|->transcountry)\(\s*['"]([A-Za-z0-9_\-\.]+)['"]""")
PASCAL_RE = re.compile(r'^[A-Z][A-Za-z0-9]*$')
SKIP_DIRS = {'.git', '.agents', '.claude', 'vendor', 'node_modules', 'includes'}


def read_lang(path):
	keys, dups = {}, []
	with open(path, encoding='utf-8', errors='replace') as f:
		for num, line in enumerate(f, 1):
			m = KEY_RE.match(line)
			if m:
				key = m.group(1)
				if key in keys:
					dups.append((key, keys[key], num))
				keys[key] = num
	return keys, dups


def main():
	moddir = sys.argv[1] if len(sys.argv) > 1 else '.'
	ref = sys.argv[2] if len(sys.argv) > 2 else 'en_US'
	langdir = os.path.join(moddir, 'langs')
	if not os.path.isdir(langdir):
		print('No langs/ directory in %s' % moddir)
		return 1

	locales = {}
	for loc in sorted(os.listdir(langdir)):
		locdir = os.path.join(langdir, loc)
		if os.path.isdir(locdir):
			locales[loc] = {f: read_lang(os.path.join(locdir, f)) for f in sorted(os.listdir(locdir)) if f.endswith('.lang')}

	allkeys = set()
	for files in locales.values():
		for keys, _ in files.values():
			allkeys.update(keys)
	refkeys = set()
	for keys, _ in locales.get(ref, {}).values():
		refkeys.update(keys)

	used = {}
	for root, dirs, files in os.walk(moddir):
		dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
		for f in files:
			if f.endswith('.php'):
				p = os.path.join(root, f)
				with open(p, encoding='utf-8', errors='replace') as fh:
					for num, line in enumerate(fh, 1):
						for key in TRANS_RE.findall(line):
							used.setdefault(key, '%s:%d' % (os.path.relpath(p, moddir), num))

	print('Locales: %s (reference %s)' % (', '.join(locales), ref))
	for loc, files in locales.items():
		for fname, (keys, dups) in files.items():
			print('  %s/%s: %d keys' % (loc, fname, len(keys)))
			for key, first, second in dups:
				print('    DUPLICATE %s (lines %d and %d)' % (key, first, second))

	for loc, files in locales.items():
		if loc == ref:
			continue
		for fname, (keys, _) in files.items():
			missing = sorted(set(keys) - refkeys)
			if missing:
				print('\nKeys of %s/%s missing in %s (%d):' % (loc, fname, ref, len(missing)))
				for key in missing:
					print('  %s' % key)

	corekeys = set()
	up = os.path.abspath(moddir)
	while up != os.path.dirname(up):
		up = os.path.dirname(up)
		coredir = os.path.join(up, 'langs', ref)
		if os.path.isfile(os.path.join(coredir, 'main.lang')):
			for f in os.listdir(coredir):
				if f.endswith('.lang'):
					corekeys.update(read_lang(os.path.join(coredir, f))[0])
			break

	undefined = sorted(k for k in used if k not in allkeys and k not in corekeys)
	if undefined:
		print('\nKeys used in the code but defined neither in the module nor in the core (%d):' % len(undefined))
		for key in undefined:
			print('  %s  (%s)' % (key, used[key]))

	notpascal = sorted(k for k in allkeys if not PASCAL_RE.match(k))
	if notpascal:
		print('\nKeys not in PascalCase (%d):' % len(notpascal))
		print('  ' + ', '.join(notpascal))
	return 0


if __name__ == '__main__':
	sys.exit(main())
