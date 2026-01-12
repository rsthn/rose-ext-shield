<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Expect extends Rule
{
    public function getName() {
        return 'expect';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $this->identifier = $value;

        switch ($value)
        {
            case 'boolean': case 'bool':
                $this->identifier = 'bool';
                return \Rose\isBool($val);

            case 'integer': case 'int':
                $this->identifier = 'int';
                return \Rose\isInteger($val);

            case 'float':
                return \Rose\isNumber($val) || \Rose\isInteger($val);

            case 'string': case 'str':
                $this->identifier = 'str';
                return \Rose\isString($val);

            case 'array':
                return \Rose\typeOf($val) === 'Rose\Arry';

            case 'object': case 'obj':
                $this->identifier = 'obj';
                return \Rose\typeOf($val) === 'Rose\Map';

            case 'null':
                return $val === null;

            default:
                $this->identifier = null;
                throw new Error('invalid type: ' . $value);
        }

        return true;
    }
};

Shield::registerRule('expect', 'Rose\Ext\Shield\Expect');
