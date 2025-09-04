#!/bin/bash
###################################################
# Usage: configure-php-versions.sh
###################################################
# This script is used to get the PHP versions file from the serversideup/docker-php repository.

set -e

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    --branch)
      SOURCE_PHP_VERSIONS_BRANCH="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      exit 1
      ;;
  esac
done

# Download settings
SCRIPTS_DIR="$(dirname "$0")"
DESTINATION_PHP_VERSIONS_FILE="$SCRIPTS_DIR/conf/php-versions.yml"
SOURCE_PHP_VERSIONS_BRANCH="${SOURCE_PHP_VERSIONS_BRANCH:-"main"}"
SOURCE_PHP_VERSIONS_REPOSITORY="https://github.com/serversideup/docker-php.git"

###################################################
# Prepare for execution
###################################################

TMP_DIR=$(mktemp -d)

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT


###################################################
# Functions
###################################################

check_dependencies() {
  for dependency in "$@"; do
    if ! command -v "$dependency" &> /dev/null; then
      echo "$dependency could not be found"
      exit 1
    fi
  done
}

clone_docker_php_repository() {
  git clone --branch "$SOURCE_PHP_VERSIONS_BRANCH" "$SOURCE_PHP_VERSIONS_REPOSITORY" "$TMP_DIR"
}

download_php_versions_file() {
  bash "$TMP_DIR/scripts/get-php-versions.sh" --input "$TMP_DIR/scripts/conf/php-versions-base-config.yml" --output "$DESTINATION_PHP_VERSIONS_FILE"
}

enforce_minimum_php_version_from_composer_json() {
  # Enforce the minimum PHP version based on composer.json's require.php
  local composer_file
  composer_file="$(dirname "$SCRIPTS_DIR")/composer.json"

  if [ ! -f "$composer_file" ]; then
    echo "composer.json not found at $composer_file; skipping minimum PHP enforcement"
    return 0
  fi

  # Extract the PHP version constraint (e.g., ^8.2, >=8.2, 8.2.*)
  local php_constraint
  php_constraint=$(jq -r '.require.php // empty' "$composer_file" 2>/dev/null)

  if [ -z "$php_constraint" ] || [ "$php_constraint" = "null" ]; then
    echo "No PHP requirement found in composer.json"
    return 1
  fi

  # Parse the first major.minor occurrence as the minimum required minor (best-effort)
  local min_minor
  min_minor=$(echo "$php_constraint" | grep -oE '[0-9]+\.[0-9]+' | head -n1)

  if [ -z "$min_minor" ]; then
    echo "Unable to parse a major.minor PHP version from constraint '$php_constraint'"
    return 1
  fi

  local req_major req_minor
  req_major="${min_minor%%.*}"
  req_minor="${min_minor#*.}"

  if [ ! -f "$DESTINATION_PHP_VERSIONS_FILE" ]; then
    echo "PHP versions file not found at $DESTINATION_PHP_VERSIONS_FILE; skipping minimum PHP enforcement"
    return 0
  fi

  # 1) Drop any majors less than the required major
  env REQ_MAJOR="$req_major" \
    yq -i '.php_versions |= map(select(.major | tonumber >= (env(REQ_MAJOR) | tonumber)))' "$DESTINATION_PHP_VERSIONS_FILE"

  # 2) Within the required major, drop any minor_versions with minor < required minor
  env REQ_MAJOR="$req_major" REQ_MINOR="$req_minor" \
    yq -i '(.php_versions[] | select(.major == env(REQ_MAJOR))).minor_versions |= map(select((.minor | split(".")) as $p | ($p[1] | tonumber) >= (env(REQ_MINOR) | tonumber)))' "$DESTINATION_PHP_VERSIONS_FILE"

  # 3) Drop any majors that end up with zero minor_versions after filtering
  yq -i '.php_versions |= map(select((.minor_versions | length) > 0))' "$DESTINATION_PHP_VERSIONS_FILE"
}

remove_operating_systems() {
  set -f
  for operating_system in "$@"; do
    pattern="$operating_system"
    regex=$(printf '%s' "$pattern" | sed -e 's|[.[\\^$|(){}+]|\\&|g' -e 's|[*]|.*|g' -e 's|[?]|.|g')
    regex="^${regex}$"
    env OS_REGEX="$regex" yq -i 'del(.php_versions[].minor_versions[].base_os[] | select(.name | test(strenv(OS_REGEX))))' "$DESTINATION_PHP_VERSIONS_FILE"
  done
  set +f
}

remove_variations() {
  for variation in "$@"; do
    yq -i 'del(.php_variations[] | select(.name == "'"$variation"'"))' "$DESTINATION_PHP_VERSIONS_FILE"
  done
}

reuse_other_serversideup_php_scripts() {
  for script in "$@"; do
    mv "$TMP_DIR/scripts/$script" "$SCRIPTS_DIR/$script"
  done
}

set_default_php_variation() {
  local default_php_variation="$1"
  # Remove default from all variations
  yq -i '.php_variations[] |= del(.default)' "$DESTINATION_PHP_VERSIONS_FILE"
  # Set default on the desired variation
  env DEFAULT_PHP_VARIATION="$default_php_variation" \
    yq -i '(.php_variations[] | select(.name == strenv(DEFAULT_PHP_VARIATION))).default = true' "$DESTINATION_PHP_VERSIONS_FILE"
}

###################################################
# Main
###################################################

check_dependencies curl yq jq git
clone_docker_php_repository
download_php_versions_file
set_default_php_variation "fpm-nginx"

# Don't test CLI or FPM variations
remove_variations cli fpm

# Remove Alpine operating systems for Geekbench compatibility reasons
remove_operating_systems alpine*
enforce_minimum_php_version_from_composer_json
reuse_other_serversideup_php_scripts assemble-docker-tags.sh generate-matrix.sh