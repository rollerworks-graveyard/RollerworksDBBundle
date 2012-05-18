<?php

// We should replace this with the autoloader of Composer and supply configuration for Travis
require_once __DIR__ . '/../../../../../../app/bootstrap.php.cache';

$loader->registerNamespaces(array(
    'Rollerworks'      => __DIR__.'/../../../..',
));
