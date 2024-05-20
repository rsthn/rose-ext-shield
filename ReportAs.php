<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;

class ReportAs extends Rule
{
    public function getName() {
        return 'report-as';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors) {
        $this->reportedName = $this->getValue($context);
        return true;
    }
};

Shield::registerRule('report-as', 'Rose\Ext\Shield\ReportAs');
