# Stockland ERP 16.2.18 — Agent Workbench Intake

This directory is the controlled input location for the installer that will be supplied for the isolated agent-integration work.

## Upload target

Upload the installer ZIP to this branch at:

`agent-workbench/intake/STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`

If available, upload its SHA-256 sidecar beside it:

`agent-workbench/intake/STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip.sha256`

## Workbench boundaries

- This branch is for development and agent integration only.
- The branch was created from source commit `387c193c7999840c731d37acd854ec920f134507`.
- The existing delivery branch and its installer are not modified by this work.
- Do not use this branch as a production deployment source.
- Keep production credentials, application passwords, customer information, and real payroll or personnel records out of the repository.

## After the installer is uploaded

The package will be inspected before environment configuration or code changes: archive integrity, SHA-256, file inventory, installer entry points, source correspondence, and existing development-container assumptions. The development container and CI workflow will then be aligned to the supplied 16.2.18 package, rather than carrying forward stale setup scripts from earlier baselines.

All agent-integration changes stay on this work branch and are delivered as reviewable commits.