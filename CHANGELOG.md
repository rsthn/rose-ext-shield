# v3.0.9 - Aug 01 2026

#### QoL
- Fixed crash when passing a ruleset name to 'shield:validate' or 'shield:validate-ctx'.
- Fixed invalid 'set' and 'block' examples in documentation.
- Documented data descriptors used by 'shield:model'.
- Corrected 'shield:ruleset' signature as the ruleset name is mandatory.
- Clarified throw conditions, value trimming and type coercion in rule documentation.

<br/>

# v3.0.8 - Apr 30 2026

#### Qol
- Updated documentation and dependencies.

<br/>

# v3.0.7 - Jan 22 2026

#### QoL
- Added new rule 'Matches' which acts like 'Pattern' but for an immediate regex.
- Updated 'NotMatches' rule to use immediate regex.
- Empty field name can be used now when validating to run checks without outputing a field.

<br/>

# v3.0.6 - Jan 12 2026

#### QoL
- Fixed bug causing value updated by 'use' rule not to be available in the input for subsequent validators.
- Added 'validate-ctx' to pass and get a context object which is available everywhere as $ctx.

<br/>

# v3.0.5 - Jan 12 2026

#### QoL
- Made semantics more robust and updated all unit tests.

<br/>

# v3.0.4 - Jan 12 2026

- Fixed bug causing certain nested error message to have a wrong key.
- Normalized all calls between validators for consistency.
- Optimized Data validator.
- Removed all deprecated code and validaton rules.
- Added new `use` rule to validate using a predefined ruleset or model.
- Added unit tests for all validation rules and error messages.

<br/>
