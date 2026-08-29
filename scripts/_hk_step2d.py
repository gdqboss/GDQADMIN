import os, glob, re, sys

hk_dir = sys.argv[1]
fixed = 0
for path in glob.glob(hk_dir + '/assets/*.js'):
    with open(path) as f: src = f.read()
    new = src.replace('["assets/', '["/gdqadmin/assets/').replace(',"assets/', ',"/gdqadmin/assets/')
    if new != src:
        with open(path, 'w') as f: f.write(new)
        fixed += 1

_entry = sorted(glob.glob(hk_dir + '/assets/index-*.js'))
if _entry:
    with open(_entry[0]) as f: content = f.read()
    remaining = len(re.findall(r'["\[,]assets/[A-Za-z0-9_-]+\.[a-z]+', content))
    fixed_count = len(re.findall(r'["\[,]/gdqadmin/assets/[A-Za-z0-9_-]+\.[a-z]+', content))
    print('  files_changed=' + str(fixed) + ' unprefixed_remaining=' + str(remaining) + ' /gdqadmin/assets/_count=' + str(fixed_count))
else:
    print('  files_changed=' + str(fixed) + ' no entry js found')
