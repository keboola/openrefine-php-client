#!/bin/bash

# deploy
cd ./docs
git init
git add .
git config --global user.email "$GITHUB_GHPAGES_COMMIT_EMAIL"
git config --global user.name "$GITHUB_GHPAGES_COMMIT_NAME"
git commit -m "Deploy to Github Pages"
git push --force --quiet "https://$GITHUB_TOKEN@$github.com/keboola/openrefine-php-client.git" master:gh-pages > /dev/null 2>&1
