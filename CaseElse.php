<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\CondValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;


class CaseElse extends Rule
{
    public function getName() {
        return 'case-else';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors) {
        throw new CondValidation(true, new Map([
            'case-when' => true,
            'case-else' => true,
        ]));
    }

    public function failed ($input, $output, $context, $errors) {
        throw new CondValidation(new Map([
            'case-end' => true
        ]), true);
    }
};

Shield::registerRule('case-else', 'Rose\Ext\Shield\CaseElse');
