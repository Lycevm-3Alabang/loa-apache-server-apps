# LOA Cert Platform - Authenticated Endpoints Specification

This specification lists all API endpoints that require authentication with a bearer token. No policies or role checks are included - only authentication requirements.

## Base URL
```
https://cert-api.lyceumalabang.edu.ph/api/v1
```

## Authenticated Endpoints

### Events
- `GET /events` - List events for the organization
- `POST /events` - Create an event
- `GET /events/{id}` - Get a single event
- `PUT /events/{id}` - Update an event (partial)
- `DELETE /events/{id}` - Delete an event
- `GET /events/{id}/stats` - Event statistics

### Events - Attendees
- `GET /events/{eventId}/attendees` - List attendees for an event
- `POST /events/{eventId}/attendees` - Add a single attendee  
- `POST /events/{eventId}/attendees/import` - Bulk import attendees

### Attendees
- `PUT /attendees/{id}` - Update an attendee
- `DELETE /attendees/{id}` - Remove an attendee
- `GET /attendees/{id}/delete-preview` - Preview delete impact
- `GET /attendees/{id}/file-data` - Get uploaded certificate source file

### Templates
- `GET /templates` - List templates
- `POST /templates` - Create a template
- `GET /templates/{id}` - Get a template
- `PUT /templates/{id}` - Update a template
- `DELETE /templates/{id}` - Delete a template

### Certificates
- `GET /certificates` - List certificates
- `POST /certificates` - Issue a single certificate
- `POST /certificates/bulk` - Bulk issue certificates
- `POST /certificates/upload` - Upload pre-rendered certificate PDF
- `GET /certificates/qr` - Generate QR code for verification
- `POST /certificates/expire` - Auto-revoke expired certificates

### Certificate Details
- `GET /certificates/{id}` - Get a single certificate
- `GET /certificates/{id}/pdf` - Stream PDF (inline)
- `GET /certificates/{id}/download` - Download PDF (attachment)
- `POST /certificates/{id}/revoke` - Revoke a certificate
- `DELETE /certificates/{id}` - Delete a certificate permanently
- `POST /certificates/{id}/email` - Send certificate email
- `GET /certificates/{id}/email-logs` - List email delivery logs
- `POST /certificates/{id}/reissue` - Reissue a certificate

## Authentication Requirements

All endpoints except those noted as "public" require:
- `Authorization: Bearer <access_token>` header
- Valid JWT token issued by LOA Auth Platform
- Token validates locally using shared JWT_SECRET (HMAC-SHA256)
- Token must be of type "access", not expired, and contain valid tenant claim

## Public Endpoints (No Authentication Required)

- `GET /verify/{certificate_number}` - Verify certificate by number
- `GET /view/{id}` - Public read-only certificate viewer data