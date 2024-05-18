<?php

namespace Rose\Ext\Shield;

use Rose\Expr;
use Rose\Arry;
use Rose\Map;
use Rose\Text;

/*
**	Shield validation rule.
*/

abstract class Rule
{
    protected $baseIdentifier;
    protected $identifier;
    protected $value;

    public function __construct ($value, $baseIdentifier=null) {
        $this->value = $value;
        $this->baseIdentifier = $baseIdentifier;
        $this->identifier = null;
    }

    protected function valueIsString () {
        return \Rose\typeOf($this->value) == 'Rose\\Arry' && $this->value->length == 1 && $this->value->get(0)->type == 'string';
    }

    protected function getValue ($context) {
        return Expr::value ($this->value, $context);
    }

    public abstract function getName();

    public function __toString() {
        return $this->getName();
    }

    public function getIdentifier() {
        $val = $this->baseIdentifier ? $this->baseIdentifier : $this->identifier;
        return Text::length($val) ? (\Rose\isString($val) && $val[0] == '@' ? Text::substring($val, 1) : $this->getName() . ':' . $val) : $this->getName();
    }

    public abstract function validate ($name, &$value, $input, $output, $context, $errors);

    public function failed ($input, $output, $context, $errors)
    { }
};
