grinway/installer
========

## How it works

:wrench: `grinway/installer` creates nothing!

Only a certain bundle(package) knows about its own configuration.

It just copies bundles' configurations by the path:\
`config/packages/grin_way_<PACKAGE_NAME_WITHOUT_OWNER_AND_-bundle>.<EXTENSION>`\
to your `kernel.project_dir/TO_THE_SAME_HIERARCHY`.