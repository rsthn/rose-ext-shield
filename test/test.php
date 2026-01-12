<?php

chdir(__DIR__);
require_once ('../vendor/autoload.php');

Rose\Main::cli (dirname(__FILE__), true);
try {
    require_once('../Shield.php');
    Rose\Ext\Wind::run('main.fn');
}
catch (\Rose\Ext\Wind\WindError $e) {
    echo("\x1b[91mError:\x1b[0m " . \Rose\Ext\Wind::prepare($e->getData()))."\n";
    exit(1);
}
catch (\Exception $e) {
    if ($e->getMessage())
        echo("\x1b[91mError:\x1b[0m " . $e->getMessage())."\n";
    else
        echo("\n");
    exit(1);
}
