<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

return [
    'driver' => 'sqlite',

    'mysql' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'dbname'  => 'sistema_empleados',
        'user'    => 'emp_app',
        'pass'    => 'ana1',
        'charset' => 'utf8mb4',
    ],

    'sqlite' => [
        'path' => obtenerRutaSQLite(),
    ],

    'sembrar_datos_demo' => false,

    'registros_por_pagina' => 10,
];
