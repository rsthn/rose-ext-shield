<?php

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
use Rose\Arry;

use Rose\Ext\Shield\StopValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Wind;
use Rose\Ext\Wind\WindError;

class Shield
{
    /**
     * Registered field definitions.
     */
    public static $fields;

    /**
     * Registered validation rules.
     */
    public static $rules;

    /**
     * Accumulated validation errors.
     */
    public static $errors;

    /**
     * Initializes the registration maps.
     */
    public static function init()
    {
        self::$fields = new Map();
        self::$rules = new Map();
        self::$errors = null;
    }

    /**
     * Registers a validation rule handler.
     */
    public static function registerRule (string $ruleName, string $className)
    {
        self::$rules->set($ruleName, $className);
    }

    /**
     * Returns `true` if the specified rule exists.
     */
    public static function ruleExists (string $ruleName)
    {
        return self::$rules->has($ruleName);
    }

    /**
     * Verifies that the given rule set is valid and returns a validation descriptor.
     */
    public static function getDescriptor (string $name, \Rose\Arry $rules, \Rose\Map $data)
    {
        $output_rules = [];

        $input_name = $name;
        $output_name = $name;

        $rules->forEach (function($value) use (&$output_rules, &$input_name, &$output_name, &$data)
        {
            $key = $value->get(0);
            $value = $value->get(1);

            if ($key == 'input')
            {
                $input_name = Expr::value($value, $data);
                return;
            }

            if ($key == 'output')
            {
                $output_name = Expr::value($value, $data);
                return;
            }

            $key = Text::split(':', $key);

            $className = self::$rules->get($key->get(0));
            if (!$className)
            {
                throw new Error ('Undefined validation rule: ' . $key->get(0));
                return;
            }

            $output_rules[] = new $className ($value, $key->{1});
        });

        // A validation descriptor is an array having three fields as follows.
        return [$input_name, $output_name, $output_rules];
    }

    /**
     * Registers a field validation descriptor.
     */
    public static function registerDescriptor (string $name, array $desc)
    {
        self::$fields->set($name, $desc);
    }

    /**
     * Returns an error message from the `messages` strings.
     */
    public static function getMessage ($id)
    {
        return Strings::get('@messages.'.$id);
    }

    /**
     * Validates a value.
     */
    public static function validateValue ($desc, $input_name, $output_name, \Rose\Map $input, \Rose\Map $output, \Rose\Map $context, \Rose\Map $errors)
    {
        if (\Rose\isString($desc))
        {
            $name = $desc;
            $desc = self::$fields->get($desc);
            if (!$desc) throw new ArgumentError ('Undefined validation descriptor: '.$name);
        }

        $_out = $context->get('$out');
        $_in = $context->get('$in');
        $_val = $context->get('$');
        $context->set('$out', $output);
        $context->set('$in', $input);

        $input_name = $input_name ?? $desc[0];
        $output_name = $output_name ?? $desc[1];

        $value = $input->get($input_name);
        $output->set($output_name, $value);
        $output->set('_selfName', $output_name);
        $remove = false;

        foreach ($desc[2] as $rule)
        {
            $output->set('_selfValue', $output->__nativeArray[$output_name]);
            $context->set('$', $output->__nativeArray[$output_name]);

            $_errors = new Map();

            try {
                if ($rule->validate($input_name, $output->__nativeArray[$output_name], $input, $output, $context, $_errors))
                    continue;
            }
            catch (StopValidation $e) {
                break;
            }
            catch (IgnoreField $e) {
                $remove = true;
                break;
            }
            catch (\Exception $e)
            {
                $tmp = $rule->getIdentifier();
                if (!$tmp)
                    $errors->merge($_errors, true);
                else
                    $errors->set($input_name, $tmp ? '('.$tmp.') '.$e->getMessage() : $e->getMessage());

                $remove = true;
                break;
            }

            $errors->set($input_name, Shield::getMessage($rule->getIdentifier()));
            $remove = true;
            break;
        }

        $context->set('$out', $_out);
        $context->set('$in', $_in);
        $context->set('$', $_val);

        $value = $output->__nativeArray[$output_name];

        $output->remove('_selfName');
        $output->remove('_selfValue');

        if ($remove) {
            $output->remove($output_name);
            return null;
        }

        return $value;
    }

    /**
     * Parses the rules of a validation descriptor.
     */
    public static function parseDescriptor ($parts, $data, $i=0)
    {
        $rules = new Arry();

        for (; $i < $parts->length(); $i += 2)
        {
            $key = Expr::value($parts->get($i), $data);
            if (substr($key, -1) == ':')
                $key = substr($key, 0, strlen($key)-1);

            $tmp = $parts->get($i+1);
    
            if ($tmp->length == 1 && ($tmp->get(0)->type != 'template' && $tmp->get(0)->type != 'string'))
                $tmp = Expr::value($tmp, $data);
                
            $rules->push(new Arry ([$key, $tmp], false));
        }

        return $rules;
    }
};

/**
 * Returns a field validation descriptor.
 * @code (`shield:field` <field-name> <rules...>)
 */
Expr::register('_shield:field', function($parts, $data)
{
    $name = Expr::value($parts->get(1), $data);

    if (!\Rose\isString($name))
        throw new ArgumentError ('shield:field expects \'name\' parameter to be a string.');

    return Shield::getDescriptor($name, Shield::parseDescriptor($parts, $data, 2), $data);
});

/**
 * Registers a type validation descriptor.
 * @code (`shield:type` <type-name> <rules...>)
 */
