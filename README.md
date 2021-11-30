## PHP CLI tool for expediting Acquia Cloud REST API requests
***
##### Run 'Composer Install'
##### Insert API Key & Secret + App UUID in Lines 11,12 & 13 of oauth.php
##### (App UUID is in your cloud interface URL: `https://cloud.acquia.com/a/applications/[app_uuid]`)
***
### Backup and Restore Procedure
***
#### - create backups and record their IDs in an external file:
`php oauth.php POST env live backups 1`
`php oauth.php GET env live backups 1 newest-ondemand register`

#### - delete unneeded backups if update is successful:
`php oauth.php DELETE env live backups 1 from-register`

#### - resotre DBs from backup ids in external file if rollback is needed:
`php oauth.php POST env live restore 1 from-register`
***
### Other Sample Shell Commands
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
