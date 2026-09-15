# FAQ — LOA Cert Platform (Backend)

## Troubleshooting

### "My Certificates" page shows "No certificates have been issued to you yet"

**Symptom:** The participant page `/my/certificates` returns an empty list despite the
certificate existing in the `certificates` table with a matching `recipient_email`.

**Root cause:** The authenticated user's JWT group lacks the required permission for
`GET /api/v1/me/certificates`. The middleware rejects the request before it reaches
`MeController`, and the frontend silently treats the error as an empty result.

**Resolution:** Ensure the user's group (e.g., `cert-user`) has the
`cert.certificate.view-own` permission (or equivalent) granted in the Auth Platform.

**Verification checklist:**

1. `recipient_email` on the `certificates` row matches the user's login email
   (including case).
2. The user's JWT group has permission to access `GET /api/v1/me/certificates`.
3. The `event_id` on the certificate references a valid event.

**Key code:** `MeController::certificates()` at
`app/Http/Controllers/MeController.php:55-99` queries:

```php
Certificate::with(['event'])
    ->where('recipient_email', $email);
```

This queries the `certificates` table directly by `recipient_email`. It does **not**
go through `event_attendees`, so a missing attendee linkage does not affect this
endpoint.
