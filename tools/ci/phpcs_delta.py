#!/usr/bin/env python3
"""PHPCS security check on changed plugin files: fail only on NEW findings.

Usage: tools/ci/phpcs_delta.py <base-ref> <head-ref>

Runs the security and DB sniffs in tools/ci/phpcs-security.xml on every PHP file under plugins/ that changed between
the merge-base of <base-ref> and <head-ref>, and <head-ref>. Each file is checked twice, as it was at the base and as
it is at the head (both read from git, not the working tree). The check fails only when, in one file, the number of
findings for one sniff code went up. Findings that were already there do not fail it, and inline
"// phpcs:ignore Sniff.Code -- reason" comments work as usual. New findings are printed as GitHub annotations.

Used by .github/workflows/deploy-check.yml. A base of all zeros (a new branch push) or one git does not know is
skipped with a notice. The phpcs binary comes from $PHPCS, else vendor/bin/phpcs in the repo, else phpcs on PATH;
it needs WordPress Coding Standards 3 installed (composer: wp-coding-standards/wpcs ^3).
"""
import collections
import json
import os
import shutil
import subprocess
import sys
import tempfile

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
RULESET = os.path.join(REPO, 'tools', 'ci', 'phpcs-security.xml')


def git(*args, check=True):
    r = subprocess.run(['git', '-C', REPO, *args], capture_output=True, text=True)
    if check and r.returncode != 0:
        sys.exit('git %s failed: %s' % (' '.join(args), r.stderr.strip()))
    return r


def phpcs_bin():
    if os.environ.get('PHPCS'):
        return os.environ['PHPCS']
    local = os.path.join(REPO, 'vendor', 'bin', 'phpcs')
    if os.path.exists(local):
        return local
    found = shutil.which('phpcs')
    if not found:
        sys.exit('phpcs not found: set PHPCS, or install wp-coding-standards/wpcs ^3 with composer.')
    return found


def esc(s, prop=False):
    s = s.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')
    if prop:
        s = s.replace(':', '%3A').replace(',', '%2C')
    return s


def run_phpcs(paths):
    """Return {path: [message, ...]} for the given files (one phpcs run)."""
    if not paths:
        return {}
    r = subprocess.run([phpcs_bin(), '--standard=' + RULESET, '--report=json', '-q', '--no-cache', *paths],
                       capture_output=True, text=True)
    if r.returncode not in (0, 1, 2):
        sys.exit('phpcs failed (exit %d): %s%s' % (r.returncode, r.stdout[-2000:], r.stderr[-2000:]))
    try:
        data = json.loads(r.stdout)
    except ValueError:
        sys.exit('phpcs did not return JSON: %s%s' % (r.stdout[-2000:], r.stderr[-2000:]))
    return {os.path.realpath(p): v.get('messages', []) for p, v in data.get('files', {}).items()}


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    base_ref, head_ref = sys.argv[1], sys.argv[2]
    if not base_ref or set(base_ref) == {'0'}:
        print('::notice title=PHPCS security::No base commit (new branch or manual run); nothing to compare, skipped.')
        return 0
    for ref in (base_ref, head_ref):
        if git('rev-parse', '--verify', '--quiet', ref + '^{commit}', check=False).returncode != 0:
            print('::warning title=PHPCS security::%s is not a known commit; skipped.' % esc(ref))
            return 0
    head = git('rev-parse', head_ref + '^{commit}').stdout.strip()
    mb = git('merge-base', base_ref, head, check=False)
    if mb.returncode != 0:
        print('::warning title=PHPCS security::No merge-base between %s and %s; skipped.' % (esc(base_ref), esc(head_ref)))
        return 0
    base = mb.stdout.strip()
    print('PHPCS security: base %s, head %s' % (base[:12], head[:12]))

    # A = added, M = modified, R = renamed (compare against the old path). Deleted files are not checked.
    diff = git('diff', '--name-status', '-M', '--diff-filter=AMR', base, head, '--', 'plugins/').stdout
    pairs = []  # (head path, base path or None)
    for line in diff.splitlines():
        parts = line.split('\t')
        status = parts[0]
        new_path = parts[-1]
        if not new_path.endswith('.php'):
            continue
        old_path = parts[1] if status.startswith('R') else (None if status == 'A' else new_path)
        if old_path is not None and not old_path.endswith('.php'):
            old_path = None
        pairs.append((new_path, old_path))
    if not pairs:
        print('No changed PHP files under plugins/.')
        return 0

    tmp = tempfile.mkdtemp(prefix='aw-phpcs-delta-')
    try:
        head_files, base_files = {}, {}
        for i, (new_path, old_path) in enumerate(pairs):
            for side, rev, path, store in (('head', head, new_path, head_files), ('base', base, old_path, base_files)):
                if path is None:
                    continue
                dest = os.path.join(tmp, side, str(i), os.path.basename(path))
                os.makedirs(os.path.dirname(dest), exist_ok=True)
                blob = subprocess.run(['git', '-C', REPO, 'show', '%s:%s' % (rev, path)], capture_output=True)
                if blob.returncode != 0:
                    continue
                with open(dest, 'wb') as fh:
                    fh.write(blob.stdout)
                store[i] = os.path.realpath(dest)
        head_res = run_phpcs(list(head_files.values()))
        base_res = run_phpcs(list(base_files.values()))
    finally:
        shutil.rmtree(tmp, ignore_errors=True)

    failed = 0
    for i, (new_path, old_path) in enumerate(pairs):
        h_msgs = head_res.get(head_files.get(i), [])
        b_msgs = base_res.get(base_files.get(i), [])
        h_by_src = collections.Counter(m['source'] for m in h_msgs)
        b_by_src = collections.Counter(m['source'] for m in b_msgs)
        grown = {s for s in h_by_src if h_by_src[s] > b_by_src.get(s, 0)}
        if not grown:
            print('ok   %s (%d existing finding(s), none new)' % (new_path, len(h_msgs)))
            continue
        # Which lines to point at: per (sniff, message text), the ones whose count went up. Line numbers move, so
        # the exact new occurrence cannot be told apart from older identical ones; all of them are listed.
        b_by_key = collections.Counter((m['source'], m['message']) for m in b_msgs)
        h_by_key = collections.Counter((m['source'], m['message']) for m in h_msgs)
        for src in sorted(grown):
            added = h_by_src[src] - b_by_src.get(src, 0)
            failed += added
            print('NEW  %s: %s went from %d to %d' % (new_path, src, b_by_src.get(src, 0), h_by_src[src]))
            mine = [m for m in h_msgs if m['source'] == src]
            picked = [m for m in mine if h_by_key[(src, m['message'])] > b_by_key.get((src, m['message']), 0)]
            for m in picked or mine:
                print('::error file=%s,line=%d,col=%d,title=%s::%s' % (
                    esc(new_path, True), m['line'], m['column'], esc('PHPCS ' + src, True), esc(m['message'])))

    if failed:
        print('\nPHPCS security: %d new finding(s). Fix them, or if one is safe add '
              '"// phpcs:ignore <Sniff.Code> -- reason" on that line.' % failed)
        return 1
    print('\nPHPCS security: no new findings in %d changed file(s).' % len(pairs))
    return 0


if __name__ == '__main__':
    sys.exit(main())
