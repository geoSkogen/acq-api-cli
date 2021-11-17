## PHP CLI tool for expediting Acquia Cloud REST API requests
***
##### Run 'Composer Install'
##### Insert API Key & Secret in Lines 164-165 of oauth.php
***
### Sample Shell Commands
***
#### - return account info:
  `php oauth.php GET`

#### - return all databases for the test environment:
  `php oauth.php GET env test databases`  

#### - return all database backups for two subsites in dev:
  `php oauth.php GET env dev backups socialwork,engineering`

#### - create a database backup for a site in test:
  `php oauth.php POST env test backups admissions`

#### - create database backups for all live sites in first priority queue:
  `php oauth.php POST env live backups 1`  

#### - restore database from most recent backup for all dev sites in third priority queue:
  `php oauth.php POST env dev restore 3`
