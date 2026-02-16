# Keycloak Admin Auth

Replaces `cp-admin`/`cp-auth` local authentication with a Keycloak OpenID Connect flow.

## What it does

- Redirects unauthenticated admin/auth requests to Keycloak login.
- Handles OIDC callback on `/<cp-auth>/login`.
- Creates or updates Castopod users from Keycloak identity.
- Maps Keycloak roles to Castopod instance roles (`superadmin`, `manager`, `podcaster`).
- Redirects logout through Keycloak end-session endpoint.

## Required settings

- `issuer_url`
- `client_id`
- `client_secret`

## Notes

- Keep at least one local superadmin until Keycloak settings are verified.
- If `allowed_roles` is empty, any authenticated Keycloak user may sign in.
