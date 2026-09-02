#!/usr/bin/env python3
"""
Keeps the releases page short without touching a single tag.

Packagist resolves a version from the git tag, not from the GitHub release, and
a lock file names a version someone has to be able to install years from now.
So this removes only the release page: `gh release delete` leaves the tag alone
unless asked for --cleanup-tag, which is exactly what must never happen here.
Nothing is lost either way — semantic-release writes the same notes into
CHANGELOG.md, which is in the repository.

Kept: every major, the last two feature releases, the last three fixes.
"""

import json
import subprocess

KEEP_FEATURE = 2
KEEP_FIX = 3


def gh(*args: str) -> str:
    return subprocess.run(['gh', *args], capture_output=True, text=True, check=True).stdout


def version(tag: str) -> tuple[int, int, int] | None:
    try:
        major, minor, patch = (int(part) for part in tag.lstrip('v').split('.')[:3])
    except ValueError:
        # Anything not shaped like a version is left alone rather than guessed at.
        return None

    return major, minor, patch


def main() -> None:
    listed = json.loads(gh('release', 'list', '--limit', '200', '--json', 'tagName,isLatest'))
    releases = [release for release in listed if version(release['tagName'])]
    releases.sort(key=lambda release: version(release['tagName']), reverse=True)

    majors = [r for r in releases if version(r['tagName'])[1:] == (0, 0)]
    features = [r for r in releases if version(r['tagName'])[2] == 0 and version(r['tagName'])[1] != 0]
    fixes = [r for r in releases if version(r['tagName'])[2] != 0]

    keep = {r['tagName'] for r in majors}
    keep |= {r['tagName'] for r in features[:KEEP_FEATURE]}
    keep |= {r['tagName'] for r in fixes[:KEEP_FIX]}
    # Whatever GitHub is showing as current stays, whichever bucket it fell in.
    keep |= {r['tagName'] for r in releases if r['isLatest']}

    for release in releases:
        tag = release['tagName']

        if tag in keep:
            continue

        print(f'Removing the release page for {tag}; the tag itself stays.')
        gh('release', 'delete', tag, '--yes')


if __name__ == '__main__':
    main()
