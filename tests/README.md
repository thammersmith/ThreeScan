# ThreeScan Testing Documentation

This directory contains tests for the ThreeScan application. The tests are organized into different categories to ensure comprehensive test coverage.

## Test Categories

### Unit Tests

Unit tests are located in the `tests/Unit` directory and focus on testing individual components in isolation. These tests verify that each component works correctly on its own.

- **TracerouteServiceTest**: Tests the TracerouteService class, which is responsible for executing and parsing traceroute commands.
- **TracerouteBenchmarkTest**: Performance benchmarks for the TracerouteService to ensure it meets performance requirements.

### Feature Tests

Feature tests are located in the `tests/Feature` directory and focus on testing API endpoints and application features. These tests verify that the components work correctly together.

- **TracerouteApiTest**: Tests the traceroute API endpoint to ensure it correctly handles requests and returns the expected responses.
- **TracerouteEndToEndTest**: End-to-end tests for critical user flows, simulating how users interact with the application.

### Browser Tests

Browser tests are located in the `tests/Browser` directory and focus on testing the application in different browsers to ensure compatibility.

- **BrowserCompatibilityTest**: Tests the application in different browsers to ensure it works correctly across all supported browsers.
- **browserConfig.json**: Configuration file specifying which browsers to test and their configurations.

## Mock Services

The application includes mock services for testing without actual network operations:

- **MockTracerouteService**: A mock implementation of the TracerouteService that returns predefined data instead of executing actual traceroute commands. This is useful for testing without network dependencies.

## Running Tests

### Unit and Feature Tests

To run unit and feature tests, use the following command:

```bash
php artisan test
```

To run a specific test file, use:

```bash
php artisan test --filter=TracerouteServiceTest
```

### Browser Tests

Browser tests require additional setup with a browser testing service like BrowserStack or Sauce Labs. Once set up, you can run the tests using the service's test runner.

## Performance Benchmarks

Performance benchmarks are included in the `TracerouteBenchmarkTest` file. These tests measure the execution time and memory usage of the TracerouteService to ensure it meets performance requirements.

To run the performance benchmarks, use:

```bash
php artisan test --filter=TracerouteBenchmarkTest
```

## Test Coverage

The tests aim to provide comprehensive coverage of the application's functionality, including:

- Unit tests for backend services
- Integration tests for API endpoints
- End-to-end tests for critical user flows
- Performance benchmarks for traceroute operations
- Browser compatibility tests
- Mock services for testing without actual network operations

## Adding New Tests

When adding new functionality to the application, be sure to add corresponding tests to maintain test coverage. Follow these guidelines:

1. Add unit tests for new services or components
2. Add feature tests for new API endpoints or features
3. Update end-to-end tests if the user flow changes
4. Add performance benchmarks for performance-critical operations
5. Update browser compatibility tests if the UI changes
