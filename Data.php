<?php

namespace Rose\Ext\Shield;

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

class SkipError extends Error
{
    public function __construct (string $message='') {
        parent::__construct ($message);
    }
};

class Data extends Rule
{
    public static $IGNORE = null;

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
            case 'bool':
            case 'integer':
            case 'int':
            case 'number':
            case 'string':
            case 'str':
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
                                        case 'bool': $mode = 7; break;
                                        case 'integer': $mode = 7; break;
                                        case 'int': $mode = 7; break;
                                        case 'number': $mode = 7; break;
                                        case 'string': $mode = 7; break;
                                        case 'str': $mode = 7; break;
                                        case 'null': $mode = 7; break;
                                    }
                                }
                                else
                                    throw new Error('Expected a type name');

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

    private static function updateValue (&$value, $ctx, $new_value)
    {
        if ($value === $ctx->get('$root'))
            $ctx->set('$root', $new_value);

        $value = $new_value;
    }

    private function checkType ($node, &$value, $path, $is_optional, $ctx, &$rel_root, $input, &$rel_key, $errors)
    {
        // Just check pattern if node is a string.
        if (\Rose\typeOf($node, true) === 'string')
        {
            if (\Rose\isString($value))
                self::updateValue($value, $ctx, Text::trim($value));

            if ($node === '...' || $node === '*' || $node === 'any')
                return;

            $tmp = (string)$value;
            $name = Shield::getMessage('pattern') . ':' . $node;

            if (Text::length($tmp) == 0) {
                if ($is_optional) throw new IgnoreField();
                $errors->set($path, Shield::getMessage('required:true'));
                throw new SkipError();
            }

            if ($node[0] !== '/' && $node[0] !== '|') {
                $regex = Strings::getInstance()->regex->$node;
                if (!$regex) {
                    $errors->set($path, Shield::getMessage('undefined_regex') . ': ' . $node);
                    throw new SkipError();
                }
            }
            else {
                $regex = $node;
                $name = 'pattern';
            }

            if (!Regex::_matches ($regex, $value)) {
                $errors->set($path, $name);
                throw new SkipError();
            }

            return;
        }

        $num_initial_errors = $errors->length();
        $validate = false;

        switch ($node->first())
        {
            case 'object':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Map') {
                    $errors->set($path, Shield::getMessage('expected:object'));
                    throw new SkipError();
                }

                $input_value = $value;
                $keys = $input_value->keys();
                $output = new Map();
                self::updateValue($value, $ctx, $output);

                for ($i = 1; $i < $node->length(); $i += 2)
                {
                    try
                    {
                        $key = $node->get($i);
                        if ($key === '...' || $key === '*') {
                            $keys->forEach(function($key) use (&$output, &$input_value) {
                                $output->set($key, $input_value->get($key));
                            });
                            break;
                        }

                        $is_optional = Text::endsWith($key, '?');
                        if ($is_optional) $key = Text::substring($key, 0, -1);

                        $cpath = $path !== '' ? ($path . '.' . $key) : $key;

                        $idx = $keys->indexOf($key);
                        if ($idx !== null) $keys->remove($idx);

                        $val = $input_value->get($key);
                        $this->checkType($node->get($i+1), $val, $cpath, $is_optional, $ctx, $output, $input_value, $key, $errors);
                        $output->set($key, $val);
                    }
                    catch (StopValidation $e) {
                        $output->set($key, $val);
                    }
                    catch (IgnoreField $e) {
                    }
                    catch (SkipError $e) {
                    }
                    catch (\Exception $e) {
                        $errors->set($cpath, $e->getMessage());
                    }
                }
                break;

            case 'array':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Arry') {
                    $errors->set($path, Shield::getMessage('expected:array'));
                    throw new SkipError();
                }

                $input_value = $value;
                $output = new Arry();
                self::updateValue($value, $ctx, $output);
                $rule = $node->get(1);

                if (self::$IGNORE === null)
                    self::$IGNORE = new Map();

                for ($i = 0; $i < $input_value->length(); $i++)
                {
                    try {
                        $cpath = $path !== '' ? ($path . '.' . $i) : $i;
                        $val = $input_value->get($i);
                        $j = $i;
                        $this->checkType($rule, $val, $cpath, false, $ctx, $output, $input_value, $j, $errors);
                        $output->push($val);
                    }
                    catch (StopValidation $e) {
                        $output->push($val);
                    }
                    catch (IgnoreField $e) {
                        $output->push(self::$IGNORE);
                    }
                    catch (SkipError $e) {
                        $output->push(self::$IGNORE);
                    }
                    catch (\Exception $e) {
                        $output->push(self::$IGNORE);
                        $errors->set($cpath, $e->getMessage());
                    }
                }

                $output = $output->filter(function($item) {
                    return $item !== self::$IGNORE;
                });

                self::updateValue($value, $ctx, $output);
                break;

            case 'vector':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Arry') {
                    $errors->set($path, Shield::getMessage('expected:vector'));
                    throw new SkipError();
                }

                if ($value->length() < $node->length()-1) {
                    $errors->set($path, Shield::getMessage('min-size:' . ($node->length()-1)));
                    throw new SkipError();
                }

                $input_value = $value;
                $output = new Arry();
                self::updateValue($value, $ctx, $output);

                for ($i = 1; $i < $node->length(); $i++)
                {
                    try {
                        $cpath = $path !== '' ? ($path . '.' . ($i-1)) : ($i-1);
                        $val = $input_value->get($i-1);
                        $j = $i-1;
                        $this->checkType($node->get($i), $val, $cpath, false, $ctx, $output, $input_value, $j, $errors);
                        $output->push($val);
                    }
                    catch (StopValidation $e) {
                        $output->push($val);
                    }
                    catch (IgnoreField $e) {
                    }
                    catch (SkipError $e) {
                    }
                    catch (\Exception $e) {
                        $errors->set($cpath, $e->getMessage());
                    }
                }
                break;

            case 'type':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                // TODO: Fix that any custom created vars at the $out level will be lost.
                $tmp = new Map();
                $tmp->set('tmp', $value);

                $_errors = new Map();
                Shield::validateValue ($node->get(1), 'tmp', 'tmp', $tmp, $tmp, $ctx, $_errors);
                if ($_errors->length)
                {
                    $_errors->forEach(function($value, $key) use($errors, $path) {
                        if (Text::startsWith($key, 'tmp'))
                            $errors->set($path.Text::substring($key, 3), $value);
                    });
                    throw new SkipError();
                }

                if (!$tmp->has('tmp'))
                    throw new IgnoreField();

                self::updateValue($value, $ctx, $tmp->get('tmp'));
                break;

            case 'value':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if ($value !== $node->get(1)) {
                    $errors->set($path, 'value should be `' . Text::toString($node->get(1)) . '`');
                    throw new SkipError();
                }

                break;

            case 'rules':
                if ($is_optional && $value === null)
                    throw new IgnoreField();
                $validate = true;
                break;

            case 'boolean':
            case 'bool':
                if (!\Rose\isBool($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expected:boolean'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'integer':
            case 'int':
                if (!\Rose\isInteger($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expected:integer'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'number':
                if (!\Rose\isNumber($value) && !\Rose\isInteger($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expected:number'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'string':
            case 'str':
                if (!\Rose\isString($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expected:string'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'null':
                if ($value !== null) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expected:null'));
                    throw new SkipError();
                }
                break;

            default:
                $errors->set($path, Shield::getMessage('unknown_descriptor') . ': ' . $node->get(0));
                throw new SkipError();
        }

        if ($validate === true && $node->length() > 1)
        {
            $_errors = new Map();
            Shield::validateValue ($node->get(1), $rel_key, $rel_key, $input, $rel_root, $ctx, $_errors);
            if ($_errors->length) {
                $path = Text::substring($path, 0, -Text::length((string)$rel_key));
                $_errors->forEach(function($value, $key) use($errors, $path) {
                    $errors->set($path.$key, $value);
                });
                throw new SkipError();
            }

            $ignore_field = !$rel_root->has($rel_key);
            $val = $rel_root->get($rel_key);
            $rel_root->remove($rel_key);
            self::updateValue($value, $ctx, $val);

            // Get final output name provided by "output xxx" rule (if any).
            if ($node->get(1)[1] !== '')
                $rel_key = $node->get(1)[1];

            if ($ignore_field)
                throw new IgnoreField();
        }

        if ($errors->length() != $num_initial_errors)
            throw new SkipError();
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        try {
            $flattened = self::flatten($this->value, $context);
        }
        catch (\Exception $e) {
            $errors->set($name, $e->getMessage());
            throw $e;
        }

        try {
            $context->set('$root', $val);
            $this->checkType($flattened, $val, $name, false, $context, $val, $input, $name, $errors);
        }
        finally {
            $context->remove('$root');
        }

        return true;
    }
};

Shield::registerRule('data', 'Rose\Ext\Shield\Data');

/*
    (shield:type my_rule
        required true
        data (array (object
            count "integer"
            type "text"
        ))
    )

    (shield:field input
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
                some_value (rules
                    required true
                    pattern integer
                )
            )
    )

*/