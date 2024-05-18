<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class UniqueItems extends Rule
{
    public function getName ()
    {
        return 'unique-items';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $type = \Rose\typeOf($val);
        if ($type !== 'Rose\Arry')
            return false;

        $val = $val->unique();
        return true;
    }
};

Shield::registerRule('unique-items', 'Rose\Ext\Shield\UniqueItems');
