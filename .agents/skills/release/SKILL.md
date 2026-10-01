---
name: release
description: Prepare or publish Modularity Noticeboard releases, including version alignment, an English changelog, dev-to-main promotion, Git tags and GitHub release notes. Use when asked to release this plugin, prepare a release, bump its version or tag a version.
---

# Modularity Noticeboard releases

Use this workflow in the `considbrs-webdev/modularity-noticeboard` repository.
The GitHub repository is `Considbrs-Webdev/modularity-noticeboard`.
Development happens on `dev`; released code lives on `main`.

Write all release material in English: changelog entries, commit messages, tag
annotations, release titles, release notes and release reports. Preserve product
names, API identifiers and existing Swedish translation files.

## Scope and release baseline

A request to prepare a release means preparing version changes, the changelog and
validation. Do not publish a tag or GitHub release for a preparation-only request.
A request to create or publish a full release authorizes the required commit,
branch promotion, pushes, tag and GitHub release; do not ask again for those steps.
Creating or editing this skill does not authorize cutting a release.

Fetch remote branches and tags, inspect `git status`, `git log`, existing releases
and `CHANGELOG.md` if present. Determine the unreleased range from the latest
release tag reachable from `dev`, rather than from unrelated tags or releases.
Preserve unrelated local changes and never include them in the release commit.
Do not overwrite divergent remote work or rewrite published history.

Use a requested version. Otherwise select a semantic version from the actual
changes: fixes are patch releases, compatible features are minor releases and
breaking public contracts require a major release. Report the chosen version
and its reason. Ask only if the intended baseline or compatibility is unclear.

For a first release, inspect repository history and current functionality;
do not invent earlier releases. A plugin header may already be bumped ahead of
publication. It is not proof that that version was released.

## Version and changelog

Keep these declarations aligned to `X.Y.Z`:

- `modularity-noticeboard.php`: the WordPress `Version` header.
- `package.json`: the top-level `version`.
- `package-lock.json`: the top-level `version` and `packages[""].version`.

`composer.json` currently has no explicit version; Composer derives it from Git
tags. Do not introduce a manual version solely for this workflow. If an explicit
version is added later, keep it aligned too. Do not change dependency versions as
part of a release bump. Inspect any newly added version declarations.

`npm version X.Y.Z --no-git-tag-version --allow-same-version --ignore-scripts`
can update the npm declarations together. Check that its diff is limited to the
intended metadata. Update the WordPress header separately.

Create `CHANGELOG.md` on the first release if absent. Use Keep a Changelog style:
`## [X.Y.Z] - YYYY-MM-DD`, followed by the applicable `Added`, `Changed`, `Fixed`,
`Deprecated`, `Removed` or `Security` sections. Use the user's timezone for the
release date. Describe observable behavior and migration requirements, rather
than listing internal classes or copying commit messages. Preserve prior entries.
Include installation or rollout requirements that affect this release; consult
`docs/integrations.md` and verify endpoint ownership and configuration on staging
before claiming an integration rollout is complete.

## Validation and packaging

Read `tests/README.md` for the current verification commands and prerequisites.
This repository has real WordPress integration, concurrency and archival tests;
it does not currently provide a `composer test` script. Run the relevant suites
against a disposable WordPress installation with an `nbtest_` table prefix.
Verify integration behavior both with and without ACF, concurrency and archival
behavior where applicable. Never run fixture creation against a client site.
If release-critical checks cannot be run, report the concrete missing dependency
and leave publication pending until resolved or the user explicitly accepts it.

Check PHP syntax for changed PHP files, run `composer validate --no-check-publish`
and `npm ci` / `npm run build`, and confirm all version declarations match.
Follow the staging checks in `tests/README.md` when required for the release.

`/assets/` and `/vendor/` are ignored. A GitHub source archive or Composer checkout
does not automatically contain compiled assets or installed dependencies. Do not
claim that source archives are ready-to-install WordPress ZIPs. If an installable
ZIP is requested or established release practice requires one, build and package
it in an isolated staging directory with a `modularity-noticeboard/` root. Include
production Composer dependencies, compiled assets, PHP/views, translations and
`source/css/admin-integrations.css`; inspect the archive before attaching it.
Do not introduce a new asset-tracking or CI policy as part of a routine release.

`build.php --cleanup` removes source files and may remove `.git`. Run cleanup
only in the isolated package staging directory, never in the working checkout.
Exclude development skills from a packaged ZIP. Retain `CHANGELOG.md` in it.

## Commit, promote and publish

Behavior changes belong in their own commits. The release commit should contain
only version metadata and the changelog, with the message `Release X.Y.Z`.
For a full release, push `dev`, fast-forward `main` to the verified release commit
and push `main`. If fast-forward promotion fails, inspect the divergence and
resolve within the user's requested scope; never force-push to make it pass.

Match existing tag conventions. If there are no release tags, use an annotated
`X.Y.Z` tag with annotation `Release X.Y.Z`; use `vX.Y.Z` as the GitHub release
title. Tag the verified `main` commit explicitly and push that specific tag.
Never move or replace an existing release tag. Verify remote refs after pushing.

Write the new changelog entry verbatim to a temporary notes file and publish with:

```bash
gh release create X.Y.Z --repo Considbrs-Webdev/modularity-noticeboard \
  --verify-tag --title "vX.Y.Z" --notes-file /path/to/release-notes.md
```

Adjust the tag argument to the repository's verified convention. Attach a
validated ZIP when one was requested. Use a prerelease only when requested or
when the chosen version is explicitly a prerelease.

Before retrying an ambiguous push or release creation, inspect remote tags and
`gh release view` to recover the outcome rather than creating duplicates.
Verify the published tag's commit, release notes and any attached assets. Return
the version, commit, tag, release URL and validation results in English. Leave the
local checkout on `dev` unless the user requests otherwise.
