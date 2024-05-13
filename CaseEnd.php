<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\CondValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;

class CaseEnd extends Rule
{
	public function getName() {
		return 'case-end';
	}

	public function validate ($name, &$val, $input, $output, $context, $errors) {
        return true;
	}

    public function failed ($input, $output, $context, $errors) {
        die('xxxxxxxx');
    }
};

Shield::registerRule('case-end', 'Rose\Ext\Shield\CaseEnd');
