<?php

namespace Rose\Ext\Shield;

use Rose\Errors\ArgumentError;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Strings;
use Rose\Regex;

class Pattern extends Rule
{
    public function getName ()
    {
        return 'pattern';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!$this->valueIsString() && $value[0] != '/' && $value[0] != '|')
        {
            $this->identifier = $value;

            $regex = Strings::getInstance()->regex->$value;
            if (!$regex) throw new ArgumentError('undefined_regex: '.$value);
        }
        else
            $regex = $value;

        return Regex::_matches ($regex, $val);
    }
};

Shield::registerRule('pattern', 'Rose\Ext\Shield\Pattern');
