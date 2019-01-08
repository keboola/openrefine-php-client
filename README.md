# OpenRefine PHP Client

[![Build Status](https://travis-ci.org/keboola/openrefine-php-client.svg?branch=master)](https://travis-ci.org/keboola/openrefine-php-client)
[![Code Climate](https://codeclimate.com/github/keboola/openrefine-php-client/badges/gpa.svg)](https://codeclimate.com/github/keboola/openrefine-php-client)
[![Test Coverage](https://codeclimate.com/github/keboola/openrefine-php-client/badges/coverage.svg)](https://codeclimate.com/github/keboola/openrefine-php-client/coverage)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/keboola/openrefine-php-client/blob/master/LICENSE.md)

A PHP Wrapper for [OpenRefine API](https://github.com/OpenRefine/OpenRefine/wiki/OpenRefine-API). 

The reason I started developing a new PHP Client is that none of the OpenRefine batch processing libraries use either latest OpenRefine or reliably work. 

Build and tested with OpenRefine 2.6 RC2, distributed via [Packagist](https://packagist.org/packages/keboola/openrefine-php-client).

## API

API documentation is deployed [here](https://keboola.github.io/openrefine-php-client/master/).

## Development

Requirements:

- Docker Engine `~1.10.0`
- Docker Compose `~1.6.0`

Application is prepared for run in container, you can start development same way:

1. Clone this repository: `git clone https://github.com/keboola/openrefine-php-client`
2. Change directory: `cd openrefine-php-client`
3. Build services: `docker-compose build`
4. Run tests `docker-compose run --rm dev composer ci`

## License

MIT. See [license file](LICENSE.md).
