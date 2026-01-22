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
use Rose\Ext\Shield\CondValidation;
use Rose\Ext\Shield\IgnoreField;
use Rose\Ext\Wind;
use Rose\Ext\Wind\WindError;

// @title Shield

class ValidationModel
{
    public $descriptor;
    public $id;
    public function __construct ($descriptor) {
        $this->id = uniqid('model_');
        $this->descriptor = $descriptor;
    }
    public function __toString() {
        return $this->id;
    }
}

class Shield
{
    /**
     * Registered rulesets.
     */
    public static $rulesets;

    /**
     * Registered models;
     */
    public static $models;

    /**
     * Registered validation rules.
     */
    public static $rules;

    /**
     * Accumulated validation errors.
     */
    public static $errors;

    /**
     * Undefined value.
     */
    public static $UNDEF;

    /**
     * Initializes the registration maps.
     */
    public static function init() {
        self::$rulesets = new Map();
        self::$models = new Map();
        self::$rules = new Map();
        self::$errors = null;
        self::$UNDEF = new \stdClass();
    }

    /**
     * Registers a validation rule handler.
     */
    public static function registerRule (string $ruleName, string $className) {
        self::$rules->set($ruleName, $className);
    }

    /**
     * Verifies that the given rule set is valid and returns a validation descriptor.
     */
    public static function getDescriptor (\Rose\Arry $rules, \Rose\Map $data)
    {
        $output_rules = [];
        $rules->forEach (function($value) use (&$output_rules, &$data)
        {
            $key = $value->get(0);
            $value = $value->get(1);

            $key = Text::split(':', $key);
            $className = self::$rules->get($key->get(0));
            if (!$className)
                throw new Error('undefined validation rule: ' . $key->get(0));

            $output_rules[] = new $className ($value, $key->{1});
        });

        return [$output_rules];
    }

    /**
     * Returns an error message from the `messages` strings.
     */
    public static function getMessage ($id) {
        return Strings::get('@messages.'.$id);
    }

    /**
     * Validates a value.
     */
    public static function validateValue ($desc, $key, $input, $output, $context, $errors)
    {
        if (\Rose\isString($desc)) {
            $name = $desc;
            $desc = self::$rulesets->get($name);
            if (!$desc) {
                $desc = self::$models->get($name);
                if (!$desc)
                    throw new Error('undefined ruleset or model: '.$name);
                $desc = $desc->descriptor;
            }
        }

        $_out = $context->get('$out');
        $_in = $context->get('$in');
        $_val = $context->get('$');
        $context->set('$out', $output);
        $context->set('$in', $input);
        
        $value = $input->has($key) ? $input->get($key) : null;
        $allowedMap = null;
        $unallowedMap = null;

        foreach ($desc[0] as $rule)
        {
            if ($unallowedMap === true) {
                if (!$allowedMap->has($rule->getName()))
                    continue;
                $allowedMap = $unallowedMap = null;
            }

            $context->set('$', $value);
            $_errors = new Map();

            try {
                if ($allowedMap === true && $unallowedMap->has($rule->getName())) {
                    $rule->failed($input, $output, $context, $_errors);
                    $allowedMap = $unallowedMap = null;
                    continue;
                }

                if ($rule->validate($key, $value, $input, $output, $context, $_errors))
                    continue;
            }
            catch (CondValidation $e) {
                $allowedMap = $e->allowedMap;
                $unallowedMap = $e->unallowedMap;
                continue;
            }
            catch (StopValidation $e) {
                break;
            }
            catch (IgnoreField $e) {
                $value = self::$UNDEF;
                break;
            }
            catch (\Exception $e) {
                // Empty identifier is used for rules that forward errors.
                $tmp = $rule->getIdentifier();
                if ($tmp === '') {
                    $msg = $e->getMessage();
                    if ($msg)
                        $errors->set($key, $msg);
                    $errors->merge($_errors, true);
                }
                else
                    $errors->set($key, $tmp ? '('.$tmp.') '.$e->getMessage() : $e->getMessage());
                $value = self::$UNDEF;
                break;
            }

            $errors->set($key, Shield::getMessage($rule->getIdentifier()));
            $value = self::$UNDEF;
            break;
        }

        if ($key === '') $value = self::$UNDEF;

        $context->set('$out', $_out);
        $context->set('$in', $_in);
        $context->set('$', $_val);

        if ($value !== self::$UNDEF)
            $output->set($key, $value);
        return $value === self::$UNDEF;
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
            if (substr($key, -1) === ':') // TODO: Deprecate using ':' to separate rule name and value
                $key = substr($key, 0, strlen($key)-1);

            // TODO: Register no-value rules somewhere
            if ($key === 'case-else' || $key === 'case-end') {
                $rules->push(new Arry ([$key, null], false));
                $i--;
                continue;
            }

            $tmp = $parts->get($i+1);

            if ($tmp->length == 1 && ($tmp->get(0)->type !== 'template' && $tmp->get(0)->type !== 'string'))
                $tmp = Expr::value($tmp, $data);

            $rules->push(new Arry ([$key, $tmp], false));
        }

