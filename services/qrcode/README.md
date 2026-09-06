# QR Code Generation
## Platform Service Specification

**Version:** 1.0
**Status:** Draft
**Layer:** Platform Service
**Audience:** Engineers, AI Development Agents

---

# 1. Purpose

The QR Code Generation Service provides reusable QR code image generation capabilities for the platform.

It answers:

> **"How do we generate QR code images from text data?"**

It does not own certificates, templates, verification logic, or business rules about what data to encode.

---

# 2. Responsibilities

The QR Code Generation Service is responsible for:

- encoding text data into QR code images
- generating PNG image output
- returning base64-encoded data URIs
- supporting configurable image dimensions

---

# 3. What the QR Code Generation Service Owns

- QR code encoding algorithm
- image rendering (PNG)
- base64 data URI formatting
- error correction level configuration

The service owns these concepts completely.

---

# 4. What the QR Code Generation Service Does NOT Own

- what data to encode (business decision)
- verification URL construction (business decision)
- certificate lifecycle
- template rendering
- PDF generation

These belong to Business Contexts or other Platform Services.

---

# 5. Public Contracts

The service exposes a single method:

```
toDataUri(string $text): string
```

**Input:** Any UTF-8 text string to encode.
**Output:** A base64-encoded PNG data URI (`data:image/png;base64,...`).

---

# 6. Business Rules

The service follows these rules:

- QR codes are always PNG format
- QR codes use error correction level M (medium)
- QR code images are square (width = height)
- Default dimensions: 256x256 pixels
- Output is always a complete data URI (not raw base64)

---

# 7. Implementation Constraints

The service MUST work on cPanel shared hosting:

- No Composer dependency required (self-contained)
- Uses PHP's GD extension (available on virtually all cPanel PHP installations)
- No external binaries or shell execution
- Pure PHP implementation

---

# 8. Anti-Patterns

The following are violations:

## Business Logic in Service

```
QrCodeService
decides what URL to encode
```

The service encodes text only. URL construction belongs to the Certificate Business Context.

---

## Direct Database Access

```
QrCodeService
queries Certificate table
```

The service is stateless. It receives text input, returns image output.

---

# 9. Consumers

The primary consumer is:

- Certificate Business Context (for certificate QR codes and PDF embedding)

---

# 10. Guiding Principle

The QR Code Generation Service is a pure utility. It transforms text into images.

It does not know about certificates, events, or verification workflows.

Those responsibilities belong to the Certificate Business Context.
