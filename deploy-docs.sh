#!/bin/bash
# generate docs
apigen generate -d ./docs/ -s ./src/

# deploy
cd ./docs
git init
git add .
git config --global user.email "$GITHUB_GHPAGES_COMMIT_EMAIL"
git config --global user.name "$GITHUB_GHPAGES_COMMIT_NAME"
git commit -m "Deploy to Github Pages"
git push --force --quiet "https://$GITHUB_TOKEN@github.com/$GITNUB_REPO.git" master:gh-pages
