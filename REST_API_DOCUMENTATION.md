# Activity Log REST API Documentation

## Overview

The Activity Log plugin now provides a REST API endpoint for retrieving activity log data with the same filtering capabilities as the admin interface.

## Endpoint

**GET** `/wp-json/activity-log/v1/logs`

## Authentication

- User must be logged in
- User must have appropriate permissions (same as admin interface)
- Supports the following role-based access:
  - `view_all_aryo_activity_log` capability (full access)
  - Administrator role (full access) 
  - Editor role (limited access to Posts, Taxonomies, Attachments, Comments)
  - Other roles based on plugin configuration

## Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number for pagination | `1` |
| `per_page` | integer | Items per page (max 100) | `20` |
| `search` | string | Search in object name/subtype | `"login"` |
| `orderby` | string | Sort field (`hist_time`, `hist_ip`) | `"hist_time"` |
| `order` | string | Sort direction (`asc`, `desc`) | `"desc"` |
| `dateshow` | string | Date filter | `"today"`, `"yesterday"`, `"week"`, `"month"`, `"01/12/2023"` |
| `usershow` | integer | Filter by user ID | `1` |
| `capshow` | string | Filter by user capabilities | `"administrator"` |
| `typeshow` | string | Filter by object type | `"Posts"`, `"Users"`, `"Options"` |
| `showaction` | string | Filter by action | `"updated"`, `"created"`, `"deleted"` |
| `filter_ip` | string | Filter by IP address | `"192.168.1.1"` |

## Response Format

```json
{
  "data": [
    {
      "id": 123,
      "action": "updated",
      "object_type": "Posts",
      "object_subtype": "post",
      "object_name": "Sample Post Title",
      "object_id": 456,
      "user_id": 1,
      "user_caps": "administrator",
      "hist_ip": "192.168.1.1",
      "hist_time": 1640995200,
      "formatted_date": "January 1, 2022 12:00 PM",
      "time_ago": "2 hours",
      "user": {
        "id": 1,
        "display_name": "Admin User",
        "user_nicename": "admin",
        "avatar_url": "https://example.com/avatar.jpg"
      },
      "action_links": {
        "view": "https://example.com/sample-post/",
        "edit": "https://example.com/wp-admin/post.php?post=456&action=edit"
      }
    }
  ],
  "total": 1500,
  "pages": 75,
  "current_page": 1,
  "per_page": 20
}
```

## Usage Examples

### Basic Request
```bash
curl -X GET "https://yoursite.com/wp-json/activity-log/v1/logs" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### With Filters
```bash
curl -X GET "https://yoursite.com/wp-json/activity-log/v1/logs?typeshow=Posts&dateshow=today&per_page=50" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Search Logs
```bash
curl -X GET "https://yoursite.com/wp-json/activity-log/v1/logs?search=login&order=asc" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Filter by User and Date Range
```bash
curl -X GET "https://yoursite.com/wp-json/activity-log/v1/logs?usershow=1&dateshow=week" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## JavaScript Example

```javascript
// Using fetch API with WordPress nonce
fetch('/wp-json/activity-log/v1/logs?typeshow=Posts&per_page=10', {
  method: 'GET',
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce // WordPress nonce for logged-in users
  }
})
.then(response => response.json())
.then(data => {
  console.log('Activity logs:', data.data);
  console.log('Total logs:', data.total);
})
.catch(error => console.error('Error:', error));
```

## Error Responses

### 401 Unauthorized
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do that.",
  "data": {
    "status": 401
  }
}
```

### 403 Forbidden (Insufficient Permissions)
```json
{
  "code": "rest_forbidden", 
  "message": "Sorry, you are not allowed to view activity logs.",
  "data": {
    "status": 403
  }
}
```

## Notes

- The API respects the same role-based permissions as the admin interface
- IP filtering is only available if IP logging is enabled in plugin settings
- Date formats follow WordPress settings for display
- Action links are only provided for objects that still exist and are accessible
- Large result sets are automatically paginated
- All parameters are optional - without parameters, returns recent logs with default pagination

## Integration with Existing Features

This REST API provides the same data and filtering capabilities as:
- The Activity Log admin page (`/wp-admin/admin.php?page=activity-log-page`)
- The `AAL_Activity_Log_List_Table` class used in the admin interface
- All existing hooks and filters still apply to the data returned by the API
