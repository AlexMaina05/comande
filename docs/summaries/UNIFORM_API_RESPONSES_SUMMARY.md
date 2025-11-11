# Uniform API Responses Implementation Summary

**Issue:** #6 - Uniform API responses and error handling  
**Date:** October 2024  
**Status:** ✅ Completed

## Objective

Implement a uniform JSON response schema for all API endpoints with proper error handling, ensuring:
- All API responses follow the schema `{ success, data, error }`
- HTTP status codes are appropriate (400, 404, 500, etc.)
- HTML-serving files don't emit JSON error dumps
- Centralized error handling for all APIs

## Changes Implemented

### 1. ✅ API Response Helper (`api/response.php`)

**Added functional interface:**
- `json_response(bool $success, $data = null, array $error = null, int $status = 200)`
  - Sets `Content-Type: application/json`
  - Sets HTTP status code
  - Outputs uniform JSON schema
  
- `api_error(int $code, string $message): array`
  - Helper to create error arrays with standard structure

**Maintained backward compatibility:**
- `ApiResponse::sendSuccess()` - now uses `json_response()`
- `ApiResponse::sendError()` - now uses `json_response()` and `api_error()`

### 2. ✅ Centralized Error Handler (`api/error_handler.php`)

**Implements three global handlers:**

1. **Exception Handler** (`set_exception_handler`)
   - Catches all unhandled exceptions
   - Converts to uniform JSON error response
   - Returns HTTP 500 (or exception code if valid)
   - Logs errors to server log

2. **Error Handler** (`set_error_handler`)
   - Converts PHP errors (warnings, notices) to exceptions
   - Ensures uniform handling via exception handler
   - Respects error suppression (@)

3. **Shutdown Function** (`register_shutdown_function`)
   - Catches fatal errors (E_ERROR, E_PARSE, etc.)
   - Cleans output buffers
   - Returns uniform JSON error response

### 3. ✅ Updated All API Endpoints

All API files now include:
```php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/error_handler.php';
```

**Updated files:**
- `api/gestisci_dati.php`
- `api/salva_ordine.php`
- `api/cerca_prodotto.php`
- `api/ripeti_comanda.php`
- `api/genera_report.php`

### 4. ✅ Database Connection (`db_connection.php`)

**Already correctly implemented:**
- Detects API context (checks REQUEST_URI for `/api/`)
- In API context: Uses `ApiResponse::sendError()` with JSON
- In HTML context: Throws Exception for page to handle
- Never emits raw JSON/var_dump in HTML pages

### 5. ✅ Documentation Updates

**Updated files:**
- `docs/API.md` - Added sections on:
  - Functional helper usage
  - Error handler description
  - Testing instructions
  
- `docs/README.md` - Added note about uniform API responses

- `tools/tests/README.md` - Added documentation for new test script

### 6. ✅ Testing

**Created new test script:**
- `tools/tests/test_api_responses.sh` - Manual smoke test
  - Verifies response schema for all endpoints
  - Tests success and error scenarios
  - Shows HTTP status codes
  - Visual validation of JSON structure

**Existing tests:**
- `tools/tests/test_api_response.php` - Unit tests (still working)
- `tools/tests/api_responses_test.sh` - Automated integration tests

## Response Schema

### Success Response
```json
{
  "success": true,
  "data": {
    // Response data here
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": 1001,
    "message": "Error description",
    "details": []  // optional
  }
}
```

## HTTP Status Codes

| Code | Usage |
|------|-------|
| 200  | Success |
| 400  | Bad Request (validation errors) |
| 404  | Not Found (resource doesn't exist) |
| 500  | Internal Server Error |

## Error Code Ranges

| Range      | Category |
|------------|----------|
| 1000-1999  | Input validation errors |
| 2000-2999  | Server/database errors |
| 3000-3999  | Connection/infrastructure errors |
| 5000-5999  | Critical system errors (from error_handler) |

## Usage Examples

### Using Functional Helpers
```php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/error_handler.php';

// Success
json_response(true, ['result' => 'ok'], null, 200);

// Error
json_response(false, null, api_error(1001, 'Invalid input'), 400);
```

### Using Class Methods (backward compatible)
```php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/error_handler.php';

// Success
ApiResponse::sendSuccess(['result' => 'ok'], 200);

// Error
ApiResponse::sendError('Invalid input', 1001, 400);
```

## Testing

### Run Manual Smoke Test
```bash
./tools/tests/test_api_responses.sh http://localhost/RICEVUTE
```

### Run Automated Tests
```bash
./tools/tests/api_responses_test.sh http://localhost/RICEVUTE
```

### Run Unit Tests
```bash
php -d error_reporting=0 tools/tests/test_api_response.php
```

## Verification Checklist

- [x] All API files include `error_handler.php`
- [x] All API files use uniform response schema
- [x] HTTP status codes are appropriate
- [x] `db_connection.php` doesn't emit JSON in HTML context
- [x] Error handler catches exceptions, errors, and fatal errors
- [x] Documentation updated
- [x] Test scripts created
- [x] All PHP files have valid syntax
- [x] Backward compatibility maintained

## Notes

- **Issues #21 and #22 ignored** as explicitly requested
- Backward compatibility maintained with existing `ApiResponse` class
- All changes are minimal and surgical
- No existing functionality broken
- Error logging to server logs maintained

## Files Modified

1. `api/response.php` - Added functional interface
2. `api/error_handler.php` - **NEW** - Centralized error handling
3. `api/gestisci_dati.php` - Added error_handler include
4. `api/salva_ordine.php` - Added error_handler include
5. `api/cerca_prodotto.php` - Added error_handler include
6. `api/ripeti_comanda.php` - Added error_handler include
7. `api/genera_report.php` - Added error_handler include
8. `docs/API.md` - Updated with helper documentation
9. `docs/README.md` - Added uniform response note
10. `tools/tests/README.md` - Added test documentation
11. `tools/tests/test_api_responses.sh` - **NEW** - Smoke test script

## Security Notes

- All input validation remains in place (Issue #5)
- SQL injection protection via prepared statements maintained
- Error details logged server-side, not exposed to client
- XSS protection via `htmlspecialchars()` where needed

---

**Implementation complete and ready for review.**
