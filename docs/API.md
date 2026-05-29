# Convoca Media Suite — REST API Reference

## Poster Endpoints

### POST /convoca/v1/media/poster/render

Generate a poster for an activity.

**Capability**: `conv_manage_media`

**Request Body:**
```json
{
  "actividad_id": 100,
  "template": "nature-classic",
  "format": "square"
}
```

**Response 200:**
```json
{
  "files": { "square": "/path/to/poster.png" },
  "url": "https://.../poster-100-nature-classic-square.png"
}
```

### POST /convoca/v1/media/poster/regenerate

Regenerate (force) a poster, clearing caches.

**Capability**: `conv_manage_media`

**Request Body:**
```json
{ "actividad_id": 100, "template": "nature-classic" }
```

**Response 200:** Same as render.

## Template Endpoints

### GET /convoca/v1/media/templates

List all available templates.

**Capability**: `conv_manage_media`

**Response 200:** Array of template objects with id, name, slug, description, config.

### GET /convoca/v1/media/templates/{id}

Get a single template.

**Capability**: `conv_manage_media`

**Response 200:** Single template object.

## Blog Endpoints

### POST /convoca/v1/media/blog/create

Create or update a blog post for an activity.

**Capability**: `conv_manage_media`

**Request Body:**
```json
{ "actividad_id": 100, "status": "draft" }
```

**Response 200:**
```json
{ "post_id": 123, "edit_url": "...", "status": "draft" }
```

## Social OAuth Endpoints

### GET /convoca/v1/social/auth/meta

Redirect to Facebook OAuth dialog.

**Capability**: `conv_manage_social`

**Query Params:** None (state auto-generated)

### GET /convoca/v1/social/callback/meta

OAuth callback from Meta. Exchanges code for long-lived token, discovers Pages + Instagram.

**Permission**: Public (OAuth redirect)

### GET /convoca/v1/social/auth/google

Redirect to Google OAuth consent screen.

**Capability**: `conv_manage_social`

### GET /convoca/v1/social/callback/google

OAuth callback from Google. Exchanges code for access + refresh tokens.

**Permission**: Public (OAuth redirect)

## Account Management

### GET /convoca/v1/social/accounts

List connected social accounts.

**Capability**: `conv_manage_social`

**Response 200:** Array of accounts with network, account_name, token_expires_at, is_active.

### DELETE /convoca/v1/social/accounts/{id}

Disconnect a social account.

**Capability**: `conv_manage_social`

**Response 200:** `{ "success": true }`

## Error Response Format

All endpoints return standard WP REST errors:

```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": { "status": 401 }
}
```
