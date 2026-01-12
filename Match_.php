<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Strings;
use Rose\Regex;

class Match_ extends Rule
{
    public function getName() {
        return 'match';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isString($value))
            throw new Error('reference expected to be string or regex name');

        if ($value[0] !== '/' && $value[0] !== '|') {
            $regex = Strings::getInstance()->regex->$value;
            if (!$regex)
                throw new Error('undefined regex: ' . $value);
            $this->identifier = $value;
        }
        else
            $regex = $value;
    
        $val = Regex::_matchFirst($regex, (string)$val);
        return $val->length() > 0;
    }
};

Shield::registerRule('match', 'Rose\Ext\Shield\Match_');
