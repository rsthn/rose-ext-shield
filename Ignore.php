<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Ignore extends Rule
{
    public function getName() {
        return 'ignore';
    }

    public function validate($name, &$val, $input, $output, $context, $errors) {
        $value = $this->getValue($context);
        if (!\Rose\isBool($value))
            throw new Error('reference value expected to be bool');

        if ($value)
            throw new IgnoreField();
        return true;
    }
};

Shield::registerRule('ignore', 'Rose\Ext\Shield\Ignore');
