<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Expected extends Rule
{
    public function getName ()
    {
        return 'expected';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $this->identifier = $value;

        switch ($value)
        {
            case 'boolean': case 'bool':
                return \Rose\isBool($val);

            case 'integer': case 'int':
                return \Rose\isInteger($val);

            case 'number':
                return \Rose\isNumber($val) || \Rose\isInteger($val);

            case 'string': case 'str':
                return \Rose\isString($val);

            case 'array':
                return \Rose\typeOf($val) === 'Rose\Arry';

            case 'object':
                return \Rose\typeOf($val) === 'Rose\Map';

            case 'null':
                return $val === null;

            default:
                throw new Error('undefined_type: ' . $value);
        }

        return true;
    }
};

Shield::registerRule('expected', 'Rose\Ext\Shield\Expected');
