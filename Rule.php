<?php

namespace Rose\Ext\Shield;

use Rose\Expr;
use Rose\Arry;
use Rose\Map;
use Rose\Text;

abstract class Rule
{
    static $counter = 0;

    protected $baseIdentifier;
    protected $identifier = null;
    protected $value;
    protected $reportedName = null;

    public function __construct ($value, $baseIdentifier=null) {
        $this->value = $value;
        $this->baseIdentifier = $baseIdentifier;
    }

    protected function valueIsString () {
        return \Rose\typeOf($this->value) === 'Rose\\Arry' && $this->value->length == 1 && $this->value->get(0)->type === 'string';
    }

    protected function getValue ($context) {
        return Expr::value($this->value, $context);
    }

    public abstract function getName();

    public function getReportedName() {
        return $this->reportedName;
    }

    public function __toString() {
        return $this->getName();
    }

    public function getIdentifier() {
        $val = Text::toString($this->baseIdentifier ?? $this->identifier);
        return $val !== '' ? ($val[0] === '@' ? Text::substring($val, 1) : $this->getName() . ':' . $val) : $this->getName();
    }

    protected function getTmpId() {
        return '_' . self::$counter++ . '_';
    }

    protected function processErrors($errors, $output, $prefix, $key)
    {
        if (!$errors->length)
            return;

        if ($prefix !== $key) {
            $key_len = Text::length((string)$key);
            $errors->forEach(function($value, $key) use($output, $prefix, $key_len) {
                $output->set($prefix.Text::substring($key, $key_len), $value);
            });
        }
        else
            $output->merge($errors, true);
        throw new SkipError();
    }

    public abstract function validate ($name, &$value, $input, $output, $context, $errors);

    public function failed ($input, $output, $context, $errors)
    { }
};
