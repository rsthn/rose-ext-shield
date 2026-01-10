<?php

namespace Rose\Ext\Shield;

use Rose\Expr;
use Rose\Arry;
use Rose\Map;
use Rose\Text;

abstract class Rule
{
    protected $baseIdentifier;
    protected $identifier = null;
    protected $value;
    protected $reportedName = null;
    static $counter = 0;

    public function __construct ($value, $baseIdentifier=null) {
        $this->value = $value;
        $this->baseIdentifier = $baseIdentifier;
    }

    protected function valueIsString () {
        return \Rose\typeOf($this->value) == 'Rose\\Arry' && $this->value->length == 1 && $this->value->get(0)->type == 'string';
    }

    protected function getValue ($context) {
        return Expr::value ($this->value, $context);
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

    public function getTmpId() {
        return '_' . self::$counter++ . '_';
    }

    public abstract function validate ($name, &$value, $input, $output, $context, $errors);

    public function failed ($input, $output, $context, $errors)
    { }
};
