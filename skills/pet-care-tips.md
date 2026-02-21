# Pet Care Tips

Community-submitted tips with moderation.

## Model
`pet_care_tips` fields:
- author (`user_id`)
- `title`, `body`, `body_html`
- optional `species`
- `status`: `pending|approved|rejected`
- approval fields
- `votes_count`

Votes table:
- `tip_votes` with `user_id`, `tip_id`, `type`

## Rules
- Guests read approved tips.
- Auth users submit tips.
- Auth users vote helpful/not helpful.
- Admin approves/rejects.
- Submitter notified on approval.

## Placement
- Main tips page `/pets/tips` (species filter)
- Pet sidebar shows top relevant tips
