<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Check extends Rule
{
    public function getName() {
        return 'check';
    }

    public function validate($name, &$val, $input, $output, $context, $errors) {
        $value = $this->getValue($context);
        if (!\Rose\isBool($value))
            throw new Error('reference value expected to be bool');
        return $value;
    }
};

Shield::registerRule('check', 'Rose\Ext\Shield\Check');
