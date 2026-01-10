# Shield Validation Extension

Shield provides a set of functions to validate data in a very robust way.

# Installation

```sh
composer require rsthn/rose-ext-shield
```

# Functions

### (`shield:method-required` \<method...>)
Ensures the request was made using the specified method(s) or fails with 405/@messages.method_not_allowed.

### (`shield:body-required` [false|true|content-type...])
Ensures the request's content-type is one of the specified types. Fails with 422/@messages.request_body_missing if there is no
request body, or with 422/@messages.invalid_content_type if the content-type is not valid. If no content type is provided then
it is assumed to be `application/json`. Use value `true` to allow any content type.

### (`shield:body-min-size` \<min-size>)
Ensures the request's body is at least the specified number of bytes. Fails with 422/@messages.request_body_too_small if not.

### (`shield:body-max-size` \<max-size>)
Ensures the request's body does not exceed the specified number of bytes. Fails with 422/@messages.request_body_too_large when so.

### (`shield:ruleset` \<ruleset-name> \<rules...>)
Registers a set of validation rules with the given name. This can later be used in
`shield:validate` using the `use \<ruleset-name>` rule.
```lisp
(shield:ruleset "email"
  max-length 256
  pattern email
)
```

### (`shield:model` \<name> \<data-descriptor>)
Registers a new validation model with the given name to be used later with `shield:validate`.
```lisp
(shield:model "Model1"
   (object
      "username" (string)
      "password" (string)
      "email" (rules
          required true
          pattern email
          use "verify-unique"
       )
   )
)
```

### (`shield:validate` [\<output-var>] \<input-object> \<model-names>...)
Validates the input data using the specified models. If any validation error occurs an exception will be thrown. If
the data is successfully validated it will be returned or placed in the specified output variable.
```lisp
(shield:validate (gateway.body) "Model1")
```

### (`shield:validate-data` [\<output-var>] \<input-object> \<data-descriptor>)
Validates the input data using the specified rules. If any validation error occurs an exception will be thrown. If
the data is successfully validated it will be returned or placed in the specified output variable.
<br/>
<br/>Note: This function will be deprecated in the future, use `shield:validate` with the related functions 
<br/>`shield:model` and `shield:ruleset` instead.
```lisp
(shield:validate-data "form" (gateway.body)
   (object
      "username" (string)
      "password" (string)
      "email" (rules
          required true
          pattern email
       )
   )
)
```

### (`shield:begin`) \<small>[deprecated]\</small>
Begins quiet validation mode. All validation errors will be accumulated, and should later be retrieved by calling `shield:end`,
this is useful to batch multiple validation blocks at once.

### (`shield:validate-fields` [output-var] \<field-descriptors...>)
Validates the fields in the gateway request. Any error will be reported, and the validated object will be available in the
global context or in the output variable (if provided) when validation succeeds.
<br/>
<br/>NOTE: This function is provided for legacy support. If your previous shield version was 2.x please replace all calls
<br/>to `shield:validate` with `shield:validate-fields` as this function has the same behavior.
```lisp
(shield:validate-fields form
    (shield:field name
        required true
        max-length 8
    )
    (shield:field email
        required true
        pattern email
    )
)
```
