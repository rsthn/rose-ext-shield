<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\Text;
use Rose\Map;

class Type extends Rule
{
    public function getName() {
        return 'type';
    }

    public function getIdentifier() {
        return '';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $_errors = new Map();

        $tmpId = $this->getTmpId();
        $tmp = new Map();
        $out = new Map();
        if ($input->has($name))
            $tmp->set($tmpId, $val);

        $val = Shield::validateValue($value, $tmpId, $tmpId, $tmp, $out, $context, $_errors);
        if ($_errors->length) {
            $_errors->forEach(function($value, $key) use($name, $errors, $tmpId) {
                if (Text::startsWith($key, $tmpId))
                    $errors->set($name.Text::substring($key, Text::length($tmpId)), $value);
            });
            throw new Error('');
        }

        if (!$out->has($tmpId))
            throw new IgnoreField();

        $val = $out->get($tmpId);
        return true;
    }
};

Shield::registerRule('type', 'Rose\Ext\Shield\Type');
Shield::registerRule('use', 'Rose\Ext\Shield\Type');
