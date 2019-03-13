{*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2019 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}


<h3>{ts domain="coop.ica.registration"}General Options{/ts}</h3>
<div class="crm-section">
  <div class="label">{$form.default_event.label}</div>
  <div class="content">{$form.default_event.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.registration_prefix.label}</div>
  <div class="content">{$form.registration_prefix.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.confirmation_sender.label}</div>
  <div class="content">{$form.confirmation_sender.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.confirmation_bcc.label}</div>
  <div class="content">{$form.confirmation_bcc.html}</div>
  <div class="clear"></div>
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
