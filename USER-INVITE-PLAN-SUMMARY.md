# Invitation Feature Summary

## Purpose
Juniper is invitation-only. This feature lets **sysops** invite people to create new accounts, and lets **account admins** invite additional users into their own account. Sysops acting as an admin (via impersonation) can also send admin-level invites, so support doesn't have to walk users through it.

## Two invite types
1. **Account invite** (sysop only) — creates a brand-new account and its first user. Recipient picks a password and the account is provisioned on acceptance.
2. **User invite** (admin, or sysop-as-admin) — adds a user to an existing account, optionally pre-assigned to a security group (e.g. Admin).

## How it works
- A new `invitations` table tracks pending invites: email, account_id (null for account invites), role/security group, inviter, expiration, accepted-at.
- Sending an invite emails a **signed, expiring link** (Laravel's built-in signed URLs — no hand-rolled tokens).
- Clicking the link lands on a public acceptance page where the invitee sets their name and password. On submit, a user (and account, if applicable) is created in one transaction, reusing the existing Fortify `CreateNewUser` plumbing.
- Invites are single-use and expire (say, 7 days). Inviters can **resend** or **revoke** from a management screen.

## Attribution & audit
- `invited_by_user_id` always records the **real** user — even when a sysop is impersonating — so the audit trail names a human.
- The invited account is whatever account the inviter is currently acting in (their own, or the impersonated one).
- Every invite action (`sent`, `accepted`, `revoked`, `expired`) writes an activity log event. The impersonation flag is stamped automatically by `ActivityLogger`, so support actions taken during impersonation are still distinguishable from an admin's own actions.

## UI surfaces
- **Sysop**: invite-new-account form + pending-invites list under `/sysops/`.
- **Admin**: invite-user form + pending-invites list under the existing admin user management screen.
- **Public**: acceptance page at a signed URL, no login required.

## Open questions to decide before building
- **Email collision**: what if the invitee already has a user on another account? (Current model is one-user-one-account.) Options: block, or allow multi-account users — a bigger architectural call.
- **Expiration window**: 7 days? 14? Configurable per invite?
- **Revocation semantics**: hard-delete the row, or soft-mark as revoked for audit?
- **Resend behavior**: new signed link (invalidates the old one) or reuse?
- **Rate limiting**: cap invites per admin per day to prevent abuse.

## Why build vs. install a package
Juniper already has all the primitives — Fortify, account scoping, security groups, activity logging, signed URLs. A custom implementation is small (~a few hundred lines) and fits the existing patterns better than pulling in a third-party invitations package.
