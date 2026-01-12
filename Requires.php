<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Requires extends Rule
{
    public function getName() {
        return 'requires';
    }

    public function validate($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isString($value))
            throw new Error('reference expected to be string');

        $value = Text::split('|', $value);
        $this->identifier = $value->get(0);

        if ($output->has($value->get(0)))
            return true;

        if ($value->has(1)) {
            switch ($value->get(1)) {
                case 'error':
                    return false;
                case 'stop':
                    throw new StopValidation();
                default:
                    $this->identifier = null;
                    throw new Error('invalid action for `requires` rule: ' . $value->get(1));
            }
        }

        throw new IgnoreField();
    }
};

Shield::registerRule('requires', 'Rose\Ext\Shield\Requires');
