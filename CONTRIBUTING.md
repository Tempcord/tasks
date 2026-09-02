# Contributing

## Commit messages decide releases

Merges are squashed, and the pull request title becomes the commit subject on
`main`. That subject is read by semantic-release, which cuts the tag and the
GitHub release — so the title is not a formality, it is the version bump.

Titles follow [Conventional Commits](https://www.conventionalcommits.org):

```
feat(cron): support step values over a range
fix(runner): keep a task that throws in the schedule
docs: explain why both day fields restricted means either
```

| Prefix | Release |
| --- | --- |
| `fix`, `perf` | patch — `1.2.3` → `1.2.4` |
| `feat` | minor — `1.2.3` → `1.3.0` |
| `feat!`, or `BREAKING CHANGE:` in the body | major — `1.2.3` → `2.0.0` |
| `docs`, `test`, `refactor`, `build`, `ci`, `chore` | none |

A pull request whose title does not parse is rejected by a check before it can
be merged, because a subject semantic-release cannot read is a release that
silently never happens.

Put the reasoning in the pull request body. It becomes the commit body, and it
is the part someone reads in a year when they are trying to work out why.

## Breaking changes

Mark them, and say what to do instead:

```
feat(task)!: name a method task after its class as well

BREAKING CHANGE: an unnamed method task is now "Housekeeping::sweep"
rather than "sweep". Pass name: to keep the old one.
```

## Before opening a pull request

```bash
composer test     # PHPUnit
composer analyse  # PHPStan
```

## Testing a scheduler

The suite drives a fake loop rather than waiting out the schedules it exercises
— an interval of a second is the smallest the attribute allows, and a cron
task's next turn can be an hour away. `FakeLoop` records what was armed and
fires it on demand, so a test can say exactly when a turn happens and assert on
the wait that was chosen.

Nothing here should need `sleep`. If a test seems to, the thing it is testing
probably wants a seam rather than the test wanting patience.
