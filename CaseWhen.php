<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\CondValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;


class CaseWhen extends Rule
{
    public function getName() {
        return 'case-when';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if (!$value) throw new CondValidation(new Map([
            'case-when' => true,
            'case-else' => true,
            'case-end' => true
        ]), true);

        throw new CondValidation(true, new Map([
            'case-when' => true,
            'case-else' => true,
            'case-end' => true,
        ]));
    }

    public function failed ($input, $output, $context, $errors) {
        throw new CondValidation(new Map([
            'case-end' => true
        ]), true);
    }
};

Shield::registerRule('case-when', 'Rose\Ext\Shield\CaseWhen');
