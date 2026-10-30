<?php

declare(strict_types=1);

/*
 * ISIS - ponto de entrada único. Somente a pasta public/ deve ficar exposta pelo servidor web.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

Isis\Core\App::run();
