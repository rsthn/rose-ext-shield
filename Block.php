<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class Block extends Rule
{
    public function getName ()
    {
        return 'block';
    }

    public function getIdentifier() {
        return null;
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $this->getValue($context);
        return true;
    }
};

Shield::registerRule('block', 'Rose\Ext\Shield\Block');
