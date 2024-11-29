<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Text;

class Requires extends Rule
{
    public function getName ()
    {
        return 'requires';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = Text::split('|', $this->getValue($context));
        $this->identifier = $value->get(0);

        if ($output->has($value->get(0)))
            return true;

        if ($value->length > 1)
        switch ($value->get(1))
        {
            case 'error':
                return false;

            case 'stop':
                throw new StopValidation();

            case 'ignore':
                \Rose\trace('[shield] using `requires` with `ignore` is the default behavior and no longer needed');
        }

        throw new IgnoreField();
    }
};

Shield::registerRule('requires', 'Rose\Ext\Shield\Requires');
