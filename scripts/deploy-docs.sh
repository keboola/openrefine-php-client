#!/bin/bash

# generate docs
$(pwd)/vendor/bin/apigen generate -d $(pwd)/docs/ -s $(pwd)/src/

# deploy
cd $(pwd)/docs
git init
git add .
git config --global user.email "$GITHUB_GHPAGES_COMMIT_EMAIL"
git config --global user.name "$GITHUB_GHPAGES_COMMIT_NAME"
git commit -m "Deploy to Github Pages"
git push --force --quiet "https://$GITHUB_TOKEN@github.com/$GITHUB_REPO.git" master:gh-pages
