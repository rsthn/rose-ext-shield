<?php
/*
**	Rose\Ext\Shield
**
**	Copyright (c) 2019-2020, RedStar Technologies, All rights reserved.
**	https://rsthn.com/
**
**	THIS LIBRARY IS PROVIDED BY REDSTAR TECHNOLOGIES "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
**	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A 
**	PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL REDSTAR TECHNOLOGIES BE LIABLE FOR ANY
**	DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT 
**	NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
**	OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, 
**	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
**	USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace Rose\Ext;

use Rose\Errors\Error;
use Rose\Errors\ArgumentError;

use Rose\Configuration;
use Rose\Session;
use Rose\Strings;
use Rose\Resources;
use Rose\Gateway;
use Rose\Extensions;
use Rose\Text;
use Rose\Expr;
use Rose\Map;

use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Wind;

if (!Extensions::isInstalled('Wind'))
	return;

/*
**	Shield Wind Extension.
*/

class Shield
{
	/*
	**	Registered field definitions.
	*/
	private static $fields;

	/*
	**	Registered validation rules.
	*/
	private static $rules;

	/*
	**	Initializes the registration maps.
	*/
	public static function init()
	{
		self::$fields = new Map();
		self::$rules = new Map();
	}

	/*
	**	Registers a validation rule handler.
	*/
	public static function registerRule (string $ruleName, string $className)
	{
		self::$rules->set($ruleName, $className);
	}

	/*
	**	Verifies that the given rule set is valid and returns a validation descriptor.
	*/
	public static function getDescriptor (string $name, \Rose\Map $rules, \Rose\Map $data)
	{
		$output = [ ];

		$rules->forEach (function($value, $key) use (&$output, &$name)
		{
			if ($key == 'name')
			{
				$name = Expr::value($value, $data);
				return;
			}

			$className = self::$rules->get($key);
			if (!$className)
			{
				throw new Error ('Undefined validation rule: ' . $key);
				return;
			}

			$output[] = new $className ($value);
		});

		// A validation descriptor is an array having the target field name and an array with the validation rules.
		return [$name, $output];
	}

	/*
	**	Registers a field validation descriptor.
	*/
	public static function registerDescriptor (string $name, array $desc)
	{
		self::$fields->set($name, $desc);
	}

	/*
	**	Validates a field from the given inputData.
	*/
	public static function validateField (string $fieldName, \Rose\Map $input, \Rose\Map $output, \Rose\Map $context, \Rose\Map $errors)
	{
		$desc = self::$fields->get($fieldName);
		if (!$desc) throw new ArgumentError ('(shield::validate) Undefined field validation descriptor: '.$fieldName);

		$name = $desc[0];
		$value = $input->get($name);

		foreach ($desc[1] as $rule)
		{
			try {
				if ($rule->validate($name, $value, $input, $output, $context))
					continue;
			}
			catch (StopValidation $e) {
				return;
			}

			$errors->set($name, Strings::get('@errors/'.$rule->getIdentifier()));
			return;
		}

		$output->set($name, $value);
	}
};

/**
**	Registers a field validation descriptor.
**
**	shield::field <name> <...rules>
*/
Expr::register('_shield::field', function($parts, $data)
{
	$name = Expr::value($parts->get(1), $data);
	$rules = Expr::value($parts->get(2), $data);

	if (!is_string($name))
		throw new ArgumentError ('shield::field expects \'name\' parameter to be a string.');

	$rules = new Map();

	for ($i = 2; $i < $parts->length(); $i += 2)
	{
		$key = Expr::value($parts->get($i), $data);
		if (substr($key, -1) == ':')
			$key = substr($key, 0, strlen($key)-1);

		$tmp = $parts->get($i+1);
		if ($tmp->length == 1 && $tmp->get(0)->type != 'template')
			$tmp = Expr::value($tmp, $data);
			
		$rules->set($key, $tmp);
	}

	Shield::registerDescriptor ($name, Shield::getDescriptor($name, $rules, $data));
	return $name;
});

/**
**	Runs a validation sequence, if any error occurs replies Wind::R_VALIDATION_ERROR. The validated fields will be
**	available in the global context if validation succeeded.
**
**	shield::validate <...field>
*/
Expr::register('shield::validate', function($args, $parts, $data)
{
	$inputData = Gateway::getInstance()->requestParams;
	$errors = new Map();

	for ($i = 1; $i < $args->length; $i++)
	{
		Shield::validateField ($args->get($i), $inputData, $data, $data, $errors);
	}

	if ($errors->length != 0)
		Wind::reply([ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);

	return null;
});

/* ****************************************************************************** */
Shield::init();

class_exists('Rose\Ext\Shield\Required');
class_exists('Rose\Ext\Shield\Presence');
class_exists('Rose\Ext\Shield\MinLength');
class_exists('Rose\Ext\Shield\MaxLength');
class_exists('Rose\Ext\Shield\Length');
class_exists('Rose\Ext\Shield\Pattern');
