<?php

declare(strict_types=1);

use App\Runtime\EnvFiles;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

EnvFiles::load(dirname(__DIR__), 'test');

(new Filesystem())->remove(dirname(__DIR__).'/var/share/test/pools');
