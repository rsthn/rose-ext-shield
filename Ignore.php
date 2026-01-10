<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class Ignore extends Rule
{
    public function getName() {
        return 'ignore';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        if (\Rose\bool($this->getValue($context)))
            throw new IgnoreField();

        return true;
    }
};

Shield::registerRule('ignore', 'Rose\Ext\Shield\Ignore');
