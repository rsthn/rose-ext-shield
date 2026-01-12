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

### (`shield:ruleset` [ruleset-name] \<rules...>)
Registers a set of validation rules with the given name. This can later be used by name
from the `use \<ruleset-name>` rule.
```lisp
(shield:ruleset "email"
  max-length 256
  pattern email
)
```

### (`shield:model` [name] \<data-descriptor>)
Registers a validation model with the given name to be used later with `shield:validate`.
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

### (`shield:validate` \<input-object> \<model-names>...)
Validates the input data using the specified models. If any validation error occurs an exception will be thrown.
```lisp
(shield:validate (gateway.body) "Model1")
```

### (`shield:validate-ctx` \<context-object> \<input-object> \<model-names>...)
Validates the input data using the specified models and passes the provided context as "$ctx"
variable to all validators. Returns the validated object and its context. If any validation
error occurs an exception will be thrown.
```lisp
(shield:validate-ctx {} (gateway.body) "Model1")
; {"data":{},"ctx":{}}
```

### (`shield:begin`)
Begins quiet validation mode. All validation errors will be accumulated, and should later be retrieved by calling `shield:end`,
this is useful to batch multiple validation blocks at once.

### (`shield:end` [automatic=true])
Ends quiet validation mode, if there are any errors and `automatic` is set to `true` (default), then Wind::R_VALIDATION_ERROR will
be thrown, otherwise, the error map will just be returned.
