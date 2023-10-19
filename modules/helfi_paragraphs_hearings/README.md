# Hearings api

Get hearings from "Kerrokantasi" service
Site: https://kerrokantasi.hel.fi/hearings/list?lang=en
Api: https://kerrokantasi.api.hel.fi/v1/hearing?format=json&langcode=fi&open=true (with query parameters)

## Helfi_hearings
- Uses external entity to fetch hearings
- Rendered using paragraph
- Paragraph set by default to landing page content type

## Tips
Get all closed hearings:
- Change "open" -query parameter from true to false
