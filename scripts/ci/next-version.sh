#!/usr/bin/env bash
# next-version.sh <channel> — compute the next SemVer for a channel from
# conventional commits. Pure git+bash, no external deps.
#
#   channel        : development | production  (any [a-z0-9-] token — add more
#                    channels freely, e.g. staging, qa)
#   prints         : v<MAJOR>.<MINOR>.<PATCH>-<channel>   on stdout
#   bump rules     : breaking (feat!|fix!|`!:`|BREAKING CHANGE) -> major
#                    feat                                        -> minor
#                    fix|perf                                    -> patch
#                    nothing conventional                        -> patch
#   first release  : v0.1.0-<channel>  (no prior tag for the channel)
#
# Each channel is an INDEPENDENT stream: production ignores development tags
# and vice versa. Tolerates legacy leading-dot tags (v.0.0.119-development).
#
# Source: copied verbatim from
# ~/WORK/wit-indonesia/ci-template/scripts/ci/next-version.sh
set -euo pipefail

channel="${1:-}"
if [[ -z "$channel" ]]; then
  echo "usage: next-version.sh <channel>" >&2
  exit 2
fi

# Highest existing X.Y.Z for this channel. `git tag` glob + version sort.
# Strip the leading v (and an optional legacy dot) and the -<channel> suffix.
latest_ver="$(git tag -l "v*-${channel}" "v.*-${channel}" \
  | sed -E "s/^v\\.?//; s/-${channel}\$//" \
  | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$' \
  | sort -t. -k1,1n -k2,2n -k3,3n \
  | tail -n1 || true)"

if [[ -z "$latest_ver" ]]; then
  # No prior release on this channel -> seed first release.
  echo "v0.1.0-${channel}"
  exit 0
fi

IFS=. read -r major minor patch <<<"$latest_ver"

# Resolve the actual tag ref (new clean form preferred, legacy dot fallback).
tag_ref="v${latest_ver}-${channel}"
git rev-parse "$tag_ref" >/dev/null 2>&1 || tag_ref="v.${latest_ver}-${channel}"

subjects="$(git log --format='%s%n%b' "${tag_ref}..HEAD" 2>/dev/null || true)"

bump="patch"
if grep -qE '^[a-z]+(\([^)]*\))?!:|BREAKING[ -]CHANGE' <<<"$subjects"; then
  bump="major"
elif grep -qE '^feat(\([^)]*\))?:' <<<"$subjects"; then
  bump="minor"
elif grep -qE '^(fix|perf)(\([^)]*\))?:' <<<"$subjects"; then
  bump="patch"
fi

case "$bump" in
  major) major=$((major+1)); minor=0; patch=0 ;;
  minor) minor=$((minor+1)); patch=0 ;;
  patch) patch=$((patch+1)) ;;
esac

echo "v${major}.${minor}.${patch}-${channel}"
