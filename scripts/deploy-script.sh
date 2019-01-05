#!/bin/bash
docker-compose run --rm \
      -e GITHUB_GHPAGES_COMMIT_EMAIL=$GITHUB_GHPAGES_COMMIT_EMAIL \
      -e GITHUB_GHPAGES_COMMIT_NAME=$GITHUB_GHPAGES_COMMIT_NAME \
      -e GITHUB_TOKEN=$GITHUB_TOKEN \
      -e GITHUB_REPO=$GITHUB_REPO \
      tests-coverage-report composer install && ./scripts/deploy-docs.sh
