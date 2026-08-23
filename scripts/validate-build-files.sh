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

PLUGIN_PATH="${PLUGIN_SLUG}"

if [[ -n "$GITHUB_WORKSPACE" ]]; then
	PLUGIN_PATH="${GITHUB_WORKSPACE}/${PLUGIN_SLUG}"
fi

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

DENY_LIST="tests vendor bin .github .git node_modules"

for item in $DENY_LIST; do
	if [[ -e "$item" ]]; then
		echo "ERROR: '$item' must not be in the build directory"
		exit 1
	fi
done

DENY_FILES="phpunit.xml composer.json composer.lock package.json package-lock.json"

for item in $DENY_FILES; do
	if [[ -f "$item" ]]; then
		echo "ERROR: '$item' must not be in the build directory"
		exit 1
	fi
done

echo "Build validation passed for ${PLUGIN_SLUG} v${PLUGIN_VERSION}"
echo "Contents:"
ls -la
