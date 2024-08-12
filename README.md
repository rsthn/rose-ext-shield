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

### (`shield:field` \<output-name> [rules...])
Returns a field descriptor.
```lisp
(shield:field username
     required true
     min-length 3
     max-length 20
)
; (descriptor)
```

### (`shield:type` \<type-name> \<rules...>)
Registers a type validation descriptor to be used by name in the `type` rules.
```lisp
(shield:type "email"
  max-length 256
  pattern email
)
```

### (`shield:begin`)
Begins quiet validation mode. All validation errors will be accumulated, and should later be retrieved by calling `shield:end`,
this is useful to batch multiple validation blocks at once.

### (`shield:end` [automatic=true])
Ends quiet validation mode, if there are any errors and `automatic` is set to `true` (default), then Wind::R_VALIDATION_ERROR will
be thrown, otherwise, the error map will just be returned.

### (`shield:validate` [output-var] \<field-descriptors...>)
Validates the fields in the gateway request. Any error will be reported, and the validated object will be available in the
global context or in the output variable (if provided) when validation succeeds.
<br/>NOTE: This is a legacy function, use the replacement `shield:validate-data` when possible.
```lisp
(shield:validate form
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

### (`shield:model` \<data-descriptors...>)
Creates and returns a new validation model to be re-used later with `shield:validate-data`.
```lisp
(shield:model
   (object
      "username" (string)
      "password" (string)
      "email" (rules
          required true
          pattern email
       )
   )
)
; model_45ef12
```

### (`shield:validate-data` \<output-var> \<input-object> \<model|data-descriptors...>)
Validates the fields in the input data using the specified data rules. If any validation error occurs an
exception will be thrown. If the data is successfully validated it will be available in the output variable.
