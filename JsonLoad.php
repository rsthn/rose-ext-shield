<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Gateway;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\IO\File;
use Rose\Errors\Error;
use Rose\Expr;
use Rose\Arry;
use Rose\JSON;

class JsonLoad extends Rule
{
    public function getName ()
    {
        return 'json-load';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        if ($value === 'POST' || $value === 'body') {
            if (Gateway::getInstance()->input->contentType !== 'application/json')
                return false;
            $val = Gateway::getInstance()->body;
        }
        else
            $val = JSON::parse($value);

        if (!$val) return false;
        return true;
    }
};

Shield::registerRule('json-load', 'Rose\Ext\Shield\JsonLoad');
