<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Required extends Rule
{
    public function getName() {
        return 'required';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if ($value === true) $value = 'true';
        if ($value === false) $value = 'false';

        if (\Rose\isString($val)) {
            $val = Text::trim($val);
            $is_empty = Text::length($val) == 0;
        }
        else
            $is_empty = $val === null;

        $this->identifier = $value;
        switch ($value)
        {
            case 'true|null':
                if ($is_empty) {
                    $val = null;
                    throw new StopValidation();
                }
                break;

            case 'true|empty':
                if ($is_empty) {
                    $val = '';
                    throw new StopValidation();
                }
                break;

            case 'true':
                if ($is_empty)
                    return false;
                break;

            case 'false':
                if ($is_empty)
                    throw new IgnoreField();
                break;

            default:
                $this->identifier = null;
                throw new Error('invalid action for `required` rule: ' . $value);
        }

        return true;
    }
};

Shield::registerRule('required', 'Rose\Ext\Shield\Required');
