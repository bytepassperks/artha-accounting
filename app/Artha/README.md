# Artha cross-module REST API (read-only)

Isolated, update-safe layer that exposes Artha Accounting data to the rest of the
Business OS suite (CRM "Ask Artha", the Automations engine). All files live under
`app/Artha`, `routes/artha.php`, and `App\Providers\ArthaServiceProvider` so that
upstream `erpsaas` updates merge without conflict.

## Auth

Every request must present the shared bearer token (env `ARTHA_API_TOKEN`):

```
Authorization: Bearer <ARTHA_API_TOKEN>
# or
X-Artha-Token: <ARTHA_API_TOKEN>
```

Missing/empty server token => `404` (API disabled). Wrong token => `401`.

## Endpoints

Base: `https://artha-accounting.osc-fr1.scalingo.io/api/artha`

| Method | Path | Query | Returns |
|---|---|---|---|
| GET | `/ping` | – | health check |
| GET | `/summary` | `company_id?` | customer count, invoice counts, total outstanding (cents) |
| GET | `/customers` | `company_id?`, `limit?` (1–200) | clients with primary-contact email, currency, website |
| GET | `/invoices` | `company_id?`, `limit?`, `unpaid?` (1=only unpaid) | invoices with client name, status, totals, due date |

`company_id` defaults to the first company when omitted. Money is reported in
integer cents under `*_cents` keys (`outstanding_cents = total_cents - amount_paid_cents`).

## Example

```bash
curl -H "Authorization: Bearer $ARTHA_API_TOKEN" \
  "https://artha-accounting.osc-fr1.scalingo.io/api/artha/summary"
# {"company":{"id":1,"name":"Artha"},"customers":10,"invoices_total":120,
#  "invoices_unpaid":12,"outstanding_cents":5627876}
```
