# DataTables for JavaScript

Embedded component for the Workshop module (Dolibarr).

## Source

- **Package:** `datatables.net` + `datatables.net-dt` (default styling)
- **Version:** 2.3.7
- **Homepage:** https://datatables.net/
- **npm:** https://www.npmjs.com/package/datatables.net
- **License:** MIT

## Files

| File | Description |
|------|-------------|
| `js/dataTables.min.js` | Core DataTables library (minified) |
| `js/dataTables.dataTables.min.js` | Default styling integration |
| `css/dataTables.dataTables.min.css` | Default CSS theme |
| `License.txt` | MIT License full text |

## Usage in Workshop

```php
// Include in page header via llxHeader()
$arrayofjs = array(
    '/workshop/includes/datatables/js/dataTables.min.js',
    '/workshop/includes/datatables/js/dataTables.dataTables.min.js'
);
$arrayofcss = array(
    '/workshop/includes/datatables/css/dataTables.dataTables.min.css'
);
```

```javascript
// Initialize on any HTML table
new DataTable('#my-table', {
    paging: true,
    searching: true,
    ordering: true
});
```

## How to update

```bash
cd /tmp
npm pack datatables.net
npm pack datatables.net-dt
tar -xzf datatables.net-*.tgz
tar -xzf datatables.net-dt-*.tgz
cp package/js/dataTables.min.js          /path/to/workshop/includes/datatables/js/
cp package/js/dataTables.dataTables.min.js /path/to/workshop/includes/datatables/js/
cp package/css/dataTables.dataTables.min.css /path/to/workshop/includes/datatables/css/
cp package/License.txt                   /path/to/workshop/includes/datatables/
```
