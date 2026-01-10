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
     * Initializes the registration maps.
     */
    public static function init()
    {
        self::$rulesets = new Map();
        self::$models = new Map();
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

            if ($key === 'input') {
                $input_name = Expr::value($value, $data);
                return;
            }

            if ($key === 'output') {
                $output_name = Expr::value($value, $data);
                return;
            }

            $key = Text::split(':', $key);
            $className = self::$rules->get($key->get(0));
            if (!$className) {
                throw new Error ('Undefined validation rule: ' . $key->get(0));
                return;
            }

            $output_rules[] = new $className ($value, $key->{1});
        });

        // A validation descriptor is an array having three fields as follows.
        return [$input_name, $output_name, $output_rules];
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
    public static function validateValue ($desc, $input_name, $output_name, $input, $output, \Rose\Map $context, \Rose\Map $errors, $input_override = null, $output_override = null)
    {
        if (\Rose\isString($desc)) {
            $name = $desc;
            $desc = self::$rulesets->get($name);
            if (!$desc) {
                $desc = self::$models->get($name);
                if (!$desc)
                    throw new Error('undefined ruleset: '.$name);
                $desc = $desc->descriptor;
            }
        }

        $_out = $context->get('$out');
        $_in = $context->get('$in');
        $_val = $context->get('$');
        $context->set('$out', $output_override ?? $output);
        $context->set('$in', $input_override ?? $input);

        $input_name = $input_name ?? $desc[0];
        $output_name = $output_name ?? $desc[1];
        $report_as = $input_name;

        $value = $input->get($input_name);
        $output->set($output_name, $value);
        $remove = false;

        $allowedMap = null;
        $unallowedMap = null;

        foreach ($desc[2] as $rule)
        {
            if ($unallowedMap === true) {
                if (!$allowedMap->has($rule->getName()))
                    continue;
                $allowedMap = $unallowedMap = null;
            }

            $context->set('$', $output->__nativeArray[$output_name]);
            $_errors = new Map();

            try {
                if ($allowedMap === true && $unallowedMap->has($rule->getName())) {
                    $rule->failed($input, $output, $context, $_errors);
                    $allowedMap = $unallowedMap = null;
                    continue;
                }

                if ($rule->validate($input_name, $output->__nativeArray[$output_name], $input, $output, $context, $_errors)) {
                    if ($rule->getReportedName() !== null)
                        $report_as = $rule->getReportedName();
                    continue;
                }
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
                $remove = true;
                break;
            }
            catch (\Exception $e) {
                // Empty identifier is used for rules that forward errors.
                $tmp = $rule->getIdentifier();
                if ($tmp === '') {
                    $msg = $e->getMessage();
                    // Non-empty error message will still be added to the report.
                    if ($msg)
                        $errors->set($report_as, $msg);
                    $errors->merge($_errors, true);
                }
                else
                    $errors->set($report_as, $tmp ? '('.$tmp.') '.$e->getMessage() : $e->getMessage());
                $remove = true;
                break;
            }

            $errors->set($report_as, Shield::getMessage($rule->getIdentifier()));
            $remove = true;
            break;
        }

        $context->set('$out', $_out);
        $context->set('$in', $_in);
        $context->set('$', $_val);

        $value = $output->__nativeArray[$output_name];
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
    if ($content_type === null || Gateway::getInstance()->input->size == 0)
        throw new WindError('RequestBodyMissing', [ 'response' => 422, 'error' => Strings::get('@messages.request_body_missing') ]);

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
    if (Gateway::getInstance()->input->contentType === null)
        throw new WindError('RequestBodyMissing', [ 'response' => 422, 'error' => Strings::get('@messages.request_body_missing') ]);

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
    if (Gateway::getInstance()->input->contentType === null)
        throw new WindError('RequestBodyMissing', [ 'response' => 422, 'error' => Strings::get('@messages.request_body_missing') ]);

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
 * Returns a field descriptor.
 * @deprecated code (`shield:field` <output-name> [rules...])
 * @example
 * (shield:field username
 *      required true
 *      min-length 3
 *      max-length 20
 * )
 * ; (descriptor)
 */
Expr::register('_shield:field', function($parts, $data)
{
    $name = Expr::value($parts->get(1), $data);

    if (!\Rose\isString($name))
        throw new ArgumentError ('shield:field expects \'name\' parameter to be a string.');

    return Shield::getDescriptor($name, Shield::parseDescriptor($parts, $data, 2), $data);
});


/**
 * Registers a type validation descriptor to be used by name in the `type` rules.
 * @deprecated code (`shield:type` <type-name> <rules...>)
 * @example
 * (shield:type "email"
 *   max-length 256
 *   pattern email
 * )
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
        if (substr($key, -1) === ':') // TODO: Deprecate using ':' to separate rule name and value
            $key = substr($key, 0, strlen($key)-1);

        $tmp = $parts->get($i+1);

        if ($tmp->length == 1 && ($tmp->get(0)->type !== 'template' && $tmp->get(0)->type !== 'string'))
            $tmp = Expr::value($tmp, $data);

        $rules->push(new Arry([$key, $tmp], false));
    }

    Shield::$rulesets->set($name, Shield::getDescriptor($name, $rules, $data));
    return null;
});

/**
 * Registers a set of validation rules with the given name. This can later be used by name
 * from the `use <ruleset-name>` rule.
 * @code (`shield:ruleset` <ruleset-name> <rules...>)
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
        throw new ArgumentError('shield:ruleset expects \'ruleset-name\' parameter to be a string.');

    $rules = new Arry();
    for ($i = 2; $i < $parts->length(); $i += 2)
    {
        $key = Expr::value($parts->get($i), $data);
        if (substr($key, -1) === ':') // TODO: Deprecate using ':' to separate rule name and value
            $key = substr($key, 0, strlen($key)-1);

        $tmp = $parts->get($i+1);
        if ($tmp->length == 1 && ($tmp->get(0)->type !== 'template' && $tmp->get(0)->type !== 'string'))
            $tmp = Expr::value($tmp, $data);

        $rules->push(new Arry([$key, $tmp], false));
    }

    Shield::$rulesets->set($name, Shield::getDescriptor($name, $rules, $data));
    return null;
});

/**
 * Registers a validation model with the given name to be used later with `shield:validate`.
 * @code (`shield:model` <name> <data-descriptor>)
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
    $desc = Shield::getDescriptor('', $desc, $data);

    $model = new ValidationModel($desc);
    if (!$name) return $model;

    Shield::$models->set($name, $model);
    return null;
});


/**
 * Validates the input data using the specified models. If any validation error occurs an exception will be thrown.
 * If the data is successfully validated it will be returned or placed in the specified output variable.
 * @code (`shield:validate` [<output-var>] <input-object> <model-names>...)
 * @example
 * (shield:validate (gateway.body) "Model1")
 */
Expr::register('_shield:validate', function($parts, $data)
{
    $name = null;
    $start = 1;
    $type = $parts->get(1)->get(0)->type;
    if ($type === "string" || $type === "identifier") {
        $name = Expr::value($parts->get(1), $data);
        $start = 2;
    }

    $inputData = new Map([ '' => Expr::value($parts->get($start), $data) ]);

    $desc = [];
    for ($i = $start+1; $i < $parts->length(); $i++)
    {
        $type = $parts->get($i)->get(0)->type;
        if ($type === "string" || $type === "identifier")
            $model = Shield::$models->get(Expr::value($parts->get($i), $data));
        else
            $model = Expr::value($parts->get($i), $data);

        if (!($model instanceof ValidationModel))
            throw new ArgumentError ('shield:validate expects a ValidationModel object');

        $desc[] = $model->descriptor;
    }

    $outputData = new Map();
    $errors = Shield::$errors != null ? Shield::$errors : new Map();
    $data->set('formData', $outputData);

    $output = null;
    foreach ($desc as $_desc) {
        Shield::validateValue($_desc, null, null, $inputData, $outputData, $data, $errors);
        $newOutput = $outputData->get('');
        if ($output === null)
            $output = $newOutput;
        else if ($newOutput !== null)
            $output->merge($newOutput, true);
    }

    if ($errors !== Shield::$errors && $errors->length != 0) {
        if ($errors->has(''))
            throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get('') ]);
        else
            throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);
    }

    $data->remove('formData');

    if ($name === null)
        return $output;

    $data->set($name, $output);
    return null;
});

