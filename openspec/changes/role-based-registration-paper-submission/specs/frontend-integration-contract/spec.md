# Delta for Frontend Integration Contract

## MODIFIED Requirements

### Requirement: Field Name Attributes

Each form input MUST use the specified `name` attribute. The backend reads `name` from the POST body; HTML `id` attributes alone are insufficient. The `rol` field determines which field set is required.

(Previously: all fields were required unconditionally. Now `rol` and `titulo_ponencia` are added; `eje_tematico` and `archivo` are conditional on `rol=Expositor`.)

#### Scenario: Required field mapping

- GIVEN the form inputs
- WHEN submitted
- THEN each field MUST use the correct `name` attribute:

| HTML id | POST name | Type | Required |
|---------|-----------|------|----------|
| `form-role` | `rol` | text (hidden or select) | yes |
| `form-author` | `nombre` | text | yes |
| `form-institution` | `institucion` | text | yes |
| `form-email` | `email` | email | yes |
| `form-dni` | `dni` | text | yes |
| `form-title` | `titulo_ponencia` | text | only if `rol=Expositor` |
| `form-topic` | `eje_tematico` | text | only if `rol=Expositor` |
| `form-file` | `archivo` | file | only if `rol=Expositor` |

#### Scenario: Asistente form omits paper fields
- GIVEN `rol=Asistente`
- WHEN the form is submitted
- THEN `titulo_ponencia`, `eje_tematico`, and `archivo` MUST NOT be present in the POST body

## ADDED Requirements

### Requirement: Role Field Contract

The form MUST include a `rol` field whose value is either `Expositor` or `Asistente`. The frontend MUST conditionally render and require `titulo_ponencia`, `eje_tematico`, and `archivo` only when `rol=Expositor`. The seven `eje_tematico` values remain as specified in the existing Ejes Temáticos Values requirement. This contract is an external prerequisite — the frontend form is out of scope for this change; the backend expects this contract to be satisfied by the form.

#### Scenario: rol field present
- GIVEN the registration form
- WHEN rendered
- THEN the `rol` field MUST be included in the POST body
- AND its value MUST be either `Expositor` or `Asistente`

#### Scenario: Expositor shows all paper fields
- GIVEN `rol=Expositor` is selected
- WHEN the form renders
- THEN `titulo_ponencia`, `eje_tematico`, and `archivo` fields MUST be visible
- AND each MUST be required before submission

#### Scenario: Asistente hides paper fields
- GIVEN `rol=Asistente` is selected
- WHEN the form renders
- THEN `titulo_ponencia`, `eje_tematico`, and `archivo` fields MUST NOT be visible
- AND they MUST NOT be included in the POST body

#### Scenario: Role change clears conditional fields
- GIVEN a user switches from Expositor to Asistente after filling paper fields
- WHEN the role changes
- THEN `titulo_ponencia`, `eje_tematico`, and `archivo` values MUST be cleared
- AND the fields MUST NOT be sent in the POST body
