# Global announcement

Global announcement module allows fetching announcements from `Etusivu`-instance.
It utilizes `json-api` and `external_entities`-module to transfer the data between instances.
Global announcements can be fetched to any instance and rendered using blocks.

## How to set up locally

Local setup requires Etusivu-instance to be up and running with some relevant data created to it.

Add following line to local.settings.php in order to connect to local etusivu-instance
`$config['helfi_global_announcement.settings']['source_environment'] = 'local'`
