# Post Types

Types are auto-detected in `PostService`:
- no media => `text`
- photos => `photo`
- video => `video`

Type is immutable after creation.

## Composer Pet Tags

Use the shared Livewire `posts.composer` pet tagging controls instead of raw multi-selects. The normal composer shows a toolbar "Tag a pet" dropdown listing owned/postable pets by name with avatar and species badge, and selected pets render as removable chips below the editor. Pet-profile composer contexts must pass a fixed pet through `contextType=pet-profile` and `contextId` or `fixedPetId` / `lockPetTags`; that hides the tagging dropdown and keeps the pre-tagged pet immutable so the post remains on that pet profile.
