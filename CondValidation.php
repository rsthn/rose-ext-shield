<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;

class CondValidation extends Error
{
    public $allowedMap;
    public $unallowedMap;

    public function __construct ($allowedMap, $unallowedMap) {
        parent::__construct ('');
        $this->allowedMap = $allowedMap;
        $this->unallowedMap = $unallowedMap;
    }
};
