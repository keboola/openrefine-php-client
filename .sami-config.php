<?php declare(strict_types = 1);

use Sami\Sami;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($dir = __DIR__ . '/src');
$versions = GitVersionCollection::create($dir)
    ->addFromTags('*\.*')
    ->add('master', 'master branch')
;
return new Sami($iterator, [
    'title' => 'php-docker-application',
    'versions'             => $versions,
    'build_dir' => __DIR__ . '/docs/%version%',
    'cache_dir' => __DIR__ . '/cache/%version%',
    'default_opened_level' => 2,
]);
