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

class JsonData extends Rule
{
	public function getName ()
	{
		return 'json-data';
	}

	protected static function isTypeNode ($node)
	{
		if ($node->length() !== 1 || $node->get(0)->type !== 'identifier')
			return false;

		switch ($node->get(0)->data) {
			case 'object':
			case 'array':
			case 'vector':
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
								if (self::isTypeNode($item)) {
									$node->data->set($i, $item->get(0)->data);
									switch ($item->get(0)->data) {
										case 'object': $mode = 1; break;
										case 'array': $mode = 3; break;
										case 'vector': $mode = 4; break;
									}
								}
								else
									throw new Error('Expected a json-data type name');
								break;

							case 1: // Key
								$node->data->set($i, Expr::value($item, $ctx));
								$mode = 2;
								break;

							case 2: // Value
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));
								$mode = 1;
								break;

							case 3: // Array Value (Single) (array xxx)
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));

								$mode = -1;
								break;

							case 4: // Vector Values (vector x y z)
								if ($item->length() == 1 && $item->get(0)->type === 'template' && self::isTypeNode($item->get(0)->data->get(0)))
									$node->data->set($i, self::flatten($item, $ctx));
								else
									$node->data->set($i, Expr::value($item, $ctx));
								break;
						}
					}

					if ($mode == 2)
						throw new Error('Expected a type name after '.$node->data->last().' in json-data');

					$node = $node->data;
					break;

				default:
					throw new Error('Unexpected token in json-data descriptor');
			}

		}

		return $node;
	}

	public function getIdentifier()
	{
		return '';
	}

	private function checkType ($node, &$value, $path, $opt)
	{
		//\Rose\trace($node);
		//exit;

		if (\Rose\typeOf($node, true) === 'string')
		{
			if (is_string($value))
				$value = Text::trim($value);

			if ($node === '*')
				return;

			$tmp = (string)$value;
			$name = 'pattern:' . $node;

			if (Text::length($tmp) == 0)
			{
				if ($opt)
					throw new IgnoreField();

				throw new ArgumentError('required: ' . $path);
			}

			if ($node[0] != '/' && $node[0] != '|') {
				$regex = Strings::getInstance()->regex->$node;
				if (!$regex) throw new ArgumentError('undefined_regex: '.$node);
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

		switch ($node->first())
		{
			case 'object':
				if (\Rose\typeOf($value, true) !== 'Rose\\Map')
					throw new Error('type:object: ' . $path);

				$out = new Map();

				for ($i = 1; $i < $node->length(); $i += 2)
				{
					try {
						$key = $node->get($i);
						$opt = Text::endsWith($key, '?');
						if ($opt) $key = Text::substring($key, 0, -1);

						$val = $value->get($key);
						$this->checkType($node->get($i+1), $val, $path . '.' . $key, $opt);
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
					throw new Error('type:array: ' . $path);

				$out = new Arry();

				for ($i = 0; $i < $value->length(); $i++)
				{
					try {
						$val = $value->get($i);
						$this->checkType($node->get(1), $val, $path . '.' . $i, false);
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
					throw new Error('type:vector: ' . $path);

				if ($value->length() < $node->length()-1)
					throw new Error('min-size:' . ($node->length()-1) . ': ' . $path);

				$out = new Arry();

				for ($i = 1; $i < $node->length(); $i++)
				{
					try {
						$val = $value->get($i-1);
						$this->checkType($node->get($i), $val, $path . '.' . ($i-1), false);
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
		}

		if ($err->length() != 0)
			throw new Error ($err->join("\n"));
	}

	public function validate ($name, &$val, $input, $output, $context)
	{
		$this->checkType (self::flatten($this->value, $context), $val, $name, false);
		return true;
	}
};

Shield::registerRule('data', 'Rose\Ext\Shield\JsonData');