/**
 * Validates the input data using the specified rules. If any validation error occurs an exception will be thrown. If
 * the data is successfully validated it will be returned or placed in the specified output variable.
 *
 * NOTE: This function has been deprecated and will be removed in the future, use `shield:validate` with the related
 * functions `shield:model` and `shield:ruleset` instead.
 * @code (`shield:validate-data` [<output-var>] <input-object> <data-descriptor>) _(deprecated)_
 * @example
 * (shield:validate-data "form" (gateway.body)
 *    (object
 *       "username" (string)
 *       "password" (string)
 *       "email" (rules
 *           required true
 *           pattern email
 *        )
 *    )
 * )
 */
Expr::register('_shield:validate-data', function($parts, $data)
{
    $name = null;
    $start = 1;
    $type = $parts->get(1)->get(0)->type;
    if ($type === "string" || $type === "identifier") {
        $name = Expr::value($parts->get(1), $data);
        $start = 2;
    }

    $inputData = new Map([ '' => Expr::value($parts->get($start), $data) ]);

    if (Expr::isIdentifier($parts, $start+1, 'using')) {
        $desc = [];
        $multi = true;
        for ($i = $start+2; $i < $parts->length(); $i++)
        {
            $type = $parts->get($i)->get(0)->type;
            if ($type === "string" || $type === "identifier")
                $model = Shield::$models->get(Expr::value($parts->get($i), $data));
            else
                $model = Expr::value($parts->get($i), $data);

            if (!($model instanceof ValidationModel))
                throw new ArgumentError ('shield:validate-data expects a ValidationModel object');

            $desc[] = $model->descriptor;
        }
    }
    else {
        $multi = false;
        $parts->set($start, 'data');
        $desc = Shield::getDescriptor('', Shield::parseDescriptor($parts, $data, $start), $data);
    }

    $outputData = new Map();
    $errors = Shield::$errors != null ? Shield::$errors : new Map();
    $data->set('formData', $outputData);

    if ($multi === true) {
        $output = null;
        foreach ($desc as $_desc) {
            Shield::validateValue($_desc, null, null, $inputData, $outputData, $data, $errors);
            $newOutput = $outputData->get('');
            if ($output === null)
                $output = $newOutput;
            else if ($newOutput !== null)
                $output->merge($newOutput, true);
        }
    }
    else {
        Shield::validateValue($desc, null, null, $inputData, $outputData, $data, $errors);
        $output = $outputData->get('');
    }

    if ($errors !== Shield::$errors && $errors->length != 0) {
        if ($errors->has(''))
            throw new WindError('BadRequest', [ 'response' => Wind::R_BAD_REQUEST, 'error' => $errors->get('') ]);
        else
            throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);
    }

    $data->remove('formData');

    if ($name === null)
        return $output;

    $data->set($name, $output);
    return null;
});

