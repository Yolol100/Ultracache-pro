import re
defaults = open('includes/options/class-ucp-options-default-groups.php').read() + "\n" + open('includes/options/traits/ucp-options-defaults-trait.php').read()
san = open('includes/admin/class-ucp-admin-sanitizer.php').read()
# default keys: 'key' => at start of array entries
keys = set(re.findall(r"'([a-z0-9_]+)'\s*=>", defaults))
# Filter out obviously nested/value keys by requiring they appear as top-level-ish; keep all for now
missing = sorted(k for k in keys if k not in san)
print(f"Total default keys: {len(keys)}")
print(f"Keys NOT referenced anywhere in sanitizer ({len(missing)}):")
for k in missing:
    print("  ", k)