Expr::register('_shield:type', function($parts, $data)
{
    $name = Expr::value($parts->get(1), $data);

    if (!\Rose\isString($name))
        throw new ArgumentError ('shield:type expects \'name\' parameter to be a string.');

    $rules = new Arry();

    for ($i = 2; $i < $parts->length(); $i += 2)
    {
        $key = Expr::value($parts->get($i), $data);
        if (substr($key, -1) == ':')
            $key = substr($key, 0, strlen($key)-1);

        $tmp = $parts->get($i+1);

        if ($tmp->length == 1 && ($tmp->get(0)->type != 'template' && $tmp->get(0)->type != 'string'))
            $tmp = Expr::value($tmp, $data);
            
        $rules->push(new Arry ([$key, $tmp], false));
    }

    Shield::registerDescriptor ($name, Shield::getDescriptor($name, $rules, $data));
    return null;
});

/**
 * Begins quiet validation mode. All validation errors will be accumulated, and can later be retrieved by calling `shield:end`.
 * @code (`shield:begin`)
 */
Expr::register('shield:begin', function($args, $parts, $data)
{
    Shield::$errors = new Map();
});

/**
 * Ends quiet validation mode, if there are any errors and `automatic` is set to `true` (default), then Wind::R_VALIDATION_ERROR will
 * be thrown, otherwise, the error map will just be returned.
 * @code (`shield:end` [automatic=true])
 */
Expr::register('shield:end', function($args, $parts, $data)
{
    $err = Shield::$errors;
    Shield::$errors = null;

    if ($args->length == 2 && \Rose\bool($args->get(1)) != true)
        return $err;

    if ($err->length != 0)
        throw new WindError([ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $err ]);

    return null;
});

/**
 * Runs a validation sequence, if any error occurs replies Wind::R_VALIDATION_ERROR. The validated fields will be available in the
 * global context if validation succeeded.
 * @code (`shield:validate` <targetName> <...field>)
 */
Expr::register('shield:validate', function($args, $parts, $data)
{
    $inputData = Gateway::getInstance()->request;
    $outputData = $data;
    $errors = Shield::$errors != null ? Shield::$errors : new Map();

    $i = $args->get(1);
    if (\Rose\isString($i) && Shield::$fields->get($i) == null)
    {
        if ($i !== 'global')
        {
            if ($data->has($i))
                $outputData = $data->get($i);
            else
                $data->set($i, $outputData = new Map());
        }

        $i = 2;
    }
    else
        $i = 1;

    $data->set('formData', $outputData);

    for (; $i < $args->length; $i++)
        Shield::validateValue ($args->get($i), null, null, $inputData, $outputData, $data, $errors);

    if ($errors !== Shield::$errors && $errors->length != 0)
        throw new WindError([ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);

    $data->remove('formData');
    return null;
});

/**
 * Runs a validation sequence on a specified data map, if any error occurs replies Wind::R_VALIDATION_ERROR. The validated fields will
 * be available in the global context if validation succeeded.
 * @code (`shield:validate-data` <inputData> <targetName> <...field>)
 */
Expr::register('shield:validate-data', function($args, $parts, $data)
{
    $inputData = $args->get(1);
    $outputData = $data;
    $errors = Shield::$errors != null ? Shield::$errors : new Map();

    $i = $args->get(2);
    if (Shield::$fields->get($i) == null)
    {
        if ($i != 'global')
        {
            if ($data->has($i))
                $outputData = $data->get($i);
            else
                $data->set($i, $outputData = new Map());
        }

        $i = 3;
    }
    else
        $i = 2;

    $data->set('formData', $outputData);

    for (; $i < $args->length; $i++)
        Shield::validateValue ($args->get($i), null, null, $inputData, $outputData, $data, $errors);

    if ($errors !== Shield::$errors && $errors->length != 0)
        throw new WindError([ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);

    $data->remove('formData');
    return $outputData;
});

/**
 * Validates the body of the request.
 */
Expr::register('_shield:validate-body', function($parts, $data)
{
	$name = 'body';
    $index = 1;
    Expr::takeIdentifier($parts, $data, $index, $name);

    $desc = Shield::parseDescriptor($parts, $data, $index-1);
    $desc->get(0)->set(0, 'data');
    $desc->unshift(new Arry(["json-load", [[ "type" => "string", "data" => "body" ]]]));
    $desc = Shield::getDescriptor($name, $desc, $data);

    $inputData = Gateway::getInstance()->request;
    $outputData = new Map();
    $errors = Shield::$errors != null ? Shield::$errors : new Map();
    $data->set('formData', $outputData);

        Shield::validateValue ($desc, null, null, $inputData, $outputData, $data, $errors);

    if ($errors !== Shield::$errors && $errors->length != 0)
        throw new WindError([ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);

    $data->remove('formData');
    $data->set($name, $outputData->get($name));
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
class_exists('Rose\Ext\Shield\Set');
class_exists('Rose\Ext\Shield\Check');
class_exists('Rose\Ext\Shield\Default_');
class_exists('Rose\Ext\Shield\DefaultStop');
class_exists('Rose\Ext\Shield\MinValue');
class_exists('Rose\Ext\Shield\MaxValue');
class_exists('Rose\Ext\Shield\Requires');
class_exists('Rose\Ext\Shield\FileType');
class_exists('Rose\Ext\Shield\MaxFileSize');
class_exists('Rose\Ext\Shield\Ignore');
class_exists('Rose\Ext\Shield\Extract');
class_exists('Rose\Ext\Shield\Stop');
class_exists('Rose\Ext\Shield\Data');
class_exists('Rose\Ext\Shield\ContentType');
class_exists('Rose\Ext\Shield\JsonLoad');
class_exists('Rose\Ext\Shield\Cast');
class_exists('Rose\Ext\Shield\Expect');
class_exists('Rose\Ext\Shield\Block');
class_exists('Rose\Ext\Shield\Type');
