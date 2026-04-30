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

# Validation Rules

Rules are listed inside a `(rules ...)` block, a `(shield:ruleset ...)` definition or directly within field descriptors. They run
top-to-bottom and each rule receives the current value through the `$` variable, the input object through `$in`, the output object
through `$out` and the context object (when using `shield:validate-ctx`) through `$ctx`.

When a rule fails, an error message is reported using the rule's identifier (e.g. `@messages.required:true`, `@messages.min-length:3`).
You can override that identifier by appending `:@your-key` to the rule name (e.g. `check:@invalid`, `fail:@error-message`,
`enum:@invalid-value`). The `@`-prefixed identifier is looked up directly under `@messages`, without the rule-name prefix.

## Presence and Required

### (`required` \<true|false|"true|null"|"true|empty">)
Validates that the trimmed value is not empty. Behavior depends on the argument:
- `true` — value must be a non-empty string, otherwise fails with `required:true`.
- `false` — if value is empty the field is dropped from the output (no error).
- `"true|null"` — if value is empty, set it to `null` and stop subsequent rules.
- `"true|empty"` — if value is empty, set it to `""` and stop subsequent rules.

```lisp
(shield:ruleset "name" required true max-length 64)
```

### (`presence` \<true|false|"true|null"|"true|empty">)
Same semantics as `required`, but checks whether the field key is present in the input rather than whether the value is non-empty.
Useful to allow empty strings while still requiring the key to exist.

```lisp
(shield:model (object name (rules presence true)))
```

## Length and Size

### (`min-length` \<n>)
Ensures the string length is `>= n`. Fails with `min-length:n`. Throws if the value is not a string.

### (`max-length` \<n>)
Ensures the string length is `<= n`. Fails with `max-length:n`.

### (`length` \<n>)
Ensures the string length is exactly `n`. Fails with `length:n`.

### (`min-value` \<n>)
Ensures the numeric value is `>= n`. Throws if the value is not numeric.

### (`max-value` \<n>)
Ensures the numeric value is `<= n`.

### (`min-items` \<n>)
Ensures an array or object contains at least `n` items.

### (`max-items` \<n>)
Ensures an array or object contains at most `n` items.

### (`unique-items` \<bool>)
When set to `true`, ensures all items in the array are unique. Throws if value is not an array.

## Pattern Matching

### (`pattern` \<regex-name|"/regex/">)
Validates the value against a named regex (declared in `strings.regex.*`) or an inline regex literal (must start with `/` or `|`).
Fails with `pattern:<name>` when using a named regex, or `pattern` for inline regexes.

```lisp
(shield:ruleset "email"
  required true
  pattern "email"
)
```

### (`matches` \<"/regex/">)
Like `pattern`, but always treats the argument as an immediate regex string (no named-regex lookup). Fails with `matches`.

### (`not-matches` \<"/regex/">)
Inverse of `matches`. Passes when the value does NOT match the supplied regex. Fails with `not-matches`.

### (`match` \<regex-name|"/regex/">)
Replaces the value with the first regex match. The new value is a map containing capture groups (numeric and named). Fails when no
match is found.

```lisp
(shield:validate "today is Sunday 11th"
    (shield:ruleset __inline__
        match `/(?<weekday>\w+)\s+(?<day>\d+)/`
    ))
; { "0": "Sunday 11", "weekday": "Sunday", "1": "Sunday", "day": "11", "2": "11" }
```

### (`extract` \<regex-name|"/regex/">)
Replaces the value with the first capture from the regex (group 0 by default). Always succeeds.

```lisp
(shield:validate "this is 2026 year" (shield:ruleset __inline__ extract "number"))
; "2026"
```

## Membership

### (`enum` \<string|array|object>)
Validates that the value belongs to the provided list:
- A comma-separated string: `enum "red,blue,green"`.
- An array: `enum ["red" "blue" "green"]`.
- A map: `enum {"red" 1 "blue" 2 "green" 3}` — when the value matches a key, the value is replaced with the mapped value.

Fails with `enum`.

## Setting and Defaulting Values

