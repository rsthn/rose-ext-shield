<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;

class Enum extends Rule
{
    public function getName() {
        return 'enum';
    }

    public function validate($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $type = \Rose\typeOf($value);

        if ($type === 'Rose\Arry') {
            if (!$value->length())
                throw new Error('reference values list is empty');
            return $value->indexOf($val) !== null;
        }

        if ($type === 'Rose\Map') {
            if (!$value->length())
                throw new Error('reference values map is empty');
            if (!$value->has($val))
                return false;
            $val = $value->get($val);
            return true;
        }

        if (!\Rose\isString($value))
            throw new Error('reference expected to be array, object or string');

        $value = Text::trim($value);
        $list = Text::split(',', $value)->map(function ($v) { return Text::trim($v); });
        if (!$list->length() || $value === '')
            throw new Error('reference values list is empty');
        return $list->indexOf($val) !== null;
    }
};

Shield::registerRule('enum', 'Rose\Ext\Shield\Enum');
