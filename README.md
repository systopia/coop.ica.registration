# ICA Registration Extension

## What is it

This extension processes event registrations for the general assembly.

## How to adjust for the next assembly?

### CiviCRM

 1. Adjust configuration: civicrm/admin/ica_registration
 2. Create templates:
    1. "{PREFIX} Registration Confirmation {LANG}", e.g. ``GA2017 Registration Confirmation EN`` 
    1. "{PREFIX} Payment Completion {LANG}", e.g. ``GA2019 Payment Completion ES``
 
 
 ### Webform
 
 1. ``drush up``
 1. uninstall + re-install cmrf + ica_event_cmrf_connector modules
 1. Adjust configuration in ``ica_event_cmrf_connector/includes/webform_integration.inc``
 1. Clean out webform submissions
 1. Adjust webform title / content
 1. Make sure CMRF connection profile still valid
 1. Create products with new prefix