## PHP CLI tool for expediting Acquia Cloud REST API requests
***
##### Run 'Composer Install'
##### Insert API Key & Secret in Lines 164-165 of oauth.php
***
### Sample Shell Commands
***
#### - return system info:
  `php oauth.php GET`

#### - return all DBs for the dev environment:
  `php oauth.php GET env dev databases`  

#### - return all DB backups for two subsites in dev:
  `php oauth.php GET env dev backups socialwork,engineering`

#### - create a DB backup for a site in test:
  `php oauth.php POST env live backups admissions`

#### - delete oldest DB backup for all dev sites in first priority queue:
  `php oauth.php DELETE env dev backups 1 oldest`   

#### - create DB backups for all live sites in first priority queue:
  `php oauth.php POST env live backups 1`  

#### - restore DB from most recent backup for all dev sites in third priority queue:
  `php oauth.php POST env dev restore 3 newest`

#### - delete oldest DB backup for all live sites in first priority queue:
  `php oauth.php DELETE env live backups 1 oldest`   

#### - export a file of most recent on-demand DB backup IDs for all live sites in first priority queue:
  `php oauth.php GET env live backups 1 newest-ondemand register`   

#### - restore DB backups for all live sites in first priority queue, reading backup IDs from file created above:
  `php oauth.php POST env live restore 1 from-register`
