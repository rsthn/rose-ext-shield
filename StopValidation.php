<?php

namespace Rose\Ext\Shield;

use Rose\Errors\Error;

class StopValidation extends Error
{
	public function __construct ($message=null)
	{
        parent::__construct ($message);
    }
};
