<?php

namespace Rose\Ext\Shield;

use Rose\Errors\ArgumentError;
use Rose\Ext\Shield\Rule;
use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Shield;
use Rose\Errors\Error;
use Rose\Text;
use Rose\Expr;
use Rose\Strings;
use Rose\Regex;
use Rose\Arry;
use Rose\Map;

class Data extends Rule
{
	public function getName ()
	{
		return 'data';
	}

	protected static function isTypeNode ($node)
	{
		if ($node->length() !== 1 || $node->get(0)->type !== 'identifier')
			return false;

		switch ($node->get(0)->data) {
			case 'object':
			case 'array':
			case 'vector':
			case 'type':
			case 'value':
			case 'rules':

			case 'boolean':
			case 'integer':
			case 'number':
			case 'string':
			case 'null':
				return true;
		}

		return false;
	}

	protected static function flatten ($node, $ctx)
	{
		$type = \Rose\typeOf ($node, true);

		if ($type === 'Rose\\Arry')
		{
			$node = $node->map(function($item) use ($ctx) {
				return self::flatten($item, $ctx);
			});

			return $node->length() == 1 ? $node->get(0) : $node;
		}

		if ($type === 'Rose\\Map')
		{
			switch ($node->type)
			{
				case 'template':
					$mode = 0;

					for ($i = 0; $i < $node->data->length() && $mode != -1; $i++)
					{
						$item = $node->data->get($i);

						switch ($mode)
						{
							case 0:
								if (self::isTypeNode($item))
								{
									$node->data->set($i, $item->get(0)->data);
									switch ($item->get(0)->data) {
										case 'object': $mode = 1; break;
										case 'array': $mode = 3; break;
										case 'vector': $mode = 4; break;
										case 'type': $mode = 5; break;
										case 'value': $mode = 6; break;
										case 'rules': $mode = 7; break;
										case 'boolean': $mode = 7; break;
										case 'integer': $mode = 7; break;
										case 'number': $mode = 7; break;
										case 'string': $mode = 7; break;
										case 'null': $mode = 7; break;
									}
								}
								else
									throw new Error('Expected a type name: object|array|vector|type|value|rules|boolean|integer|number|string|null');

								break;

							case 1: // Key
								$tmp = Expr::value($item, $ctx);
								$node->data->set($i, $tmp);

								if ($tmp === '...')
								{
									if ((1+$i) != $node->data->length())
										throw new Error('Specifier `...` must be the last element in the object');

									$mode = 0;
									break;
								}

								$mode = 2;
								break;

							case 2: // Value (after key)
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));
								$mode = 1;
								break;

							case 3: // Array Type (array <type>)
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));

								$mode = -1;
								break;

							case 4: // Vector Values (vector <types...>)
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));
								break;

							case 5: // Type name (type <typeName>)
								$node->data->set($i, Expr::value($item, $ctx));
								$mode = -1;
								break;

							case 6: // Specific value (value <value>)
								$node->data->set($i, Expr::value($item, $ctx));
								$mode = -1;
								break;

							case 7: // Shield validation rules.
								$node->data->set(1, Shield::getDescriptor('', Shield::parseDescriptor($node->data, $ctx, 1), $ctx));
								$mode = -1;
								break;
						}
					}

					if ($mode == 2)
						throw new Error('Expected a type name after `'.$node->data->last().'` in object descriptor');

					$node = $node->data;
					break;

				default:
					throw new Error('Unexpected token in data descriptor');
			}

		}

		return $node;
	}

	public function getIdentifier()
	{
		return '';
	}

	private function checkType ($node, &$value, $path, $opt, $ctx, &$root, $input, &$key)
	{
		// Just check pattern if $node is a string.
		if (\Rose\typeOf($node, true) === 'string')
		{
			if (\Rose\isString($value))
				$value = Text::trim($value);

			if ($node === '*' || $node === 'any' || $node === '...')
				return;

			$tmp = (string)$value;
			$name = Shield::getMessage('pattern') . ':' . $node;

			if (Text::length($tmp) == 0)
			{
				if ($opt) throw new IgnoreField();
				throw new ArgumentError(Shield::getMessage('required:true') . ': ' . $path);
			}

			if ($node[0] != '/' && $node[0] != '|') {
				$regex = Strings::getInstance()->regex->$node;
				if (!$regex) throw new ArgumentError(Shield::getMessage('undefined_regex') . ': ' . $node);
			}
			else {
				$regex = $node;
				$name = 'pattern';
			}

			if (!Regex::_matches ($regex, $value))
				throw new ArgumentError($name . ': ' . $path);

			return;
		}

		$err = new Arry();
		$validate = false;

		switch ($node->first())
		{
			case 'object':
				if (\Rose\typeOf($value, true) !== 'Rose\\Map')
					throw new Error(Shield::getMessage('expected_object') . ': ' . $path);

				$out = new Map();
				$keys = $value->keys();

				for ($i = 1; $i < $node->length(); $i += 2)
				{
					try
					{
						$key = $node->get($i);
						if ($key === '...')
						{
							$keys->forEach( function($key) use (&$out, &$value) {
								$out->set($key, $value->get($key));
							});
							break;
						}

						$opt = Text::endsWith($key, '?');
						if ($opt) $key = Text::substring($key, 0, -1);

						$exists = $keys->indexOf($key);
						if ($exists !== null) $keys->remove($exists);

						$val = $value->get($key);
						$this->checkType($node->get($i+1), $val, $path . '.' . $key, $opt, $ctx, $root, $value, $key);
						$out->set($key, $val);
					}
					catch (StopValidation $e) {
						$out->set($key, $val);
					}
					catch (IgnoreField $e) {
					}
					catch (\Exception $e) {
						$err->push($e->getMessage());
					}
				}

				$value = $out;
				break;

			case 'array':
				if (\Rose\typeOf($value, true) !== 'Rose\\Arry')
					throw new Error(Shield::getMessage('expected_array') . ': ' . $path);

				$out = new Arry();
				$rule = $node->get(1);

				for ($i = 0; $i < $value->length(); $i++)
				{
					try {
						$val = $value->get($i);
						$j = $i;
						$this->checkType($rule, $val, $path . '.' . $i, false, $ctx, $root, $value, $j);
						$out->push($val);
					}
					catch (StopValidation $e) {
						$out->push($val);
					}
					catch (IgnoreField $e) {
					}
					catch (\Exception $e) {
						$err->push($e->getMessage());
					}
				}

				$value = $out;
				break;

			case 'vector':
				if (\Rose\typeOf($value, true) !== 'Rose\\Arry')
					throw new Error(Shield::getMessage('expected_vector') . ': ' . $path);

				if ($value->length() < $node->length()-1)
					throw new Error(Shield::getMessage('min-size:' . ($node->length()-1)) . ': ' . $path);

				$out = new Arry();

				for ($i = 1; $i < $node->length(); $i++)
				{
					try {
						$val = $value->get($i-1);
						$j = $i-1;
						$this->checkType($node->get($i), $val, $path . '.' . ($i-1), false, $ctx, $root, $value, $j);
						$out->push($val);
					}
					catch (StopValidation $e) {
						$out->push($val);
					}
					catch (IgnoreField $e) {
					}
					catch (\Exception $e) {
						$err->push($e->getMessage());
					}
				}

				$value = $out;
				break;

			case 'type':
				$errors = new Map();

				$tmp = new Map();
				$tmp->set('tmp', $value);
				Shield::validateValue ($node->get(1), 'tmp', 'tmp', $tmp, $tmp, $ctx, $errors);

				if ($errors->has('tmp'))
					throw new Error($errors->get('tmp') . ': ' . $path);

				if (!$tmp->has('tmp'))
					throw new IgnoreField();

				$value = $tmp->get('tmp');
				break;

			case 'value':
				if ($value !== $node->get(1)) throw new Error('value: ' . $path . ' should be `' . Text::toString($node->get(1)) . '`');
				break;

			case 'rules':
				$validate = true;
				break;

			case 'boolean':
				if (!\Rose\isBool($value)) {
					if ($opt) throw new IgnoreField();
					throw new Error(Shield::getMessage('expected_boolean') . ': ' . $path);
				}
				$validate = true;
				break;

			case 'integer':
				if (!\Rose\isInteger($value)) {
					if ($opt) throw new IgnoreField();
					throw new Error(Shield::getMessage('expected_integer') . ': ' . $path);
				}
				$validate = true;
				break;

			case 'number':
				if (!\Rose\isNumber($value)) {
					if ($opt) throw new IgnoreField();
					throw new Error(Shield::getMessage('expected_number') . ': ' . $path);
				}
				$validate = true;
				break;

			case 'string':
				if (!\Rose\isString($value)) {
					if ($opt) throw new IgnoreField();
					throw new Error(Shield::getMessage('expected_string') . ': ' . $path);
				}
				$validate = true;
				break;

			case 'null':
				if ($value !== null) {
					if ($opt) throw new IgnoreField();
					throw new Error(Shield::getMessage('expected_null') . ': ' . $path);
				}
				break;

			default:
				throw new Error(Shield::getMessage('unknown_descriptor') . ': ' . $node->get(0));
		}

		if ($validate && $node->length() > 1)
		{
			$errors = new Map();
			$output = new Map();

			Shield::validateValue ($node->get(1), $key, $key, $input, $output, $ctx, $errors);

			if ($errors->has($key))
				throw new Error($errors->get($key) . ': ' . $path);

			if (!$output->has($key))
				throw new IgnoreField();

			$value = $output->get($key);

			if ($node->get(1)[1] != '')
				$key = $node->get(1)[1];
		}

		if ($err->length() != 0)
			throw new Error ($err->join("\n"));
	}

	public function validate ($name, &$val, $input, $output, $context)
	{
		$this->checkType (self::flatten($this->value, $context), $val, $name, false, $context, $val, $input, $name);
		return true;
	}
};

Shield::registerRule('data', 'Rose\Ext\Shield\Data');

/*
	(shield::type my_rule
		required true
		data (array (object
			count "integer"
			type "text"
		))
	)
	(shield::field input
		json-load "POST"
		data (object
				id "integer"
				desc "text"
				mode (value "MODE_1")
				value any
				second_value *
				answers (array (object
					count "integer"
					type "text"
					desc "text"
				))
				other_answers (type my_rule)
				colors (array ...)
				options (object ...)

				some_value (boolean)
				some_value (integer)
				some_value (number default 25.5)
				some_value (string max-length 10)
				some_value (null)
			)
	)

*/