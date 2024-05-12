<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;

class IgnoreField extends Error
{
	public function __construct ($message=null)
	{
        parent::__construct ($message);
    }
};
