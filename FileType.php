<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield;
use Rose\IO\Path;
use Rose\Text;

class FileType extends Rule
{
    public function getName ()
    {
        return 'file-type';
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        $value = $this->getValue($context);
        $this->identifier = $value;

        if (\Rose\typeOf($val) != 'Rose\\Map')
            return false;

        if ($val->error != 0)
            return false;

        $value = Text::split(',', $value)->map(function($i) { return Text::trim($i); });

        if ($value->indexOf(Text::toLowerCase(Text::substring(Path::extname($val->name), 1))) === null)
            return false;

        return true;
    }
};

Shield::registerRule('file-type', 'Rose\Ext\Shield\FileType');