        return $rules;
    }
};

/**
 * Ensures the request was made using the specified method(s) or fails with 405/@messages.method_not_allowed.
 * @code (`shield:method-required` <method...>)
 */
Expr::register('shield:method-required', function($args, $parts, $data) {
    $method = Gateway::getInstance()->method;
    for ($i = 1; $i < $args->length; $i++) {
        if ($method === Text::toUpperCase($args->get($i)))
            return true;
    }

    throw new WindError('MethodNotAllowed', [
        'response' => 405, 
        'error' => Strings::get('@messages.method_not_allowed'),
        'expected' => $args->slice(1)
    ]);
});

/**
 * Ensures the request's content-type is one of the specified types. Fails with 422/@messages.request_body_missing if there is no
 * request body, or with 422/@messages.invalid_content_type if the content-type is not valid. If no content type is provided then
 * it is assumed to be `application/json`. Use value `true` to allow any content type.
 * @code (`shield:body-required` [false|true|content-type...])
 */
Expr::register('shield:body-required', function($args, $parts, $data)
{
    if ($args->length === 2 && $args->get(1) === false)
        return true;

    $content_type = Gateway::getInstance()->input->contentType;
    if ($content_type === null || Gateway::getInstance()->input->size == 0) {
        throw new WindError('RequestBodyMissing', [
            'response' => 422,
            'error' => Strings::get('@messages.request_body_missing')
        ]);
    }

    if ($args->length === 2 && $args->get(1) === true)
        return true;

    if ($args->length === 1) {
        if ($content_type === 'application/json')
            return true;
    }
    else {
        for ($i = 1; $i < $args->length; $i++) {
            if ($content_type === $args->get($i))
                return true;
        }
    }

    throw new WindError('InvalidContentType', [
        'response' => 422, 
        'error' => Strings::get('@messages.invalid_content_type'),
        'expected' => $args->slice(1)
    ]);
});


/**
 * Ensures the request's body is at least the specified number of bytes. Fails with 422/@messages.request_body_too_small if not.
 * @code (`shield:body-min-size` <min-size>)
 */
Expr::register('shield:body-min-size', function($args, $parts, $data) {
    if (Gateway::getInstance()->input->contentType === null) {
        throw new WindError('RequestBodyMissing', [
            'response' => 422,
            'error' => Strings::get('@messages.request_body_missing')
        ]);
    }

    $min_size = (int)$args->get(1);
    $size = Gateway::getInstance()->input->size;
    if ($size >= $min_size)
        return true;

    throw new WindError('RequestBodyTooSmall', [
        'response' => 422, 
        'error' => Strings::get('@messages.request_body_too_small'),
        'minimum' => $min_size
    ]);
});


/**
 * Ensures the request's body does not exceed the specified number of bytes. Fails with 422/@messages.request_body_too_large when so.
 * @code (`shield:body-max-size` <max-size>)
 */
Expr::register('shield:body-max-size', function($args, $parts, $data) {
    if (Gateway::getInstance()->input->contentType === null) {
        throw new WindError('RequestBodyMissing', [
            'response' => 422,
            'error' => Strings::get('@messages.request_body_missing')
        ]);
    }

    $max_size = (int)$args->get(1);
    $size = Gateway::getInstance()->input->size;
    if ($size <= $max_size)
        return true;

    throw new WindError('RequestBodyTooLarge', [
        'response' => 422, 
        'error' => Strings::get('@messages.request_body_too_large'),
        'maximum' => $max_size
    ]);
});

