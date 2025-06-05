# ThreeScan API Documentation

This document provides comprehensive documentation for the ThreeScan API, which allows you to perform traceroute
operations programmatically.

## API Endpoints

### Traceroute

Executes a traceroute to the specified host and returns the results.

**URL**: `/api/traceroute`

**Method**: `POST`

**Authentication**: None (currently)

#### Request Parameters

| Parameter | Type    | Required | Default | Description                                                |
|-----------|---------|----------|---------|------------------------------------------------------------|
| host      | string  | Yes      | -       | The hostname or IP address to trace                        |
| name      | string  | No       | "Trace to {host}" | A name for this traceroute operation            |
| max_hops  | integer | No       | 30      | Maximum number of hops to trace (1-64)                    |
| timeout   | integer | No       | 5       | Timeout in seconds for each probe (1-30)                  |
| queries   | integer | No       | 3       | Number of queries to send for each hop (1-10)             |

#### Success Response

**Code**: `200 OK`

**Content Example**:

```json
{
  "success": true,
  "data": {
    "id": "trace_60a7e9f5c3b4a",
    "name": "Trace to example.com",
    "host": "example.com",
    "timestamp": 1621234567,
    "hops": [
      {
        "hop": 1,
        "ttl": 1,
        "hostname": "localhost",
        "ip": "127.0.0.1",
        "pingTime": 0
      },
      {
        "hop": 2,
        "ttl": 2,
        "hostname": "router.local",
        "ip": "192.168.1.1",
        "pingTime": 1
      },
      {
        "hop": 3,
        "ttl": 3,
        "hostname": "isp-gateway.net",
        "ip": "203.0.113.1",
        "pingTime": 15
      },
      {
        "hop": 4,
        "ttl": 4,
        "hostname": "Timeout",
        "ip": "*",
        "pingTime": 0
      },
      {
        "hop": 5,
        "ttl": 5,
        "hostname": "core-router.example.net",
        "ip": "198.51.100.1",
        "pingTime": 25
      },
      {
        "hop": 6,
        "ttl": 6,
        "hostname": "example.com",
        "ip": "93.184.216.34",
        "pingTime": 30
      }
    ]
  }
}
```

#### Error Response

**Code**: `500 Internal Server Error`

**Content Example**:

```json
{
  "success": false,
  "message": "Traceroute command failed with code 1: Unable to resolve hostname"
}
```

**OR**

**Code**: `422 Unprocessable Entity` (Validation Error)

**Content Example**:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "host": [
      "The host field is required."
    ],
    "max_hops": [
      "The max hops must be between 1 and 64."
    ]
  }
}
```

## Examples

### Example 1: Basic Traceroute

```bash
curl -X POST \
  http://your-threescan-instance/api/traceroute \
  -H 'Content-Type: application/json' \
  -d '{
    "host": "example.com"
}'
```

### Example 2: Traceroute with Custom Parameters

```bash
curl -X POST \
  http://your-threescan-instance/api/traceroute \
  -H 'Content-Type: application/json' \
  -d '{
    "host": "example.com",
    "name": "Example.com Trace",
    "max_hops": 20,
    "timeout": 2,
    "queries": 2
}'
```

## Notes

- The traceroute operation is executed on the server where ThreeScan is installed.
- The response time may vary depending on the network conditions and the distance to the target host.
- Timeouts are represented with an asterisk (*) as the IP address and "Timeout" as the hostname.
- The first hop (hop 1) is always the local machine (127.0.0.1).
- The API currently has no rate limiting or authentication, so it should be used responsibly.
