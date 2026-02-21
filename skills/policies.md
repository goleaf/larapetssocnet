# Policies

- Policy methods should contain authorization-only logic.
- Controllers should call `$this->authorize()` for protected actions.
- Keep policy decisions deterministic and side-effect free.