/**
 * Registers a set of validation rules with the given name. This can later be used by name
 * from the `use <ruleset-name>` rule.
 * @code (`shield:ruleset` [ruleset-name] <rules...>)
 * @example
 * (shield:ruleset "email"
 *   max-length 256
 *   pattern email
 * )
 */
Expr::register('_shield:ruleset', function($parts, $data)
{
    $name = Expr::value($parts->get(1), $data);
    if (!\Rose\isString($name))
        throw new Error('expected `ruleset-name` parameter to be a string');

    $rules = Shield::parseDescriptor($parts, $data, 2);

    if ($name === '__inline__') {
        $desc = Shield::getDescriptor($rules, $data);
        $model = new ValidationModel($desc);
        return $model;
    }

    if (Shield::$rulesets->has($name))
        throw new Error('duplicate model name: ' . $name);
    if (Shield::$models->has($name))
        throw new Error('model with the same name already exists: ' . $name);

    Shield::$rulesets->set($name, Shield::getDescriptor($rules, $data));
    return Shield::$rulesets->get($name);
});

/**
 * Registers a validation model with the given name to be used later with `shield:validate`.
 * @code (`shield:model` [name] <data-descriptor>)
 * @example
 * (shield:model "Model1"
 *    (object
 *       "username" (string)
 *       "password" (string)
 *       "email" (rules
 *           required true
 *           pattern email
 *           use "verify-unique"
 *        )
 *    )
 * )
 */
Expr::register('_shield:model', function($parts, $data) {
    $type = $parts->get(1)->get(0)->type;
    $name = null;
    if ($type === "string" || $type === "identifier")
        $name = Expr::value($parts->get(1), $data);
    $i = $name === null ? 0 : 1;

    $parts->set($i, 'data');
    $desc = Shield::parseDescriptor($parts, $data, $i);
    $desc = Shield::getDescriptor($desc, $data);
    $model = new ValidationModel($desc);

    $info = $model->descriptor[0][0]->prepare($data);
    $type = $info->get(0);
    if ($type !== 'object' && $type !== 'obj' && $type !== 'array' && $type !== 'vector')
        throw new Error('model root requires only `obj`, `array` or `vector` specifiers');

    if (!$name)
        return $model;

    if (Shield::$models->has($name))
        throw new Error('duplicate model name: ' . $name);
    if (Shield::$rulesets->has($name))
        throw new Error('ruleset with the same name already exists: ' . $name);

    Shield::$models->set($name, $model);
    return null;
});


/**
 * Validates the input data using the specified models. If any validation error occurs an exception will be thrown.
 * @code (`shield:validate` <input-object> <model-names>...)
 * @example
 * (shield:validate (gateway.body) "Model1")
 */
Expr::register('_shield:validate', function($parts, $data)
{
    $tmpId = '__';
    $inputData = new Map([ $tmpId => Expr::value($parts->get(1), $data) ]);

    $desc = [];
    for ($i = 2; $i < $parts->length(); $i++)
    {
        $type = $parts->get($i)->get(0)->type;
        if ($type === "string" || $type === "identifier") {
            $tmp = Expr::value($parts->get($i));
            $model = Shield::$models->get($tmp, $data);
            if (!$model) {
                $model = Shield::$rulesets->get($tmp, $data);
                if (!$model)
                    throw new Error('undefined ruleset or model: ' . $tmp);
            }
        }
        else {
            $model = Expr::value($parts->get($i), $data);
            if (!($model instanceof ValidationModel))
                throw new Error('value is not a validation model or ruleset');
        }

        $desc[] = $model->descriptor;
    }

    $outputData = new Map();
    $errors = Shield::$errors != null ? Shield::$errors : new Map();

    $_ctx = $data->get('$ctx');
    $data->set('$ctx', new Map());

    try {
        $output = null;
        foreach ($desc as $_desc) {
            Shield::validateValue($_desc, $tmpId, $inputData, $outputData, $data, $errors);
            $newOutput = $outputData->get($tmpId);
            if ($output === null)
                $output = $newOutput;
            else if ($newOutput !== null)
                $output->merge($newOutput, true);
        }

        if ($errors !== Shield::$errors && $errors->length != 0) {
            if ($errors->has($tmpId))
                throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get($tmpId) ]);
            if ($errors->has($tmpId.'.'))
                throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get($tmpId.'.') ]);

            $_errors = new Map();
            $errors->forEach(function($value, $key) use($_errors, $tmpId) {
                if (Text::startsWith($key, $tmpId))
                    $_errors->set(Text::substring($key, Text::length($tmpId)+1), $value);
            });

            throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $_errors ]);
        }
    }
    finally {
        $data->set('$ctx', $_ctx);
    }

    return $output;
});

