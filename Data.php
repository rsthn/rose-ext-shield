<?php

namespace Rose\Ext\Shield;

use Rose\Ext\Shield\Rule;
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

    private $flattened = null;

    public function getName()
    {
        return 'data';
    }

    protected static function isTypeNode ($node)
    {
        if ($node->length() !== 1 || ($node->get(0)->type !== 'identifier' && $node->get(0)->type !== 'string'))
            return false;

        if ($node->get(0)->data === null)
            $node->get(0)->data = 'null';

        switch ($node->get(0)->data) {
            case 'rules':

            case 'object':
            case 'obj':
            case 'array':
            case 'vector':
            case 'boolean':
            case 'bool':
            case 'integer':
            case 'int':
            case 'float':
            case 'string':
            case 'str':
            case 'null':
                return true;
        }

        return false;
    }

    protected static function flatten ($node, $ctx)
    {
        $type = \Rose\typeOf($node, true);
        if ($type === 'Rose\\Arry') {
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
                                        case 'rules': $mode = 7; break;

                                        case 'object': $mode = 1; break;
                                        case 'obj': $mode = 1; break;
                                        case 'array': $mode = 3; break;
                                        case 'vector': $mode = 4; break;
                                        case 'boolean': $mode = 7; break;
                                        case 'bool': $mode = 7; break;
                                        case 'integer': $mode = 7; break;
                                        case 'int': $mode = 7; break;
                                        case 'float': $mode = 7; break;
                                        case 'string': $mode = 7; break;
                                        case 'str': $mode = 7; break;
                                        case 'null': $mode = 7; break;
                                    }
                                }
                                else
                                    throw new Error('invalid specifier name: ' . $item->get(0)->data);

                                break;

                            case 1: // Key
                                $tmp = Expr::value($item, $ctx);
                                $node->data->set($i, $tmp);

                                if ($tmp === '*') {
                                    if ((1+$i) != $node->data->length())
                                        throw new Error('rest indicator `*` must be the last element in object specifier');
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

                            case 7: // Shield validation rules.
                                $node->data->set(1, Shield::getDescriptor(Shield::parseDescriptor($node->data, $ctx, 1), $ctx));
                                $mode = -1;
                                break;
                        }
                    }

                    if ($mode == 2)
                        throw new Error('expected a specifier name after `'.$node->data->last().'` in object specifier');

                    $node = $node->data;
                    break;

                default:
                    throw new Error('unexpected token in descriptor');
            }

        }

        return $node;
    }

    public function getIdentifier() {
        return '';
    }

    private function checkType ($node, $value, $path, $is_optional, $ctx, &$rel_root, $input, $rel_key, $errors)
    {
        // Just check pattern if node is a string.
        if (\Rose\typeOf($node, true) === 'string')
        {
            /*$tmp = (string)$value;
            $name = Shield::getMessage('pattern') . ':' . $node;

            if (Text::length($tmp) == 0) {
                if ($is_optional) throw new IgnoreField();
                $errors->set($path, Shield::getMessage('required:true'));
                throw new SkipError();
            }

            if ($node[0] !== '/' && $node[0] !== '|') {
                $regex = Strings::getInstance()->regex->$node;
                if (!$regex) {
                    $errors->set($path, 'undefined regex: ' . $node);
                    throw new SkipError();
                }
            }
            else {
                $regex = $node;
                $name = 'pattern';
            }

            if (!Regex::_matches($regex, $value)) {
                $errors->set($path, $name);
                throw new SkipError();
            }*/

            $_errors = new Map();
            $ignored = Shield::validateValue($node, $rel_key, $input, $rel_root, $ctx, $_errors);
            self::processErrors($_errors, $errors, $path, $rel_key);
            if ($ignored)
                throw new IgnoreField();

            return;
        }

        $num_initial_errors = $errors->length();
        $validate = false;

        switch ($node->first())
        {
            case 'rules':
                if ($is_optional && $value === null)
                    throw new IgnoreField();
                $validate = true;
                break;

            case 'object': case 'obj':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Map') {
                    $errors->set($path, Shield::getMessage('expect:obj'));
                    throw new SkipError();
                }

                $cur_input = $value;
                $cur_output = new Map();
                $keys = $cur_input->keys();

                for ($i = 1; $i < $node->length(); $i += 2)
                {
                    try
                    {
                        $key = $node->get($i);
                        if ($key === '*') {
                            // todo: optimize???
                            $keys->forEach(function($key) use (&$cur_output, &$cur_input) {
                                $cur_output->set($key, $cur_input->get($key));
                            });
                            break;
                        }

                        $is_optional = Text::endsWith($key, '?');
                        if ($is_optional) $key = Text::substring($key, 0, -1);

                        $cur_path = $path . '.' . $key;
                        $key_index = $keys->indexOf($key);
                        if ($key_index !== null) $keys->remove($key_index);

                        $this->checkType($node->get($i+1), $cur_input->get($key), $cur_path, $is_optional, $ctx, $cur_output, $cur_input, $key, $errors);
                    }
                    catch (IgnoreField $e) {
                    }
                    catch (SkipError $e) {
                    }
                    catch (\Exception $e) {
                        $errors->set($cur_path, $e->getMessage());
                    }
                }

                $rel_root->set($rel_key, $cur_output);
                break;

            case 'array':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Arry') {
                    $errors->set($path, Shield::getMessage('expect:array'));
                    throw new SkipError();
                }

                $rule = $node->get(1);
                if ($rule === '*') {
                    $rel_root->set($rel_key, $value);
                    break;
                }

                $cur_input = $value;
                $cur_output = new Arry();

                if ($node->length() > 2)
                    throw new \Exception("array specifier expects only one argument");

                if (self::$IGNORE === null)
                    self::$IGNORE = new Map();

                for ($i = 0; $i < $cur_input->length(); $i++)
                {
                    try {
                        $cur_path = $path . '.' . $i;
                        $this->checkType($rule, $cur_input->get($i), $cur_path, false, $ctx, $cur_output, $cur_input, $i, $errors);
                    }
                    catch (IgnoreField $e) {
                        $cur_output->set($i, self::$IGNORE);
                    }
                    catch (SkipError $e) {
                        $cur_output->set($i, self::$IGNORE);
                    }
                    catch (\Exception $e) {
                        $cur_output->set($i, self::$IGNORE);
                        $errors->set($cur_path, $e->getMessage());
                    }
                }

                $rel_root->set($rel_key, $cur_output->filter(function($item) {
                    return $item !== self::$IGNORE;
                }));
                break;

            case 'vector':
                if ($is_optional && $value === null)
                    throw new IgnoreField();

                if (\Rose\typeOf($value, true) !== 'Rose\\Arry') {
                    $errors->set($path, Shield::getMessage('expect:vector'));
                    throw new SkipError();
                }

                if ($value->length() < $node->length()-1) {
                    $errors->set($path, Shield::getMessage('min-size:' . ($node->length()-1)));
                    throw new SkipError();
                }

                $cur_input = $value;
                $cur_output = new Arry();

                for ($i = 1; $i < $node->length(); $i++) {
                    try
                    {
                        if ($node->get($i) === '*') {
                            $cur_output->set($i-1, $cur_input->get($i-1));
                            continue;
                        }

                        $cur_path = $path . '.' . ($i-1);
                        $this->checkType($node->get($i), $cur_input->get($i-1), $cur_path, false, $ctx, $cur_output, $cur_input, $i-1, $errors);
                    }
                    catch (IgnoreField $e) {
                    }
                    catch (SkipError $e) {
                    }
                    catch (\Exception $e) {
                        $errors->set($cur_path, $e->getMessage());
                    }
                }

                $rel_root->set($rel_key, $cur_output);
                break;



            case 'boolean': case 'bool':
                if (!\Rose\isBool($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expect:bool'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'integer': case 'int':
                if (!\Rose\isInteger($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expect:int'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'float':
                if (!\Rose\isNumber($value) && !\Rose\isInteger($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expect:float'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'string': case 'str':
                if (!\Rose\isString($value)) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expect:str'));
                    throw new SkipError();
                }
                $validate = true;
                break;

            case 'null':
                if ($value !== null) {
                    if ($is_optional) throw new IgnoreField();
                    $errors->set($path, Shield::getMessage('expect:null'));
                    throw new SkipError();
                }
                $rel_root->set($rel_key, $value);
                break;

            default:
                $errors->set($path, Shield::getMessage('unknown_descriptor') . ': ' . $node->get(0));
                throw new SkipError();
        }

        if ($validate === true) {
            if ($node->length() > 1) {
                $_errors = new Map();
                $ignored = Shield::validateValue($node->get(1), $rel_key, $input, $rel_root, $ctx, $_errors);
                self::processErrors($_errors, $errors, $path, $rel_key);
                if ($ignored)
                    throw new IgnoreField();
            }
            else
                $rel_root->set($rel_key, $value);
        }

        if ($errors->length() != $num_initial_errors)
            throw new SkipError();
    }

    public function prepare ($context) {
        if (!$this->flattened)
            $this->flattened = self::flatten($this->value, $context);
        return $this->flattened;
    }

    public function validate ($name, &$val, $input, $output, $context, $errors)
    {
        try {
            if (!$this->flattened)
                $this->flattened = self::flatten($this->value, $context);
        }
        catch (\Exception $e) {
            $errors->set($name, $e->getMessage());
            throw $e;
        }

        try {
            $context->set('$root', $val);
            $cur_output = new Map();
            $this->checkType($this->flattened, $val, $name, false, $context, $cur_output, $input, $name, $errors);

            if (!$cur_output->has($name))
                return false;
            $val = $cur_output->get($name);
        }
        finally {
            $context->remove('$root');
        }

        return true;
    }
};

Shield::registerRule('data', 'Rose\Ext\Shield\Data');