/**
 * Begins quiet validation mode. All validation errors will be accumulated, and should later be retrieved by calling `shield:end`,
 * this is useful to batch multiple validation blocks at once.
 * @code (`shield:begin`) _(deprecated)_
 */
Expr::register('shield:begin', function($args, $parts, $data) {
    Shield::$errors = new Map();
});

/**
 * Ends quiet validation mode, if there are any errors and `automatic` is set to `true` (default), then Wind::R_VALIDATION_ERROR will
 * be thrown, otherwise, the error map will just be returned.
 * @code (`shield:end` [automatic=true]) _(deprecated)_
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

/**
 * Validates the fields in the gateway request. Any error will be reported, and the validated object will be available in the
 * global context or in the output variable (if provided) when validation succeeds.
 *
 * NOTE: This function is provided for legacy support. If your previous shield version was 2.x please replace all calls
 * to `shield:validate` with `shield:validate-fields` as this function has the same behavior.
 * @code (`shield:validate-fields` [output-var] <field-descriptors...>) _(deprecated)_
 * @example
 * (shield:validate-fields form
 *     (shield:field name
 *         required true
 *         max-length 8
 *     )
 *     (shield:field email
 *         required true
 *         pattern email
 *     )
 * )
 */
Expr::register('shield:validate-fields', function($args, $parts, $data)
{
    $inputData = Gateway::getInstance()->request;
    $outputData = $data;
    $errors = Shield::$errors != null ? Shield::$errors : new Map();

    $i = $args->get(1);
    if (\Rose\isString($i) && Shield::$rulesets->get($i) === null) {
        if ($i !== 'global') {
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
        Shield::validateValue($args->get($i), null, null, $inputData, $outputData, $data, $errors);

    if ($errors !== Shield::$errors && $errors->length != 0)
        throw new WindError('ValidationError', [ 'response' => Wind::R_VALIDATION_ERROR, 'fields' => $errors ]);

    $data->remove('formData');
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
class_exists('Rose\Ext\Shield\NotPattern');
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
class_exists('Rose\Ext\Shield\Expected');
class_exists('Rose\Ext\Shield\Block');
class_exists('Rose\Ext\Shield\Type');
class_exists('Rose\Ext\Shield\CaseWhen');
class_exists('Rose\Ext\Shield\CaseElse');
class_exists('Rose\Ext\Shield\CaseEnd');
class_exists('Rose\Ext\Shield\MinItems');
class_exists('Rose\Ext\Shield\MaxItems');
class_exists('Rose\Ext\Shield\UniqueItems');
class_exists('Rose\Ext\Shield\Enum');
class_exists('Rose\Ext\Shield\ReportAs');
class_exists('Rose\Ext\Shield\Fail');
class_exists('Rose\Ext\Shield\Match_');
