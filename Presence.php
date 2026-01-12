<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Errors\Error;
use Rose\Ext\Shield;

class Presence extends Rule
{
    public function getName() {
        return 'presence';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if ($value === true) $value = 'true';
        if ($value === false) $value = 'false';

        $this->identifier = $value;
        switch ($value)
        {
            case 'true|null':
                if (!$input->has($name)) {
                    $val = null;
                    throw new StopValidation();
                }

                break;

            case 'true|empty':
                if (!$input->has($name)) {
                    $val = '';
                    throw new StopValidation();
                }

                break;

            case 'true':
                if (!$input->has($name))
                    return false;
                break;

            case 'false':
                if (!$input->has($name))
                    throw new IgnoreField();
                break;

            default:
                $this->identifier = null;
                throw new Error('invalid action for `presence` rule: ' . $value);
        }

        return true;
    }
};

Shield::registerRule('presence', 'Rose\Ext\Shield\Presence');
