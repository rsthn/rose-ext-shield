<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;

class Use_ extends Rule
{
    public function getName() {
        return 'use';
    }

    public function getIdentifier() {
        return '';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isString($value))
            throw new Error('reference expected to be string');

        $_errors = new Map();
        $ignored = Shield::validateValue($value, $name, $input, $output, $context, $_errors);
        self::processErrors($_errors, $errors, $name, $name);

        if ($ignored)
            throw new IgnoreField();

        $val = $output->get($name);
        return true;
    }
};

Shield::registerRule('use', 'Rose\Ext\Shield\Use_');