/**
 * Validates the input data using the specified models and passes the provided context as "$ctx"
 * variable to all validators. Returns the validated object and its context. If any validation
 * error occurs an exception will be thrown.
 * @code (`shield:validate-ctx` <context-object> <input-object> <model-names>...)
 * @example
 * (shield:validate-ctx {} (gateway.body) "Model1")
 * ; {"data":{},"ctx":{}}
 */
Expr::register('_shield:validate-ctx', function($parts, $data)
{
    $tmpId = '__';
    $ctx = Expr::value($parts->get(1), $data);
    $inputData = new Map([ $tmpId => Expr::value($parts->get(2), $data) ]);

    $desc = [];
    for ($i = 3; $i < $parts->length(); $i++)
    {
        $type = $parts->get($i)->get(0)->type;
        if ($type === "string" || $type === "identifier") {
            $tmp = Expr::value($parts->get($i));
            $model = Shield::$models->get($tmp, $data);
            if (!$model) {
                $model = Shield::$rulesets->get($tmp, $data);
                if (!$model)
                    throw new Error('undefined ruleset or model: ' . $tmp);
            }
        }
        else {
            $model = Expr::value($parts->get($i), $data);
            if (!($model instanceof ValidationModel))
                throw new Error('value is not a validation model or ruleset');
        }

        $desc[] = $model->descriptor;
    }

    $outputData = new Map();
    $errors = Shield::$errors != null ? Shield::$errors : new Map();

    $_ctx = $data->get('$ctx');
    $data->set('$ctx', $ctx);

    try {
        $output = null;
        foreach ($desc as $_desc) {
            Shield::validateValue($_desc, $tmpId, $inputData, $outputData, $data, $errors);
            $newOutput = $outputData->get($tmpId);
            if ($output === null)
                $output = $newOutput;
            else if ($newOutput !== null)
                $output->merge($newOutput, true);
        }

        if ($errors !== Shield::$errors && $errors->length != 0) {
            if ($errors->has($tmpId))
                throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get($tmpId) ]);
            if ($errors->has($tmpId.'.'))
                throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get($tmpId.'.') ]);

            $_errors = new Map();
            $errors->forEach(function($value, $key) use($_errors, $tmpId) {
                if (Text::startsWith($key, $tmpId))
                    $_errors->set(Text::substring($key, Text::length($tmpId)+1), $value);
            });

            throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $_errors ]);
        }
    }
    finally {
        $data->set('$ctx', $_ctx);
    }

    return new Map([ 'data' => $output, 'ctx' => $ctx ]);
});

/**
 * Begins quiet validation mode. All validation errors will be accumulated, and should later be retrieved by calling `shield:end`,
 * this is useful to batch multiple validation blocks at once.
 * @code (`shield:begin`)
 */
Expr::register('shield:begin', function($args, $parts, $data) {
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
        throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $err ]);

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
class_exists('Rose\Ext\Shield\NotMatches');
class_exists('Rose\Ext\Shield\Matches');
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
class_exists('Rose\Ext\Shield\Cast');
class_exists('Rose\Ext\Shield\Expect');
class_exists('Rose\Ext\Shield\Block');
class_exists('Rose\Ext\Shield\Use');
class_exists('Rose\Ext\Shield\CaseWhen');
class_exists('Rose\Ext\Shield\CaseElse');
class_exists('Rose\Ext\Shield\CaseEnd');
class_exists('Rose\Ext\Shield\MinItems');
class_exists('Rose\Ext\Shield\MaxItems');
class_exists('Rose\Ext\Shield\UniqueItems');
class_exists('Rose\Ext\Shield\Enum');
class_exists('Rose\Ext\Shield\Fail');
class_exists('Rose\Ext\Shield\Match_');
