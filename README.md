# SPP Framework

A high-performance, modular PHP framework for modern application development.

## Documentation Index

- [Lifecycle & Security Engine](file:///c:/projects/apache/school1/documentation/framework/lifecycle-and-security.md)
- [SPPUX UI System](file:///c:/projects/apache/school1/documentation/framework/sppux.md)
- [Module Architecture](file:///c:/projects/apache/school1/documentation/framework/modules.md)
- [API Authentication](file:///c:/projects/apache/school1/docs/api-authentication.md)
- [PHPDoc Codebase Documentation](file:///c:/projects/apache/school1/docs/phpdoc/index.html)

## Core Features
- **Modular Design**: Fully decoupled components.
- **XDB Engine**: XML-based database with native XPath support and AES-256 encryption.
- **Deployment Workbench**: Integrated ALM for dev-to-prod synchronization.
- **Polyglot Bridge**: Interoperability between different runtime environments.

## Development Environment (Docker)
The framework supports a containerized development environment using Docker and Docker Compose. This ensures a consistent environment with PHP 8.2, Apache, MySQL, and Redis.

### Starting the Environment
1. Ensure you have Docker and Docker Compose installed.
2. Run the following command in the project root:
   \\\ash
   docker-compose up -d
   \\\
3. The application will be available at http://localhost:8080.

### Running Parikshak Tests
To run the automated test suite within the Docker environment:
\\\ash
docker-compose exec app php spp.php sys:test:auto default
\\\

