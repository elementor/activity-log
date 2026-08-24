#!/bin/bash
set -eo pipefail

if [[ -z "$PLUGIN_SLUG" ]]; then
	echo "Set the PLUGIN_SLUG env var"
	exit 1
fi

if [[ -z "$PLUGIN_VERSION" ]]; then
	echo "Set the PLUGIN_VERSION env var"
	exit 1
fi

REPO_ROOT="${GITHUB_WORKSPACE:-.}"
PLUGIN_PATH="${REPO_ROOT}/${PLUGIN_SLUG}"
EXCLUDE_FILE="${REPO_ROOT}/.build-rsync-exclude"

if [[ ! -d "$PLUGIN_PATH" ]]; then
	echo "BUILD_DIR '$PLUGIN_PATH' does not exist"
	exit 1
fi

cd "$PLUGIN_PATH"

PLUGIN_MAIN_FILE="${PLUGIN_SLUG}.php"

if [[ ! -f "$PLUGIN_MAIN_FILE" ]]; then
	echo "${PLUGIN_MAIN_FILE} does not exist in build dir"
	exit 1
fi

if [[ ! -f "readme.txt" ]]; then
	echo "readme.txt does not exist in build dir"
	exit 1
fi

if [[ $(grep -c "Version: $PLUGIN_VERSION" "$PLUGIN_MAIN_FILE") -eq 0 ]]; then
	echo "${PLUGIN_MAIN_FILE} does not contain Version: $PLUGIN_VERSION"
	EXISTING_VERSION=$(sed -n 's/.*Version: \(.*\)/\1/p' "$PLUGIN_MAIN_FILE")
	echo "Found: $EXISTING_VERSION"
	exit 1
fi

if [[ $(grep -c "Stable tag: $PLUGIN_VERSION" "readme.txt") -eq 0 ]]; then
	echo "readme.txt does not contain Stable tag: $PLUGIN_VERSION"
	EXISTING_VERSION=$(sed -n 's/.*Stable tag: \(.*\)/\1/p' "readme.txt")
	echo "Found: $EXISTING_VERSION"
	exit 1
fi

if [[ ! -f "$EXCLUDE_FILE" ]]; then
	echo "WARNING: $EXCLUDE_FILE not found, skipping exclude-list check"
else
	ERRORS=0
	while IFS= read -r entry; do
		# Skip empty lines, comments, and glob-only patterns (e.g. *.zip)
		[[ -z "$entry" ]] && continue
		[[ "$entry" == \#* ]] && continue

		# Strip trailing slash for directory entries
		clean="${entry%/}"

		# Skip self-references and glob patterns
		[[ "$clean" == "$PLUGIN_SLUG" ]] && continue
		[[ "$clean" == *"*"* ]] && continue

		if [[ -e "$clean" ]]; then
			echo "ERROR: '$clean' from .build-rsync-exclude found in build directory"
			ERRORS=1
		fi
	done < "$EXCLUDE_FILE"

	if [[ $ERRORS -ne 0 ]]; then
		exit 1
	fi
fi

echo "Build validation passed for ${PLUGIN_SLUG} v${PLUGIN_VERSION}"
echo "Contents:"
ls -la