### (`set` \<value>)
Unconditionally sets the current value to the given expression. The expression can reference `$`, `$in`, `$out` and `$ctx`.

```lisp
set (upper ($))
set $out.name "buffy"
```

### (`default` \<value>)
If the field is missing from the input or the trimmed value is an empty string, replaces the value with the given default. Validation
continues with the new value.

### (`default-stop` \<value>)
Same as `default` but immediately stops further rules in the current ruleset once the default is applied.

## Type Checks and Conversion

### (`expect` \<type>)
Validates the value type without modifying it. Allowed types: `bool`/`boolean`, `int`/`integer`, `float`, `str`/`string`,
`array`, `obj`/`object`, `null`. Fails with `expect:<type>`.

### (`cast` \<type>)
Coerces the value to the given type. Allowed types: `bool`/`boolean`, `int`/`integer`, `float`, `str`/`string`, `array`,
`obj`/`object`, `null`. Non-array values cast to `array` are wrapped as `[value]`; non-object values cast to `obj` become
`{"value": ...}`; casting to `str` uses JSON for arrays and objects.

## Conditional Logic

### (`check` \<bool>)
Asserts that the supplied expression evaluates to `true`. Useful for ad-hoc predicates. Fails with `check`.

```lisp
check (in? ["red" "blue"] ($))
check:@invalid (> ($) 0)
```

### (`fail` \<bool>)
Inverse of `check` — fails when the expression evaluates to `true`. Use `fail:@my-msg true` to abort with a custom error.

### (`ignore` \<bool>)
When the argument is `true`, drops the current field from the output and stops further rules. Useful inside `(array ...)` to skip
elements based on a predicate (`ignore (in? ["red"] ($))`).

### (`stop` \<bool>)
When the argument is `true`, stops further rules in the current ruleset, keeping the current value as-is.

### (`requires` \<"field"|"field|error"|"field|stop">)
Ensures another field has already been validated and emitted to the output. The action after `|`:
- (omitted) — if the field is missing, drop the current field silently (`IgnoreField`).
- `error` — if missing, fails with `requires:<field>`.
- `stop` — if missing, stop further rules but keep the current value.

```lisp
(shield:model (object
    ref? (int)
    name (rules requires "ref|error" required true)
))
```

### (`case-when` \<cond>) / (`case-else`) / (`case-end`)
Conditional execution of subsequent rules until the next `case-when`, `case-else` or `case-end`. The first matching `case-when`
runs its block; remaining branches are skipped until `case-end`.

```lisp
(shield:ruleset __inline__
    case-when (in? ($) "hello")  set "found_hello"
    case-when (in? ($) "bye")    set "found_bye"
    case-else                    set "none"
    case-end
)
```

> Note: nested `case-when` blocks are not supported.

## Composition

### (`use` \<ruleset-or-model-name>)
Runs a previously registered ruleset or model against the current value, threading errors back into the current path. Allows
sharing rule pipelines.

```lisp
(shield:ruleset "email" required true pattern "email")
(shield:model (object email (rules use "email")))
```

### (`block` \<expressions...>)
Executes one or more expressions purely for their side effects, without affecting the current value. Useful to write computed fields
to `$out` or to perform extra logic.

```lisp
block (
    set $out.full-name (concat $out.first " " $out.last)
)
```

## File Uploads

### (`file-type` \<comma-separated-extensions>)
Validates that an uploaded file (a Rose file map) has a name with one of the given extensions. Fails when the upload has an error
or the extension does not match.

```lisp
(rules file-type "jpg,jpeg,png")
```

### (`max-file-size` \<bytes>)
Validates that an uploaded file's size is `<= bytes`. Fails when the upload has an error or exceeds the size.

# Custom Error Messages

Every rule that emits an error uses an identifier of the form `<rule-name>` or `<rule-name>:<value>`. To override the message id,
append `:@key` to the rule name; the resulting message will be looked up at `@messages.<key>` directly.

```lisp
check:@invalid    (in? ["red" "blue"] ($))
fail:@unauthorized true
enum:@invalid-value [1 2 3]
```
