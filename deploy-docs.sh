#!/bin/bash

# generate docs
apigen generate -d /docs/ -s ./src/

# deploy
cd /docs
git init
git add .
git commit -m "Deploy to Github Pages"
git push --force --quiet "https://$GITHUB_TOKEN@$github.com/keboola/openrefine-php-client.git" master:gh-pages > /dev/null 2>&1
