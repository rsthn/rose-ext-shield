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

class Matches extends Rule
{
    public function getName() {
        return 'matches';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!\Rose\isString($value))
            throw new Error('reference expected to be regex string');

        return Regex::_matches($value, (string)$val);
    }
};

Shield::registerRule('matches', 'Rose\Ext\Shield\Matches');
